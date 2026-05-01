<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions\Results;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class ListSmsResults extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $smslist = SmsLog::with('user', 'user.profile', 'user.player')
                ->where('practice_id', '=', $request->practice)->get();
            if(0 === $smslist->count()) {
                $response = [
                    'code' => '054',
                    'message' => '',
                    'status' => 'success',
                    'data' => ['status_send'=>false,],
                ];
                return response()->json($response, HttpCodes::HTTP_OK);
            }
            $result = $smslist->map(function ($element) {
                return[
                    'name'=>[
                        'full'=>$element->user->profile->first_name." ".$element->user->profile->last_name,
                        'first'=>$element->user->profile->first_name,
                        'last'=>$element->user->profile->last_name,
                    ],
                    'response'=>$element->response,
                    'status'=>$element->status,
                    'message'=>$element->message,
                    'shirt'=>$element->user->player->number_in_shirt??0

                ];
            });
            $response = [
                'code' => '054',
                'message' => '',
                'status' => 'success',
                'data' => $result->values(),
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '054-E',
                'message' => ' ',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
