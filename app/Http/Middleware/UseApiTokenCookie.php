<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Security\ApiTokenCookie;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseApiTokenCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if ( ! $request->bearerToken()) {
            $token = $request->cookie(ApiTokenCookie::NAME);
            if (is_string($token) && '' !== $token) {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
