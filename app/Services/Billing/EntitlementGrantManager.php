<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\EntitlementGrant;
use App\Models\SubscriptionAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EntitlementGrantManager
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): EntitlementGrant
    {
        return DB::transaction(function () use ($data, $actor): EntitlementGrant {
            $this->validateEntitlement((string) $data['entitlement_key']);
            if ((null === $data['user_id']) === (null === $data['team_id'])) {
                throw ValidationException::withMessages(['owner' => 'Exactly one user or team owner is required.']);
            }
            $grant = EntitlementGrant::create([
                'user_id' => $data['user_id'], 'team_id' => $data['team_id'],
                'entitlement_key' => $data['entitlement_key'], 'source_type' => 'admin',
                'source_id' => $actor->id, 'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'metadata' => ['reason' => $data['reason'] ?? null, 'metadata' => $data['metadata'] ?? []],
            ]);
            $this->audit($actor, 'grant.created', $grant, null, $grant->toArray(), $data['reason'] ?? null);
            return $grant;
        });
    }

    public function revoke(EntitlementGrant $grant, User $actor, ?string $reason = null): EntitlementGrant
    {
        return DB::transaction(function () use ($grant, $actor, $reason): EntitlementGrant {
            if ($grant->revoked_at) {
                return $grant;
            }
            $before = $grant->toArray();
            $grant->update(['revoked_at' => now()]);
            $this->audit($actor, 'grant.revoked', $grant, $before, $grant->fresh()->toArray(), $reason);
            return $grant->fresh();
        });
    }

    private function validateEntitlement(string $key): void
    {
        $keys = collect(config('access.plans'))->pluck('entitlements')->flatten()->unique();
        if ( ! $keys->contains($key)) {
            throw ValidationException::withMessages(['entitlement_key' => 'Unknown entitlement key.']);
        }
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function audit(User $actor, string $action, EntitlementGrant $grant, ?array $before, array $after, ?string $reason): void
    {
        SubscriptionAudit::create([
            'actor_user_id' => $actor->id, 'action' => $action,
            'target_user_id' => $grant->user_id, 'target_team_id' => $grant->team_id,
            'grant_id' => $grant->id, 'before_state' => $before, 'after_state' => $after,
            'reason' => $reason, 'correlation_id' => request()?->header('X-Request-ID'), 'created_at' => now(),
        ]);
    }
}
