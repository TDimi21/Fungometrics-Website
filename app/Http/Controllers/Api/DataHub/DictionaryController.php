<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PlatformDefinition;
use App\Models\UnknownSourceColumn;
use App\Services\Access\AdministrativeAccess;
use App\Services\DataHub\Dictionary\BaseballDictionaryService;
use App\Services\DataHub\Dictionary\ConceptSubmissionService;
use App\Services\DataHub\Dictionary\ConceptCompatibilityService;
use App\Services\DataHub\Dictionary\MappingApprovalService;
use App\Services\DataHub\Dictionary\MappingResolutionService;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\Dictionary\UnknownColumnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class DictionaryController extends Controller
{
    public function index(BaseballDictionaryService $dictionary): JsonResponse
    {
        return response()->json(['success' => true,'data' => $dictionary->catalog()]);
    }
    public function resolve(Request $request, TemplateFingerprintService $fingerprints, MappingResolutionService $resolver): JsonResponse
    {
        $data = $request->validate(['team_id' => ['required','uuid'],'platform' => ['required','string', Rule::exists('platform_definitions', 'key')->where('is_active', true)],'headers' => ['required','array'],'headers.*' => ['string']]);
        $this->team($request, $data['team_id']);
        $platform = PlatformDefinition::query()->where('key', $data['platform'])->firstOrFail();
        $fingerprint = $fingerprints->fingerprint($data['headers']);
        return response()->json(['success' => true,'data' => ['template_fingerprint' => $fingerprint,'columns' => $resolver->resolve($data['team_id'], $platform->id, $fingerprint, $data['headers'])]]);
    }
    public function approve(Request $request, MappingApprovalService $approval, UnknownColumnService $unknown, ConceptCompatibilityService $compatibility): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required','uuid'],'platform' => ['required','string', Rule::exists('platform_definitions', 'key')->where('is_active', true)],'template_fingerprint' => ['required','size:64'],
            'headers' => ['required','array'],'headers.*' => ['required','string','max:255'],'entries' => ['required','array','min:1'],
            'entries.*.source_column_name' => ['required','string','max:255'],'entries.*.normalized_source_column' => ['required','string','max:255'],
            'entries.*.baseball_concept_id' => ['nullable','uuid','exists:baseball_concepts,id'],
            'entries.*.source_unit_id' => ['nullable','uuid','exists:unit_definitions,id'],'entries.*.canonical_unit_id' => ['nullable','uuid','exists:unit_definitions,id'],
            'entries.*.transformation_key' => ['nullable','string','max:128'],'entries.*.resolution_source' => ['required','string','max:40'],
            'entries.*.confidence' => ['required','integer','between:0,100'],'entries.*.required_type' => ['nullable','string','max:40'],
            'entries.*.action' => ['required','in:map,ignore,store_unknown,submit_new'],'entries.*.metadata' => ['nullable','array'],
            'entries.*.compatibility_level' => ['required','in:compatible,warning,incompatible,not_importing'],
            'entries.*.warning_confirmed' => ['boolean'],
            'destination' => ['required','string','max:40'],
            'confirmed_duplicate_concepts' => ['array'],
            'confirmed_duplicate_concepts.*' => ['uuid'],
            'remember' => ['boolean'],
        ]);
        $this->team($request, $data['team_id']);
        $platform = PlatformDefinition::query()->where('key', $data['platform'])->firstOrFail();
        $mapped = array_values(array_filter($data['entries'], fn (array $entry): bool => 'map' === $entry['action'] && ! empty($entry['baseball_concept_id'])));
        $domains = DB::table('baseball_concepts')
            ->join('baseball_domains', 'baseball_domains.id', '=', 'baseball_concepts.domain_id')
            ->whereIn('baseball_concepts.id', array_column($mapped, 'baseball_concept_id'))
            ->pluck('baseball_domains.key', 'baseball_concepts.id');
        if (0 === count(array_filter($mapped, fn (array $entry): bool => 'session_context' !== ($domains[$entry['baseball_concept_id']] ?? null)))) {
            throw ValidationException::withMessages(['entries' => 'Connect at least one performance column before continuing.']);
        }
        $classified = array_map(fn (array $entry): array => $entry + [
            'server_compatibility_level' => $compatibility->classify($data['destination'], $domains[$entry['baseball_concept_id']] ?? null),
        ], $mapped);
        if (array_filter($classified, fn (array $entry): bool => 'incompatible' === $entry['server_compatibility_level'])) {
            throw ValidationException::withMessages(['entries' => 'Incompatible columns must be changed or marked Not Importing.']);
        }
        if (array_filter($classified, fn (array $entry): bool => 'warning' === $entry['server_compatibility_level'] && ! ($entry['warning_confirmed'] ?? false))) {
            throw ValidationException::withMessages(['entries' => 'Confirm every destination compatibility warning before approval.']);
        }
        $counts = array_count_values(array_column($mapped, 'baseball_concept_id'));
        $unconfirmedDuplicates = array_filter($counts, fn (int $count, string $conceptId): bool => $count > 1 && ! in_array($conceptId, $data['confirmed_duplicate_concepts'] ?? [], true), ARRAY_FILTER_USE_BOTH);
        if ($unconfirmedDuplicates) {
            throw ValidationException::withMessages(['entries' => 'Confirm duplicate Baseball Concept mappings before approval.']);
        }
        foreach($data['entries'] as $entry) {
            if(($entry['action'] ?? '') === 'store_unknown') {
                $unknown->remember($data['team_id'], $platform->id, $data['template_fingerprint'], $entry['source_column_name'], $entry['metadata']['sample_values'] ?? []);
            }
        }
        $version = $approval->approve($request->user(), $data['team_id'], $platform->id, $data['template_fingerprint'], $data['headers'], $data['entries'], (bool)($data['remember'] ?? false));
        return response()->json(['success' => true,'data' => ['approved' => true,'version' => $version?->version,'remembered' => (bool)$version]]);
    }
    public function unknown(Request $request): JsonResponse
    {
        $data = $request->validate(['team_id' => ['required','uuid']]);
        $this->team($request, $data['team_id']);
        return response()->json(['success' => true,'data' => UnknownSourceColumn::query()->where('team_id', $data['team_id'])->latest('last_seen_at')->get()]);
    }
    public function updateUnknown(Request $request, UnknownSourceColumn $unknown): JsonResponse
    {
        $this->team($request, $unknown->team_id);
        $data = $request->validate(['status' => ['required','in:unresolved,resolved,ignored,archived'],'resolved_concept_id' => ['nullable','uuid','exists:baseball_concepts,id']]);
        if('resolved' === $data['status'] && ! isset($data['resolved_concept_id'])) {
            return response()->json(['success' => false,'message' => 'Choose a Baseball Concept before resolving this column.'], 422);
        }
        $unknown->update($data);
        return response()->json(['success' => true,'data' => $unknown->fresh()]);
    }
    public function submit(Request $request, ConceptSubmissionService $service): JsonResponse
    {
        $data = $request->validate(['team_id' => ['required','uuid'],'platform_definition_id' => ['nullable','uuid'],'source_column_name' => ['required','string'],'proposed_display_name' => ['required','string'],'proposed_domain_id' => ['nullable','uuid'],'proposed_unit_key' => ['nullable','string'],'description' => ['nullable','string'],'sample_values' => ['nullable','array']]);
        $this->team($request, $data['team_id']);
        return response()->json(['success' => true,'data' => $service->submit($request->user(), $data)], 201);
    }
    private function team(Request $request, string $teamId): void
    {
        $admin = app(AdministrativeAccess::class)->canManageSubscriptions($request->user());
        abort_unless($admin || CoachTeam::query()->where('coach_id', $request->user()->id)->where('team_id', $teamId)->exists(), 403);
    }
}
