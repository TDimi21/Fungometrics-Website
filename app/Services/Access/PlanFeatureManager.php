<?php

declare(strict_types=1);

namespace App\Services\Access;

use App\Models\PlanEntitlement;
use App\Models\SubscriptionAudit;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlanFeatureManager
{
    /** @return Collection<int, SubscriptionPlan> */
    public function plans(): Collection
    {
        return SubscriptionPlan::query()->with('entitlements')->orderBy('audience')->orderBy('key')->get();
    }

    /** @return array<string, mixed> */
    public function serialize(SubscriptionPlan $plan): array
    {
        $plan->loadMissing('entitlements');
        $metadata = $plan->metadata ?? [];
        $limits = array_replace(['players' => null, 'coaches' => null, 'teams' => null], $metadata['limits'] ?? []);
        $immutable = config("entitlements.immutable_by_plan.{$plan->key}", []);

        return [
            'key' => $plan->key,
            'display_name' => $plan->name,
            'audience' => $plan->audience,
            'active' => $plan->active,
            'legacy' => 'coach_basic' === $plan->key,
            'entitlements' => $plan->entitlements->pluck('entitlement_key')->sort()->values()->all(),
            'immutable_entitlements' => $immutable,
            'limits' => $limits,
            'version' => (int) ($metadata['version'] ?? 1),
            'updated_at' => $plan->updated_at?->toIso8601String(),
        ];
    }

    /** @param array<int, string> $entitlements @param array<string, int|null> $limits @return array<string, mixed> */
    public function update(
        string $planKey,
        array $entitlements,
        array $limits,
        int $version,
        string $reason,
        User $actor,
        ?string $correlationId = null
    ): array {
        return DB::transaction(function () use ($planKey, $entitlements, $limits, $version, $reason, $actor, $correlationId): array {
            $plan = SubscriptionPlan::query()->where('key', $planKey)->lockForUpdate()->firstOrFail();
            if ('coach_basic' === $plan->key) {
                throw ValidationException::withMessages(['plan' => 'Coach Basic is legacy and cannot be edited.']);
            }
            $metadata = $plan->metadata ?? [];
            if ($version !== (int) ($metadata['version'] ?? 1)) {
                abort(409, 'This plan changed after it was loaded. Refresh before saving.');
            }

            $catalog = config('entitlements.items', []);
            $requested = array_values(array_unique($entitlements));
            sort($requested);
            $unknown = array_values(array_diff($requested, array_keys($catalog)));
            if ([] !== $unknown) {
                throw ValidationException::withMessages(['entitlements' => 'Unknown entitlement keys: '.implode(', ', $unknown)]);
            }
            $wrongAudience = array_values(array_filter($requested, function (string $key) use ($catalog, $plan): bool {
                $audience = $catalog[$key]['audience'];
                return 'shared' !== $audience && $audience !== $plan->audience;
            }));
            if ([] !== $wrongAudience) {
                throw ValidationException::withMessages(['entitlements' => 'Entitlements do not match the plan audience: '.implode(', ', $wrongAudience)]);
            }
            $immutable = config("entitlements.immutable_by_plan.{$plan->key}", []);
            $removedImmutable = array_values(array_diff($immutable, $requested));
            if ([] !== $removedImmutable) {
                throw ValidationException::withMessages(['entitlements' => 'Immutable baseline capabilities cannot be removed: '.implode(', ', $removedImmutable)]);
            }
            $current = $plan->entitlements()->pluck('entitlement_key')->all();
            $editableStatuses = ['fully_wired', 'platform_wired', 'composite_wired'];
            $coverage = config('entitlement_coverage.entitlements', []);
            $locked = collect($catalog)->filter(function (array $definition, string $key) use ($coverage, $editableStatuses): bool {
                return ! (bool) ($definition['toggleable'] ?? false)
                    || ! in_array($coverage[$key]['implementation_status'] ?? 'disabled_incomplete', $editableStatuses, true);
            })->keys()->all();
            $changedLocked = array_values(array_unique(array_merge(
                array_diff(array_intersect($current, $locked), $requested),
                array_diff(array_intersect($requested, $locked), $current)
            )));
            if ([] !== $changedLocked) {
                throw ValidationException::withMessages([
                    'entitlements' => 'Baseline, deprecated, and unimplemented capabilities cannot be changed: '.implode(', ', $changedLocked),
                ]);
            }
            $validatedLimits = $this->validateLimits($limits);
            $before = $this->serialize($plan);

            PlanEntitlement::query()->where('subscription_plan_id', $plan->id)->delete();
            foreach ($requested as $key) {
                PlanEntitlement::query()->create(['subscription_plan_id' => $plan->id, 'entitlement_key' => $key]);
            }
            $metadata['limits'] = $validatedLimits;
            $metadata['version'] = $version + 1;
            $plan->forceFill(['metadata' => $metadata, 'updated_at' => now()])->save();
            $after = $this->serialize($plan->fresh('entitlements'));

            SubscriptionAudit::query()->create([
                'actor_user_id' => $actor->id,
                'action' => 'plan.entitlements.updated',
                'before_state' => $before,
                'after_state' => $after,
                'reason' => $reason,
                'correlation_id' => $correlationId ?: (string) Str::uuid(),
                'created_at' => now(),
            ]);

            return $after;
        });
    }

    /** @param array<string, int|null> $limits @return array<string, int|null> */
    private function validateLimits(array $limits): array
    {
        $definitions = config('entitlements.limits', []);
        $validated = [];
        foreach ($definitions as $key => $definition) {
            $value = $limits[$key] ?? null;
            if (null !== $value && ( ! is_int($value) || $value < $definition['min'] || $value > $definition['max'])) {
                throw ValidationException::withMessages(["limits.{$key}" => "{$definition['display_name']} is outside the allowed range."]);
            }
            $validated[$key] = $value;
        }
        return $validated;
    }
}
