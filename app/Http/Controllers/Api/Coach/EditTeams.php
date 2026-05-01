<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\RoasterUtils;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Coach\EditTeamsRequest;
use App\Models\Team;
use App\Services\UploadS3File;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class EditTeams extends Controller
{
    /**
     * @param EditTeamsRequest $request
     * @return JsonResponse
     */
    public function __invoke(EditTeamsRequest $request): JsonResponse
    {
        try {
            $dataEdit = $request->validated();
            $model = Team::findOrFail($request->team);
            if (isset($dataEdit['logo']) && RoasterUtils::isImage($dataEdit['logo'])) {
                UploadS3File::deleteObject($model->logo, '/teams');
                $dataEdit['logo'] = UploadS3File::getUrl($request->logo, '/teams');
            }
            $model->update($dataEdit);

            $response = [
                'code' => '032',
                'message' => 'team '.$request->team.' updated',
                'status' => 'success',
                'data' => $model,
            ];

            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '032-E',
                'message' => 'error  team '.$request->team.' updated ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
