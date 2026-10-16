<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Http\Controllers\Api\Coach\CoachUtils;
use App\Jobs\SendTeamInvitation;
use App\Services\Security\SecurityAuditLogger;
use App\Models\{CoachTeam, PlayerTeam, Profile, Team, TeamInvitation, User};
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function enabled(): void
    {
        abort_unless(config('communications.invitations_enabled'), 503, 'Team invitations are awaiting activation.');
    }

    public function authorize(User $actor, string $teamId, bool $coachInvite = false): void
    {
        abort_unless($actor->status && 'coach' === $actor->type && CoachUtils::isCoachOnTeam($actor->id, $teamId), 403);
        if ($coachInvite) {
            abort_unless(CoachUtils::isHeadCoach($actor->id, $teamId), 403, 'Only the head coach can invite coaches.');
        }
    }

    public function frontendUrl(): string
    {
        $url = rtrim((string) config('communications.frontend_url'), '/');
        // Invitations must never deliver credentials to an arbitrary host or API path.
        abort_unless('https://app.fmtrx.com' === $url, 503, 'Invitation frontend URL must be https://app.fmtrx.com.');
        return $url;
    }

    public function send(User $actor, string $teamId, array $data): TeamInvitation
    {
        $this->enabled();
        $this->frontendUrl();
        $driver = config('queue.connections.'.config('queue.default').'.driver');
        abort_unless(in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true), 503, 'Enable a supervised asynchronous queue before sending invitations.');
        return DB::transaction(function () use ($actor, $teamId, $data) {
            Team::query()->lockForUpdate()->findOrFail($teamId);
            $this->authorize($actor, $teamId, 'coach' === $data['role']);
            $email = mb_strtolower(trim($data['email']));
            if (strlen($email) > 191) $this->invalid('Email must not exceed 191 characters.');
            $user = null;
            if ('player' === $data['role']) {
                $user = User::query()->lockForUpdate()->findOrFail($data['user_id'] ?? '');
                abort_unless('player' === $user->type && $user->status && PlayerTeam::where('team_id', $teamId)->where('user_id', $user->id)->where('actual', true)->exists(), 404);
                app(InvitationEligibility::class)->assertPlayerAge($user);
                // A coach cannot redirect an existing account's invitation to another mailbox.
                if (filled($user->email) && mb_strtolower($user->email) !== $email) {
                    $this->invalid('Use the email already recorded on this player account.');
                }
                if (User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->where('id', '!=', $user->id)->exists()) {
                    $this->invalid('This email belongs to another account. Contact support to review the profiles; they will not be merged automatically.');
                }
            } else {
                $matches = User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->get();
                if ($matches->count() > 1) {
                    $this->invalid('This contact needs account review.');
                }
                $user = $matches->first();
                if ($user && ($user->trashed() || ! $user->status || 'coach' !== $user->type)) {
                    $this->invalid('This email cannot be invited as a coach.');
                }
            }
            // Team row mutex serializes initial sends and explicit resends.
            $previous = TeamInvitation::where('team_id', $teamId)->where('role', $data['role'])
                ->where(function ($q) use ($email, $user): void {
                    $q->where('email', $email);
                    if ($user) {
                        $q->orWhere('user_id', $user->id);
                    }
                })
                ->orderByDesc('created_at')->get();
            foreach ($previous as $invite) {
                if ('accepted' === $invite->state()) {
                    $this->invalid('This invitation has already been accepted.');
                }
                if ('pending' === $invite->state()) {
                    return $invite;
                }
            }
            $invite = TeamInvitation::create(['team_id' => $teamId,'invited_by' => $actor->id,'user_id' => $user?->id,'email' => $email,'role' => $data['role'],'expires_at' => now()->addDays(config('communications.invitation_days'))]);
            app(SecurityAuditLogger::class)->record('team_invitation.issued', $actor->id, $teamId, metadata: ['invitation_id' => $invite->id,'role' => $invite->role]);
            SendTeamInvitation::dispatch($invite->id, 1)->afterCommit();
            return $invite;
        });
    }

    public function manage(User $actor, string $id, bool $resend): TeamInvitation
    {
        $this->enabled();
        return DB::transaction(function () use ($actor, $id, $resend) {
            $snapshot = TeamInvitation::findOrFail($id);
            Team::query()->lockForUpdate()->findOrFail($snapshot->team_id);
            $this->authorize($actor, $snapshot->team_id, 'coach' === $snapshot->role);
            $invite = TeamInvitation::lockForUpdate()->findOrFail($id);
            if ($invite->accepted_at) {
                $this->invalid('Accepted invitations cannot be changed.');
            }
            if ($resend) {
                app(InvitationEligibility::class)->assertAvailable($invite);
                $this->frontendUrl();
                abort_unless(in_array(config('queue.connections.'.config('queue.default').'.driver'), ['database','redis','sqs','beanstalkd'], true), 503, 'An asynchronous queue is required.');
                if ('revoked' === $invite->state()) {
                    $this->invalid('Create a new invitation after revoking.');
                }
                if ($invite->updated_at->gt(now()->subMinute())) {
                    $this->invalid('Wait one minute before resending.');
                }
                $invite->fill(['generation' => $invite->generation + 1,'token_hash' => null,'sent_at' => null,'delivery_status' => 'queued','failure_code' => null,'expires_at' => now()->addDays(config('communications.invitation_days'))])->save();
                SendTeamInvitation::dispatch($invite->id, $invite->generation)->afterCommit();
            } else {
                $invite->update(['revoked_at' => now(),'token_hash' => null]);
            }
            app(SecurityAuditLogger::class)->record($resend ? 'team_invitation.resent' : 'team_invitation.revoked', $actor->id, $invite->team_id, metadata: ['invitation_id' => $invite->id]);
            return $invite;
        });
    }

    public function resolve(string $token): TeamInvitation
    {
        $this->enabled();
        $invite = TeamInvitation::where('token_hash', hash('sha256', $token))->first();
        if ( ! $invite || 'pending' !== $invite->state()) {
            $this->invalid('This invitation is invalid or no longer available.');
        }
        return $invite;
    }

    public function accept(array $data, ?User $actor): string
    {
        $snapshot = $this->resolve($data['token']);
        return DB::transaction(function () use ($snapshot, $data, $actor) {
            Team::query()->lockForUpdate()->findOrFail($snapshot->team_id);
            $invite = TeamInvitation::lockForUpdate()->findOrFail($snapshot->id);
            if ('pending' !== $invite->state() || ! hash_equals((string)$invite->token_hash, hash('sha256', $data['token']))) {
                $this->invalid('This invitation is invalid or no longer available.');
            }
            $inviter = User::find($invite->invited_by);
            if ( ! $inviter) {
                $this->invalid('This invitation is no longer available.');
            }
            $this->authorize($inviter, $invite->team_id, 'coach' === $invite->role);
            if (mb_strtolower(trim($data['email'])) !== $invite->email) {
                $this->invalid('The invitation and account credentials do not match.');
            }
            $user = $invite->user_id ? User::query()->lockForUpdate()->find($invite->user_id) : User::query()->whereRaw('LOWER(email) = ?', [$invite->email])->lockForUpdate()->first();
            if ($invite->user_id && ! $user) {
                $this->invalid('This account is no longer available.');
            }
            if ($user) {
                if ($invite->role === 'player') app(InvitationEligibility::class)->assertPlayerAge($user);
                if ( ! $user->status || $user->type !== $invite->role || (filled($user->email) && mb_strtolower($user->email) !== $invite->email)) {
                    $this->invalid('This account needs review.');
                }
                if ($actor && $actor->id !== $user->id) {
                    $this->invalid('Sign out of the other account before accepting.');
                }
                if (filled($user->password)) {
                    if (( ! $actor || ! $actor->tokenCan($invite->role)) && ! Hash::check($data['password'] ?? '', $user->password)) {
                        $this->invalid('The invitation and account credentials do not match.');
                    }
                } else {
                    if (empty($data['password'])) {
                        $this->invalid('Choose a password to complete your account.');
                    }
                    $user->update(['password' => Hash::make($data['password']), 'email' => $invite->email]);
                    $user->tokens()->where('name', 'like', 'profile_claim%')->delete();
                    DB::table('account_claims')->where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);
                }
            } else {
                if ('coach' !== $invite->role || $actor) {
                    $this->invalid('This account needs review.');
                }
                if (empty($data['password']) || empty($data['first_name']) || empty($data['last_name'])) {
                    $this->invalid('Enter your name and choose a password.');
                }
                if (User::withTrashed()->whereRaw('LOWER(email) = ?', [$invite->email])->exists()) {
                    $this->invalid('This account needs review.');
                }
                $user = User::create(['email' => $invite->email,'password' => Hash::make($data['password']),'type' => 'coach','status' => true]);
                Profile::create(['user_id' => $user->id,'first_name' => $data['first_name'],'last_name' => $data['last_name']]);
            }
            if (blank($user->email)) {
                $user->update(['email' => $invite->email]);
            }
            if ('player' === $invite->role) {
                // Never resurrect a membership removed after invitation issuance.
                abort_unless(PlayerTeam::where('team_id', $invite->team_id)->where('user_id', $user->id)->where('actual', true)->exists(), 409, 'The player is no longer on this team.');
            } else {
                $membership = CoachTeam::withTrashed()->where('team_id', $invite->team_id)->where('coach_id', $user->id)->first();
                if ( ! $membership || $membership->trashed()) {
                    if (CoachUtils::coachSeatLimitReached($invite->team_id)) {
                        $this->invalid('The team has no available coach seats.');
                    }
                    if ($membership) {
                        $membership->is_main = false;
                        $membership->restore();
                    } else {
                        CoachTeam::create(['team_id' => $invite->team_id,'coach_id' => $user->id,'is_main' => false]);
                    }
                }
            }
            // Possession of the single-use token delivered to this email verifies it for this acceptance.
            $invite->update(['user_id' => $user->id,'accepted_at' => now(),'email_verified_at' => now(),'token_hash' => null]);
            app(SecurityAuditLogger::class)->record('team_invitation.accepted', $user->id, $invite->team_id, metadata: ['invitation_id' => $invite->id]);
            return $user->type;
        });
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['invitation' => $message]);
    }
}
