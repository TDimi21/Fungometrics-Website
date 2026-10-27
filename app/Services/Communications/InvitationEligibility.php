<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\{PlayerTeam, TeamInvitation, User};
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class InvitationEligibility
{
    public function assertPlayerAge(User $user): void
    {
        $this->assertBirthDate($user->player?->born_date);
    }

    public function assertBirthDate($raw, string $field = 'invitation'): void
    {
        // Same existing 13+ self-service policy for registration and claiming.
        try {
            $born = Carbon::createFromFormat('!Y-m-d', (string) $raw);
            $valid = $born && $born->format('Y-m-d') === $raw && !$born->isFuture();
        } catch (\Throwable) { $valid = false; }
        if (!$valid) throw ValidationException::withMessages([$field => 'A valid birth date is required for player account setup.']);
        if ($born->greaterThan(now()->startOfDay()->subYears(13))) {
            throw ValidationException::withMessages([$field => 'Players under 13 cannot set up their own account until an approved guardian consent process is available. Your coach can continue managing your profile.']);
        }
    }

    public function assertAvailable(TeamInvitation $invite): void
    {
        if ($invite->role !== 'player') {
            $coach = $invite->user_id ? User::withTrashed()->find($invite->user_id) : User::withTrashed()->whereRaw('LOWER(email) = ?', [$invite->email])->first();
            if (($invite->user_id && !$coach) || ($coach && ($coach->trashed() || $coach->type !== 'coach' || strtolower((string) $coach->email) !== $invite->email))) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer available.']);
            }
            return;
        }
        $user = User::find($invite->user_id);
        // Default User scope excludes deleted accounts; legacy status is not player eligibility.
        if (!$user || $user->type !== 'player'
            || (filled($user->email) && strtolower($user->email) !== $invite->email)
            || !PlayerTeam::where('team_id', $invite->team_id)->where('user_id', $user->id)->where('actual', true)->exists()) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is no longer available.']);
        }
        if (filled($user->player?->born_date)) $this->assertPlayerAge($user);
    }
}
