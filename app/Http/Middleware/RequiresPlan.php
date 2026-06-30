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
        // ── Free Coach ────────────────────────────────────────────────────────
        'free' => [
            'create_session',           // bullpen, cage, batting only
            'record_pitches',
            'view_session_history',
            'roster_view',
            'invite_players',           // max 10 (enforced in AddPlayers controller)
            'add_coaches',              // up to 5 coaches/team (seat cap in AddCoaches controller)
            'notifications',
            'recent_sessions',
        ],

        // ── Coach Basic (legacy — same as free) ───────────────────────────────
        'coach_basic' => [
            'create_session',
            'record_pitches',
            'view_session_history',
            'roster_view',
            'invite_players',
            'add_coaches',
            'notifications',
            'recent_sessions',
        ],

        // ── Coach Pro ─────────────────────────────────────────────────────────
        'coach_pro' => [
            // Base
            'create_session',
            'record_pitches',
            'view_session_history',
            'roster_view',
            'invite_players',
            'notifications',
            'recent_sessions',
            // Advanced session types
            'liveab_sessions',
            'exit_velocity_sessions',
            'long_toss_sessions',
            'weighted_ball_sessions',
            'practice_sessions',
            // Stats & analytics
            'view_team_stats',
            'view_advanced_stats',
            'performance_overview',
            'heat_maps',
            'export_stats',
            'ai_analytics',
            // Session reports (all 7)
            'view_session_report',
            // Arm care
            'arm_care',
            // Live AB premium
            'liveab_analytics',
            'box_score',
            'team_recaps',
            'player_recaps',
            // Team management
            'add_coaches',
            'team_switching',
            'edit_team',
            'edit_player',
            'add_team',
            'manage_multiple_teams',
            // Other
            'sms_results',
            'view_player_cards',
            'unlimited_players',
        ],

        // ── Player Basic ($2.99) ──────────────────────────────────────────────
        'player_basic' => [
            'view_own_profile',
            'view_own_sessions',
            'personal_stats',
            'arm_care',
            'notifications',
            'recent_sessions',
        ],

        // ── Player Pro / Premium ($6.99) ──────────────────────────────────────
        'player_pro' => [
            'view_own_profile',
            'view_own_sessions',
            'personal_stats',
            'arm_care',
            'notifications',
            'recent_sessions',
            // Advanced session types
            'liveab_sessions',
            'exit_velocity_sessions',
            'long_toss_sessions',
            'weighted_ball_sessions',
            // Stats
            'view_own_stats',
            'view_advanced_stats',
            'heat_maps',
            'development_graphs',
            'ai_recommendations',
            // Reports
            'view_session_report',
            'export_stats',
            // Live AB
            'box_score',
            'player_recaps',
            // Profile
            'shareable_profile',
            'recruiting_profile',
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
