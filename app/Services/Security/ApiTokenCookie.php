<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

class ApiTokenCookie
{
    public const NAME = 'fmtrx_api_token';

    public function attach(JsonResponse $response, string $token): JsonResponse
    {
        $response->headers->setCookie(cookie(
            self::NAME,
            $token,
            (int) config('security.web_token_ttl_minutes', 480),
            '/',
            null,
            true,
            true,
            false,
            Cookie::SAMESITE_STRICT
        ));

        return $response;
    }

    public function forget(JsonResponse $response): JsonResponse
    {
        $response->headers->setCookie(cookie()->forget(self::NAME));

        return $response;
    }
}
