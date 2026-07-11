<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Planner;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PlannerCustomDrill;
use Auth;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

/**
 * Coach: create or update a custom drill / lift (upsert by client id). The full
 * normalized drill is kept in `data`; identity + filter fields are promoted to
 * columns. Only the author can edit their own drill.
 */
class SaveCustomDrill extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id'             => ['nullable', 'string', 'max:64'],
                'team_id'        => ['nullable', 'string'],
                'name'           => ['required', 'string', 'max:200'],
                'bucket'         => ['required', 'string', 'max:60'],
                'category_group' => ['nullable', 'string', 'max:80'],
                'equipment'      => ['nullable', 'string', 'max:120'],
                'visibility'     => ['nullable', 'string', 'in:private,team,public'],
                'source'         => ['nullable', 'string', 'max:40'],
                'data'           => ['nullable', 'array'],
            ]);

            $drillId  = $validated['id'] ?? (string) Str::uuid();
            $existing = PlannerCustomDrill::find($drillId);

            // Only the author may edit an existing drill.
            if ($existing && $existing->created_by !== Auth::id()) {
                return response()->json([
                    'code'    => '096-F',
                    'message' => 'not allowed to edit this drill',
                    'status'  => 'error',
                    'data'    => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            // Only allow tagging the drill to one of the coach's own teams.
            $teamId = null;
            if (! empty($validated['team_id'])) {
                $teamIds = CoachTeam::where('coach_id', Auth::id())->pluck('team_id')->all();
                $teamId  = in_array($validated['team_id'], $teamIds, true) ? $validated['team_id'] : null;
            }

            // Store the whole incoming drill (minus server-managed keys) as the
            // canonical `data`, so any extended fields the app sends round-trip.
            $data = $request->except(['id', 'visibility', 'source']);

            $drill = PlannerCustomDrill::updateOrCreate(
                ['id' => $drillId],
                [
                    'created_by'     => $existing->created_by ?? Auth::id(),
                    'team_id'        => $teamId ?? ($existing->team_id ?? null),
                    'name'           => $validated['name'],
                    'bucket'         => $validated['bucket'],
                    'category_group' => $validated['category_group'] ?? null,
                    'equipment'      => $validated['equipment'] ?? null,
                    'visibility'     => $validated['visibility'] ?? ($existing->visibility ?? 'private'),
                    'source'         => $validated['source'] ?? ($existing->source ?? 'custom'),
                    'data'           => $data,
                ]
            );

            return response()->json([
                'code'    => '096',
                'message' => 'custom drill saved',
                'status'  => 'success',
                'data'    => $drill->toDrillArray(),
            ], HttpCodes::HTTP_OK);
        } catch (Exception $e) {
            Log::error('SaveCustomDrill: ' . $e->getMessage());

            return response()->json([
                'code'    => '096-E',
                'message' => 'failed to save custom drill',
                'status'  => 'error',
                'data'    => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
