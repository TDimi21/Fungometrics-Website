<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\Concerns\PracticeTypes;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpCodes;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GetLiveABPractices extends Controller
{
    /**
     * List the authenticated player's Live AB sessions across ALL teams.
     *
     * A player appears in a Live AB as a batter or pitcher (stored as in-match
     * batting / bullpen results), via the practice lineup, or as the creator.
     * Team-agnostic by design — the player sees every Live AB they took part in.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $battingPracticeIds = BattingPracticeResult::where('is_in_match', '=', true)
                ->where('batter_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();

            $bullpenPracticeIds = BullpenPracticeResult::where('is_in_match', '=', true)
                ->where('pitcher_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();

            $lineupPracticeIds = PracticeLineUp::where('user_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();

            $data = Practice::with('liveABTeams', 'team', 'batting', 'bullpen')
                ->where('type', '=', PracticeTypes::LIVE_AB->value)
                ->where(function ($query) use ($battingPracticeIds, $bullpenPracticeIds, $lineupPracticeIds): void {
                    $query->where('user_id', '=', auth()->id())
                        ->orWhereIn('id', $battingPracticeIds)
                        ->orWhereIn('id', $bullpenPracticeIds)
                        ->orWhereIn('id', $lineupPracticeIds);
                })
                ->paginate();

            $response = [
                'code' => '052',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (AuthenticationException|AuthorizationException|ValidationException|HttpException $exception) {
            // Auth/validation failures belong to the framework's exception handler.
            throw $exception;
        } catch (Exception $exception) {
            Log::error('Failed to load Live AB practice sessions', ['exception' => $exception]);
            return response()->json([
                'code' => '052-E',
                'message' => 'Unable to load sessions',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
