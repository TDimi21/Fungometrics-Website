<?php

declare(strict_types=1);

namespace App\Services\FreeAssessment;

use App\Models\{CoachTeam, FreeAssessment, User};
use App\Services\Access\AdministrativeAccess;

class AssessmentAccess
{
    public function teamIds(User $user): array
    {
        abort_unless('coach' === $user->type || $this->admin($user), 403);
        return CoachTeam::where('coach_id', $user->id)->pluck('team_id')->all();
    }
    public function admin(User $user): bool
    {
        return app(AdministrativeAccess::class)->canManageSubscriptions($user);
    }
    public function team(User $user, string $teamId): void
    {
        abort_unless($this->admin($user) || in_array($teamId, $this->teamIds($user), true), 404);
    }
    public function event(User $user, FreeAssessment $assessment): void
    {
        $this->team($user, $assessment->team_id);
    }
}
