<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProductCopyQaAudit extends Command
{
    protected $signature = 'product:copy-qa
        {--frontend : Scan common frontend product surfaces}
        {--backend : Reserved for backend display-copy scanning}
        {--json : Output JSON}';

    protected $description = 'Warn about likely user-facing raw technical terms in FMTRX product copy.';

    /**
     * @var array<string, string>
     */
    private array $terms = [
        'source_mix' => 'Benchmark Source',
        'population_policy' => 'Population Learning Policy',
        'global_clean' => 'Broad FMTRX Population',
        'trusted_payload_only' => 'Trusted Benchmark Data',
        'submitted_payload' => 'Submitted Results',
        'approved_payload' => 'Approved Results',
        'payload' => 'data details',
        'bucket_count' => 'Comparison Group Size',
        'selected_bucket_key' => 'Selected Comparison Group',
        'attempted_buckets' => 'Comparison Groups Checked',
        'review_status' => 'Coach Review Status',
        'metric_key' => 'Metric',
        'command_center' => 'Coach Command Center',
    ];

    /**
     * @var array<int, string>
     */
    private array $frontendFiles = [
        'resources/js/pages/practice/DailyPlanner.vue',
        'resources/js/features/development/pages/TeamDevelopmentDashboard.vue',
        'resources/js/components/planner/PlayerWorkoutsPanel.vue',
    ];

    public function handle(): int
    {
        $scanFrontend = (bool) $this->option('frontend') || ! $this->option('backend');
        $findings = [];

        if ($scanFrontend) {
            foreach ($this->frontendFiles as $file) {
                $findings = array_merge($findings, $this->scanFile(base_path($file), $file));
            }
        }

        $result = [
            'status' => empty($findings) ? 'passed' : 'warning',
            'finding_count' => count($findings),
            'findings' => $findings,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX PRODUCT COPY QA');
        $this->line('Status: '.strtoupper($result['status']));
        $this->line('Findings: '.$result['finding_count']);
        $this->newLine();

        if (empty($findings)) {
            $this->line('- No likely user-facing raw terms found in the scanned frontend files.');

            return self::SUCCESS;
        }

        foreach ($findings as $finding) {
            $this->line(sprintf(
                '- %s:%d [%s] Suggestion: %s',
                $finding['file'],
                $finding['line'],
                $finding['term'],
                $finding['suggestion'],
            ));
            $this->line('  '.$finding['excerpt']);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scanFile(string $path, string $relativePath): array
    {
        if (! is_file($path)) {
            return [[
                'file' => $relativePath,
                'line' => 0,
                'term' => 'missing_file',
                'suggestion' => 'Confirm the target file exists.',
                'excerpt' => 'File was not found.',
            ]];
        }

        $findings = [];
        $inTemplate = false;

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '<template')) {
                $inTemplate = true;
            }
            if (str_starts_with($trimmed, '</template')) {
                $inTemplate = false;
            }

            foreach ($this->terms as $term => $suggestion) {
                if (! str_contains(strtolower($line), strtolower($term))) {
                    continue;
                }

                if (! $this->looksUserFacing($line, $term, $inTemplate)) {
                    continue;
                }

                $findings[] = [
                    'file' => $relativePath,
                    'line' => $index + 1,
                    'term' => $term,
                    'suggestion' => $suggestion,
                    'excerpt' => trim($line),
                ];
            }
        }

        return $findings;
    }

    private function looksUserFacing(string $line, string $term, bool $inTemplate): bool
    {
        $lower = strtolower($line);

        foreach ([':key=', ':class=', 'v-for=', 'v-if=', 'v-else', '@click=', 'data-dp-section=', 'data-'] as $skip) {
            if (str_contains($lower, $skip)) {
                return false;
            }
        }

        if ($inTemplate) {
            $staticLine = preg_replace('/\{\{.*?\}\}/', '', $line) ?? $line;

            return preg_match('/>[^<]*'.preg_quote($term, '/').'[^<]*</i', $staticLine) === 1
                || preg_match('/(?:title|aria-label|placeholder)=["\'][^"\']*'.preg_quote($term, '/').'[^"\']*["\']/i', $staticLine) === 1;
        }

        if (preg_match('/^\s*(const|let|return|if|for|while|switch|\}|\]|\[)/', $line) === 1) {
            return false;
        }

        return str_contains(strtolower($this->quotedLiteralText($line)), strtolower($term));
    }

    private function quotedLiteralText(string $line): string
    {
        preg_match_all('/([\'"`])((?:\\\\.|(?!\1).)*?)\1/s', $line, $matches);

        return collect($matches[2] ?? [])
            ->reject(fn (string $literal): bool => preg_match('/^[a-z0-9_:\-]+$/', $literal) === 1)
            ->map(fn (string $literal): string => preg_replace('/\$\{[^}]*\}/', '', $literal) ?? $literal)
            ->implode(' ');
    }
}
