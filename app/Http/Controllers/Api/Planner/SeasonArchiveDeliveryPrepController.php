<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\SeasonArchiveDeliveryPrepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SeasonArchiveDeliveryPrepController extends Controller
{
    public function preview(string $teamId, Request $request, SeasonArchiveDeliveryPrepService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'SAD-F',
                'message' => 'not allowed to prepare season archive delivery for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'SAD-P',
            'message' => 'season archive delivery preview',
            'status' => 'success',
            'data' => $service->prepareDelivery($teamId, [
                ...$this->queryFilters($request),
                'current_user_id' => (string) $request->user()->id,
            ]),
        ], HttpCodes::HTTP_OK);
    }

    public function createDraft(string $teamId, Request $request, SeasonArchiveDeliveryPrepService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'SAD-F',
                'message' => 'not allowed to create season archive delivery draft for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        $payload = $request->validate([
            'season_start_date' => ['nullable', 'date'],
            'season_end_date' => ['nullable', 'date'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'template' => ['nullable', 'string', Rule::in(['staff_review_packet', 'director_packet', 'parent_safe_season_summary', 'player_development_summary', 'internal_qa_packet'])],
            'audience' => ['nullable', 'string', Rule::in(['coach', 'staff', 'director', 'players', 'parents'])],
            'channel' => ['nullable', 'string', Rule::in(['copy', 'email', 'message', 'announcement', 'notification'])],
            'format' => ['nullable', 'string', Rule::in(['text', 'html'])],
            'include_player_rows' => ['nullable'],
            'include_benchmark_progress' => ['nullable'],
            'include_planner_progress' => ['nullable'],
            'include_communication_summary' => ['nullable'],
            'include_weekly_timeline' => ['nullable'],
            'include_next_steps' => ['nullable'],
            'include_private_notes' => ['nullable'],
            'recipient_player_ids' => ['nullable', 'array'],
            'recipient_player_ids.*' => ['nullable', 'string'],
            'recipient_user_ids' => ['nullable', 'array'],
            'recipient_user_ids.*' => ['nullable', 'string'],
            'recipient_emails' => ['nullable', 'array'],
            'recipient_emails.*' => ['nullable', 'string'],
            'message_overrides' => ['nullable', 'array'],
            'message_overrides.subject' => ['nullable', 'string'],
            'message_overrides.message_text' => ['nullable', 'string'],
            'message_overrides.message_html' => ['nullable', 'string'],
        ]);

        $draft = $service->createDraftDelivery($teamId, [
            ...$payload,
            'weeks' => max(1, min(52, (int) ($payload['weeks'] ?? 12))),
            'include_private_notes' => false,
        ], (string) $request->user()->id);

        return response()->json([
            'code' => 'SAD-D',
            'message' => 'season archive delivery draft prepared',
            'status' => 'success',
            'data' => $draft,
        ], HttpCodes::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function queryFilters(Request $request): array
    {
        $validated = $request->validate([
            'season_start_date' => ['nullable', 'date'],
            'season_end_date' => ['nullable', 'date'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'template' => ['nullable', 'string'],
            'audience' => ['nullable', 'string', Rule::in(['coach', 'staff', 'director', 'players', 'parents'])],
            'channel' => ['nullable', 'string', Rule::in(['copy', 'email', 'message', 'announcement', 'notification'])],
            'format' => ['nullable', 'string', Rule::in(['text', 'html'])],
            'include_player_rows' => ['nullable'],
            'include_benchmark_progress' => ['nullable'],
            'include_planner_progress' => ['nullable'],
            'include_communication_summary' => ['nullable'],
            'include_weekly_timeline' => ['nullable'],
            'include_next_steps' => ['nullable'],
            'include_private_notes' => ['nullable'],
            'recipient_player_ids' => ['nullable'],
            'recipient_user_ids' => ['nullable'],
            'recipient_emails' => ['nullable'],
        ]);

        $validated['weeks'] = max(1, min(52, (int) ($validated['weeks'] ?? 12)));
        $validated['template'] = (string) ($validated['template'] ?? '');
        $validated['audience'] = (string) ($validated['audience'] ?? 'staff');
        $validated['channel'] = (string) ($validated['channel'] ?? 'copy');
        $validated['format'] = (string) ($validated['format'] ?? 'text');
        $validated['include_private_notes'] = false;
        $validated['recipient_player_ids'] = $this->listFromQuery($validated['recipient_player_ids'] ?? null);
        $validated['recipient_user_ids'] = $this->listFromQuery($validated['recipient_user_ids'] ?? null);
        $validated['recipient_emails'] = $this->listFromQuery($validated['recipient_emails'] ?? null);

        return $validated;
    }

    private function canAccessTeam(Request $request, string $teamId): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if (in_array((string) ($user->type ?? ''), ['admin', 'super_admin'], true)) {
            return true;
        }

        return CoachTeam::query()
            ->where('team_id', $teamId)
            ->where('coach_id', (string) $user->id)
            ->exists();
    }

    private function listFromQuery(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($item): string => trim((string) $item),
            Arr::wrap($value)
        ))));
    }
}
