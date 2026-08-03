<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DataHub;

use App\Http\Controllers\Controller;
use App\Models\CoachTeam;
use App\Models\User;
use App\Services\Access\AdministrativeAccess;
use App\Services\DataHub\Persistence\BlastImportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

final class BlastImportController extends Controller
{
    public function store(Request $request, BlastImportService $imports): JsonResponse
    {
        $maxKb = (int) ceil(((int) config('data_hub.max_file_size_bytes')) / 1024);
        $data = $request->validate([
            'platform' => ['required', Rule::in(['blast-motion'])],
            'team_id' => ['required', 'uuid', 'exists:teams,id'],
            'player_id' => ['required', 'uuid', 'exists:users,id'],
            'destination' => ['required', Rule::in(['batting_practice', 'cage', 'assessment'])],
            'template_fingerprint' => ['required', 'string', 'size:64'],
            'file' => ['required', 'file', 'mimes:csv,txt', "max:{$maxKb}"],
        ]);
        $this->authorizeTeam($request, $data['team_id']);
        abort_unless(DB::table('player_teams')->where('team_id', $data['team_id'])->where('user_id', $data['player_id'])
            ->where('actual', true)->whereNull('deleted_at')->exists(), 422, 'Choose a current player from this team.');

        try {
            $result = $imports->import($request->user(), $data['team_id'], $data['player_id'], $data['destination'], $data['template_fingerprint'], $request->file('file'));
            return response()->json(['success' => true, 'message' => 'Blast data imported successfully.', 'data' => $result], 201);
        } catch (RuntimeException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], str_contains($exception->getMessage(), 'already been imported') ? 409 : 422);
        } catch (ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'The approved Blast mapping is unavailable. Inspect and approve the file again.'], 422);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate(['team_id' => ['required', 'uuid', 'exists:teams,id']]);
        $this->authorizeTeam($request, $data['team_id']);
        $rows = DB::table('import_batches as b')->join('translation_snapshots as s', 's.id', '=', 'b.translation_snapshot_id')
            ->join('platform_definitions as p', 'p.id', '=', 's.platform_definition_id')->join('profiles as profile', 'profile.user_id', '=', 's.player_id')
            ->where('s.team_id', $data['team_id'])->orderByDesc('b.created_at')->limit(50)
            ->get(['b.id', 'b.status', 'b.session_count', 'b.event_count', 'b.metric_count', 'b.completed_at', 's.source_file_name', 's.player_id', 'profile.first_name', 'profile.last_name', 'p.name as platform']);
        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function playerMetrics(Request $request, User $player): JsonResponse
    {
        abort_unless($request->user()->id === $player->id || app(AdministrativeAccess::class)->canManageSubscriptions($request->user())
            || CoachTeam::query()->where('coach_id', $request->user()->id)->whereIn('team_id', function ($query) use ($player): void {
                $query->select('team_id')->from('player_teams')->where('user_id', $player->id)->where('actual', true)->whereNull('deleted_at');
            })->exists(), 403);
        $metrics = DB::table('canonical_metrics as m')->join('canonical_events as e', 'e.id', '=', 'm.canonical_event_id')
            ->join('external_sessions as s', 's.id', '=', 'e.external_session_id')->join('baseball_concepts as c', 'c.id', '=', 'm.baseball_concept_id')
            ->join('import_batches as b', 'b.id', '=', 's.import_batch_id')->where('e.player_id', $player->id)->where('b.status', 'completed')
            ->orderByDesc('e.occurred_at')->orderByDesc('e.created_at')->limit(500)
            ->get(['e.id as event_id', 'e.occurred_at', 'e.event_order', 'c.canonical_key', 'c.display_name', 'm.value', 'm.numeric_value', 'm.canonical_unit_key', 'm.measurement_classification']);
        return response()->json(['success' => true, 'data' => $metrics]);
    }

    private function authorizeTeam(Request $request, string $teamId): void
    {
        abort_unless(app(AdministrativeAccess::class)->canManageSubscriptions($request->user())
            || CoachTeam::query()->where('coach_id', $request->user()->id)->where('team_id', $teamId)->exists(), 403);
    }
}
