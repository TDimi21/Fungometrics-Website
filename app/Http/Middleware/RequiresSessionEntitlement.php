<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\Concerns\PracticeModes;
use App\Models\Concerns\PracticeTypes;
use App\Models\ExitVelocityPractice;
use App\Models\LiveABPracticeResult;
use App\Models\LongTossPractice;
use App\Models\Practice;
use App\Models\WeightBallPractice;
use App\Services\Access\EntitlementResolver;
use BackedEnum;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use Symfony\Component\HttpFoundation\Response;

class RequiresSessionEntitlement
{
    public function __construct(private readonly EntitlementResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $isTrainingCreate = $request->isMethod('POST') && $request->is('api/training');

        // Let the request validator own required-field responses. The middleware
        // must not turn a missing session type into a plan decision.
        if ($isTrainingCreate && ! $request->has('type')) {
            return $next($request);
        }

        $practice = $this->practice($request);

        // Result endpoints preserve their existing not-found/validation contract.
        // A plan decision is possible only when there is a real session to classify.
        if ( ! $isTrainingCreate && ! $practice) {
            return $next($request);
        }

        $type = (string) ($practice?->type ?? $request->input('type', PracticeTypes::TRAINING->value));
        $mode = (string) ($practice?->modes ?? $request->input('modes', PracticeModes::HIT_OR_PITCH->value));
        $scripted = (bool) ($practice?->is_scripted ?? $request->boolean('scripted'));

        if ($isTrainingCreate && ( ! in_array($type, array_column(PracticeTypes::cases(), 'value'), true)
            || ! in_array($mode, array_column(PracticeModes::cases(), 'value'), true))) {
            return $this->invalid('The session type or mode is invalid.');
        }

        $entitlement = $this->entitlement($type, $mode, $scripted);
        if (false === $entitlement) {
            return $this->invalid('Scripted mode is supported only for batting practice and bullpen sessions.');
        }

        $user = $request->user();
        if ( ! $user) {
            return $this->denied($entitlement ?: 'create_session');
        }

        if (null === $entitlement) {
            $audience = $user->type instanceof BackedEnum ? (string) $user->type->value : (string) $user->type;
            $isRead = $request->isMethod('GET');
            $entitlement = $isTrainingCreate
                ? ('player' === $audience ? 'personal_stats' : 'create_session')
                : ($isRead
                    ? ('player' === $audience ? 'view_own_sessions' : 'view_session_history')
                    : ('player' === $audience ? 'personal_stats' : 'record_pitches'));
        }

        // Team context is authoritative at creation time. Existing endpoints
        // retain their controller/policy ownership behavior and use this
        // middleware only for session-type entitlement classification.
        $teamId = $isTrainingCreate
            ? (string) ($request->input('team') ?? $request->input('team_id') ?? '')
            : '';

        try {
            if ('' !== $teamId) {
                // Resolving with a team ID is also the authoritative membership check.
                $this->resolver->getAccessSummary($user, $teamId);
            }
            if ($entitlement && ! $this->resolver->hasEntitlement($user, $entitlement, '' === $teamId ? null : $teamId)) {
                return $this->denied($entitlement);
            }
        } catch (UnauthorizedException) {
            return $this->denied($entitlement);
        }

        return $next($request);
    }

    /** @return string|false|null */
    private function entitlement(string $type, string $mode, bool $scripted): string|false|null
    {
        if ($scripted) {
            return match ($type) {
                PracticeTypes::BATTING->value => 'scripted_bp',
                PracticeTypes::BULLPEN->value => 'scripted_bullpen',
                default => false,
            };
        }

        return match (true) {
            PracticeTypes::LIVE_AB->value === $type => 'liveab_sessions',
            PracticeTypes::TRAINING->value === $type && PracticeModes::EXIT_VELOCITY->value === $mode => 'exit_velocity_sessions',
            PracticeTypes::TRAINING->value === $type && PracticeModes::LONG_TOSS->value === $mode => 'long_toss_sessions',
            PracticeTypes::TRAINING->value === $type && PracticeModes::WEIGHT_BALL->value === $mode => 'weighted_ball_sessions',
            default => null,
        };
    }

    private function practice(Request $request): ?Practice
    {
        $practiceId = $request->input('practice_id') ?? $request->route('practice');
        if ($practiceId) {
            return Practice::query()->find($practiceId);
        }

        if ($request->is('api/training/*')) {
            return Practice::query()->find($request->route('uuid'));
        }

        $resultId = $request->route('uuid');
        if ( ! $resultId) {
            return null;
        }

        $result = match (true) {
            $request->is('api/result/batting/*') => BattingPracticeResult::query()->with('practice')->find($resultId),
            $request->is('api/result/bullpen/*') => BullpenPracticeResult::query()->with('practice')->find($resultId),
            $request->is('api/result/cage/*') => CagePracticeResult::query()->with('practice')->find($resultId),
            $request->is('api/result/longtoss/*') => LongTossPractice::query()->with('practice')->find($resultId),
            $request->is('api/result/exitvelocity/*') => ExitVelocityPractice::query()->with('practice')->find($resultId),
            $request->is('api/result/weightball/*') => WeightBallPractice::query()->with('practice')->find($resultId),
            $request->is('api/result/liveab/*') => LiveABPracticeResult::query()->with('practice')->find($resultId),
            default => null,
        };

        return $result?->practice;
    }

    private function denied(string $entitlement): JsonResponse
    {
        return response()->json([
            'message' => 'This feature is not available on the current plan.',
            'required_entitlement' => $entitlement,
        ], Response::HTTP_FORBIDDEN);
    }

    private function invalid(string $message): JsonResponse
    {
        return response()->json(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
