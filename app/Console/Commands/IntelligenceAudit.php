<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\ExitVelocityPractice;
use App\Models\LiveABPracticeResult;
use App\Models\LongTossPractice;
use App\Models\AthleticPerformanceScore;
use App\Models\PlayerAssessment;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\Practice;
use App\Models\PracticeLineUp;
use App\Models\Profile;
use App\Models\User;
use App\Models\WeightBallPractice;
use App\Services\Statistics\BullpenStatisticsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IntelligenceAudit extends Command
{
    protected $signature = 'intelligence:audit {teamId} {playerId} {--days=60 : Match the intelligence lookback window}';

    protected $description = 'Audit the raw data used by PlayerIntelligenceService for a team/player.';

    private array $loaded = [];

    private array $missing = [];

    public function handle(BullpenStatisticsService $bullpenStatistics): int
    {
        $teamId = (string) $this->argument('teamId');
        $playerId = (string) $this->argument('playerId');
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $this->info('INTELLIGENCE DATA AUDIT');
        $this->line('Team ID: '.$teamId);
        $this->line('Player ID: '.$playerId);
        $this->line('Date filter: created_at >= '.$since->toDateTimeString().' (last '.$days.' days)');
        $this->newLine();

        $user = User::query()->with(['profile', 'player'])->find($playerId);
        $profile = $user?->profile;
        $player = $user?->player;
        $playerTeam = PlayerTeam::query()->where('team_id', $teamId)->where('user_id', $playerId)->first();
        $assessment = PlayerAssessment::query()
            ->where('user_id', $playerId)
            ->where(fn (Builder $query) => $query->where('team_id', $teamId)->orWhereNull('team_id'))
            ->orderByDesc('assessment_date')
            ->orderByDesc('created_at')
            ->first();
        $fitness = PlayerFitness::query()
            ->where('user_id', $playerId)
            ->orderByDesc('fitness_date')
            ->orderByDesc('created_at')
            ->first();
        $athleticScore = AthleticPerformanceScore::query()
            ->where('player_id', $playerId)
            ->where(fn (Builder $query) => $query->where('team_id', $teamId)->orWhereNull('team_id'))
            ->orderByDesc('calculated_at')
            ->orderByDesc('created_at')
            ->first();

        $this->section('PLAYER');
        $this->trace('User', (new User())->getTable(), 'users.id = {playerId}', 'id,email,type,status', 'none');
        $this->trace('Profile', (new Profile())->getTable(), 'profiles.user_id = users.id', 'first_name,last_name,level,picture', 'none');
        $this->trace('PlayerTeam', (new PlayerTeam())->getTable(), 'player_teams.user_id = {playerId}; player_teams.team_id = {teamId}', 'user_id,team_id,actual', 'none');
        $this->trace('Player', 'players', 'players.user_id = users.id', 'born_date,grad_year,hit_side,throw_side,height,number', 'none');
        $this->trace('PlayerAssessment', (new PlayerAssessment())->getTable(), 'user_id = {playerId}; team_id = {teamId} OR null', 'overall_score,strength_overall_score,mobility_overall_score,hitting_score,pitching_score,arm_health_score', 'none');
        $this->trace('PlayerFitness', (new PlayerFitness())->getTable(), 'user_id = {playerId}', 'strength_score,mobility_score,recovery_score,overall_api_score,pitch_velo,body_weight,sleep_hours', 'none');
        $this->trace('AthleticPerformanceScore', (new AthleticPerformanceScore())->getTable(), 'player_id = {playerId}; team_id = {teamId} OR null', 'overall_api_score,strength_score,grade_label,projection_label,team_percentile', 'none');
        $this->kv('Found user', $this->yn($user));
        $this->kv('Found profile', $this->yn($profile));
        $this->kv('Found player', $this->yn($player));
        $this->kv('Found player_team', $this->yn($playerTeam));
        $this->kv('Found assessment', $this->yn($assessment));
        $this->kv('Found fitness', $this->yn($fitness));
        $this->kv('Found athletic score', $this->yn($athleticScore));
        $this->track('profile', $profile !== null ? 'YES' : null, 'No Profile row with user_id='.$playerId);
        $this->track('player', $player !== null ? 'YES' : null, 'No Player row with user_id='.$playerId);
        $this->track('player_team', $playerTeam !== null ? 'YES' : null, 'No PlayerTeam row with team_id='.$teamId.' and user_id='.$playerId);
        $this->track('assessment', $assessment?->id, 'No PlayerAssessment row where user_id='.$playerId.' and team_id is '.$teamId.' or null.');
        $this->track('fitness', $fitness?->id, 'No PlayerFitness row where user_id='.$playerId.'.');
        $this->track('athletic_performance_score', $athleticScore?->id, 'No AthleticPerformanceScore row where player_id='.$playerId.' and team_id is '.$teamId.' or null.');

        if ($assessment) {
            $this->kv('Assessment date', $this->fmtDate($assessment->assessment_date ?? $assessment->created_at));
            foreach ([
                'overall_score',
                'strength_overall_score',
                'mobility_overall_score',
                'hitting_score',
                'pitching_score',
                'throwing_workload_score',
                'arm_health_score',
            ] as $column) {
                $this->track('assessment.'.$column, $this->num($assessment->{$column}), 'Assessment exists, but '.$column.' is null/non-numeric.');
            }
        }

        if ($fitness) {
            $this->kv('Fitness date', $this->fmtDate($fitness->fitness_date ?? $fitness->created_at));
            foreach (['strength_score', 'mobility_score', 'recovery_score', 'overall_api_score', 'pitch_velo', 'body_weight'] as $column) {
                $this->track('fitness.'.$column, $this->num($fitness->{$column}), 'Fitness exists, but '.$column.' is null/non-numeric.');
            }
        }

        if ($athleticScore) {
            $this->kv('Athletic calculated at', $this->fmtDate($athleticScore->calculated_at ?? $athleticScore->created_at));
            foreach (['overall_api_score', 'strength_score', 'power_score', 'speed_score', 'baseball_score', 'recovery_mobility_score'] as $column) {
                $this->track('athletic.'.$column, $this->num($athleticScore->{$column}), 'Athletic score exists, but '.$column.' is null/non-numeric.');
            }
        }

        $bullpen = BullpenPracticeResult::query()
            ->where('team_id', $teamId)
            ->where('pitcher_id', $playerId)
            ->where('created_at', '>=', $since)
            ->get();
        $this->section('Bullpen');
        $this->trace('BullpenPracticeResult', (new BullpenPracticeResult())->getTable(), 'team_id = {teamId}; pitcher_id = {playerId}', 'miles_per_hour,is_strike,zone,type_throw,sort,created_at', 'created_at >= '.$since->toDateTimeString());
        $this->rowsCommon($bullpen, BullpenPracticeResult::class, $teamId, $playerId, 'pitcher_id', $since);
        $veloRows = $this->positiveRows($bullpen, 'miles_per_hour');
        $bps = $bullpen->isNotEmpty() ? $bullpenStatistics->bps($bullpen) : [];
        $this->metric('bullpen.avg_pitch_velocity', $this->avg($veloRows, 'miles_per_hour'), $bullpen, 'Rows exist, but no positive miles_per_hour values.');
        $this->metric('bullpen.max_pitch_velocity', $this->max($veloRows, 'miles_per_hour'), $bullpen, 'Rows exist, but no positive miles_per_hour values.');
        $this->metric('bullpen.strike_rate', $this->num($bps['strikeRate'] ?? null), $bullpen, 'Rows exist, but BullpenStatisticsService did not return strikeRate.');
        $this->kv('Average velocity', $this->fmt($this->avg($veloRows, 'miles_per_hour'), ' mph'));
        $this->kv('Max velocity', $this->fmt($this->max($veloRows, 'miles_per_hour'), ' mph'));
        $this->kv('Strike %', $this->fmt($this->num($bps['strikeRate'] ?? null), '%'));

        $batting = BattingPracticeResult::query()
            ->where('team_id', $teamId)
            ->where('batter_id', $playerId)
            ->where('created_at', '>=', $since)
            ->get();
        $this->section('Batting Practice');
        $this->trace('BattingPracticeResult', (new BattingPracticeResult())->getTable(), 'team_id = {teamId}; batter_id = {playerId}', 'velocity,is_contact,quality_of_contact,type_of_hit,field_direction,created_at', 'created_at >= '.$since->toDateTimeString());
        $this->rowsCommon($batting, BattingPracticeResult::class, $teamId, $playerId, 'batter_id', $since);
        $bpEvRows = $this->positiveRows($batting, 'velocity');
        $contactRows = $batting->filter(fn ($row) => $row->is_contact === true || $row->is_contact === 1);
        $this->metric('batting.avg_exit_velocity', $this->avg($bpEvRows, 'velocity'), $batting, 'Rows exist, but no positive velocity values.');
        $this->metric('batting.max_exit_velocity', $this->max($bpEvRows, 'velocity'), $batting, 'Rows exist, but no positive velocity values.');
        $this->metric('batting.contact_count', $contactRows->count() > 0 ? $contactRows->count() : null, $batting, 'Rows exist, but no row has is_contact=true.');
        $this->kv('Rows found', $batting->count());
        $this->kv('Latest date', $this->fmtDate($batting->max('created_at')));
        $this->kv('Average EV', $this->fmt($this->avg($bpEvRows, 'velocity'), ' mph'));
        $this->kv('Max EV', $this->fmt($this->max($bpEvRows, 'velocity'), ' mph'));
        $this->kv('Contact rows', $contactRows->count());

        $longToss = LongTossPractice::query()
            ->where('user_id', $playerId)
            ->where(fn (Builder $query) => $query->where('team_id', $teamId)->orWhereNull('team_id'))
            ->where('created_at', '>=', $since)
            ->get();
        $this->section('Long Toss');
        $this->trace('LongTossPractice', (new LongTossPractice())->getTable(), 'user_id = {playerId}; team_id = {teamId} OR null', 'distance,hop,created_at', 'created_at >= '.$since->toDateTimeString());
        $this->rowsCommon($longToss, LongTossPractice::class, $teamId, $playerId, 'user_id', $since);
        $distanceRows = $this->positiveRows($longToss, 'distance');
        $this->metric('long_toss.avg_distance', $this->avg($distanceRows, 'distance'), $longToss, 'Rows exist, but no positive distance values.');
        $this->metric('long_toss.max_distance', $this->max($distanceRows, 'distance'), $longToss, 'Rows exist, but no positive distance values.');
        $this->kv('Max', $this->fmt($this->max($distanceRows, 'distance'), ' ft'));
        $this->kv('Average', $this->fmt($this->avg($distanceRows, 'distance'), ' ft'));

        $weighted = WeightBallPractice::query()
            ->where('user_id', $playerId)
            ->where(fn (Builder $query) => $query->where('team_id', $teamId)->orWhereNull('team_id'))
            ->where('created_at', '>=', $since)
            ->get();
        $this->section('Weighted Balls');
        $this->trace('WeightBallPractice', (new WeightBallPractice())->getTable(), 'user_id = {playerId}; team_id = {teamId} OR null', 'weight,velocity,created_at', 'created_at >= '.$since->toDateTimeString());
        $this->rowsCommon($weighted, WeightBallPractice::class, $teamId, $playerId, 'user_id', $since);
        $weightedVeloRows = $this->positiveRows($weighted, 'velocity');
        $fiveOzRows = $weightedVeloRows->filter(fn ($row) => (float) $row->weight === 5.0);
        $this->metric('weighted_ball.avg_velocity', $this->avg($weightedVeloRows, 'velocity'), $weighted, 'Rows exist, but no positive velocity values.');
        $this->metric('weighted_ball.five_oz_max_velocity', $this->max($fiveOzRows, 'velocity'), $weighted, 'Rows exist, but no rows have weight=5 and positive velocity.');
        $this->kv('5 oz max', $this->fmt($this->max($fiveOzRows, 'velocity'), ' mph'));
        $this->kv('Average', $this->fmt($this->avg($weightedVeloRows, 'velocity'), ' mph'));

        $exitVelocity = ExitVelocityPractice::query()
            ->where('user_id', $playerId)
            ->where(fn (Builder $query) => $query->where('team_id', $teamId)->orWhereNull('team_id'))
            ->where('created_at', '>=', $since)
            ->get();
        $this->section('Exit Velocity');
        $this->trace('ExitVelocityPractice', (new ExitVelocityPractice())->getTable(), 'user_id = {playerId}; team_id = {teamId} OR null', 'velocity,trajectory,created_at', 'created_at >= '.$since->toDateTimeString());
        $this->rowsCommon($exitVelocity, ExitVelocityPractice::class, $teamId, $playerId, 'user_id', $since);
        $evRows = $this->positiveRows($exitVelocity, 'velocity');
        $this->metric('exit_velocity.avg_exit_velocity', $this->avg($evRows, 'velocity'), $exitVelocity, 'Rows exist, but no positive velocity values.');
        $this->metric('exit_velocity.max_exit_velocity', $this->max($evRows, 'velocity'), $exitVelocity, 'Rows exist, but no positive velocity values.');
        $this->kv('Average', $this->fmt($this->avg($evRows, 'velocity'), ' mph'));
        $this->kv('Max', $this->fmt($this->max($evRows, 'velocity'), ' mph'));

        $cage = CagePracticeResult::query()
            ->where('team_id', $teamId)
            ->where('user_id', $playerId)
            ->where('created_at', '>=', $since)
            ->get();
        $this->section('Cage');
        $this->trace('CagePracticeResult', (new CagePracticeResult())->getTable(), 'team_id = {teamId}; user_id = {playerId}', 'launch_angle_velocity,launch_angle,distance_travel,spray_angle,created_at', 'created_at >= '.$since->toDateTimeString());
        $this->rowsCommon($cage, CagePracticeResult::class, $teamId, $playerId, 'user_id', $since);
        $cageEvRows = $this->positiveRows($cage, 'launch_angle_velocity');
        $cageLaRows = $cage->filter(fn ($row) => $this->num($row->launch_angle) !== null);
        $cageDistanceRows = $this->positiveRows($cage, 'distance_travel');
        $this->metric('cage.avg_exit_velocity', $this->avg($cageEvRows, 'launch_angle_velocity'), $cage, 'Rows exist, but no positive launch_angle_velocity values.');
        $this->metric('cage.avg_launch_angle', $this->avg($cageLaRows, 'launch_angle'), $cage, 'Rows exist, but launch_angle is null/non-numeric.');
        $this->metric('cage.avg_distance', $this->avg($cageDistanceRows, 'distance_travel'), $cage, 'Rows exist, but no positive distance_travel values.');
        $this->kv('Average EV', $this->fmt($this->avg($cageEvRows, 'launch_angle_velocity'), ' mph'));
        $this->kv('Average LA', $this->fmt($this->avg($cageLaRows, 'launch_angle'), ' deg'));
        $this->kv('Distance', $this->fmt($this->avg($cageDistanceRows, 'distance_travel'), ' ft'));

        $liveAb = LiveABPracticeResult::query()
            ->whereHas('practice', fn (Builder $query) => $query->where('team_id', $teamId))
            ->where(function (Builder $query) use ($playerId) {
                $query->whereHas('batting', fn (Builder $q) => $q->where('batter_id', $playerId))
                    ->orWhereHas('pitching', fn (Builder $q) => $q->where('pitcher_id', $playerId));
            })
            ->where('created_at', '>=', $since)
            ->get();
        $this->section('Live AB');
        $this->trace('LiveABPracticeResult', (new LiveABPracticeResult())->getTable(), 'practice.team_id = {teamId}; batting.batter_id = {playerId} OR pitching.pitcher_id = {playerId}', 'turn_is_over,is_hit,play_result,outs_recorded,runs_scored,rbi,created_at', 'created_at >= '.$since->toDateTimeString());
        $this->kv('Rows', $liveAb->count());
        $this->kv('Latest', $this->fmtDate($liveAb->max('created_at')));
        $plateAppearances = $liveAb->where('turn_is_over', true)->count();
        $hits = $liveAb->where('is_hit', true)->count();
        $strikeouts = $liveAb->filter(fn ($row) => in_array(strtoupper((string) $row->play_result), ['K', 'SO', 'STRIKEOUT'], true))->count();
        $this->metric('liveab.plate_appearances', $plateAppearances > 0 ? $plateAppearances : null, $liveAb, 'Rows exist, but no row has turn_is_over=true.');
        $this->metric('liveab.hits', $hits > 0 ? $hits : null, $liveAb, 'Rows exist, but no row has is_hit=true.');
        $this->metric('liveab.strikeouts', $strikeouts > 0 ? $strikeouts : null, $liveAb, 'Rows exist, but no play_result is K/SO/STRIKEOUT.');
        $this->kv('Plate Appearances', $plateAppearances);
        $this->kv('Hits', $hits);
        $this->kv('Strikeouts', $strikeouts);

        $this->section('Metrics Successfully Loaded');
        foreach ($this->loaded as $line) {
            $this->line('- '.$line);
        }
        if (! $this->loaded) {
            $this->line('- none');
        }

        $this->section('Metrics Missing');
        foreach ($this->missing as $line) {
            $this->line('- '.$line);
        }
        if (! $this->missing) {
            $this->line('- none');
        }

        return self::SUCCESS;
    }

    private function rowsCommon(Collection $rows, string $modelClass, string $teamId, string $playerId, string $playerColumn, Carbon $since): void
    {
        $allTime = $modelClass::query()
            ->where($playerColumn, $playerId)
            ->when(in_array('team_id', $this->columnsForModel($modelClass), true), fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('team_id', $teamId)->orWhereNull('team_id')))
            ->count();
        $latestAllTime = $modelClass::query()
            ->where($playerColumn, $playerId)
            ->when(in_array('team_id', $this->columnsForModel($modelClass), true), fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('team_id', $teamId)->orWhereNull('team_id')))
            ->max('created_at');

        $this->kv('Rows found', $rows->count());
        $this->kv('Rows all-time for same keys', $allTime);
        $this->kv('Latest all-time for same keys', $this->fmtDate($latestAllTime));
        $this->kv('Latest date', $this->fmtDate($rows->max('created_at')));

        if ($rows->isEmpty()) {
            $reason = $allTime > 0
                ? 'Rows exist all-time, but none match created_at >= '.$since->toDateTimeString().'.'
                : 'No rows match the player/team key filters.';
            $this->missing[] = class_basename($modelClass).' rows: '.$reason;
        }
    }

    private function columnsForModel(string $modelClass): array
    {
        return (new $modelClass())->getFillable();
    }

    private function metric(string $name, mixed $value, Collection $rows, string $nullReason): void
    {
        $this->track($name, $this->num($value), $rows->isEmpty() ? 'No rows matched the assembler query/date filter.' : $nullReason);
    }

    private function track(string $name, mixed $value, string $missingReason): void
    {
        if ($value !== null && $value !== '') {
            $this->loaded[] = $name.': '.$this->stringValue($value);
            return;
        }

        $this->missing[] = $name.': '.$missingReason;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', max(8, strlen($title))));
    }

    private function trace(string $model, string $table, string $joinKey, string $columns, string $dateFilter): void
    {
        $this->line('Source: '.$model);
        $this->line('Table: '.$table);
        $this->line('Join/key filter: '.$joinKey);
        $this->line('Columns used: '.$columns);
        $this->line('Date filter: '.$dateFilter);
    }

    private function kv(string $key, mixed $value): void
    {
        $this->line($key.': '.$this->stringValue($value));
    }

    private function positiveRows(Collection $rows, string $column): Collection
    {
        return $rows->filter(fn ($row) => ($this->num($row->{$column} ?? null) ?? 0) > 0);
    }

    private function avg(Collection $rows, string $column): ?float
    {
        $values = $rows->map(fn ($row) => $this->num($row->{$column} ?? null))->filter(fn ($value) => $value !== null);

        return $values->isNotEmpty() ? round((float) $values->avg(), 1) : null;
    }

    private function max(Collection $rows, string $column): ?float
    {
        $values = $rows->map(fn ($row) => $this->num($row->{$column} ?? null))->filter(fn ($value) => $value !== null);

        return $values->isNotEmpty() ? round((float) $values->max(), 1) : null;
    }

    private function num(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function fmt(?float $value, string $suffix = ''): string
    {
        return $value === null ? '—' : rtrim(rtrim((string) $value, '0'), '.').$suffix;
    }

    private function fmtDate(mixed $value): string
    {
        if (! $value) {
            return '—';
        }

        try {
            return Carbon::parse((string) $value)->toDateTimeString();
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function yn(mixed $value): string
    {
        return $value ? 'YES' : 'NO';
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        if (is_array($value)) {
            return json_encode($value) ?: '[]';
        }

        return (string) $value;
    }
}
