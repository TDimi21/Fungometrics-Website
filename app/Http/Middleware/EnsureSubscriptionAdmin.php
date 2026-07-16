<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $type = (string) ($user?->type ?? '');
        $email = mb_strtolower(trim((string) ($user?->email ?? '')));
        $adminEmails = array_map(fn (string $value): string => mb_strtolower($value), config('access.admin_emails', []));

        if ( ! $user || ( ! in_array($type, ['admin', 'super_admin'], true) && ! in_array($email, $adminEmails, true))) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription administration requires administrator access.',
            ], 403);
        }

        return $next($request);
    }
}
