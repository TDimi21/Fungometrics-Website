<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\WeeklyReportDeliveryReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class WeeklyReportDeliveryReviewController extends Controller
{
    public function review(string $teamId, Request $request, WeeklyReportDeliveryReviewService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden('not allowed to review weekly report delivery for this team');
        }

        return response()->json([
            'code' => 'WRDR-R',
            'message' => 'weekly report delivery draft review',
            'status' => 'success',
            'data' => $service->buildDraftReview($teamId, $this->payload($request)),
        ], HttpCodes::HTTP_OK);
    }

    public function updateDraft(string $teamId, Request $request, WeeklyReportDeliveryReviewService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden('not allowed to update weekly report delivery draft for this team');
        }

        $payload = $this->payload($request);

        return response()->json([
            'code' => 'WRDR-U',
            'message' => 'weekly report delivery draft rechecked',
            'status' => 'success',
            'data' => $service->updateDraftContent($teamId, $payload, [
                'subject' => $payload['subject'] ?? null,
                'message_text' => $payload['message_text'] ?? null,
                'message_html' => $payload['message_html'] ?? null,
            ], (string) $request->user()->id),
        ], HttpCodes::HTTP_OK);
    }

    public function sendDraft(string $teamId, Request $request, WeeklyReportDeliveryReviewService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden('not allowed to send weekly report delivery draft for this team');
        }

        $payload = $this->payload($request, true);

        return response()->json([
            'code' => 'WRDR-S',
            'message' => 'weekly report delivery send result',
            'status' => 'success',
            'data' => $service->sendDraft($teamId, $payload, (string) $request->user()->id, [
                'confirm_send' => (bool) ($payload['confirm_send'] ?? false),
            ]),
        ], HttpCodes::HTTP_OK);
    }

    private function payload(Request $request, bool $send = false): array
    {
        $rules = [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'template' => ['nullable', 'string'],
            'audience' => ['nullable', 'string', Rule::in(['coach', 'staff', 'players', 'parents'])],
            'channel' => ['nullable', 'string', Rule::in(['copy', 'email', 'message', 'announcement', 'notification'])],
            'format' => ['nullable', 'string', Rule::in(['text', 'html'])],
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
        $payload['days'] = max(1, min(365, (int) ($payload['days'] ?? 7)));
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
            'code' => 'WRDR-F',
            'message' => $message,
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
