<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\CoachTeam;
use App\Models\EntitlementGrant;
use App\Models\PlayerTeam;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\UnauthorizedException;

class EntitlementResolver
{
    public function getEffectivePlan(User $user, ?string $teamId = null): string
    {
        return $this->resolve($user, $teamId)['plan'];
    }

    /** @return array<int, string> */
    public function getEntitlements(User $user, ?string $teamId = null): array
    {
        return $this->resolve($user, $teamId)['entitlements'];
    }

    public function hasEntitlement(User $user, string $entitlement, ?string $teamId = null): bool
    {
        return in_array($entitlement, $this->getEntitlements($user, $teamId), true);
    }

    /** @return array<string, mixed> */
    public function getAccessSummary(User $user, ?string $teamId = null): array
    {
        return $this->resolve($user, $teamId);
    }

    /** @return array<string, mixed> */
    private function resolve(User $user, ?string $teamId): array
    {
        $membership = $teamId ? $this->membership($user, $teamId) : null;
        $sources = [];
        $audience = $this->audience($user);
        $hasSubscriptionHistory = false;

        if (Schema::hasTable('subscriptions')) {
            $hasSubscriptionHistory = Subscription::query()->where('user_id', $user->id)->exists();
            $sources = array_merge($sources, $this->subscriptionSources('user_id', $user->id, audience: $audience));
        }
        $legacyKey = $hasSubscriptionHistory ? 'free' : ($user->subscription_plan ?: 'free');
        $sources[] = $this->catalogSource($legacyKey, 'legacy', audience: $audience);
        if (Schema::hasTable('entitlement_grants')) {
            $sources = array_merge($sources, $this->grantSources('user_id', $user->id));
        }

        if (null !== $teamId) {
            $teamSubscriptionSources = [];
            if (Schema::hasTable('subscriptions')) {
                $teamSubscriptionSources = $this->subscriptionSources('team_id', $teamId, $membership['role']);
                $sources = array_merge($sources, $teamSubscriptionSources);
            }
            if (Schema::hasTable('entitlement_grants')) {
                $sources = array_merge($sources, $this->grantSources('team_id', $teamId, $membership['role']));
            }
            $legacyTeamPlan = [] === $teamSubscriptionSources ? $this->legacyTeamPlan($teamId) : null;
            if (null !== $legacyTeamPlan) {
                $sources[] = $this->catalogSource($legacyTeamPlan, 'team_legacy', null, null, $membership['role'], $audience);
            }
        }

        $entitlements = [];
        foreach ($sources as $source) {
            $entitlements = array_merge($entitlements, $source['entitlements']);
        }
        $entitlements = array_merge(
            $entitlements,
            config("access.audience_baselines.{$audience}.entitlements", [])
        );
        if (app(AdministrativeAccess::class)->canManageSubscriptions($user)) {
            $entitlements = array_merge($entitlements, config('access.administrative_entitlements', []));
        }
        $nonRuntimeEntitlements = array_merge(
            config('entitlements.deprecated', []),
            config('entitlements.not_implemented', [])
        );
        $entitlements = array_values(array_diff($entitlements, $nonRuntimeEntitlements));
        $entitlements = array_values(array_unique($entitlements));

        usort($sources, fn (array $a, array $b): int => $this->sourceRank($b) <=> $this->sourceRank($a)
            ?: strcmp($a['identity'], $b['identity']));
        $effective = $sources[0];
        $limits = $this->limits($effective['plan'], $audience);
        if (
            'coach' === $audience
            && (null === $limits['teams'] || $limits['teams'] > 1)
            && in_array('add_team', $entitlements, true)
            && in_array('team_switching', $entitlements, true)
        ) {
            $entitlements[] = 'manage_multiple_teams';
        }
        $entitlements = array_values(array_unique($entitlements));
        sort($entitlements);
        $usage = $this->usage($user, $teamId, $audience, $limits);

        return [
            'plan' => $effective['plan'],
            'source_plan' => $effective['plan'],
            'effective_access_tier' => $this->effectiveAccessTier($effective['plan'], $audience, $teamId),
            'audience' => $audience,
            'status' => $effective['status'],
            'source' => $effective['source'],
            'provider' => $effective['provider'],
            'expires_at' => $effective['expires_at'],
            'team' => $teamId ? ['id' => $teamId, 'role' => $membership['role']] : null,
            'inheritance' => $teamId && 'player' === $audience
                ? ['inherited' => true, 'reason' => 'team_access', 'team_id' => $teamId]
                : ['inherited' => false, 'reason' => null, 'team_id' => null],
            'entitlements' => $entitlements,
            'limits' => $limits,
            'usage' => $usage,
            'remaining' => collect($limits)->mapWithKeys(function ($limit, string $key) use ($usage): array {
                $used = $usage[$key] ?? null;
                return [$key => null === $limit || null === $used ? null : max(0, $limit - $used)];
            })->all(),
        ];
    }

    /** @param array<string, int|null> $limits @return array<string, int|null> */
    private function usage(User $user, ?string $teamId, string $audience, array $limits): array
    {
        if ('coach' !== $audience) {
            return ['players' => null, 'coaches' => null, 'teams' => null];
        }

        $teams = CoachTeam::query()->where('coach_id', $user->id)->pluck('team_id')->unique();
        $scope = $teamId ? collect([$teamId]) : $teams;

        return [
            'players' => PlayerTeam::query()->whereIn('team_id', $scope)->where('actual', true)->distinct('user_id')->count('user_id'),
            'coaches' => CoachTeam::query()->whereIn('team_id', $scope)->distinct('coach_id')->count('coach_id'),
            'teams' => $teams->count(),
        ];
    }

    /** @return array{role:string} */
    private function membership(User $user, string $teamId): array
    {
        $coach = CoachTeam::query()->where('coach_id', $user->id)->where('team_id', $teamId)->exists();
        if ($coach) {
            return ['role' => 'coach'];
        }
        $player = PlayerTeam::query()->where('user_id', $user->id)->where('team_id', $teamId)->where('actual', true)->exists();
        if ($player) {
            return ['role' => 'player'];
        }
        throw new UnauthorizedException('The requested team is not available to this user.');
    }

    /** @return array<int, array<string, mixed>> */
    private function subscriptionSources(string $ownerColumn, string $ownerId, ?string $teamRole = null, ?string $audience = null): array
    {
        return Subscription::query()->with('plan.entitlements')->where($ownerColumn, $ownerId)
            ->whereNull('ended_at')->where(function ($query): void {
                $now = now();
                $query->where(function ($active) use ($now): void {
                    $active->whereIn('status', ['active', 'trialing'])
                        ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                        ->where(fn ($q) => $q->whereNull('current_period_ends_at')->orWhere('current_period_ends_at', '>', $now));
                })->orWhere(function ($grace) use ($now): void {
                    $grace->where('status', 'grace_period')->where('grace_period_ends_at', '>', $now);
                });
            })->get()->map(function (Subscription $subscription) use ($teamRole, $audience): array {
                $entitlements = $subscription->plan->entitlements->pluck('entitlement_key')->all();
                return $this->source(
                    $subscription->plan->key,
                    $entitlements,
                    'subscription',
                    $subscription->status,
                    $subscription->provider,
                    $subscription->grace_period_ends_at ?? $subscription->current_period_ends_at,
                    $subscription->id,
                    $teamRole,
                    $audience
                );
            })->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function grantSources(string $ownerColumn, string $ownerId, ?string $teamRole = null): array
    {
        $now = now();
        return EntitlementGrant::query()->where($ownerColumn, $ownerId)->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->get()->map(fn (EntitlementGrant $grant): array => $this->source(
                'free',
                [$grant->entitlement_key],
                'grant',
                'active',
                null,
                $grant->ends_at,
                $grant->id,
                $teamRole
            ))->all();
    }

    private function legacyTeamPlan(string $teamId): ?string
    {
        return CoachTeam::query()->where('team_id', $teamId)->where('is_main', true)
            ->get()->map(fn (CoachTeam $membership) => User::find($membership->coach_id)?->subscription_plan)
            ->filter()->sortByDesc(fn (string $key): int => $this->planRank($key))->first();
    }

    /** @return array<string, mixed> */
    private function catalogSource(string $key, string $source, ?string $provider = null, ?Carbon $expires = null, ?string $teamRole = null, ?string $audience = null): array
    {
        $definition = config("access.plans.{$key}") ?? config('access.plans.free');
        $entitlements = $definition['entitlements'];
        if (Schema::hasTable('subscription_plans') && Schema::hasTable('plan_entitlements')) {
            $databasePlan = SubscriptionPlan::query()->with('entitlements')->where('key', $key)->first();
            if ($databasePlan) {
                $entitlements = $databasePlan->entitlements->pluck('entitlement_key')->all();
            }
        }
        return $this->source($key, $entitlements, $source, 'active', $provider, $expires, $key, $teamRole, $audience);
    }

    /** @param array<int, string> $entitlements @return array<string, mixed> */
    private function source(string $plan, array $entitlements, string $source, string $status, ?string $provider, ?Carbon $expires, string $identity, ?string $teamRole, ?string $audience = null): array
    {
        if ('player' === $teamRole || 'player' === $audience) {
            $entitlements = array_values(array_intersect($entitlements, config('access.plans.player_pro.entitlements', [])));
        }
        if ('free' === $plan && 'player' === $audience) {
            $entitlements = array_values(array_intersect($entitlements, config('access.audience_baselines.player.entitlements', [])));
        }
        return compact('plan', 'entitlements', 'source', 'status', 'provider') + [
            'expires_at' => $expires?->toIso8601String(), 'identity' => $identity,
        ];
    }

    /** @return array<string, int|null> */
    private function limits(string $plan, string $audience): array
    {
        if ('free' === $plan && 'player' === $audience) {
            return config('access.audience_baselines.player.limits', ['players' => null, 'coaches' => null, 'teams' => null]);
        }

        if (Schema::hasTable('subscription_plans')) {
            $metadata = SubscriptionPlan::query()->where('key', $plan)->value('metadata');
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true);
            }
            if (is_array($metadata) && isset($metadata['limits']) && is_array($metadata['limits'])) {
                return array_replace(['players' => null, 'coaches' => null, 'teams' => null], $metadata['limits']);
            }
        }

        return config("access.plans.{$plan}.limits", ['players' => null, 'coaches' => null, 'teams' => null]);
    }

    private function audience(User $user): string
    {
        return $user->type instanceof BackedEnum ? (string) $user->type->value : (string) $user->type;
    }

    private function sourceRank(array $source): int
    {
        return $this->planRank($source['plan']) * 100 + match ($source['source']) {
            'subscription' => 30, 'grant' => 20, 'team_legacy' => 10, default => 0,
        };
    }

    private function planRank(string $key): int
    {
        $rank = array_search($key, config('access.plan_priority'), true);
        return false === $rank ? 0 : $rank;
    }

    private function effectiveAccessTier(string $plan, string $audience, ?string $teamId): string
    {
        if ('player' === $audience && null !== $teamId && str_starts_with($plan, 'coach_')) {
            return 'player_inherited';
        }

        return $plan;
    }
}
