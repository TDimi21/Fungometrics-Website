<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterCoachRequest;
use App\Models\Concerns\LevelTypes;
use App\Models\Concerns\UserTypes;
use App\Models\Profile;
use App\Models\User;
use App\Services\UploadS3File;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class CompleteCoachController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(RegisterCoachRequest $request, User $user): JsonResponse
    {
        try {
            DB::beginTransaction();
            //  $url = UploadS3File::getUrl($request->logo, '/teams');

            $user->update([
                'email' => $request->get('email'),
                'password' => bcrypt($request->get('password')),
                'type' => UserTypes::COACH->value,
            ]);

            Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name' => $request->get('profile')['name']['first'],
                    'last_name' => $request->get('profile')['name']['last'],
                    'level' => $request->get('profile')['level'] ?? LevelTypes::PLAYER->value,
                    'city' => $request->get('city'),
                    'state' => $request->get('state'),
                    'zip' => $request->get('zip'),
                ]
            );

            $response = [
                'code' => 'code',
                'message' => 'register coach ok',
                'status' => 'success',
                'data' => [],
            ];
            DB::commit();
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
