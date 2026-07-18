<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sessions\Results;

use App\Events\SentResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\SendSMSRequest;
use App\Models\Practice;
use App\Models\SmsLog;
use App\Models\CoachTeam;
use App\Models\User;
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
        $session = Practice::with('lineup')->findOrFail($request->practice);
        $ownsSession = $session->user_id === $request->user()->id
            || ($session->team_id && CoachTeam::query()
                ->where('coach_id', $request->user()->id)
                ->where('team_id', $session->team_id)
                ->exists());
        if ( ! $ownsSession) {
            return response()->json(['message' => 'This session is not available to this coach.'], HttpCodes::HTTP_FORBIDDEN);
        }

        try {
            $practice = SmsLog::where('practice_id', $request->practice)
                ->first();
            if(null === $practice) {
                $players = User::with('profile')->whereIn('id', $session->lineup->pluck('user_id'))
                    ->whereNotNull('phone')->get()->map(fn (User $user): array => [
                        'id' => $user->id,
                        'name' => trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: 'Player',
                        'phone' => $user->phone,
                    ]);
                foreach ($players as $item) {
                    event(new SentResults(['data' => $item,'practice' => $request->practice]));
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
