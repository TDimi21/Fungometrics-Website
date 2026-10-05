<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\FreeAssessment;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Coach\CoachUtils;
use App\Models\{FreeAssessment, Player, PlayerFitness, PlayerPosition, PlayerTeam, Team, User};
use App\Services\FreeAssessment\{AssessmentAccess, AssessmentReportService, AssessmentScoringService};
use App\Services\Security\AccountClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FreeAssessmentController extends Controller
{
    public function __construct(private AssessmentAccess $access, private AssessmentReportService $reports)
    {
    }
    public function index(Request $request)
    {
        $ids = $this->access->teamIds($request->user());
        $admin = $this->access->admin($request->user());
        return response()->json(['events' => FreeAssessment::when( ! $admin, fn ($q) => $q->whereIn('team_id', $ids))->orderByDesc('assessment_date')->get(), 'teams' => Team::when( ! $admin, fn ($q) => $q->whereIn('id', $ids))->get(['id', 'name'])]);
    }
    public function store(Request $request)
    {
        $data = $request->validate(['team_id' => 'required|uuid|exists:teams,id', 'name' => 'required|string|max:150', 'location' => 'required|string|max:150', 'assessment_date' => 'required|date_format:Y-m-d']);
        $this->access->team($request->user(), $data['team_id']);
        return response()->json(FreeAssessment::create($data + ['created_by' => $request->user()->id]), 201);
    }
    public function show(Request $request, FreeAssessment $assessment)
    {
        $this->access->event($request->user(), $assessment);
        return response()->json($this->reports->snapshot($assessment) + ['can_export_phone' => $this->access->admin($request->user())]);
    }
    public function status(Request $request, FreeAssessment $assessment)
    {
        $this->access->event($request->user(), $assessment);
        $data = $request->validate(['status' => 'required|in:active,completed']);
        DB::transaction(function () use ($assessment, $data): void {
            FreeAssessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail()->update($data);
        });
        return response()->json(['status' => $data['status']]);
    }
    public function candidates(Request $request, FreeAssessment $assessment)
    {
        $this->access->event($request->user(), $assessment);
        $query = $request->validate(['q' => 'nullable|string|max:100'])['q'] ?? '';
        $ids = PlayerTeam::where('team_id', $assessment->team_id)->pluck('user_id');
        $players = User::where('type', 'player')->whereIn('id', $ids)->with(['profile', 'player', 'fitness', 'positions'])
            ->whereHas('profile', fn ($q) => $q->where('first_name', 'like', '%'.$query.'%')->orWhere('last_name', 'like', '%'.$query.'%'))->limit(50)->get();
        return response()->json($players->map(fn ($p) => $this->reports->player($p)));
    }
    public function enroll(Request $request, FreeAssessment $assessment)
    {
        $this->access->event($request->user(), $assessment);
        $data = $request->validate(['player_id' => 'required|uuid']);
        abort_unless(PlayerTeam::where('team_id', $assessment->team_id)->where('user_id', $data['player_id'])->exists() && User::whereKey($data['player_id'])->where('type', 'player')->exists(), 404);
        $this->enrollPlayer($assessment, $data['player_id']);
        return response()->json(['player_id' => $data['player_id']]);
    }
    private function enrollPlayer(FreeAssessment $event, string $playerId): void
    {
        DB::transaction(function () use ($event, $playerId): void {
            $event = FreeAssessment::whereKey($event->id)->sharedLock()->firstOrFail();
            abort_if('completed' === $event->status, 409, 'Reopen the assessment before adding players.');
            DB::table('free_assessment_players')->insertOrIgnore(['id' => (string) Str::uuid(), 'assessment_id' => $event->id, 'player_id' => $playerId, 'created_at' => now(), 'updated_at' => now()]);
        });
    }
    public function quickAdd(Request $request, FreeAssessment $assessment)
    {
        $this->access->event($request->user(), $assessment);
        $data = $request->validate([
            'first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100',
            'phone' => 'required|string|max:30', 'born_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'grad_year' => 'required|integer|min:1950|max:2100', 'height_in_ft' => 'required|integer|min:2|max:8', 'height_in_inch' => 'required|integer|min:0|max:11',
            'weight' => 'required|numeric|min:20|max:600', 'position' => 'nullable|in:P,C,1B,2B,3B,SS,LF,CF,RF,OF,IF,DH', 'hit_side' => 'nullable|in:R,L,S', 'throw_side' => 'nullable|in:R,L',
        ]);
        $phone = preg_replace('/\D/', '', $data['phone']);
        abort_unless(mb_strlen($phone) >= 10 && mb_strlen($phone) <= 15, 422, 'Enter a valid phone number including area code.');
        return DB::transaction(function () use ($assessment, $data, $phone) {
            // Serialize quick-add for this team; the users.phone unique constraint
            // also protects simultaneous creation from different teams.
            Team::whereKey($assessment->team_id)->lockForUpdate()->firstOrFail();
            $existing = User::withTrashed()->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '') = ?", [$phone])->exists();
            abort_if($existing, 409, 'That phone is already associated with an account. Select the existing roster player or resolve the shared contact before creating another account.');
            $created = CoachUtils::saveNewUser(['phone' => $phone, 'name' => ['first' => $data['first_name'], 'last' => $data['last_name']], 'team' => $assessment->team_id, 'player' => ['grad_year' => $data['grad_year']]]);
            $id = $created['user']->id;
            Player::updateOrCreate(['user_id' => $id], collect($data)->only(['born_date', 'grad_year', 'height_in_ft', 'height_in_inch', 'hit_side', 'throw_side'])->all());
            PlayerFitness::create(['user_id' => $id, 'fitness_date' => $assessment->assessment_date, 'body_weight' => $data['weight']]);
            if ( ! empty($data['position'])) {
                PlayerPosition::create(['player_id' => $id, 'position' => $data['position']]);
            }
            $this->enrollPlayer($assessment, $id);
            return response()->json(['player_id' => $id], 201);
        });
    }
    public function save(Request $request, FreeAssessment $assessment, string $player, string $station)
    {
        $this->access->event($request->user(), $assessment);
        return response()->json(app(AssessmentScoringService::class)->save($assessment, $request->user(), $player, $station, $request->all()));
    }
    public function rankings(Request $request, FreeAssessment $assessment)
    {
        $this->access->event($request->user(), $assessment);
        return response()->json($this->reports->rankings($assessment));
    }
    public function export(Request $request, FreeAssessment $assessment)
    {
        $this->access->event($request->user(), $assessment);
        $phone = $request->boolean('include_phone');
        abort_if($phone && ! $this->access->admin($request->user()), 403, 'Phone export requires administrative access.');
        return response($this->reports->csv($assessment, $phone), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="free-assessment-'.$assessment->id.'.csv"', 'Cache-Control' => 'no-store']);
    }
    public function teamReport(Request $request, FreeAssessment $assessment)
    {
        $this->access->event($request->user(), $assessment);
        $service = app(\App\Services\FreeAssessment\TeamAssessmentReportService::class);
        $report = $service->build($assessment);
        if ($request->query('format') === 'csv') {
            return response($service->csv($report), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="team-assessment-'.$assessment->id.'.csv"', 'Cache-Control' => 'no-store']);
        }
        return response()->json($report)->header('Cache-Control', 'no-store');
    }
    public function claim(Request $request, FreeAssessment $assessment, string $player)
    {
        $this->access->event($request->user(), $assessment);
        abort_unless($assessment->participants()->where('users.id', $player)->exists(), 404);
        return response()->json(['code' => app(AccountClaimService::class)->issue(User::findOrFail($player))])->header('Cache-Control', 'no-store');
    }
}
