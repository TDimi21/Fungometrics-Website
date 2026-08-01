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
    public function claim(string $phone, string $teamCode, Request $request): array
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';
        $normalizedTeamCode = mb_strtoupper(trim($teamCode));

        return DB::transaction(function () use ($normalizedPhone, $normalizedTeamCode, $request): array {
            $team = Team::query()
                ->where('join_code', $normalizedTeamCode)
                ->first();

            if ( ! $team) {
                $this->failClaim();
            }

            // Restrict phone matching to players already added to this roster.
            // Comparing normalized values in PHP also supports older formatted
            // phone values without relying on database-specific SQL functions.
            $matchedPlayer = User::query()
                ->where('type', UserTypes::PLAYER->value)
                ->where('status', true)
                ->whereHas('team_players', fn ($query) => $query
                    ->where('team_id', $team->id)
                    ->where('actual', true))
                ->get()
                ->first(fn (User $user): bool => $this->normalizePhone((string) $user->phone) === $normalizedPhone);

            if ( ! $matchedPlayer) {
                $this->failClaim();
            }

            /** @var User $player */
            $player = User::query()->lockForUpdate()->findOrFail($matchedPlayer->id);

            if (filled($player->email) || filled($player->password)) {
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
            ];
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
            'claim' => 'No unclaimed player profile matches that mobile number and team code.',
        ]);
    }
}
