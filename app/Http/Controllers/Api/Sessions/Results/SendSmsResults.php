<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions\Results;

use App\Events\SentResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\SendSMSRequest;
use App\Models\Practice;
use App\Models\SmsLog;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SendSmsResults extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(SendSMSRequest $request): JsonResponse
    {
        try {
            Practice::findOrFail($request->practice);
            $practice = SmsLog::where('practice_id', $request->practice)
                ->first();
            if(null === $practice) {
                foreach ($request->players as $item) {
                    event(new SentResults(['data'=>$item,'practice'=>$request->practice]));
                }
                $response = 'Results sent';
                $action = true;
            } else {
                $response = 'The results of this practice have already been sent.';
                $action = false;
            }
            $response = [
                'code' => '053',
                'message' => $response,
                'status' => 'success',
                'data' => $action,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '053-E',
                'message' => 'NOT SENT SMS',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
