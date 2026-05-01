<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Api\RoasterUtils;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UploadS3File;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class EditCoach extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $dataUser = User::with('profile')->find(Auth::user()->id);
            $dataUser->update([
                'email'=>$request->email,
                'phone'=>$request->phone
            ]);

            $url = $request->picture;

            if (RoasterUtils::isImage($request->picture)) {
                $url = UploadS3File::getUrl($request->picture, '/players');
            }

            $dataUser->profile->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'picture' => $url,
            ]);

            $response = [
                'code' => '037',
                'message' => 'edit coach',
                'status' => 'success',
                'data' => $dataUser,
            ];
            DB::commit();
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            DB::rollBack();
            $response = [
                'code' => '037-E',
                'message' => ' ',
                'status' => 'error to edit coach',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
