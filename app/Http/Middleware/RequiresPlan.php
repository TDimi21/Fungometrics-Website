<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Access\EntitlementResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class RequiresPlan
{
    public function __construct(private EntitlementResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();
        if ( ! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $teamId = $this->teamContext($request);
        try {
            $summary = $this->resolver->getAccessSummary($user, $teamId);
        } catch (UnauthorizedException $exception) {
            return $this->denied($feature, $user->subscription_plan ?: 'free', $teamId, $exception->getMessage());
        }

        if ( ! in_array($feature, $summary['entitlements'], true)) {
            return $this->denied($feature, $summary['plan'], $teamId);
        }

        return $next($request);
    }

    private function teamContext(Request $request): ?string
    {
        foreach (['team', 'teamId'] as $parameter) {
            $value = $request->route($parameter);
            if (is_object($value) && isset($value->id)) {
                return (string) $value->id;
            }
            if (is_string($value)) {
                return $value;
            }
        }
        if (str_contains((string) $request->route()?->uri(), 'teams/{id}')) {
            return $request->route('id');
        }

        foreach (['team_id', 'team'] as $parameter) {
            $value = $request->input($parameter);
            if (is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return null;
    }

    private function denied(string $feature, string $plan, ?string $teamId, string $message = 'Your current plan does not include access to this feature. Please upgrade.'): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'required_entitlement' => $feature,
            'effective_plan' => $plan,
            'team_id' => $teamId,
        ], 403);
    }
}
