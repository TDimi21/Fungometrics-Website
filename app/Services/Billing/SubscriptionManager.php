<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\SubscriptionAudit;
use App\Models\SubscriptionPlan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionManager
{
    private const TERMINAL = ['expired', 'revoked'];

    public function createManualUserSubscription(User $user, string $planKey, ?Carbon $startsAt = null, ?Carbon $endsAt = null, ?User $actor = null, ?string $reason = null): Subscription
    {
        return $this->createManual(['user_id' => $user->id], $planKey, $startsAt, $endsAt, $actor, $reason, (string) $user->type);
    }

    public function createManualTeamSubscription(Team $team, string $planKey, ?Carbon $startsAt = null, ?Carbon $endsAt = null, ?User $actor = null, ?string $reason = null): Subscription
    {
        if ( ! ($team->status ?? true)) {
            throw ValidationException::withMessages(['team' => 'The team is inactive.']);
        }
        return $this->createManual(['team_id' => $team->id], $planKey, $startsAt, $endsAt, $actor, $reason, 'coach');
    }

    public function changeSubscriptionPlan(Subscription $subscription, string $planKey, ?User $actor = null, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $planKey, $actor, $reason): Subscription {
            $subscription->refresh();
            $this->assertMutable($subscription);
            $plan = $this->plan($planKey);
            if ($subscription->plan_id === $plan->id) {
                return $subscription;
            }
            $before = $subscription->toArray();
            $subscription->update(['status' => 'expired', 'ended_at' => now()]);
            $replacement = Subscription::create([
                'user_id' => $subscription->user_id, 'team_id' => $subscription->team_id,
                'plan_id' => $plan->id, 'provider' => $subscription->provider,
                'status' => 'active', 'starts_at' => now(),
                'current_period_ends_at' => $subscription->current_period_ends_at,
                'metadata' => ['replaces_subscription_id' => $subscription->id],
            ]);
            $this->audit($actor, 'subscription.plan_changed', $replacement, null, $before, $replacement->toArray(), $reason);
            return $replacement;
        });
    }

    public function cancelSubscription(Subscription $subscription, bool $immediately = false, ?User $actor = null, ?string $reason = null): Subscription
    {
        if ( ! $immediately && null === $subscription->current_period_ends_at) {
            throw ValidationException::withMessages(['subscription' => 'Cancel-at-period-end requires a period end date.']);
        }
        return $this->transition($subscription, $immediately ? 'canceled' : $subscription->status, [
            'canceled_at' => now(),
            'ended_at' => $immediately ? now() : null,
        ], $actor, $immediately ? 'subscription.canceled_immediately' : 'subscription.cancel_at_period_end', $reason);
    }

    public function expireSubscription(Subscription $subscription, ?User $actor = null, ?string $reason = null): Subscription
    {
        return $this->transition($subscription, 'expired', ['ended_at' => now()], $actor, 'subscription.expired', $reason);
    }

    public function revokeSubscription(Subscription $subscription, ?User $actor = null, ?string $reason = null): Subscription
    {
        return $this->transition($subscription, 'revoked', ['ended_at' => now()], $actor, 'subscription.revoked', $reason);
    }

    public function startGracePeriod(Subscription $subscription, Carbon $until, ?User $actor = null, ?string $reason = null): Subscription
    {
        if ($until->isPast()) {
            throw ValidationException::withMessages(['grace_period_ends_at' => 'Grace period must end in the future.']);
        }
        return $this->transition($subscription, 'grace_period', ['grace_period_ends_at' => $until->utc()], $actor, 'subscription.grace_started', $reason);
    }

    /** @param array<string, mixed> $attributes */
    public function reconcileSubscription(Subscription $subscription, array $attributes, ?User $actor = null, ?string $reason = null): Subscription
    {
        $allowed = array_intersect_key($attributes, array_flip(['status', 'current_period_ends_at', 'grace_period_ends_at', 'canceled_at', 'ended_at', 'metadata']));
        $status = (string) ($allowed['status'] ?? $subscription->status);
        unset($allowed['status']);
        return $this->transition($subscription, $status, $allowed, $actor, 'subscription.reconciled', $reason);
    }

    /** @param array<string, mixed> $metadata */
    public function reconcileProviderUserSubscription(User $user, string $provider, string $providerSubscriptionId, string $productId, string $planKey, string $status, Carbon $startsAt, ?Carbon $periodEndsAt, array $metadata = [], ?string $reason = null): Subscription
    {
        if ('' === $providerSubscriptionId) {
            throw ValidationException::withMessages(['transaction_id' => 'Provider transaction identity is required.']);
        }
        return DB::transaction(function () use ($user, $provider, $providerSubscriptionId, $productId, $planKey, $status, $startsAt, $periodEndsAt, $metadata, $reason): Subscription {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $plan = $this->plan($planKey);
            if ($plan->audience !== (string) $locked->type) {
                throw ValidationException::withMessages(['plan' => 'The plan audience does not match the subscription owner.']);
            }
            $subscription = Subscription::query()->where('provider', $provider)->where('provider_subscription_id', $providerSubscriptionId)->lockForUpdate()->first();
            if ($subscription && $subscription->user_id !== $locked->id) {
                throw ValidationException::withMessages(['owner' => 'Provider subscription belongs to another FMTRX user.']);
            }
            $before = $subscription?->toArray();
            $subscription ??= new Subscription(['user_id' => $locked->id, 'provider' => $provider, 'provider_subscription_id' => $providerSubscriptionId]);
            $subscription->fill(['plan_id' => $plan->id, 'provider_product_id' => $productId, 'status' => $status,
                'starts_at' => $startsAt->utc(), 'current_period_ends_at' => $periodEndsAt?->utc(),
                'grace_period_ends_at' => 'grace_period' === $status ? $periodEndsAt?->utc() : null,
                'canceled_at' => 'CANCELLATION' === $reason ? now() : null,
                'ended_at' => in_array($status, ['expired', 'revoked'], true) ? ($periodEndsAt ?? now())->utc() : null,
                'metadata' => $metadata])->save();
            $this->audit(null, 'subscription.provider_reconciled', $subscription, null, $before, $subscription->fresh()->toArray(), $reason);
            return $subscription->fresh();
        });
    }

    /** @param array<string, string> $owner */
    private function createManual(array $owner, string $planKey, ?Carbon $startsAt, ?Carbon $endsAt, ?User $actor, ?string $reason, string $audience): Subscription
    {
        return DB::transaction(function () use ($owner, $planKey, $startsAt, $endsAt, $actor, $reason, $audience): Subscription {
            if (1 !== count($owner)) {
                throw ValidationException::withMessages(['owner' => 'Exactly one owner is required.']);
            }
            // Serialize creation even when no active subscription row exists yet.
            // Locking the stable owner row closes the concurrent first-write gap.
            if (isset($owner['user_id'])) {
                User::query()->whereKey($owner['user_id'])->lockForUpdate()->firstOrFail();
            } else {
                Team::query()->whereKey($owner['team_id'])->lockForUpdate()->firstOrFail();
            }
            $plan = $this->plan($planKey);
            if ($plan->audience !== $audience && 'free' !== $plan->key) {
                throw ValidationException::withMessages(['plan' => 'The plan audience does not match the subscription owner.']);
            }
            $active = Subscription::query()->where($owner)->where('provider', 'manual')
                ->whereIn('status', ['trialing', 'active', 'grace_period'])->whereNull('ended_at')->lockForUpdate()->first();
            $sameEnd = (null === $active?->current_period_ends_at && null === $endsAt)
                || (null !== $active?->current_period_ends_at && null !== $endsAt && $active->current_period_ends_at->equalTo($endsAt));
            if ($active && $active->plan_id === $plan->id && $sameEnd) {
                return $active;
            }
            if ($active) {
                $active->update(['status' => 'expired', 'ended_at' => now()]);
            }
            $subscription = Subscription::create($owner + [
                'plan_id' => $plan->id, 'provider' => 'manual', 'status' => 'active',
                'starts_at' => ($startsAt ?? now())->utc(), 'current_period_ends_at' => $endsAt?->utc(),
                'metadata' => ['admin_actor_id' => $actor?->id],
            ]);
            $this->audit($actor, 'subscription.created', $subscription, null, null, $subscription->toArray(), $reason);
            return $subscription;
        });
    }

    /** @param array<string, mixed> $changes */
    private function transition(Subscription $subscription, string $status, array $changes, ?User $actor, string $action, ?string $reason): Subscription
    {
        return DB::transaction(function () use ($subscription, $status, $changes, $actor, $action, $reason): Subscription {
            $subscription->refresh();
            $this->assertTransition($subscription->status, $status);
            $before = $subscription->toArray();
            $subscription->update(['status' => $status] + $changes);
            $this->audit($actor, $action, $subscription, null, $before, $subscription->fresh()->toArray(), $reason);
            return $subscription->fresh();
        });
    }

    private function assertTransition(string $from, string $to): void
    {
        $allowed = [
            'trialing' => ['trialing', 'active', 'grace_period', 'canceled', 'expired', 'revoked'],
            'active' => ['active', 'grace_period', 'past_due', 'canceled', 'expired', 'revoked'],
            'grace_period' => ['grace_period', 'active', 'expired', 'revoked'],
            'past_due' => ['past_due', 'active', 'grace_period', 'expired', 'revoked'],
            'canceled' => ['canceled', 'expired', 'revoked'],
            'expired' => ['expired'], 'revoked' => ['revoked'],
        ];
        if ( ! in_array($to, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Invalid subscription transition: {$from} to {$to}."]);
        }
    }

    private function assertMutable(Subscription $subscription): void
    {
        if (in_array($subscription->status, self::TERMINAL, true)) {
            throw ValidationException::withMessages(['subscription' => 'Terminal subscriptions cannot be modified.']);
        }
    }

    private function plan(string $key): SubscriptionPlan
    {
        $plan = SubscriptionPlan::query()->where('key', $key)->where('active', true)->first();
        if ( ! $plan) {
            throw ValidationException::withMessages(['plan' => 'Unknown or inactive subscription plan.']);
        }
        return $plan;
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    private function audit(?User $actor, string $action, ?Subscription $subscription, ?string $grantId, ?array $before, ?array $after, ?string $reason): void
    {
        SubscriptionAudit::create([
            'actor_user_id' => $actor?->id, 'action' => $action,
            'target_user_id' => $subscription?->user_id, 'target_team_id' => $subscription?->team_id,
            'subscription_id' => $subscription?->id, 'grant_id' => $grantId,
            'before_state' => $before, 'after_state' => $after, 'reason' => $reason,
            'correlation_id' => request()?->header('X-Request-ID'), 'created_at' => now(),
        ]);
    }
}
