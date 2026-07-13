<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\SeasonArchiveDeliveryReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SeasonArchiveDeliveryReviewController extends Controller
{
    public function review(string $teamId, Request $request, SeasonArchiveDeliveryReviewService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden('not allowed to review season archive delivery for this team');
        }

        return response()->json([
            'code' => 'SADR-R',
            'message' => 'season archive delivery draft review',
            'status' => 'success',
            'data' => $service->buildDraftReview($teamId, [
                ...$this->payload($request),
                'record_history' => true,
                'current_user_id' => (string) $request->user()->id,
            ]),
        ], HttpCodes::HTTP_OK);
    }

    public function updateDraft(string $teamId, Request $request, SeasonArchiveDeliveryReviewService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden('not allowed to update season archive delivery draft for this team');
        }

        $payload = $this->payload($request);

        return response()->json([
            'code' => 'SADR-U',
            'message' => 'season archive delivery draft rechecked',
            'status' => 'success',
            'data' => $service->updateDraftContent($teamId, $payload, [
                'subject' => $payload['subject'] ?? null,
                'message_text' => $payload['message_text'] ?? null,
                'message_html' => $payload['message_html'] ?? null,
            ], (string) $request->user()->id),
        ], HttpCodes::HTTP_OK);
    }

    public function sendDraft(string $teamId, Request $request, SeasonArchiveDeliveryReviewService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden('not allowed to send season archive delivery draft for this team');
        }

        $payload = $this->payload($request, true);

        return response()->json([
            'code' => 'SADR-S',
            'message' => 'season archive delivery send result',
            'status' => 'success',
            'data' => $service->sendDraft($teamId, $payload, (string) $request->user()->id, [
                'confirm_send' => (bool) ($payload['confirm_send'] ?? false),
            ]),
        ], HttpCodes::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, bool $send = false): array
    {
        $rules = [
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
            'subject' => ['nullable', 'string'],
            'message_text' => ['nullable', 'string'],
            'message_html' => ['nullable', 'string'],
            'recipient_player_ids' => ['nullable', 'array'],
            'recipient_player_ids.*' => ['nullable', 'string'],
            'recipient_user_ids' => ['nullable', 'array'],
            'recipient_user_ids.*' => ['nullable', 'string'],
            'recipient_emails' => ['nullable', 'array'],
            'recipient_emails.*' => ['nullable', 'string'],
            'message_overrides' => ['nullable', 'array'],
        ];

        if ($send) {
            $rules['confirm_send'] = ['accepted'];
        } else {
            $rules['confirm_send'] = ['nullable', 'boolean'];
        }

        $payload = $request->validate($rules);
        $payload['weeks'] = max(1, min(52, (int) ($payload['weeks'] ?? 12)));
        $payload['include_private_notes'] = false;

        return $payload;
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

    private function forbidden(string $message): JsonResponse
    {
        return response()->json([
            'code' => 'SADR-F',
            'message' => $message,
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
