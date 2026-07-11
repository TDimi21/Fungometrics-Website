<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PlayerGroup;
use App\Models\PlayerTeam;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: create or update a player sub-group (upsert by client id). Members are
 * limited to players actually on the group's team.
 */
class SavePlayerGroup extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id'          => ['nullable', 'string', 'max:64'],
                'team_id'     => ['nullable', 'string'],
                'name'        => ['required', 'string', 'max:120'],
                'member_ids'  => ['nullable', 'array'],
                'member_ids.*' => ['string'],
            ]);

            $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();
            if (empty($teamIds)) {
                return response()->json([
                    'code'    => '0B1-NT',
                    'message' => 'no team for this coach',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_UNPROCESSABLE_ENTITY);
            }

            $groupId  = $validated['id'] ?? (string) Str::uuid();
            $existing = PlayerGroup::find($groupId);

            if ($existing && ! in_array($existing->team_id, $teamIds, true)) {
                return response()->json([
                    'code'    => '0B1-F',
                    'message' => 'not allowed to edit this group',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            $teamId = in_array($validated['team_id'] ?? null, $teamIds, true)
                ? $validated['team_id']
                : ($existing->team_id ?? $teamIds[0]);

            // Keep only players who are actually on the group's team.
            $requested  = array_values(array_unique($validated['member_ids'] ?? []));
            $validMembers = PlayerTeam::where('team_id', $teamId)
                ->whereIn('user_id', $requested)
                ->pluck('user_id')
                ->all();

            $group = PlayerGroup::updateOrCreate(
                ['id' => $groupId],
                [
                    'team_id'    => $teamId,
                    'created_by' => $existing->created_by ?? Auth::id(),
                    'name'       => $validated['name'],
                    'member_ids' => array_values($validMembers),
                ]
            );

            return response()->json([
                'code'    => '0B1',
                'message' => 'player group saved',
                'status'  => 'success',
                'data'    => $group,
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('SavePlayerGroup: ' . $e->getMessage());

            return response()->json([
                'code'    => '0B1-E',
                'message' => 'failed to save player group',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
