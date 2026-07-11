<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\DailyPlan;
use App\Models\DailyPlanAssignment;
use App\Models\PlayerTeam;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: create or update a Daily Planner plan (upsert by client id) and sync its
 * player assignments. Only players on the plan's team can be assigned.
 */
class SaveDailyPlan extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id'                  => ['nullable', 'string', 'max:64'],
                'team_id'             => ['nullable', 'string'],
                'name'                => ['nullable', 'string', 'max:200'],
                'date'                => ['nullable', 'date'],
                'phase'               => ['nullable', 'string', 'max:60'],
                'primary_goal'        => ['nullable', 'string', 'max:200'],
                'estimated_minutes'   => ['nullable', 'integer', 'min:0', 'max:1440'],
                'workload_level'      => ['nullable', 'string', 'max:40'],
                'status'              => ['nullable', 'string', 'in:draft,published,template'],
                'buckets'             => ['nullable', 'array'],
                'assigned_player_ids' => ['nullable', 'array'],
                'assigned_player_ids.*' => ['string'],
                'published_at'        => ['nullable', 'date'],
            ]);

            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();
            if (empty($teamIds)) {
                return response()->json([
                    'code'    => '091-NT',
                    'message' => 'no team for this coach',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
            }

            $planId = $validated['id'] ?? (string) Str::uuid();

            // If updating an existing plan, it must belong to one of the coach's teams.
            $existing = DailyPlan::find($planId);
            if ($existing && ! in_array($existing->team_id, $teamIds, true)) {
                return response()->json([
                    'code'    => '091-F',
                    'message' => 'not allowed to edit this plan',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            // Tie the plan to one of the coach's teams (so team staff can reuse it).
            $teamId = in_array($validated['team_id'] ?? null, $teamIds, true)
                ? $validated['team_id']
                : ($existing->team_id ?? $teamIds[0]);

            $status = $validated['status'] ?? ($existing->status ?? 'draft');

            $plan = DB::transaction(function () use ($validated, $planId, $teamId, $status, $existing) {
                $plan = DailyPlan::updateOrCreate(
                    ['id' => $planId],
                    [
                        'team_id'           => $teamId,
                        'created_by'        => $existing->created_by ?? Auth::id(),
                        'name'              => $validated['name'] ?? null,
                        'date'              => $validated['date'] ?? null,
                        'phase'             => $validated['phase'] ?? null,
                        'primary_goal'      => $validated['primary_goal'] ?? null,
                        'estimated_minutes' => $validated['estimated_minutes'] ?? null,
                        'workload_level'    => $validated['workload_level'] ?? null,
                        'status'            => $status,
                        'buckets'           => $validated['buckets'] ?? [],
                        'published_at'      => $validated['published_at']
                            ?? ($status === 'published' ? ($existing->published_at ?? now()) : null),
                    ]
                );

                // Sync assignments — only players who are actually on the plan's team.
                if (array_key_exists('assigned_player_ids', $validated)) {
                    $requested = array_values(array_unique($validated['assigned_player_ids'] ?? []));
                    $validIds  = PlayerTeam::where('team_id', $teamId)
                        ->whereIn('user_id', $requested)
                        ->pluck('user_id')
                        ->all();

                    DailyPlanAssignment::where('plan_id', $plan->id)
                        ->whereNotIn('user_id', $validIds)
                        ->delete();

                    $already = DailyPlanAssignment::where('plan_id', $plan->id)
                        ->pluck('user_id')
                        ->all();

                    foreach (array_diff($validIds, $already) as $uid) {
                        DailyPlanAssignment::create(['plan_id' => $plan->id, 'user_id' => $uid]);
                    }
                }

                return $plan;
            });

            $plan->load('assignments');

            return response()->json([
                'code'    => '091',
                'message' => 'daily plan saved',
                'status'  => 'success',
                'data'    => $plan,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('SaveDailyPlan: ' . $e->getMessage());

            return response()->json([
                'code'    => '091-E',
                'message' => 'failed to save daily plan',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
