<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Planner\DevelopmentHealthAlertActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class DevelopmentHealthAlertActionsController extends Controller
{
    public function index(string $teamId, Request $request, DevelopmentHealthAlertActionService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden('not allowed to view development health alert actions for this team');
        }

        return response()->json([
            'code' => 'DHAA-I',
            'message' => 'development health alert actions',
            'status' => 'success',
            'data' => $service->buildActionsForTeam($teamId, $this->filters($request)),
        ], HttpCodes::HTTP_OK);
    }

    public function execute(string $teamId, Request $request, DevelopmentHealthAlertActionService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden('not allowed to run development health alert actions for this team');
        }

        $validated = $request->validate([
            'alert_id' => ['nullable', 'string', 'max:120'],
            'action_type' => ['required', 'string', 'max:80'],
            'payload' => ['nullable', 'array'],
            'confirm' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'severity_threshold' => ['nullable', 'in:critical,high,medium,low'],
        ]);

        $result = $service->executeAlertAction(
            $teamId,
            (string) $validated['action_type'],
            $validated,
            (string) $request->user()?->id,
        );

        return response()->json([
            'code' => 'DHAA-X',
            'message' => 'development health alert action result',
            'status' => ($result['status'] ?? null) === 'failed' ? 'error' : 'success',
            'data' => $result,
        ], ($result['status'] ?? null) === 'failed'
            ? HttpCodes::HTTP_UNPROCESSABLE_ENTITY
            : HttpCodes::HTTP_OK);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'weeks' => ['nullable', 'integer', 'min:1', 'max:52'],
            'severity_threshold' => ['nullable', 'in:critical,high,medium,low'],
        ]);
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
            'code' => 'DHAA-F',
            'message' => $message,
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }
}
