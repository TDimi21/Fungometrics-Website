<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Coach;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Development\PlayerDevelopmentDashboardCache;
use App\Services\Intelligence\BenchmarkDefinitions;
use App\Services\Intelligence\BenchmarkLibrary;
use App\Services\Intelligence\TeamBenchmarkOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamBenchmarkOverrideController extends Controller
{
    private function authorizeTeam(string $teamId): void
    {
        abort_unless(CoachTeam::where('coach_id', Auth::id())->where('team_id', $teamId)->exists(), 403);
    }

    public function index(string $teamId, BenchmarkLibrary $library, TeamBenchmarkOverrideService $overrides): JsonResponse
    {
        $this->authorizeTeam($teamId);
        $saved = $overrides->overridesForTeam($teamId);
        $metrics = collect($library->all())->values()->map(function (array $metric) use ($saved): array {
            $metric['age_percentile_anchors'] = collect($metric['age_percentile_anchors'])->mapWithKeys(
                fn (array $anchors, string $age) => [$age => [
                    'defaults' => $anchors,
                    'values' => $saved[$metric['metric_key'].'|'.$age] ?? $anchors,
                    'overridden' => isset($saved[$metric['metric_key'].'|'.$age]),
                ]]
            )->all();
            return $metric;
        })->all();

        return response()->json(['status' => 'success', 'data' => ['metrics' => $metrics]]);
    }

    public function update(Request $request, string $teamId, BenchmarkLibrary $library, PlayerDevelopmentDashboardCache $cache): JsonResponse
    {
        $this->authorizeTeam($teamId);
        $data = $request->validate([
            'metric_key' => ['required', 'string'],
            'age_group' => ['required', 'string'],
            'anchors' => ['required', 'array'],
            'anchors.p5' => ['required', 'numeric'], 'anchors.p25' => ['required', 'numeric'],
            'anchors.p50' => ['required', 'numeric'], 'anchors.p75' => ['required', 'numeric'],
            'anchors.p95' => ['required', 'numeric'],
        ]);
        $key = BenchmarkDefinitions::normalizeMetricKey($data['metric_key']);
        $metric = $library->metric($key);
        abort_unless($metric && isset($metric['age_percentile_anchors'][$data['age_group']]), 422);
        $values = array_map('floatval', $data['anchors']);
        $ordered = array_values(array_intersect_key($values, array_flip(TeamBenchmarkOverrideService::TIERS)));
        $sorted = array_values($metric['higher_is_better'] ? collect($ordered)->sort()->all() : collect($ordered)->sortDesc()->all());
        if (count(array_unique($ordered)) !== count($ordered) || $ordered !== $sorted) {
            throw ValidationException::withMessages(['anchors' => 'Percentile values must progress from P5 through P95 in the metric direction.']);
        }
        DB::table('team_benchmark_overrides')->updateOrInsert(
            ['team_id' => $teamId, 'metric_key' => $key, 'age_group' => $data['age_group']],
            $values + ['updated_by' => Auth::id(), 'updated_at' => now(), 'created_at' => now()]
        );
        $cache->forgetTeam($teamId);
        return response()->json(['status' => 'success', 'data' => ['saved' => true]]);
    }

    public function destroy(Request $request, string $teamId, PlayerDevelopmentDashboardCache $cache): JsonResponse
    {
        $this->authorizeTeam($teamId);
        $data = $request->validate(['metric_key' => ['required', 'string'], 'age_group' => ['required', 'string']]);
        DB::table('team_benchmark_overrides')->where('team_id', $teamId)
            ->where('metric_key', BenchmarkDefinitions::normalizeMetricKey($data['metric_key']))
            ->where('age_group', $data['age_group'])->delete();
        $cache->forgetTeam($teamId);
        return response()->json(['status' => 'success', 'data' => ['reset' => true]]);
    }
}
