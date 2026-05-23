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
            $user = (new ListServiceData(new User()))->byParamFirst('phone', $data['phone']);

            if ( ! isset($user)) {
                $savePlayer = CoachUtils::saveNewUser($data, UserTypes::COACH->value);
                event(new UserCreated($savePlayer['user']));
            }

            if (isset($user)) {
                // Ensure existing user is promoted to coach type
                if ($user->type !== UserTypes::COACH->value) {
                    $user->update(['type' => UserTypes::COACH->value]);
                }

                // Avoid duplicate CoachTeam record — also restore if soft-deleted
                $existing = CoachTeam::withTrashed()
                    ->where('coach_id', $user->id)
                    ->where('team_id', $data['team'])
                    ->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    DB::commit();
                    return response()->json([
                        'code' => '005',
                        'message' => 'the coach is added to team',
                        'status' => 'success',
                        'data' => [],
                    ], HttpCodes::HTTP_OK);
                }

                (new CreateServiceData(new CoachTeam()))->handle([
                    'team_id' => $data['team'],
                    'coach_id' => $user->id,
                    'is_main' => false,
                ]);
            }

            $response = [
                'code' => '005',
                'message' => 'the coach is added to team',
                'status' => 'success',
                'data' => [],
            ];
            DB::commit();

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
