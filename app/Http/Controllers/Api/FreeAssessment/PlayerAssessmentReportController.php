<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\FreeAssessment;

use App\Http\Controllers\Controller;
use App\Models\{FreeAssessment, FreeAssessmentResult, User};
use App\Services\FreeAssessment\{AssessmentAccess, AssessmentReportService, Stations};
use Illuminate\Http\Request;

class PlayerAssessmentReportController extends Controller
{
    public function __construct(private AssessmentAccess $access, private AssessmentReportService $reports)
    {
    }

    public function index(Request $request)
    {
        $data = $request->validate(['player_id' => 'nullable|uuid', 'team_id' => 'nullable|uuid']);
        $user = $request->user();
        $query = FreeAssessmentResult::query()->selectRaw('assessment_id, player_id, COUNT(*) as completed_stations')
            ->groupBy('assessment_id', 'player_id')->with(['assessment', 'player.profile']);
        if ('player' === $user->type) {
            abort_if(isset($data['player_id']) && $data['player_id'] !== $user->id, 403);
            $query->where('player_id', $user->id);
        } else {
            $ids = $this->access->teamIds($user);
            if ( ! $this->access->admin($user)) {
                $query->whereHas('assessment', fn ($q) => $q->whereIn('team_id', $ids));
            }
            if (isset($data['player_id'])) {
                $query->where('player_id', $data['player_id']);
            }
        }
        if (isset($data['team_id'])) {
            if ('player' !== $user->type) {
                $this->access->team($user, $data['team_id']);
            }
            $query->whereHas('assessment', fn ($q) => $q->where('team_id', $data['team_id']));
        }
        $rows = $query->get()->filter(fn ($r) => $r->assessment && $r->player)->map(fn ($r) => [
            'id' => $r->assessment_id.':'.$r->player_id, 'kind' => 'free_assessment',
            'assessment_id' => $r->assessment_id, 'player_id' => $r->player_id,
            'assessment_date' => $r->assessment->assessment_date->toDateString(),
            'name' => $r->assessment->name, 'location' => $r->assessment->location,
            'status' => $r->assessment->status, 'completed_stations' => (int) $r->completed_stations,
            'total_stations' => count(Stations::all()),
            'profile' => ['first_name' => $r->player->profile?->first_name, 'last_name' => $r->player->profile?->last_name],
        ])->sortByDesc('assessment_date')->values();
        return response()->json(['data' => $rows])->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, FreeAssessment $assessment, User $player)
    {
        if ('player' === $request->user()->type) {
            abort_unless($request->user()->id === $player->id, 404);
        } else {
            $this->access->event($request->user(), $assessment);
        }
        abort_unless($assessment->participants()->where('users.id', $player->id)->exists(), 404);
        $results = collect($this->reports->rankings($assessment, $player->id))->values();
        abort_if($results->isEmpty(), 404, 'No saved assessment results for this player.');
        $player->load(['profile', 'player', 'fitness', 'positions']);
        // History uses the same athlete and team boundary as this report.
        $history = FreeAssessment::where('team_id', $assessment->team_id)
            ->where('assessment_date', '<', $assessment->assessment_date)
            ->whereHas('results', fn ($q) => $q->where('player_id', $player->id))
            ->with(['results' => fn ($q) => $q->where('player_id', $player->id)])
            ->orderByDesc('assessment_date')->orderByDesc('created_at')->limit(12)->get()
            ->map(fn ($event) => [
                'assessment_id' => $event->id, 'name' => $event->name,
                'assessment_date' => $event->assessment_date->toDateString(),
                'results' => $event->results->map(fn ($r) => [
                    'station' => $r->station, 'summary' => $r->summary, 'protocol' => $r->protocol,
                ])->values(),
            ])->values();
        return response()->json(['data' => [
            'assessment' => ['id' => $assessment->id, 'name' => $assessment->name, 'location' => $assessment->location, 'assessment_date' => $assessment->assessment_date->toDateString(), 'status' => $assessment->status],
            'player' => $this->reports->player($player, $assessment->assessment_date->toDateString()) + ['picture' => $player->profile?->picture],
            'history' => $history,
            'overall_score' => null,
            'overall_score_note' => 'An overall score is not available for this assessment protocol.',
            'stations' => Stations::all(), 'results' => $results,
            'completed_stations' => $results->count(), 'total_stations' => count(Stations::all()),
        ]])->header('Cache-Control', 'no-store');
    }
}
