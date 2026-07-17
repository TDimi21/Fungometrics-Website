<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionAudit;
use App\Models\SubscriptionPlan;
use App\Services\Access\PlanFeatureManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanFeatureAdminController extends Controller
{
    public function plans(PlanFeatureManager $manager): JsonResponse
    {
        return $this->ok([
            'plans' => $manager->plans()->map(fn (SubscriptionPlan $plan): array => $manager->serialize($plan))->values(),
            'feature_groups' => config('entitlements.categories'),
            'system_capabilities' => config('entitlements.system_capabilities'),
            'limit_metadata' => config('entitlements.limits'),
        ]);
    }

    public function entitlements(): JsonResponse
    {
        return $this->ok(['entitlements' => array_values(config('entitlements.items', []))]);
    }

    public function update(Request $request, string $plan, PlanFeatureManager $manager): JsonResponse
    {
        $data = $request->validate([
            'entitlements' => ['required', 'array'],
            'entitlements.*' => ['string', 'distinct'],
            'limits' => ['required', 'array:players,coaches,teams'],
            'limits.players' => ['nullable', 'integer'],
            'limits.coaches' => ['nullable', 'integer'],
            'limits.teams' => ['nullable', 'integer'],
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'correlation_id' => ['nullable', 'string', 'max:128'],
            'provider' => ['prohibited'],
            'provider_product_id' => ['prohibited'],
            'provider_subscription_id' => ['prohibited'],
            'price' => ['prohibited'],
        ]);
        abort_unless(SubscriptionPlan::query()->where('key', $plan)->exists(), 404);

        return $this->ok(['plan' => $manager->update(
            $plan,
            $data['entitlements'],
            $data['limits'],
            $data['version'],
            $data['reason'],
            $request->user(),
            $data['correlation_id'] ?? null
        )]);
    }

    public function audits(Request $request): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->integer('limit', 50)));
        $audits = SubscriptionAudit::query()->with('actor:id,email')->where('action', 'plan.entitlements.updated')
            ->latest('created_at')->limit($limit)->get()->map(fn (SubscriptionAudit $audit): array => [
                'id' => $audit->id,
                'actor' => $audit->actor ? ['id' => $audit->actor->id, 'email' => $audit->actor->email] : null,
                'action' => $audit->action,
                'reason' => $audit->reason,
                'correlation_id' => $audit->correlation_id,
                'before' => $audit->before_state,
                'after' => $audit->after_state,
                'created_at' => $audit->created_at?->toIso8601String(),
            ]);
        return $this->ok(['audits' => $audits]);
    }

    private function ok(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
