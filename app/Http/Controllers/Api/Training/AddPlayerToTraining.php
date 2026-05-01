<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Http\Resources\LineUpResource;
use App\Models\PracticeLineUp;
use App\Services\CreateServiceData;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class AddPlayerToTraining extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $practice = PracticeLineUp::with('user', 'user.player')->where('practice_id', '=', $request->training)
                ->first();
            if (null === $practice) {
                throw new NotFound();
            }

            $addLineup = (new CreateServiceData(new PracticeLineUp()))->handle([
                'practice_id'=>$request->training,
                'user_id'=>$request->player,
                'sort'=>$request->sort,
                'is_pitching' => $request->pitching,
                'is_batting' => $request->batting,
            ]);

            $response = [
                'code' => '045',
                'message' => 'add player to training '.$request->training,
                'status' => 'success',
                'data' => new LineUpResource($addLineup),
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $message = 'not add player to training';
            $code = HttpCodes::HTTP_INTERNAL_SERVER_ERROR;
            if ($exception instanceof NotFound) {
                $message = 'Not Training Found';
                $code = HttpCodes::HTTP_NOT_FOUND;

            }
            $response = [
                'code' => '045-E',
                'message' => $message,
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, $code);
        }
    }
}
