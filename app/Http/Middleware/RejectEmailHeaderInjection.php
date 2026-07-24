<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RejectEmailHeaderInjection
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->containsUnsafeEmail($request->all())) {
            return response()->json([
                'message' => 'The email field is invalid.',
                'errors' => ['email' => ['The email field is invalid.']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $next($request);
    }

    private function containsUnsafeEmail(array $input): bool
    {
        foreach ($input as $key => $value) {
            if (is_array($value) && $this->containsUnsafeEmail($value)) {
                return true;
            }

            if (is_string($key)
                && strtolower($key) === 'email'
                && is_string($value)
                && preg_match('/[\r\n]/', $value) === 1) {
                return true;
            }
        }

        return false;
    }
}
