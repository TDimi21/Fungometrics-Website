<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterCoachRequest;
use App\Models\CoachTeam;
use App\Models\Team;
use App\Services\CreateServiceData;
use App\Services\UploadS3File;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class RegisterCoachController extends Controller
{
    /**
     * @param  RegisterCoachRequest  $request
     * @return JsonResponse
     */
    public function __invoke(RegisterCoachRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $url = UploadS3File::getUrl($request->logo, '/teams');
            $response_user = AuthUtils::saveUser($request, true);
            $response_profile = AuthUtils::saveProfile($request, $response_user, $url);
            $response_team = $this->saveTeam($request, $url, $response_user);
            $this->saveTeamCoach($response_user, $response_team, true);
            DB::commit();
            $data_to_response = [
                'user' => $response_user,
                'profile' => $response_profile,
                'team' => $response_team,
                'token' => null !== $request->get('email') ? AuthUtils::createTokenFromUser($response_user)['token'] : '',
            ];
            $response = [
                'code' => '003',
                'message' => 'register coach ok',
                'status' => 'success',
                'data' => $data_to_response,
            ];

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            UploadS3File::deleteObject($url, 'teams');
            DB::rollBack();
            $response = [
                'code' => '003-E',
                'message' => 'error to register coach',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());

            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function saveTeam(RegisterCoachRequest $request, string $url, Model $response_user): Model
    {
        $request_team_data = [
            'name' => $request->get('team'),
            'city' => $request->get('city'),
            'state' => $request->get('state'),
            'zip' => $request->get('zip'),
            'logo' => $url,
        ];

        return (new CreateServiceData(new Team()))->handle($request_team_data);
    }

    public function saveTeamCoach(Model $user, Model $team, bool $coach = false): Model
    {
        return (new CreateServiceData(new CoachTeam()))->handle([
            'coach_id' => $user->id,
            'team_id' => $team->id,
            'is_main' => $coach,
        ]);
    }
}
