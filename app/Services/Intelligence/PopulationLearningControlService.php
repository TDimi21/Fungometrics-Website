<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

use App\Models\PopulationLearningControl;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class PopulationLearningControlService
{
    private const CONFIDENCE_ORDER = [
        'insufficient' => 0,
        'low' => 1,
        'medium' => 2,
        'high' => 3,
    ];

    public function __construct(
        private readonly BenchmarkLibrary $benchmarkLibrary,
    ) {
    }

    public function getControlForMetric(string $metricKey): array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $definition = $this->benchmarkLibrary->metric($metricKey);
        $defaults = $this->defaultControl($metricKey, $definition);

        if (! $this->tableReady()) {
            return $defaults + [
                'exists' => false,
                'warning' => 'population_learning_controls table is not available yet.',
            ];
        }

        $control = PopulationLearningControl::query()->where('metric_key', $metricKey)->first();
        if (! $control) {
            return $defaults + ['exists' => false];
        }

        return array_merge($defaults, [
            'exists' => true,
            'id' => (string) $control->id,
            'metric_key' => $metricKey,
            'category' => $control->category ?: ($definition['category'] ?? null),
            'status' => $this->status((string) $control->status),
            'population_enabled' => (bool) $control->population_enabled,
            'research_enabled' => (bool) $control->research_enabled,
            'composite_enabled' => (bool) $control->composite_enabled,
            'minimum_sample_size' => max(1, (int) $control->minimum_sample_size),
            'minimum_confidence' => $this->confidenceOrNull($control->minimum_confidence),
            'allow_global_bucket' => (bool) $control->allow_global_bucket,
            'allow_exact_peer_bucket' => (bool) $control->allow_exact_peer_bucket,
            'allow_age_bucket' => (bool) $control->allow_age_bucket,
            'max_exclusion_rate' => $control->max_exclusion_rate !== null ? (float) $control->max_exclusion_rate : null,
            'admin_notes' => $control->admin_notes,
            'last_audit_summary' => is_array($control->last_audit_summary) ? $control->last_audit_summary : null,
            'last_reviewed_at' => $control->last_reviewed_at?->toIso8601String(),
            'reviewed_by_user_id' => $control->reviewed_by_user_id,
        ]);
    }

    public function resolvePolicyForMetric(string $metricKey, array $audit = []): array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $control = $this->getControlForMetric($metricKey);
        $status = $this->status((string) ($control['status'] ?? PopulationLearningControl::STATUS_AUTO));
        $qaFlags = array_values(array_unique(array_map('strval', $audit['qa_flags'] ?? [])));
        $sampleSize = $this->sampleSize($audit);
        $confidence = $this->confidence((string) ($audit['population_confidence'] ?? $audit['source_mix']['population_confidence'] ?? 'insufficient'));
        $bucketLevel = (string) ($audit['selected_bucket_level'] ?? $audit['source_mix']['selected_bucket_level'] ?? '');
        $bucketAllowed = $this->bucketAllowed($bucketLevel, $control);
        $exclusionAllowed = $this->exclusionRateAllowed($audit, $control);
        $sampleAllowed = $sampleSize >= (int) ($control['minimum_sample_size'] ?? PopulationPercentileEngine::MIN_LOW_CONFIDENCE);
        $confidenceAllowed = $this->confidenceMeetsMinimum($confidence, $control['minimum_confidence'] ?? null);
        $researchAvailable = ($audit['research_available'] ?? true) === true;
        $researchAllowed = ($control['research_enabled'] ?? true) === true;
        $populationAllowed = false;
        $compositeAllowed = false;
        $reason = 'Population learning is in safe auto mode.';

        if ($status === PopulationLearningControl::STATUS_DISABLED) {
            return $this->policy($metricKey, $control, false, false, false, 'Metric is disabled by admin control.', $qaFlags);
        }

        if ($status === PopulationLearningControl::STATUS_RESEARCH_ONLY) {
            return $this->policy($metricKey, $control, false, $researchAllowed, false, 'Metric is locked to research benchmark by admin control.', $qaFlags);
        }

        if ($status === PopulationLearningControl::STATUS_NEEDS_REVIEW) {
            return $this->policy($metricKey, $control, false, $researchAllowed, false, 'Metric is marked needs review; research fallback remains active.', $qaFlags);
        }

        $requiresResearchFallback = in_array($status, [
            PopulationLearningControl::STATUS_AUTO,
            PopulationLearningControl::STATUS_COMPOSITE_ENABLED,
        ], true);
        $researchFallbackAllowed = ! $requiresResearchFallback || $researchAvailable;
        $qualityAllowed = $sampleAllowed && $confidenceAllowed && $bucketAllowed && $exclusionAllowed && $researchFallbackAllowed;
        if (! $qualityAllowed) {
            $reason = $this->blockedReason($sampleAllowed, $confidenceAllowed, $bucketAllowed, $exclusionAllowed, $researchFallbackAllowed, $sampleSize, $confidence, $control);

            return $this->policy($metricKey, $control, false, $researchAllowed, false, $reason, $qaFlags);
        }

        if ($status === PopulationLearningControl::STATUS_POPULATION_ENABLED) {
            $populationAllowed = ($control['population_enabled'] ?? false) === true;
            $compositeAllowed = false;
            $reason = $populationAllowed
                ? 'Population-only use is allowed by admin control and quality gates passed.'
                : 'Population status is enabled, but population_enabled flag is false.';
        } elseif ($status === PopulationLearningControl::STATUS_COMPOSITE_ENABLED) {
            $populationAllowed = ($control['population_enabled'] ?? false) === true;
            $compositeAllowed = ($control['composite_enabled'] ?? true) === true;
            $reason = $compositeAllowed
                ? 'Composite blend is allowed by admin control and quality gates passed.'
                : 'Composite status is enabled, but composite_enabled flag is false.';
        } else {
            $populationAllowed = true;
            $compositeAllowed = ($control['composite_enabled'] ?? true) === true;
            $reason = 'Auto mode allows population blend because quality gates passed.';
        }

        return $this->policy($metricKey, $control, $populationAllowed, $researchAllowed, $compositeAllowed, $reason, $qaFlags);
    }

    public function updateControl(string $metricKey, array $data, ?string $reviewedByUserId = null): array
    {
        if (! $this->tableReady()) {
            throw new InvalidArgumentException('population_learning_controls table is not available. Run migrations first.');
        }

        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $definition = $this->benchmarkLibrary->metric($metricKey);
        $payload = $this->sanitizeControlData($data, $definition);
        $control = PopulationLearningControl::query()->firstOrNew(['metric_key' => $metricKey]);
        $control->fill($payload + [
            'metric_key' => $metricKey,
            'category' => $payload['category'] ?? $definition['category'] ?? $control->category,
        ]);

        if ($reviewedByUserId !== null && $reviewedByUserId !== '') {
            $control->reviewed_by_user_id = $reviewedByUserId;
        }
        $control->last_reviewed_at = now();
        $control->save();

        return $this->getControlForMetric($metricKey);
    }

    public function applyMetricPolicy(string $metricKey, array $policy, ?string $reviewedByUserId = null): array
    {
        $metricKey = BenchmarkDefinitions::normalizeMetricKey($metricKey);
        $current = $this->getControlForMetric($metricKey);
        $preserveNotes = (bool) ($policy['preserve_notes'] ?? false);
        $rolloutNote = trim((string) ($policy['admin_notes'] ?? ''));
        $existingNotes = trim((string) ($current['admin_notes'] ?? ''));

        $payload = [
            'category' => $policy['category'] ?? $current['category'] ?? null,
            'status' => $policy['recommended_status'] ?? $policy['status'] ?? PopulationLearningControl::STATUS_AUTO,
            'population_enabled' => (bool) ($policy['population_enabled'] ?? false),
            'research_enabled' => (bool) ($policy['research_enabled'] ?? true),
            'composite_enabled' => (bool) ($policy['composite_enabled'] ?? true),
            'minimum_sample_size' => (int) ($policy['minimum_sample_size'] ?? PopulationPercentileEngine::MIN_LOW_CONFIDENCE),
            'minimum_confidence' => $policy['minimum_confidence'] ?? null,
            'allow_global_bucket' => (bool) ($policy['allow_global_bucket'] ?? true),
            'allow_exact_peer_bucket' => (bool) ($policy['allow_exact_peer_bucket'] ?? true),
            'allow_age_bucket' => (bool) ($policy['allow_age_bucket'] ?? true),
            'max_exclusion_rate' => $policy['max_exclusion_rate'] ?? null,
            'last_audit_summary' => $policy['last_audit_summary'] ?? null,
        ];

        if ($preserveNotes && $existingNotes !== '') {
            $payload['admin_notes'] = $existingNotes;
        } else {
            $payload['admin_notes'] = $this->rolloutNotes($existingNotes, $rolloutNote, (string) ($policy['rollout_profile'] ?? 'metric_trust_rollout'));
        }

        return $this->updateControl($metricKey, $payload, $reviewedByUserId);
    }

    public function listControls(array $filters = []): array
    {
        $category = $filters['category'] ?? null;
        $metric = $filters['metric_key'] ?? $filters['metric'] ?? null;
        $keys = is_string($metric) && trim($metric) !== ''
            ? [BenchmarkDefinitions::normalizeMetricKey(trim($metric))]
            : $this->benchmarkLibrary->metricKeys();

        return collect($keys)
            ->map(fn (string $key) => $this->getControlForMetric($key))
            ->filter(function (array $control) use ($category): bool {
                if (! is_string($category) || trim($category) === '') {
                    return true;
                }

                return strtolower((string) ($control['category'] ?? '')) === strtolower(trim($category));
            })
            ->values()
            ->all();
    }

    public function syncControlsFromAudit(array $options = []): array
    {
        if (! $this->tableReady()) {
            throw new InvalidArgumentException('population_learning_controls table is not available. Run migrations first.');
        }

        $report = app(PopulationLearningAuditService::class)->buildAuditReport($options);
        $updated = [];
        foreach ($report['metrics'] ?? [] as $metricReport) {
            $metricKey = (string) ($metricReport['metric_key'] ?? '');
            if ($metricKey === '') {
                continue;
            }

            $existing = PopulationLearningControl::query()->where('metric_key', $metricKey)->first();
            $status = $existing?->status ?: $this->suggestedStatus($metricReport);
            $control = PopulationLearningControl::query()->firstOrNew(['metric_key' => $metricKey]);
            $control->fill([
                'category' => $metricReport['category'] ?? null,
                'status' => $this->status((string) $status),
                'population_enabled' => (bool) ($control->population_enabled ?? false),
                'research_enabled' => (bool) ($control->research_enabled ?? true),
                'composite_enabled' => (bool) ($control->composite_enabled ?? true),
                'minimum_sample_size' => (int) ($control->minimum_sample_size ?: PopulationPercentileEngine::MIN_LOW_CONFIDENCE),
                'last_audit_summary' => $this->auditSummary($metricReport),
                'last_reviewed_at' => $control->last_reviewed_at,
            ]);
            $control->save();
            $updated[] = $this->getControlForMetric($metricKey);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'metric_count' => count($updated),
            'controls' => $updated,
            'audit_summary' => [
                'readiness_summary' => $report['readiness_summary'] ?? [],
                'guardrail_summary' => $report['guardrail_summary'] ?? [],
                'trusted_task_summary' => $report['trusted_task_summary'] ?? [],
            ],
        ];
    }

    public function buildControlSummary(array $options = []): array
    {
        $controls = $this->listControls($options);
        $statuses = [];

        foreach ($controls as $control) {
            $status = (string) ($control['status'] ?? PopulationLearningControl::STATUS_AUTO);
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'metric_count' => count($controls),
            'status_counts' => $statuses,
            'controls' => $controls,
        ];
    }

    private function defaultControl(string $metricKey, ?array $definition): array
    {
        return [
            'exists' => false,
            'id' => null,
            'metric_key' => $metricKey,
            'category' => $definition['category'] ?? BenchmarkDefinitions::categoryForMetric($metricKey),
            'status' => PopulationLearningControl::STATUS_AUTO,
            'population_enabled' => false,
            'research_enabled' => true,
            'composite_enabled' => true,
            'minimum_sample_size' => PopulationPercentileEngine::MIN_LOW_CONFIDENCE,
            'minimum_confidence' => null,
            'allow_global_bucket' => true,
            'allow_exact_peer_bucket' => true,
            'allow_age_bucket' => true,
            'max_exclusion_rate' => null,
            'admin_notes' => null,
            'last_audit_summary' => null,
            'last_reviewed_at' => null,
            'reviewed_by_user_id' => null,
        ];
    }

    private function policy(string $metricKey, array $control, bool $populationAllowed, bool $researchAllowed, bool $compositeAllowed, string $reason, array $qaFlags): array
    {
        return [
            'metric_key' => $metricKey,
            'status' => (string) ($control['status'] ?? PopulationLearningControl::STATUS_AUTO),
            'population_allowed' => $populationAllowed,
            'research_allowed' => $researchAllowed,
            'composite_allowed' => $compositeAllowed,
            'reason' => $reason,
            'minimum_sample_size' => (int) ($control['minimum_sample_size'] ?? PopulationPercentileEngine::MIN_LOW_CONFIDENCE),
            'minimum_confidence' => $control['minimum_confidence'] ?? null,
            'allow_global_bucket' => (bool) ($control['allow_global_bucket'] ?? true),
            'qa_flags' => $qaFlags,
            'admin_notes' => $control['admin_notes'] ?? null,
            'evidence' => [
                'control_exists' => (bool) ($control['exists'] ?? false),
                'population_enabled' => (bool) ($control['population_enabled'] ?? false),
                'research_enabled' => (bool) ($control['research_enabled'] ?? true),
                'composite_enabled' => (bool) ($control['composite_enabled'] ?? true),
                'allow_exact_peer_bucket' => (bool) ($control['allow_exact_peer_bucket'] ?? true),
                'allow_age_bucket' => (bool) ($control['allow_age_bucket'] ?? true),
                'max_exclusion_rate' => $control['max_exclusion_rate'] ?? null,
                'last_audit_summary' => $control['last_audit_summary'] ?? null,
            ],
        ];
    }

    private function sanitizeControlData(array $data, ?array $definition): array
    {
        $payload = [];

        if (array_key_exists('category', $data)) {
            $payload['category'] = $data['category'] ?: ($definition['category'] ?? null);
        }
        if (array_key_exists('status', $data)) {
            $payload['status'] = $this->status((string) $data['status']);
        }
        foreach ([
            'population_enabled',
            'research_enabled',
            'composite_enabled',
            'allow_global_bucket',
            'allow_exact_peer_bucket',
            'allow_age_bucket',
        ] as $key) {
            if (array_key_exists($key, $data)) {
                $payload[$key] = (bool) $data[$key];
            }
        }
        if (array_key_exists('minimum_sample_size', $data)) {
            $payload['minimum_sample_size'] = max(1, (int) $data['minimum_sample_size']);
        }
        if (array_key_exists('minimum_confidence', $data)) {
            $payload['minimum_confidence'] = $this->confidenceOrNull($data['minimum_confidence']);
        }
        if (array_key_exists('max_exclusion_rate', $data)) {
            $payload['max_exclusion_rate'] = is_numeric($data['max_exclusion_rate']) ? max(0.0, min(100.0, (float) $data['max_exclusion_rate'])) : null;
        }
        if (array_key_exists('admin_notes', $data)) {
            $payload['admin_notes'] = $data['admin_notes'] !== null ? trim((string) $data['admin_notes']) : null;
        }
        if (array_key_exists('last_audit_summary', $data) && is_array($data['last_audit_summary'])) {
            $payload['last_audit_summary'] = $data['last_audit_summary'];
        }

        return $payload;
    }

    private function suggestedStatus(array $metricReport): string
    {
        $flags = $metricReport['qa_flags'] ?? [];
        if (($metricReport['readiness'] ?? null) === 'not_ready' || in_array('missing_metric_mapping', $flags, true)) {
            return PopulationLearningControl::STATUS_NEEDS_REVIEW;
        }

        if (($metricReport['readiness'] ?? null) === 'research_only') {
            return PopulationLearningControl::STATUS_RESEARCH_ONLY;
        }

        if (in_array('high_exclusion_rate', $flags, true) || in_array('suspicious_outliers_removed', $flags, true)) {
            return PopulationLearningControl::STATUS_NEEDS_REVIEW;
        }

        return PopulationLearningControl::STATUS_AUTO;
    }

    private function auditSummary(array $metricReport): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'readiness' => $metricReport['readiness'] ?? null,
            'population_confidence' => $metricReport['population_confidence'] ?? null,
            'bucket_count' => $metricReport['bucket_count'] ?? null,
            'raw_values_found' => $metricReport['raw_values_found'] ?? null,
            'guardrail_excluded_count' => $metricReport['guardrail_excluded_count'] ?? null,
            'trusted_task_values_count' => $metricReport['trusted_task_values_count'] ?? null,
            'qa_flags' => $metricReport['qa_flags'] ?? [],
            'recommended_actions' => array_slice($metricReport['recommended_actions'] ?? [], 0, 5),
        ];
    }

    private function rolloutNotes(string $existingNotes, string $rolloutNote, string $profile): ?string
    {
        if ($rolloutNote === '') {
            return $existingNotes !== '' ? $existingNotes : null;
        }

        $marker = 'Metric Trust Rollout '.$profile;
        if (str_contains($existingNotes, $marker)) {
            return $existingNotes;
        }

        $entry = $marker.' @ '.now()->toIso8601String().': '.$rolloutNote;

        return $existingNotes !== '' ? $existingNotes."\n".$entry : $entry;
    }

    private function sampleSize(array $audit): int
    {
        return (int) (
            $audit['final_population_values_count']
            ?? $audit['bucket_count']
            ?? $audit['source_mix']['final_population_values_count']
            ?? $audit['source_mix']['population_bucket_count']
            ?? 0
        );
    }

    private function bucketAllowed(string $bucketLevel, array $control): bool
    {
        if ($bucketLevel === BenchmarkLibrary::BUCKET_GLOBAL_CLEAN && ($control['allow_global_bucket'] ?? true) !== true) {
            return false;
        }

        if ($bucketLevel === BenchmarkLibrary::BUCKET_EXACT_PEER && ($control['allow_exact_peer_bucket'] ?? true) !== true) {
            return false;
        }

        if ($bucketLevel === BenchmarkLibrary::BUCKET_AGE_ONLY && ($control['allow_age_bucket'] ?? true) !== true) {
            return false;
        }

        return true;
    }

    private function exclusionRateAllowed(array $audit, array $control): bool
    {
        if (! is_numeric($control['max_exclusion_rate'] ?? null)) {
            return true;
        }

        $raw = (int) ($audit['raw_values_found'] ?? 0);
        $excluded = (int) ($audit['guardrail_excluded_count'] ?? $audit['values_excluded'] ?? 0);
        $rate = $raw > 0 ? ($excluded / $raw) * 100 : 0.0;

        return $rate <= (float) $control['max_exclusion_rate'];
    }

    private function confidenceMeetsMinimum(string $confidence, mixed $minimum): bool
    {
        $minimum = $this->confidenceOrNull($minimum);
        if ($minimum === null) {
            return true;
        }

        return (self::CONFIDENCE_ORDER[$confidence] ?? 0) >= (self::CONFIDENCE_ORDER[$minimum] ?? 0);
    }

    private function blockedReason(bool $sampleAllowed, bool $confidenceAllowed, bool $bucketAllowed, bool $exclusionAllowed, bool $researchFallbackAllowed, int $sampleSize, string $confidence, array $control): string
    {
        if (! $sampleAllowed) {
            return 'Population sample '.$sampleSize.' is below minimum sample '.(int) ($control['minimum_sample_size'] ?? PopulationPercentileEngine::MIN_LOW_CONFIDENCE).'.';
        }
        if (! $confidenceAllowed) {
            return 'Population confidence '.$confidence.' is below minimum confidence '.($control['minimum_confidence'] ?? 'required').'.';
        }
        if (! $bucketAllowed) {
            return 'Selected population bucket is not allowed by admin control.';
        }
        if (! $exclusionAllowed) {
            return 'Guardrail exclusion rate exceeds admin control threshold.';
        }
        if (! $researchFallbackAllowed) {
            return 'Research benchmark fallback is unavailable, so safe auto/composite population learning is blocked.';
        }

        return 'Population quality gates did not pass.';
    }

    private function status(string $status): string
    {
        return in_array($status, PopulationLearningControl::STATUSES, true)
            ? $status
            : PopulationLearningControl::STATUS_AUTO;
    }

    private function confidence(string $confidence): string
    {
        return array_key_exists($confidence, self::CONFIDENCE_ORDER) ? $confidence : 'insufficient';
    }

    private function confidenceOrNull(mixed $confidence): ?string
    {
        if ($confidence === null || $confidence === '') {
            return null;
        }

        $confidence = strtolower(trim((string) $confidence));

        return array_key_exists($confidence, self::CONFIDENCE_ORDER) ? $confidence : null;
    }

    private function tableReady(): bool
    {
        return Schema::hasTable('population_learning_controls');
    }
}
