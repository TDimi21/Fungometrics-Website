<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Events\UserChanged;
use App\Events\UserCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\AddUserRequest;
use App\Models\CoachTeam;
use App\Models\Concerns\UserTypes;
use App\Models\User;
use App\Services\CreateServiceData;
use App\Services\ListServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AddCoaches extends Controller
{
    /**
     * @param  AddUserRequest  $request
     * @return JsonResponse
     */
    public function __invoke(AddUserRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $teamId = $data['team'];
            $actor = $request->user();
            $changedEventData = null;

            // ── Role gate: only the head coach manages coach seats ──
            if ( ! CoachUtils::isHeadCoach($actor->id, $teamId)) {
                DB::rollBack();
                return response()->json([
                    'code' => '005-ROLE',
                    'message' => 'Only the head coach can add coaches to this team.',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            $user = (new ListServiceData(new User()))->byParamFirst('phone', $data['phone']);

            // Coach already actively on this team → no-op success, no seat consumed.
            $existing = null;
            if (isset($user)) {
                $existing = CoachTeam::withTrashed()
                    ->where('coach_id', $user->id)
                    ->where('team_id', $teamId)
                    ->first();

                if ($existing && ! $existing->trashed()) {
                    DB::commit();
                    return response()->json([
                        'code' => '005',
                        'message' => 'the coach is added to team',
                        'status' => 'success',
                        'data' => [],
                    ], HttpCodes::HTTP_OK);
                }
            }

            // ── Seat limit: 5 coaches per team unless the head coach is on Coach Pro ──
            if (CoachUtils::coachSeatLimitReached($teamId)) {
                DB::rollBack();
                return response()->json([
                    'code' => '005-LIMIT',
                    'message' => 'This team has reached its '.CoachUtils::coachSeatLimit($teamId)
                        .'-coach limit.',
                    'status' => 'error',
                    'data' => [],
                ], HttpCodes::HTTP_FORBIDDEN);
            }

            if ( ! isset($user)) {
                $saved = CoachUtils::saveNewUser($data, UserTypes::COACH->value);
                event(new UserCreated($saved['user']));
            } else {
                // Ensure existing user is promoted to coach type
                if ($user->type !== UserTypes::COACH->value) {
                    $user->update(['type' => UserTypes::COACH->value]);
                }

                if ($existing && $existing->trashed()) {
                    $existing->restore();
                    $membership = $existing;
                } else {
                    $membership = (new CreateServiceData(new CoachTeam()))->handle([
                        'team_id' => $teamId,
                        'coach_id' => $user->id,
                        'is_main' => false,
                    ]);
                }
                $changedEventData = ['user' => $user, 'team' => $membership];
            }

            $response = [
                'code' => '005',
                'message' => 'the coach is added to team',
                'status' => 'success',
                'data' => [],
            ];
            DB::commit();
            if (null !== $changedEventData) {
                event(new UserChanged($changedEventData));
            }

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '005-E',
                'message' => 'error to add coach ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());

            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
