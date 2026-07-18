<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\BullpenPracticeResult;
use App\Models\CoachTeam;
use App\Models\Practice;
use App\Services\Access\EntitlementResolver;
use BackedEnum;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresScriptedPracticeEntitlement
{
    public function __construct(private readonly EntitlementResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next, string $entitlement = 'scripted_bullpen'): Response
    {
        $practice = $this->practice($request);
        $creatingScripted = $request->isMethod('post')
            && 'api/training' === trim($request->path(), '/')
            && $request->boolean('scripted');

        if ( ! $creatingScripted && ! $practice?->is_scripted) {
            return $next($request);
        }

        $user = $request->user();
        $teamId = (string) ($practice?->team_id ?? $request->input('team') ?? '');
        $audience = $user?->type instanceof BackedEnum ? $user->type->value : $user?->type;

        if ( ! $user || 'coach' !== $audience || '' === $teamId) {
            return $this->denied($entitlement, $teamId);
        }

        $ownsPersonalPractice = $practice && null === $practice->team_id && $practice->user_id === $user->id;
        $isTeamCoach = CoachTeam::query()
            ->where('coach_id', $user->id)
            ->where('team_id', $teamId)
            ->exists();

        if ( ! $ownsPersonalPractice && ! $isTeamCoach) {
            return $this->denied($entitlement, $teamId);
        }

        if ( ! $this->resolver->hasEntitlement($user, $entitlement, $teamId)) {
            return $this->denied($entitlement, $teamId);
        }

        return $next($request);
    }

    private function practice(Request $request): ?Practice
    {
        $practiceId = $request->input('practice_id')
            ?? $request->route('practice')
            ?? ($request->is('api/training/*') ? $request->route('uuid') : null);

        if ($practiceId) {
            return Practice::query()->find($practiceId);
        }

        $resultId = $request->route('uuid');
        if ( ! $resultId) {
            return null;
        }

        return BullpenPracticeResult::query()->with('practice')->find($resultId)?->practice;
    }

    private function denied(string $entitlement, string $teamId): JsonResponse
    {
        return response()->json([
            'message' => 'This feature is not available on the current plan.',
            'required_entitlement' => $entitlement,
            'team_id' => '' === $teamId ? null : $teamId,
        ], Response::HTTP_FORBIDDEN);
    }
}
