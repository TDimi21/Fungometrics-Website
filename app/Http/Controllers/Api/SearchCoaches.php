<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\NotFound;
use App\Http\Controllers\Controller;
use App\Models\Concerns\UserTypes;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as HttpCodes;

class SearchCoaches extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $data = User::with('profile')
                ->where('type', '=', UserTypes::COACH->value)
                ->where('phone', 'LIKE', "%{$request->search}%")
                ->paginate();
            if (0 === $data->count()) {
                throw new NotFound();
            }
            $response = [
                'code' => '043',
                'message' => 'list of coaches search',
                'status' => 'success',
                'data' =>  $data
            ];
            return response()->json($response, HttpCodes::HTTP_OK);
        } catch (Exception $exception) {
            $response = [
                'code' => '043-E',
                'message' => 'Not  Results Found',
                'status' => 'error',
                'data' => [],
            ];
            Log::error($exception->getMessage());
            return response()->json($response, HttpCodes::HTTP_NOT_FOUND);
        }
    }
}
