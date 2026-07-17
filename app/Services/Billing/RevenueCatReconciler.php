<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\SubscriptionReconciler;
use App\Models\BillingEvent;
use App\Models\Subscription;
use App\Models\SubscriptionAudit;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

class RevenueCatReconciler implements SubscriptionReconciler
{
    private const ACCESS_STATUSES = ['active', 'trialing', 'in_grace_period', 'in_billing_retry'];

    public function __construct(private SubscriptionManager $subscriptions)
    {
    }

    public function reconcile(BillingEvent $event): void
    {
        $payload = (array) $event->payload;
        $type = (string) ($payload['type'] ?? $event->event_type);
        if ('TEST' === $type) {
            return;
        }
        if ('TRANSFER' === $type) {
            SubscriptionAudit::create(['action' => 'revenuecat.transfer_received', 'before_state' => null, 'after_state' => $payload, 'reason' => 'TRANSFER', 'created_at' => now()]);
            return;
        }
        $supported = ['INITIAL_PURCHASE','RENEWAL','CANCELLATION','UNCANCELLATION','BILLING_ISSUE','EXPIRATION','PRODUCT_CHANGE','SUBSCRIPTION_EXTENDED','REFUND','REFUND_REVERSED'];
        if ( ! in_array($type, $supported, true)) {
            return;
        }
        $user = $this->user((string) ($payload['app_user_id'] ?? ''));
        $product = (string) ($payload['new_product_id'] ?? $payload['product_id'] ?? '');
        $mapping = $this->mapping($product, $user);
        $entitlements = array_values(array_filter((array) ($payload['entitlement_ids'] ?? [])));
        if ([] !== $entitlements && ! in_array($mapping['entitlement'], $entitlements, true)) {
            throw ValidationException::withMessages(['entitlement_ids' => 'RevenueCat entitlement does not match the mapped product.']);
        }
        $environment = mb_strtoupper((string) ($payload['environment'] ?? ''));
        $expected = 'test' === config('billing.revenuecat.environment') ? 'SANDBOX' : 'PRODUCTION';
        if ($environment !== $expected) {
            throw ValidationException::withMessages(['environment' => 'RevenueCat environment mismatch.']);
        }
        $endsAt = $this->milliseconds($payload['grace_period_expiration_at_ms'] ?? $payload['expiration_at_ms'] ?? null);
        $startsAt = $this->milliseconds($payload['purchased_at_ms'] ?? null) ?? now();
        $status = match ($type) {
            'INITIAL_PURCHASE', 'RENEWAL', 'UNCANCELLATION', 'PRODUCT_CHANGE', 'SUBSCRIPTION_EXTENDED', 'REFUND_REVERSED' => 'active',
            'BILLING_ISSUE' => $endsAt?->isFuture() ? 'grace_period' : 'past_due',
            'EXPIRATION' => 'expired',
            'REFUND' => 'revoked',
            'CANCELLATION' => 'active',
            default => throw ValidationException::withMessages(['event' => 'Unsupported RevenueCat lifecycle event.']),
        };
        $this->upsert($user, $mapping['plan'], $product, (string) ($payload['original_transaction_id'] ?? $payload['transaction_id'] ?? ''), $status, $startsAt, $endsAt, $type, $payload);
    }

    /** @param array<int, array<string, mixed>> $items */
    public function reconcileCustomer(User $user, array $items): void
    {
        $seen = [];
        foreach ($items as $item) {
            $product = (string) ($item['product_id'] ?? '');
            $mapping = $this->mapping($product, $user);
            $seen[] = (string) ($item['id'] ?? '');
            $status = in_array((string) ($item['status'] ?? ''), self::ACCESS_STATUSES, true)
                ? ('in_grace_period' === ($item['status'] ?? '') ? 'grace_period' : ('in_billing_retry' === ($item['status'] ?? '') ? 'past_due' : (string) $item['status']))
                : 'expired';
            $this->upsert(
                $user,
                $mapping['plan'],
                $product,
                (string) ($item['id'] ?? ''),
                $status,
                $this->milliseconds($item['starts_at'] ?? null) ?? now(),
                $this->milliseconds($item['current_period_ends_at'] ?? null),
                'SYNC',
                $item
            );
        }
        Subscription::query()->where('user_id', $user->id)->where('provider', 'revenuecat')->whereNotIn('provider_subscription_id', $seen)
            ->whereIn('status', ['active', 'trialing', 'grace_period', 'past_due'])->update(['status' => 'expired', 'ended_at' => now()]);
        $this->updateCompatibilityCache($user);
    }

    /** @param array<string, mixed> $metadata */
    private function upsert(User $user, string $planKey, string $product, string $transaction, string $status, Carbon $startsAt, ?Carbon $endsAt, string $reason, array $metadata): void
    {
        DB::transaction(function () use ($user, $planKey, $product, $transaction, $status, $startsAt, $endsAt, $reason, $metadata): void {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->subscriptions->reconcileProviderUserSubscription($locked, 'revenuecat', $transaction, $product, $planKey, $status, $startsAt, $endsAt, ['revenuecat' => $metadata], $reason);
            $this->updateCompatibilityCache($locked);
        });
    }

    private function updateCompatibilityCache(User $user): void
    {
        $effectivePersonal = Subscription::query()->where('user_id', $user->id)->whereIn('status', ['trialing', 'active', 'grace_period'])
            ->whereNull('ended_at')->where(function ($query): void {
                $now = now();
                $query->where(function ($active) use ($now): void {
                    $active->whereIn('status', ['active', 'trialing'])
                        ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                        ->where(fn ($q) => $q->whereNull('current_period_ends_at')->orWhere('current_period_ends_at', '>', $now));
                })->orWhere(function ($grace) use ($now): void {
                    $grace->where('status', 'grace_period')->where('grace_period_ends_at', '>', $now);
                });
            })->with('plan')->get()
            ->sortByDesc(fn (Subscription $s) => array_search($s->plan->key, config('access.plan_priority', []), true) ?: 0)->first();
        $user->update(['subscription_plan' => $effectivePersonal?->plan?->key ?? 'free']);
    }

    /** @return array<string, string> */
    private function mapping(string $product, User $user): array
    {
        $mapping = config('billing.revenuecat.products.'.$product);
        if ( ! is_array($mapping)) {
            throw ValidationException::withMessages(['product_id' => 'Unknown RevenueCat product.']);
        }
        if (($mapping['audience'] ?? null) !== $user->type) {
            throw ValidationException::withMessages(['product_id' => 'Product audience does not match user.']);
        }
        return $mapping;
    }

    private function user(string $id): User
    {
        if ( ! Uuid::isValid($id) || ! ($user = User::find($id))) {
            throw ValidationException::withMessages(['app_user_id' => 'Unknown RevenueCat App User ID.']);
        }
        return $user;
    }

    private function milliseconds(mixed $value): ?Carbon
    {
        return is_numeric($value) ? Carbon::createFromTimestampMsUTC((int) $value) : null;
    }
}
