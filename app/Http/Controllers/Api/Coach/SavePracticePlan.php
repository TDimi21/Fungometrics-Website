<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PracticePlan;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SavePracticePlan extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id'                => ['nullable', 'string', 'max:64'],
                'team_id'           => ['nullable', 'string'],
                'title'             => ['required', 'string', 'max:200'],
                'date'              => ['nullable', 'date'],
                'focus'             => ['nullable', 'string', 'max:40'],
                'notes'             => ['nullable', 'string', 'max:4000'],
                'total_duration'    => ['nullable', 'integer', 'min:0', 'max:1440'],
                'scheduled_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
                'drill_count'       => ['nullable', 'integer', 'min:0', 'max:200'],
                'slots'             => ['nullable', 'array'],
            ]);

            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();
            if (empty($teamIds)) {
                return response()->json([
                    'code'    => '081-NT',
                    'message' => 'no team for this coach',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
            }

            $planId = $validated['id'] ?? (string) Str::uuid();

            // If updating an existing plan, it must belong to one of the coach's teams.
            $existing = PracticePlan::find($planId);
            if ($existing && ! in_array($existing->team_id, $teamIds, true)) {
                return response()->json([
                    'code'    => '081-F',
                    'message' => 'not allowed to edit this plan',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            // Tie the plan to one of the coach's teams (so team staff can reuse it).
            $teamId = in_array($validated['team_id'] ?? null, $teamIds, true)
                ? $validated['team_id']
                : ($existing->team_id ?? $teamIds[0]);

            $plan = PracticePlan::updateOrCreate(
                ['id' => $planId],
                [
                    'team_id'           => $teamId,
                    'created_by'        => $existing->created_by ?? Auth::id(),
                    'title'             => $validated['title'],
                    'date'              => $validated['date'] ?? null,
                    'focus'             => $validated['focus'] ?? null,
                    'notes'             => $validated['notes'] ?? null,
                    'total_duration'    => $validated['total_duration'] ?? null,
                    'scheduled_minutes' => $validated['scheduled_minutes'] ?? null,
                    'drill_count'       => $validated['drill_count'] ?? null,
                    'slots'             => $validated['slots'] ?? [],
                ]
            );

            return response()->json([
                'code'    => '081',
                'message' => 'practice plan saved',
                'status'  => 'success',
                'data'    => $plan,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('SavePracticePlan: ' . $e->getMessage());

            return response()->json([
                'code'    => '081-E',
                'message' => 'failed to save practice plan',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
