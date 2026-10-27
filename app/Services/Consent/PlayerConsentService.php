<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Models\{Player, PlayerClaimSetup, PlayerPolicyAcceptance, User};
use App\Services\Security\SecurityAuditLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayerConsentService
{
    public function policies(): array
    {
        return [
            'terms_url' => config('legal.terms_url'), 'privacy_url' => config('legal.privacy_url'),
            'terms_version' => config('player_consent.terms_version'), 'privacy_version' => config('player_consent.privacy_version'),
            'acknowledgment_version' => config('player_consent.acknowledgment_version'),
            'coach_acknowledgment' => config('player_consent.coach_acknowledgment'),
            'player_acknowledgment' => config('player_consent.player_acknowledgment'),
        ];
    }

    public function acceptanceRules(): array
    {
        return [
            'policy_accepted' => 'required|accepted',
            'terms_version' => 'required|in:'.config('player_consent.terms_version'),
            'privacy_version' => 'required|in:'.config('player_consent.privacy_version'),
            'acknowledgment_version' => 'required|in:'.config('player_consent.acknowledgment_version'),
        ];
    }

    public function record(User $player, User $actor, ?string $teamId, string $context, Request $request): void
    {
        PlayerPolicyAcceptance::create([
            'player_id' => $player->id, 'actor_id' => $actor->id, 'team_id' => $teamId, 'context' => $context,
            'terms_version' => config('player_consent.terms_version'), 'privacy_version' => config('player_consent.privacy_version'),
            'acknowledgment_version' => config('player_consent.acknowledgment_version'),
            'acknowledgment_text' => config('player_consent.'.($context === 'player_claim' ? 'player' : 'coach').'_acknowledgment'),
            'accepted_at' => now(),
        ]);
        app(SecurityAuditLogger::class)->record('player_policy.accepted', $actor->id, $teamId, $request, ['player_id' => $player->id, 'context' => $context]);
    }

    public function setup(User $actor, array $data, Request $request): array
    {
        // Return an age-blocked state rather than throwing inside the transaction:
        // the age declaration must survive retries that try a different birthday.
        return DB::transaction(function () use ($actor, $data, $request) {
            $user = User::lockForUpdate()->findOrFail($actor->id);
            abort_if($user->type !== 'player' || filled($user->password), 409, 'This profile cannot be set up again.');
            $existing = PlayerClaimSetup::find($user->id);
            $born = $data['born_date'];
            if ($existing && $existing->born_date !== $born) {
                throw ValidationException::withMessages(['born_date' => 'Contact support to correct the birth date already supplied during setup.']);
            }
            $knownBorn = $user->player?->born_date;
            if ($knownBorn && Carbon::parse($knownBorn)->greaterThan(now()->startOfDay()->subYears(13)) && $knownBorn !== $born) {
                throw ValidationException::withMessages(['born_date' => 'This birth date differs from the youth profile. Contact support to correct it.']);
            }
            $under13 = Carbon::parse($born)->greaterThan(now()->startOfDay()->subYears(13));
            $setup = PlayerClaimSetup::updateOrCreate(['user_id' => $user->id], ['born_date' => $born, 'guardian_required' => $under13]);
            if ($under13) {
                return ['next_action' => 'coach_managed', 'message' => 'Your profile is saved with your team. Players under 13 cannot create their own login yet. Your coach can continue managing your profile.'];
            }
            validator($data, $this->acceptanceRules())->validate();
            Player::updateOrCreate(['user_id' => $user->id], ['born_date' => $born]);
            if (!$setup->accepted_at || $setup->terms_version !== config('player_consent.terms_version') || $setup->privacy_version !== config('player_consent.privacy_version') || $setup->acknowledgment_version !== config('player_consent.acknowledgment_version')) {
                $this->record($user, $user, null, 'player_claim', $request);
            }
            $setup->update(['accepted_at' => now(), 'terms_version' => $data['terms_version'], 'privacy_version' => $data['privacy_version'], 'acknowledgment_version' => $data['acknowledgment_version']]);
            return ['next_action' => 'credentials'];
        });
    }

    public function assertReady(User $user): void
    {
        $setup = PlayerClaimSetup::find($user->id);
        if (!$setup || !$setup->accepted_at) throw ValidationException::withMessages(['setup' => 'Complete your birth date and policy acceptance before creating a login.']);
        // Check the declaration and current roster DOB: later edits cannot bypass the age gate.
        app(\App\Services\Communications\InvitationEligibility::class)->assertBirthDate($setup->born_date, 'born_date');
        app(\App\Services\Communications\InvitationEligibility::class)->assertPlayerAge($user);
        if ($setup->terms_version !== config('player_consent.terms_version') || $setup->privacy_version !== config('player_consent.privacy_version') || $setup->acknowledgment_version !== config('player_consent.acknowledgment_version')) {
            throw ValidationException::withMessages(['setup' => 'Please accept the current Terms and acknowledge the Privacy Policy.']);
        }
    }
}
