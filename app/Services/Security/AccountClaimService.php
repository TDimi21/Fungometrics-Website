<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\AccountClaim;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountClaimService
{
    public function __construct(private SecurityAuditLogger $audit)
    {
    }

    public function issue(User $user): string
    {
        if ($this->isCompleted($user)) {
            throw ValidationException::withMessages(['claim' => 'This account is already complete.']);
        }

        $rawToken = $this->generateHumanClaimCode();
        DB::transaction(function () use ($user, $rawToken): void {
            AccountClaim::query()->where('user_id', $user->id)->whereNull('used_at')
                ->update(['used_at' => now()]);
            AccountClaim::create([
                'user_id' => $user->id,
                'intended_type' => (string) $user->type,
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => now()->addMinutes(config('security.account_claim_ttl_minutes')),
            ]);
        });

        $this->audit->record('account_claim.issued', $user->id, metadata: [
            'intended_type' => (string) $user->type,
        ]);

        return $rawToken;
    }

    public function resolve(string $rawToken, ?string $intendedType = null): AccountClaim
    {
        $claim = AccountClaim::query()->with('user.profile')
            ->where('token_hash', hash('sha256', $rawToken))->first();

        if ( ! $claim || $claim->used_at || $claim->expires_at->isPast()) {
            throw ValidationException::withMessages(['claim' => 'This account claim is invalid or expired.']);
        }
        if ($intendedType && $claim->intended_type !== $intendedType) {
            throw ValidationException::withMessages(['claim' => 'This claim cannot complete that account type.']);
        }
        if ($this->isCompleted($claim->user)) {
            throw ValidationException::withMessages(['claim' => 'This account is already complete.']);
        }

        return $claim;
    }

    public function consume(AccountClaim $claim, Request $request): void
    {
        DB::transaction(function () use ($claim, $request): void {
            $locked = AccountClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if ($locked->used_at || $locked->expires_at->isPast()) {
                throw ValidationException::withMessages(['claim' => 'This account claim is invalid or expired.']);
            }
            $locked->update(['used_at' => now(), 'attempts' => $locked->attempts + 1]);
            $locked->user->tokens()->delete();
            $this->audit->record('account_claim.completed', $locked->user_id, request: $request, metadata: [
                'intended_type' => $locked->intended_type,
            ]);
        });
    }

    private function isCompleted(User $user): bool
    {
        return filled($user->email) || filled($user->password);
    }

    private function generateHumanClaimCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($index = 0; $index < 12; $index++) {
                $code .= $alphabet[random_int(0, mb_strlen($alphabet) - 1)];
            }
        } while (AccountClaim::query()->where('token_hash', hash('sha256', $code))->exists());

        return $code;
    }
}
