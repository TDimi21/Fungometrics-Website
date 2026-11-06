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

    /** Start contact verification; a team code never issues an authentication token. */
    public function claim(string $contact, string $teamCode, Request $request): array
    {
        return app(PlayerContactVerification::class)->start($contact, $teamCode);
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
