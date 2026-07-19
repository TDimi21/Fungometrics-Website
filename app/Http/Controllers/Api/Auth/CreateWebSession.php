<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\ApiTokenCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreateWebSession extends Controller
{
    public function __invoke(Request $request, ApiTokenCookie $cookies): JsonResponse
    {
        $token = $request->bearerToken();
        if ( ! is_string($token) || '' === $token) {
            return response()->json(['message' => 'A bearer token is required for session exchange.'], 422);
        }

        return $cookies->attach(response()->json(['status' => 'success']), $token);
    }
}
