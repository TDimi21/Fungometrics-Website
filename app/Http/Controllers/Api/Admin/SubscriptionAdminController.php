<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Services\Access\EntitlementResolver;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubscriptionAdminController extends Controller
{
    public function userIndex(User $user, EntitlementResolver $resolver): JsonResponse
    {
        return $this->ok(['subscriptions' => $this->subscriptions('user_id', $user->id), 'access' => $resolver->getAccessSummary($user)]);
    }

    public function userStore(Request $request, User $user, SubscriptionManager $manager, EntitlementResolver $resolver): JsonResponse
    {
        $data = $this->validateWrite($request);
        $subscription = $manager->createManualUserSubscription($user, $data['plan'], $this->date($data['starts_at'] ?? null), $this->date($data['ends_at'] ?? null), $request->user(), $data['reason'] ?? null);
        return $this->ok(['subscription' => $subscription->load('plan'), 'access' => $resolver->getAccessSummary($user)], 201);
    }

    public function teamIndex(Team $team): JsonResponse
    {
        return $this->ok(['subscriptions' => $this->subscriptions('team_id', $team->id), 'inheritance' => ['coach' => 'full_team_plan', 'player' => 'player_appropriate_entitlements']]);
    }

    public function teamStore(Request $request, Team $team, SubscriptionManager $manager): JsonResponse
    {
        $data = $this->validateWrite($request);
        $subscription = $manager->createManualTeamSubscription($team, $data['plan'], $this->date($data['starts_at'] ?? null), $this->date($data['ends_at'] ?? null), $request->user(), $data['reason'] ?? null);
        return $this->ok(['subscription' => $subscription->load('plan.entitlements'), 'inheritance' => ['coach' => 'full_team_plan', 'player' => 'player_appropriate_entitlements']], 201);
    }

    public function update(Request $request, Subscription $subscription, SubscriptionManager $manager): JsonResponse
    {
        $data = $request->validate(['plan' => ['required', 'string'], 'reason' => ['nullable', 'string', 'max:1000']]);
        return $this->ok(['subscription' => $manager->changeSubscriptionPlan($subscription, $data['plan'], $request->user(), $data['reason'] ?? null)->load('plan')]);
    }

    public function cancel(Request $request, Subscription $subscription, SubscriptionManager $manager): JsonResponse
    {
        $data = $request->validate(['immediately' => ['sometimes', 'boolean'], 'reason' => ['nullable', 'string', 'max:1000']]);
        return $this->ok(['subscription' => $manager->cancelSubscription($subscription, (bool) ($data['immediately'] ?? false), $request->user(), $data['reason'] ?? null)]);
    }

    public function revoke(Request $request, Subscription $subscription, SubscriptionManager $manager): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        return $this->ok(['subscription' => $manager->revokeSubscription($subscription, $request->user(), $data['reason'] ?? null)]);
    }

    /** @return array<string, mixed> */
    private function validateWrite(Request $request): array
    {
        return $request->validate(['plan' => ['required', 'string'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'reason' => ['nullable', 'string', 'max:1000']]);
    }

    private function date(?string $date): ?Carbon
    {
        return $date ? Carbon::parse($date)->utc() : null;
    }
    private function subscriptions(string $column, string $id)
    {
        return Subscription::with('plan')->where($column, $id)->latest()->get()->makeHidden(['provider_customer_id', 'provider_subscription_id', 'provider_product_id', 'metadata']);
    }
    private function ok(array $data, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }
}
