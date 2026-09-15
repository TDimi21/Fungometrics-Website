<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Training\Result\BullpenResultRequest;
use App\Models\BullpenPracticeResult;
use App\Models\Practice;
use Illuminate\Database\QueryException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SaveBullpenResultPractice extends Controller
{
    /**
     * @param  BullpenResultRequest  $request
     * @return JsonResponse
     */
    public function __invoke(BullpenResultRequest $request): JsonResponse
    {
        $data = $request->validated();
        $key = $data['client_request_id'] ?? null;
        unset($data['client_request_id']);
        ksort($data);
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $owner = (string) $request->user()->id;
        try {
            // Serialize saves within a session, including ordering and replay.
            // The unique constraint also protects a key reused across sessions.
            $result = DB::transaction(function () use ($data, $key, $hash, $owner) {
                Practice::query()->whereKey($data['practice_id'])->lockForUpdate()->firstOrFail();
                if (null !== $key && ($existing = $this->existing($owner, $key))) {
                    return $existing;
                }
                $next = BullpenPracticeResult::withTrashed()->where('practice_id', $data['practice_id'])->max('sort');
                $result = new BullpenPracticeResult($data + ['sort' => null === $next ? 0 : $next + 1]);
                if (null !== $key) {
                    $result->forceFill(['recorded_by' => $owner, 'client_request_id' => $key, 'request_hash' => $hash]);
                }
                $result->save();
                return $result;
            }, 3);

            if (null !== $key && $result->request_hash !== $hash) {
                return $this->conflict();
            }
            if ($result->trashed()) {
                return response()->json(['message' => 'This pitch was deleted and cannot be replayed.'], 410);
            }

            $teamId = $result->team_id ?? null;
            if ($teamId) {
                Cache::forget("last_sessions_{$teamId}");
                Cache::forget("performance_overview_{$teamId}");
                Cache::forget("dashboard_graphics_{$teamId}");
            }

            $response = [
                'code' => '009',
                'message' => 'save bullpen result',
                'status' => 'success',
                'client_request_id' => $key,
                'data' => $result
            ];
            return response()->json($response, $result->wasRecentlyCreated ? HttpCodes::HTTP_CREATED : HttpCodes::HTTP_OK);
        } catch (QueryException $exception) {
            // A concurrent submission against another session can lose the
            // unique-key race. Never create a second result for that identity.
            if (null !== $key && ($existing = $this->existing($owner, $key))) {
                if ($existing->request_hash !== $hash) {
                    return $this->conflict();
                }
                if ($existing->trashed()) {
                    return response()->json(['message' => 'This pitch was deleted and cannot be replayed.'], 410);
                }
                return response()->json([
                    'code' => '009', 'status' => 'success', 'message' => 'save bullpen result',
                    'client_request_id' => $key, 'data' => $existing,
                ]);
            }
            Log::error($exception->getMessage());
            return response()->json(['message' => 'Unable to save pitch. Please retry.'], 500);
        } catch (Exception $exception) {
            $response = [
                'code' => '009-E',
                'message' => 'error to save bullpen result',
                'status' => 'error',
                'data' => []
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    private function existing(string $owner, string $key): ?BullpenPracticeResult
    {
        return BullpenPracticeResult::withTrashed()->where('recorded_by', $owner)->where('client_request_id', $key)->first();
    }

    private function conflict(): JsonResponse
    {
        return response()->json(['message' => 'This request ID was already used for a different pitch.'], 409);
    }

}
