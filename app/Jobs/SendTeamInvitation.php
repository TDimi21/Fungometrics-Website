<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\{Team, TeamInvitation, User};
use App\Notifications\TeamInvitationNotification;
use App\Services\Communications\InvitationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\{DB, Notification};

class SendTeamInvitation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    public $tries = 1;
    public $timeout = 60;

    public function __construct(public string $invitationId, public int $generation)
    {
        $this->onQueue('communications');
    }

    public function handle(InvitationService $service): void
    {
        // Store only IDs in the queue. The raw token exists only while composing/delivering email.
        if ( ! config('communications.invitations_enabled')) {
            return;
        }
        $raw = bin2hex(random_bytes(32));
        $invite = DB::transaction(function () use ($raw, $service) {
            $invite = TeamInvitation::lockForUpdate()->find($this->invitationId);
            if ( ! $invite || $invite->generation !== $this->generation || 'pending' !== $invite->state() || 'queued' !== $invite->delivery_status) {
                return null;
            }
            $actor = User::find($invite->invited_by);
            $team = Team::find($invite->team_id);
            try {
                if ( ! $actor || ! $team) {
                    throw new RuntimeException('unavailable');
                }
                $service->authorize($actor, $team->id, 'coach' === $invite->role);
                $service->frontendUrl();
            } catch (Throwable) {
                $invite->update(['delivery_status' => 'failed','failure_code' => 'invitation_unavailable']);
                return null;
            }
            $invite->update(['token_hash' => hash('sha256', $raw),'delivery_status' => 'sending']);
            return $invite;
        });
        if ( ! $invite) {
            return;
        }
        try {
            $coach = User::with('profile')->find($invite->invited_by);
            $team = Team::find($invite->team_id);
            Notification::route('mail', $invite->email)->notify(new TeamInvitationNotification(
                $service->frontendUrl().'/invitations/accept#token='.$raw,
                trim(($coach?->profile?->first_name ?? '').' '.($coach?->profile?->last_name ?? '')) ?: 'Your coach',
                $team?->name ?? 'your team',
                $invite->expires_at->toFormattedDateString(),
                $invite->role
            ));
            TeamInvitation::whereKey($invite->id)->where('generation', $this->generation)->update(['delivery_status' => 'sent','sent_at' => now()]);
        } catch (Throwable) {
            // A provider may have accepted a message before a timeout. Never retry automatically.
            // An explicit resend rotates the token; no raw exception or credential enters failed_jobs.
            TeamInvitation::whereKey($invite->id)->where('generation', $this->generation)->update(['delivery_status' => 'failed','failure_code' => 'delivery_unconfirmed']);
        }
    }
}
