<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\AddTeamRequest;
use App\Models\CoachTeam;
use App\Models\Team;
use App\Services\CreateServiceData;
use App\Services\UploadS3File;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AddTeams extends Controller
{
    /**
     * @param  AddTeamRequest  $request
     * @return JsonResponse
     */
    public function __invoke(AddTeamRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $url = UploadS3File::getUrl($request->logo, '/teams');
            $data = $request->validated();

            $serviceTeam = new CreateServiceData(new Team());
            $data['logo']= $url;
            $responseTeam = $serviceTeam->handle($data);

            (new CreateServiceData(new CoachTeam()))->handle([
                'coach_id' => Auth::id(),
                'team_id' => $responseTeam->id,
                'is_main' => true,
            ]);

            $response = [
                'code' => '004',
                'message' => 'add team ok',
                'status' => 'success',
                'data' => $responseTeam,
            ];
            DB::commit();

            return response()->json($response, HttpCodes::HTTP_CREATED);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '004-E',
                'message' => 'error to add team ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());

            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
