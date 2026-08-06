<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Models\CagePracticeResult;
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

class GetCagePractices extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $practicesId = CagePracticeResult::where('user_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();
            $lineupPracticeIds = PracticeLineUp::where('user_id', '=', auth()->id())
                ->pluck('practice_id')
                ->unique()
                ->all();
            $data = Practice::with('cage', 'cageMeta', 'team')
                ->where('type', '=', PracticeTypes::CAGE->value)
                ->where(function ($query) use ($practicesId, $lineupPracticeIds): void {
                    $query->where('user_id', '=', auth()->id())
                        ->orWhereIn('id', $practicesId)
                        ->orWhereIn('id', $lineupPracticeIds);
                })
                ->paginate();
            $response = [
                'code' => '051',
                'message' => '',
                'status' => 'success',
                'data' => $data,
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (AuthenticationException|AuthorizationException|ValidationException|HttpException $exception) {
            // Auth/validation failures belong to the framework's exception handler.
            throw $exception;
        } catch (Exception $exception) {
            Log::error('Failed to load cage practice sessions', ['exception' => $exception]);
            return response()->json([
                'code' => '051-E',
                'message' => 'Unable to load sessions',
                'status' => 'error',
                'data' => [],
            ], HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
