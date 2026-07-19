<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Access\AdministrativeAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionAdmin
{
    public function __construct(private AdministrativeAccess $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ( ! $this->access->canManageSubscriptions($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription administration requires administrator access.',
            ], 403);
        }

        return $next($request);
    }
}
