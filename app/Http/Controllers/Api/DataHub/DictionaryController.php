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
use App\Services\DataHub\Dictionary\MappingApprovalService;
use App\Services\DataHub\Dictionary\MappingResolutionService;
use App\Services\DataHub\Dictionary\TemplateFingerprintService;
use App\Services\DataHub\Dictionary\UnknownColumnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DictionaryController extends Controller
{
    public function index(BaseballDictionaryService $dictionary): JsonResponse
    {
        return response()->json(['success' => true,'data' => $dictionary->catalog()]);
    }
    public function resolve(Request $request, TemplateFingerprintService $fingerprints, MappingResolutionService $resolver): JsonResponse
    {
        $data = $request->validate(['team_id' => ['required','uuid'],'platform' => ['required','string'],'headers' => ['required','array'],'headers.*' => ['string']]);
        $this->team($request, $data['team_id']);
        $platform = PlatformDefinition::query()->where('key', $data['platform'])->firstOrFail();
        $fingerprint = $fingerprints->fingerprint($data['headers']);
        return response()->json(['success' => true,'data' => ['template_fingerprint' => $fingerprint,'columns' => $resolver->resolve($data['team_id'], $platform->id, $fingerprint, $data['headers'])]]);
    }
    public function approve(Request $request, MappingApprovalService $approval, UnknownColumnService $unknown): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required','uuid'],'platform' => ['required','string'],'template_fingerprint' => ['required','size:64'],
            'headers' => ['required','array'],'headers.*' => ['required','string','max:255'],'entries' => ['required','array','min:1'],
            'entries.*.source_column_name' => ['required','string','max:255'],'entries.*.normalized_source_column' => ['required','string','max:255'],
            'entries.*.baseball_concept_id' => ['nullable','uuid','exists:baseball_concepts,id'],
            'entries.*.source_unit_id' => ['nullable','uuid','exists:unit_definitions,id'],'entries.*.canonical_unit_id' => ['nullable','uuid','exists:unit_definitions,id'],
            'entries.*.transformation_key' => ['nullable','string','max:128'],'entries.*.resolution_source' => ['required','string','max:40'],
            'entries.*.confidence' => ['required','integer','between:0,100'],'entries.*.required_type' => ['nullable','string','max:40'],
            'entries.*.action' => ['required','in:map,ignore,store_unknown,submit_new'],'entries.*.metadata' => ['nullable','array'],
            'remember' => ['boolean'],
        ]);
        $this->team($request, $data['team_id']);
        $platform = PlatformDefinition::query()->where('key', $data['platform'])->firstOrFail();
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
