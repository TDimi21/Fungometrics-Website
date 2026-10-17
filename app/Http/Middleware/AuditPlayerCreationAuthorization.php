<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditPlayerCreationAuthorization
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->isMethod('POST') && $request->is('api/coach/add/players')
            && in_array($response->getStatusCode(), [401, 403], true)) {
            // Deliberate allowlist: never log headers, request body, tokens, or player data.
            $role = $request->user()?->type;
            $body = $response instanceof \Illuminate\Http\JsonResponse ? $response->getData(true) : [];
            $reason = match (true) {
                $response->getStatusCode() === 401 => 'authentication_required',
                ($body['code'] ?? null) === '016-LIMIT' => 'player_limit',
                $role !== 'coach' => 'coach_role_required',
                !$request->user()?->status => 'active_account_required',
                !is_string($request->input('team')) || !\App\Http\Controllers\Api\Coach\CoachUtils::isCoachOnTeam($request->user()->id, $request->input('team')) => 'team_membership_required',
                default => 'token_ability_or_policy_denied',
            };
            Log::notice('player_creation.authorization_denied', [
                'endpoint' => 'coach/add/players',
                'http_status' => $response->getStatusCode(),
                'authenticated' => $request->user() !== null,
                'actor_role' => in_array($role, ['coach', 'player', 'admin'], true) ? $role : 'other',
                'reason' => $reason,
            ]);
        }
        return $response;
    }
}
