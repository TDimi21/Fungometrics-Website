<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Http\Controllers\Api\Auth\AuthUtils;
use App\Http\Controllers\Api\Coach\CoachUtils;
use App\Models\Concerns\UserTypes;
use App\Models\Team;
use App\Models\TeamJoinChallenge;
use App\Models\User;
use App\Services\SendSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TeamJoinChallengeService
{
    public function __construct(
        private SendSmsService $sms,
        private SecurityAuditLogger $audit
    ) {
    }

    public function request(string $phone, string $teamCode, Request $request): TeamJoinChallenge
    {
        $team = Team::query()->where('join_code', mb_strtoupper(trim($teamCode)))->first();
        if ( ! $team) {
            throw ValidationException::withMessages(['team_code' => 'The team code is invalid.']);
        }

        $normalizedPhone = preg_replace('/\D+/', '', $phone) ?? '';
        $user = User::query()->where('phone', $normalizedPhone)->first();
        $code = (string) random_int(100000, 999999);
        $challenge = TeamJoinChallenge::create([
            'user_id' => $user?->id,
            'team_id' => $team->id,
            'phone_hash' => hash('sha256', $normalizedPhone),
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('security.team_join_ttl_minutes')),
        ]);

        $this->sms->sendSms(
            $normalizedPhone,
            "Your FungoMetrics team verification code is {$code}. It expires soon.",
            type: 'team_join_verification',
            user: $user?->id,
            sensitive: true
        );
        $this->audit->record('team_join.requested', $user?->id, $team->id, $request);

        return $challenge;
    }

    /** @return array<string, mixed> */
    public function verify(string $challengeId, string $code, Request $request): array
    {
        return DB::transaction(function () use ($challengeId, $code, $request): array {
            $challenge = TeamJoinChallenge::query()->lockForUpdate()->find($challengeId);
            if ( ! $challenge || $challenge->used_at || $challenge->expires_at->isPast()) {
                throw ValidationException::withMessages(['verification_code' => 'The verification challenge is invalid or expired.']);
            }
            if ($challenge->attempts >= config('security.team_join_max_attempts')) {
                throw ValidationException::withMessages(['verification_code' => 'Too many verification attempts.']);
            }
            if ( ! Hash::check($code, $challenge->code_hash)) {
                $challenge->increment('attempts');
                $this->audit->record('team_join.verification_failed', $challenge->user_id, $challenge->team_id, $request);
                throw ValidationException::withMessages(['verification_code' => 'The verification code is invalid.']);
            }

            $challenge->update(['used_at' => now(), 'attempts' => $challenge->attempts + 1]);
            $user = $challenge->user_id ? User::query()->find($challenge->user_id) : null;
            if ( ! $user) {
                $this->audit->record('team_join.registration_required', null, $challenge->team_id, $request);
                return ['status' => 'registration_required', 'team_id' => $challenge->team_id];
            }
            if (UserTypes::PLAYER->value !== (string) $user->type) {
                $this->audit->record('team_join.wrong_account_type', $user->id, $challenge->team_id, $request);
                throw ValidationException::withMessages(['account' => 'Coach accounts must use a coach invitation flow.']);
            }

            CoachUtils::addPlayerToRoaster($user, $challenge->team_id);
            $token = AuthUtils::createTokenFromUser($user)['token'];
            $this->audit->record('team_join.completed', $user->id, $challenge->team_id, $request);

            return [
                'status' => 'success',
                'token' => $token,
                'user' => $user->load('profile', 'player', 'positions', 'fitness'),
                'team' => Team::query()->findOrFail($challenge->team_id),
            ];
        });
    }

    public function joinAuthenticatedPlayer(User $user, string $teamCode, Request $request): Team
    {
        if (UserTypes::PLAYER->value !== (string) $user->type) {
            throw ValidationException::withMessages(['account' => 'Only players may use this team join endpoint.']);
        }
        $team = Team::query()->where('join_code', mb_strtoupper(trim($teamCode)))->first();
        if ( ! $team) {
            throw ValidationException::withMessages(['team_code' => 'The team code is invalid.']);
        }
        CoachUtils::addPlayerToRoaster($user, $team->id);
        $this->audit->record('team_join.authenticated', $user->id, $team->id, $request);

        return $team;
    }
}
