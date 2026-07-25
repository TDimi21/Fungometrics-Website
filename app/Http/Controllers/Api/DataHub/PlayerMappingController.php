<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\PlatformDefinition;
use App\Services\Access\AdministrativeAccess;
use App\Services\DataHub\Services\PlayerMappingApprovalService;
use App\Services\DataHub\Services\PlayerMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlayerMappingController extends Controller
{
    public function roster(Request $request, PlayerMatchingService $matching): JsonResponse
    {
        $data = $request->validate(['team_id' => ['required', 'uuid', 'exists:teams,id']]);
        $this->authorizeTeam($request, $data['team_id']);

        return response()->json(['success' => true, 'data' => $matching->roster($data['team_id'])]);
    }

    public function approve(Request $request, PlayerMappingApprovalService $approval): JsonResponse
    {
        $data = $request->validate([
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'platform' => ['required', 'in:trackman'],
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.source_key' => ['required', 'string', 'max:255'],
            'mappings.*.source_name' => ['required', 'string', 'max:255'],
            'mappings.*.external_player_id' => ['nullable', 'string', 'max:96'],
            'mappings.*.roles' => ['required', 'array'],
            'mappings.*.roles.*' => ['in:batter,pitcher'],
            'mappings.*.fmtrx_player_id' => ['nullable', 'uuid'],
            'mappings.*.skipped' => ['required', 'boolean'],
            'confirmed_duplicate_targets' => ['array'],
            'confirmed_duplicate_targets.*' => ['uuid'],
        ]);
        $this->authorizeTeam($request, $data['team_id']);
        $platformId = (string) PlatformDefinition::query()->where('key', $data['platform'])->value('id');

        return response()->json([
            'success' => true,
            'data' => $approval->approve(
                $request->user(),
                $data['team_id'],
                $platformId,
                $data['mappings'],
                $data['confirmed_duplicate_targets'] ?? [],
            ),
        ]);
    }

    private function authorizeTeam(Request $request, string $teamId): void
    {
        $admin = app(AdministrativeAccess::class)->canManageSubscriptions($request->user());
        abort_unless(
            $admin || CoachTeam::query()->where('coach_id', $request->user()->id)->where('team_id', $teamId)->exists(),
            403
        );
    }
}
