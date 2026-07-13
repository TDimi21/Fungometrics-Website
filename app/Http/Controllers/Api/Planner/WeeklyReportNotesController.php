<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\WeeklyReportNote;
use App\Services\Planner\WeeklyReportNotesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class WeeklyReportNotesController extends Controller
{
    public function index(string $teamId, Request $request, WeeklyReportNotesService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden();
        }

        return response()->json([
            'code' => 'WRN-L',
            'message' => 'weekly report notes',
            'status' => 'success',
            'data' => [
                'notes' => $service->listNotes($teamId, [
                    'start_date' => $request->query('start_date'),
                    'end_date' => $request->query('end_date'),
                    'days' => $this->days($request),
                    'audience' => $request->query('audience'),
                    'visibility' => $request->query('visibility'),
                    'note_type' => $request->query('note_type'),
                    'player_id' => $request->query('player_id'),
                ]),
            ],
        ], HttpCodes::HTTP_OK);
    }

    public function store(string $teamId, Request $request, WeeklyReportNotesService $service): JsonResponse
    {
        if (! $this->canAccessTeam($request, $teamId)) {
            return $this->forbidden();
        }

        $validated = $this->validated($request, false);

        return response()->json([
            'code' => 'WRN-C',
            'message' => 'weekly report note saved',
            'status' => 'success',
            'data' => [
                'note' => $service->saveNote($teamId, [
                    ...$validated,
                    'start_date' => $request->input('start_date'),
                    'end_date' => $request->input('end_date'),
                    'days' => $this->days($request),
                ], (string) $request->user()->id),
            ],
        ], HttpCodes::HTTP_CREATED);
    }

    public function update(string $noteId, Request $request, WeeklyReportNotesService $service): JsonResponse
    {
        $note = WeeklyReportNote::query()->find($noteId);
        if (! $note || ! $this->canAccessTeam($request, (string) $note->team_id)) {
            return $this->forbidden();
        }

        $validated = $this->validated($request, true);

        return response()->json([
            'code' => 'WRN-U',
            'message' => 'weekly report note updated',
            'status' => 'success',
            'data' => [
                'note' => $service->updateNote($noteId, $validated, (string) $request->user()->id),
            ],
        ], HttpCodes::HTTP_OK);
    }

    public function destroy(string $noteId, Request $request, WeeklyReportNotesService $service): JsonResponse
    {
        $note = WeeklyReportNote::query()->find($noteId);
        if (! $note || ! $this->canAccessTeam($request, (string) $note->team_id)) {
            return $this->forbidden();
        }

        return response()->json([
            'code' => 'WRN-D',
            'message' => 'weekly report note deleted',
            'status' => 'success',
            'data' => [
                'note' => $service->deleteNote($noteId, (string) $request->user()->id),
            ],
        ], HttpCodes::HTTP_OK);
    }

    private function validated(Request $request, bool $partial): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'audience' => [$partial ? 'sometimes' : 'nullable', 'string', Rule::in(WeeklyReportNotesService::AUDIENCES)],
            'note_type' => [$partial ? 'sometimes' : 'nullable', 'string', Rule::in(WeeklyReportNotesService::NOTE_TYPES)],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => [$required, 'string', 'max:5000'],
            'visibility' => [$partial ? 'sometimes' : 'nullable', 'string', Rule::in(WeeklyReportNotesService::VISIBILITIES)],
            'player_id' => ['nullable', 'string', 'max:64'],
            'payload' => ['nullable', 'array'],
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

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'code' => 'WRN-F',
            'message' => 'not allowed to manage weekly report notes',
            'status' => 'error',
            'data' => [],
        ], HttpCodes::HTTP_FORBIDDEN);
    }

    private function days(Request $request): int
    {
        $days = (int) ($request->input('days', $request->query('days', 7)));

        return max(1, min(365, $days));
    }
}
