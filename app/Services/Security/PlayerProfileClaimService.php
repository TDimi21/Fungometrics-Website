<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Http\Controllers\Api\Coach\CoachUtils;
use App\Models\Concerns\UserTypes;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayerProfileClaimService
{
    public function __construct(private SecurityAuditLogger $audit)
    {
    }

    /** @return array{status: string, token: string} */
    public function claim(string $contact, string $teamCode, Request $request): array
    {
        $contact = trim($contact);
        $email = filter_var($contact, FILTER_VALIDATE_EMAIL) ? strtolower($contact) : null;
        $normalizedPhone = $this->normalizePhone($contact);
        if ($email === null && (!preg_match('/^[+()\d\s.-]+$/', $contact) || strlen($normalizedPhone) < 10 || strlen($normalizedPhone) > 15)) {
            $this->failClaim();
        }
        $normalizedTeamCode = mb_strtoupper(trim($teamCode));

        return DB::transaction(function () use ($email, $normalizedPhone, $normalizedTeamCode, $request): array {
            $team = Team::query()
                ->where('join_code', $normalizedTeamCode)
                ->first();

            if ( ! $team) {
                $this->failClaim();
            }

            // Match contact details only among active players on this roster.
            // Comparing normalized values in PHP also supports older formatted
            // phone values without relying on database-specific SQL functions.
            $matches = User::query()
                ->where('type', UserTypes::PLAYER->value)
                ->where('status', true)
                ->whereHas('team_players', fn ($query) => $query
                    ->where('team_id', $team->id)
                    ->where('actual', true))
                ->get()
                ->filter(fn (User $user): bool => $email !== null
                    ? strtolower((string) $user->email) === $email
                    : $this->normalizePhone((string) $user->phone) === $normalizedPhone);

            if ($matches->count() !== 1) {
                $this->failClaim();
            }

            /** @var User $player */
            $player = User::query()->lockForUpdate()->findOrFail($matches->first()->id);

            if (filled($player->password)) {
                $this->audit->record('player_profile.claim_rejected_already_complete', $player->id, $team->id, $request);
                $this->failClaim();
            }

            // Only the newest short-lived claim session remains valid.
            $player->tokens()->where('name', 'like', 'profile_claim%')->delete();
            $accessToken = $player->createToken(
                'profile_claim:'.$team->id,
                ['profile-claim'],
                now()->addMinutes((int) config('security.profile_claim_ttl_minutes', 10))
            );

            $this->audit->record('player_profile.claim_matched', $player->id, $team->id, $request);

            return [
                'status' => 'success',
                'token' => $accessToken->plainTextToken,
                'next_action' => 'setup',
                'policies' => app(\App\Services\Consent\PlayerConsentService::class)->policies(),
            ];
        });
    }

    public function claimWithCode(string $contact, string $code, Request $request): array
    {
        return DB::transaction(function () use ($contact, $code, $request): array {
            $claims = app(AccountClaimService::class);
            $claim = $claims->resolve(strtoupper(trim($code)), 'player');
            $player = User::query()->lockForUpdate()->findOrFail($claim->user_id);
            $matches = str_contains($contact, '@')
                ? strtolower(trim($contact)) === strtolower((string) $player->email)
                : ($this->normalizePhone($contact) !== '' && $this->normalizePhone($contact) === $this->normalizePhone((string) $player->phone));
            if (!$matches || !$player->status || filled($player->password)) {
                $this->failClaim();
            }
            $claims->consume($claim, $request);
            $token = $player->createToken('profile_claim:individual', ['profile-claim'], now()->addMinutes(10));
            return ['status' => 'success', 'token' => $token->plainTextToken];
        });
    }

    public function joinAuthenticatedPlayer(User $user, string $teamCode, Request $request): Team
    {
        if (UserTypes::PLAYER->value !== (string) $user->type) {
            throw ValidationException::withMessages([
                'account' => 'Only players may use this team join endpoint.',
            ]);
        }

        $team = Team::query()->where('join_code', mb_strtoupper(trim($teamCode)))->first();
        if ( ! $team) {
            throw ValidationException::withMessages(['team_code' => 'The team code is invalid.']);
        }

        CoachUtils::addPlayerToRoaster($user, $team->id);
        $this->audit->record('team_join.authenticated', $user->id, $team->id, $request);

        return $team;
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    private function failClaim(): never
    {
        throw ValidationException::withMessages([
            'claim' => 'No unclaimed player profile matches that email or phone number and team code.',
        ]);
    }
}
