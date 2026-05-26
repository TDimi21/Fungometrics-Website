<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresPlan
{
    /**
     * Features available per subscription plan.
     * Any feature not listed for a plan is blocked.
     */
    private const PLAN_FEATURES = [
        'free' => [
            'create_session',
            'record_pitches',
        ],
        'coach_basic' => [
            'create_session',
            'record_pitches',
            'view_session_report',
            'view_team_stats',
            'manage_team',
        ],
        'coach_pro' => [
            'create_session',
            'record_pitches',
            'view_session_report',
            'view_team_stats',
            'manage_team',
            'view_player_cards',
            'view_advanced_stats',
            'export_stats',
            'performance_overview',
            'sms_results',
        ],
        'player_basic' => [
            'view_session_report',
            'view_own_stats',
        ],
        'player_pro' => [
            'view_session_report',
            'view_own_stats',
            'view_advanced_stats',
            'export_stats',
        ],
    ];

    /**
     * Handle an incoming request.
     *
     * Usage in routes:
     *   ->middleware('plan:view_session_report')
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $plan = $user->subscription_plan ?? 'free';
        $allowed = self::PLAN_FEATURES[$plan] ?? self::PLAN_FEATURES['free'];

        if (! in_array($feature, $allowed, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Your current plan does not include access to this feature. Please upgrade.',
                'required_feature' => $feature,
                'current_plan' => $plan,
            ], 403);
        }

        return $next($request);
    }
}
