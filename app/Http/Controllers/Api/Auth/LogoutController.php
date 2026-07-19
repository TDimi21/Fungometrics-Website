<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\ApiTokenCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(Request $request, ApiTokenCookie $cookies): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $cookies->forget(response()->json(['status' => 'success']));
    }
}
