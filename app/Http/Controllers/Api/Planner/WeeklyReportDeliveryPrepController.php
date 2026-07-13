<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\WeeklyReportDeliveryHistoryService;
use App\Services\Planner\WeeklyReportDeliveryPrepService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class WeeklyReportDeliveryPrepController extends Controller
{
    public function preview(string $teamId, Request $request, WeeklyReportDeliveryPrepService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'WRD-F',
                'message' => 'not allowed to prepare weekly report delivery for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        return response()->json([
            'code' => 'WRD-P',
            'message' => 'weekly report delivery preview',
            'status' => 'success',
            'data' => $service->prepareDelivery($teamId, [
                'start_date' => $request->query('start_date'),
                'end_date' => $request->query('end_date'),
                'days' => $this->days($request),
                'template' => $request->query('template'),
                'audience' => $request->query('audience', 'coach'),
                'channel' => $request->query('channel', 'copy'),
                'format' => $request->query('format', 'text'),
                'include_player_rows' => $request->query('include_player_rows', true),
                'include_benchmark_details' => $request->query('include_benchmark_details', true),
                'include_private_notes' => false,
                'recipient_player_ids' => $this->listFromQuery($request->query('recipient_player_ids')),
                'recipient_user_ids' => $this->listFromQuery($request->query('recipient_user_ids')),
                'recipient_emails' => $this->listFromQuery($request->query('recipient_emails')),
                'current_user_id' => (string) $request->user()->id,
            ]),
        ], HttpCodes::HTTP_OK);
    }

    public function createDraft(string $teamId, Request $request, WeeklyReportDeliveryPrepService $service, WeeklyReportDeliveryHistoryService $historyService): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return response()->json([
                'code' => 'WRD-F',
                'message' => 'not allowed to create weekly report delivery draft for this team',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_FORBIDDEN);
        }

        $payload = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'template' => ['nullable', 'string'],
            'audience' => ['nullable', 'string', Rule::in(['coach', 'staff', 'players', 'parents'])],
            'channel' => ['nullable', 'string', Rule::in(['copy', 'email', 'message', 'announcement', 'notification'])],
            'format' => ['nullable', 'string', Rule::in(['text', 'html'])],
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
            'days' => max(1, min(365, (int) ($payload['days'] ?? 7))),
            'include_private_notes' => false,
        ], (string) $request->user()->id);
        $draft['delivery_history'] = $historyService->recordDraftCreated($draft, (string) $request->user()->id);

        return response()->json([
            'code' => 'WRD-D',
            'message' => 'weekly report delivery draft prepared',
            'status' => 'success',
            'data' => $draft,
        ], HttpCodes::HTTP_OK);
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

    private function days(Request $request): int
    {
        $days = (int) $request->query('days', 7);

        return max(1, min(365, $days));
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
