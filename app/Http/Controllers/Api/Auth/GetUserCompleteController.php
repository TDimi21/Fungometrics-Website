<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class GetUserCompleteController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request, $id): JsonResponse
    {
        try {
            preg_match(
                pattern: "/(.{8})(.{4})(.{4})(.{4})(.{12})/",
                subject: $id,
                matches: $matches
            );
            unset($matches[0]);

            $userId = Arr::join($matches, '-');

            $user = User::findOrFail($userId)->load('profile');

            $response = [
                'code' => 'code',
                'message' => '',
                'status' => 'success',
                'data' => [
                    'user'=>$user
                ],
            ];

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
