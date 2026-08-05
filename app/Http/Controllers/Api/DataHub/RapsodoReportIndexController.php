<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Services\Access\AdministrativeAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RapsodoReportIndexController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = DB::table('import_batches as batch')
            ->join('translation_snapshots as snapshot', 'snapshot.id', '=', 'batch.translation_snapshot_id')
            ->join('platform_definitions as platform', 'platform.id', '=', 'snapshot.platform_definition_id')
            ->join('external_sessions as session', 'session.import_batch_id', '=', 'batch.id')
            ->join('profiles as profile', 'profile.user_id', '=', 'session.player_id')
            ->join('teams as team', 'team.id', '=', 'session.team_id')
            ->where('platform.key', 'rapsodo')
            ->where('batch.status', 'completed')
            ->whereExists(fn ($events) => $events->selectRaw('1')->from('canonical_events as event')
                ->whereColumn('event.external_session_id', 'session.id')->whereColumn('event.player_id', 'session.player_id'));

        if (!app(AdministrativeAccess::class)->canManageSubscriptions($user)) {
            if ('player' === (string) $user->type) {
                $query->where('session.player_id', $user->id);
            } else {
                $query->whereIn('session.team_id', CoachTeam::query()->where('coach_id', $user->id)->select('team_id'));
            }
        }

        $query->select([
            'batch.id', 'batch.completed_at', 'batch.event_count', 'batch.metric_count',
            'session.player_id', 'session.team_id', 'profile.first_name', 'profile.last_name', 'team.name as team_name',
        ])->selectSub(function ($events): void {
            $events->from('canonical_events as report_event')
                ->join('external_sessions as report_session', 'report_session.id', '=', 'report_event.external_session_id')
                ->whereColumn('report_session.import_batch_id', 'batch.id')
                ->whereColumn('report_event.player_id', 'session.player_id')
                ->selectRaw('COUNT(*)');
        }, 'player_pitch_count');

        $rows = $query->orderByDesc('batch.completed_at')->limit(50)->get()
            ->unique(fn ($row): string => $row->id.':'.$row->player_id)->values()->map(fn ($row): array => [
            'id' => $row->id,
            'player_id' => $row->player_id,
            'player_name' => trim($row->first_name.' '.$row->last_name),
            'team_id' => $row->team_id,
            'team_name' => $row->team_name,
            'completed_at' => $row->completed_at,
            'pitch_count' => (int) $row->player_pitch_count,
            'metric_count' => (int) $row->metric_count,
            'report_path' => 'player' === (string) $user->type
                ? "/player/reports/rapsodo/{$row->id}?player_id={$row->player_id}"
                : "/data-hub/imports/{$row->id}/rapsodo-report?player_id={$row->player_id}",
        ])->all();

        return response()->json(['success' => true, 'data' => $rows]);
    }
}
