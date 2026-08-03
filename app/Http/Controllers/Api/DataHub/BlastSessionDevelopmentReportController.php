<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Access\AdministrativeAccess;
use App\Services\Blast\BlastSessionDevelopmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

final class BlastSessionDevelopmentReportController extends Controller
{
    public function __invoke(Request $request, string $batch, BlastSessionDevelopmentReportService $reports): JsonResponse
    {
        $levels = array_keys((array) config('blast_benchmarks.levels'));
        $data = $request->validate(['benchmark_level' => ['nullable', Rule::in($levels)]]);
        $scope = DB::table('import_batches as b')->join('translation_snapshots as s', 's.id', '=', 'b.translation_snapshot_id')->where('b.id', $batch)->first(['s.team_id', 's.player_id']);
        abort_unless($scope, 404);
        $admin = app(AdministrativeAccess::class)->canManageSubscriptions($request->user());
        $coach = CoachTeam::query()->where('coach_id', $request->user()->id)->where('team_id', $scope->team_id)->exists();
        abort_unless($admin || $coach || $request->user()->id === $scope->player_id, 403);

        $level = (string) ($data['benchmark_level'] ?? '');
        if ('' === $level) {
            $stored = (string) DB::table('profiles')->where('user_id', $scope->player_id)->value('level');
            $level = $this->governedLevel($stored) ?? '';
        }
        if ('' === $level) return response()->json(['success' => false, 'code' => 'benchmark_level_required', 'message' => 'Select a Benchmark Level to view this report.', 'data' => ['levels' => config('blast_benchmarks.levels')]], 422);
        try {
            $report = $reports->report($batch, $level);
            unset($report['_scope']);
            return response()->json(['success' => true, 'data' => $report]);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 404);
        }
    }

    private function governedLevel(string $stored): ?string
    {
        $key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $stored), '_'));
        return array_key_exists($key, (array) config('blast_benchmarks.levels')) ? $key : null;
    }
}
