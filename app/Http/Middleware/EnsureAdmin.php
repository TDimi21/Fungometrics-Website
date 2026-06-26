<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Restrict a route to admins.
     *
     * There is no admin role in the schema, so admin access is granted via an
     * explicit email allowlist (config/admin.php, fed by ADMIN_EMAILS). This
     * replaces gating privileged routes with the generic `coach` ability, which
     * let any coach perform admin actions such as changing billing plans.
     *
     * Usage in routes: ->middleware('admin')
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $allowed = config('admin.emails', []);
        $email = strtolower((string) $user->email);

        if ($email === '' || ! in_array($email, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'This action requires administrator access.',
            ], 403);
        }

        return $next($request);
    }
}
