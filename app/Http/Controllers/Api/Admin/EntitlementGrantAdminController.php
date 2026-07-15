<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntitlementGrant;
use App\Models\User;
use App\Services\Access\EntitlementResolver;
use App\Services\Billing\EntitlementGrantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntitlementGrantAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'uuid'], 'team_id' => ['nullable', 'uuid']]);
        return response()->json(['success' => true, 'data' => EntitlementGrant::query()->when($data['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))->when($data['team_id'] ?? null, fn ($q, $id) => $q->where('team_id', $id))->latest()->get()]);
    }

    public function store(Request $request, EntitlementGrantManager $manager, EntitlementResolver $resolver): JsonResponse
    {
        $data = $request->validate(['entitlement_key' => ['required', 'string'], 'user_id' => ['nullable', 'uuid', 'exists:users,id'], 'team_id' => ['nullable', 'uuid', 'exists:teams,id'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'reason' => ['nullable', 'string', 'max:1000'], 'metadata' => ['nullable', 'array']]);
        $grant = $manager->create($data + ['user_id' => null, 'team_id' => null], $request->user());
        $access = $grant->user_id ? $resolver->getAccessSummary(User::findOrFail($grant->user_id)) : null;
        return response()->json(['success' => true, 'data' => ['grant' => $grant, 'access' => $access]], 201);
    }

    public function revoke(Request $request, EntitlementGrant $grant, EntitlementGrantManager $manager): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        return response()->json(['success' => true, 'data' => $manager->revoke($grant, $request->user(), $data['reason'] ?? null)]);
    }
}
