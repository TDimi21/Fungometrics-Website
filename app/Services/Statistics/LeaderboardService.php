<?php

declare(strict_types=1);

namespace App\Services\Statistics;

use App\Models\BattingPracticeResult;
use App\Models\BullpenPracticeResult;
use App\Models\CagePracticeResult;
use App\Models\ExitVelocityPractice;
use App\Models\LongTossPractice;
use App\Models\PlayerFitness;
use App\Models\PlayerTeam;
use App\Models\User;
use Carbon\Carbon;

/**
 * LeaderboardService — the "Hall of Fame Wall" data source.
 *
 * Returns every leaderboard CATEGORY for a team in ONE shape the rotating wall
 * component consumes directly: a Top-10 board + the #1 featured athlete with bio
 * and per-category sub-metrics. Trend + sparkline fields are present as
 * placeholders (null) for now so the UI can render every slot; they can be filled
 * incrementally from per-player history without changing the contract.
 */
class LeaderboardService
{
    /**
     * Category catalog. `metric` is the per-player key ranked (desc); `sub` are the
     * featured athlete's tiles [label, player-metric key, unit]. `placeholder`
     * categories have no reliable per-player source yet and render as a coming-soon
     * slot on the wall.
     */
    private const CATEGORIES = [
        ['key' => 'hitter',     'label' => 'Hitters',              'color' => '#ef4444', 'metric' => 'max_ev',        'unit' => 'mph', 'bigLabel' => 'Max Exit Velocity', 'sub' => [['Exit Velo', 'exit_velo', 'mph'], ['Bat Speed', 'bat_speed', 'mph'], ['Avg EV', 'avg_ev', 'mph'], ['Throw Velo', 'throwing_velo', 'mph']]],
        ['key' => 'pitcher',    'label' => 'Pitchers',             'color' => '#3b82f6', 'metric' => 'max_fb',        'unit' => 'mph', 'bigLabel' => 'Top Fastball',      'sub' => [['Avg FB', 'avg_fb', 'mph'], ['Pitch Velo', 'pitch_velo', 'mph'], ['Throw Velo', 'throwing_velo', 'mph']]],
        ['key' => 'avg_ev',     'label' => 'Avg Exit Velocity',    'color' => '#22c55e', 'metric' => 'avg_ev',        'unit' => 'mph', 'bigLabel' => 'Avg Exit Velocity', 'sub' => [['Max EV', 'max_ev', 'mph'], ['Bat Speed', 'bat_speed', 'mph']]],
        ['key' => 'max_ev',     'label' => 'Max Exit Velocity',    'color' => '#a855f7', 'metric' => 'max_ev',        'unit' => 'mph', 'bigLabel' => 'Max Exit Velocity', 'sub' => [['Avg EV', 'avg_ev', 'mph'], ['Bat Speed', 'bat_speed', 'mph']]],
        ['key' => 'avg_fb',     'label' => 'Avg Fastball Velocity','color' => '#38bdf8', 'metric' => 'avg_fb',        'unit' => 'mph', 'bigLabel' => 'Avg Fastball',      'sub' => [['Top FB', 'max_fb', 'mph'], ['Pitch Velo', 'pitch_velo', 'mph']]],
        ['key' => 'max_fb',     'label' => 'Max Fastball Velocity','color' => '#818cf8', 'metric' => 'max_fb',        'unit' => 'mph', 'bigLabel' => 'Top Fastball',      'sub' => [['Avg FB', 'avg_fb', 'mph'], ['Pitch Velo', 'pitch_velo', 'mph']]],
        ['key' => 'bullpen',    'label' => 'Bullpen Score',        'color' => '#6366f1', 'metric' => null,            'unit' => '',    'bigLabel' => 'Bullpen Score',     'sub' => [], 'placeholder' => true],
        ['key' => 'cage',       'label' => 'Cage Score',           'color' => '#14b8a6', 'metric' => 'cage_max_ev',   'unit' => 'mph', 'bigLabel' => 'Cage Max EV',       'sub' => [['Exit Velo', 'exit_velo', 'mph'], ['Bat Speed', 'bat_speed', 'mph']]],
        ['key' => 'long_toss',  'label' => 'Long Toss',            'color' => '#eab308', 'metric' => 'long_toss_max', 'unit' => 'ft',  'bigLabel' => 'Longest Throw',     'sub' => [['Avg Dist', 'long_toss_avg', 'ft'], ['Throw Velo', 'throwing_velo', 'mph']]],
        ['key' => 'strength',   'label' => 'Strength Score',       'color' => '#06b6d4', 'metric' => 'strength_score','unit' => '',    'bigLabel' => 'Strength Score',    'sub' => [['Bench', 'bench_press', 'lb'], ['F. Squat', 'front_squat', 'lb'], ['Deadlift', 'dead_lift', 'lb'], ['Pwr Clean', 'power_clean', 'lb'], ['Grip', 'hand_strength', 'lb'], ['Body Wt', 'body_weight', 'lb']]],
        ['key' => 'mobility',   'label' => 'Mobility Score',       'color' => '#a78bfa', 'metric' => 'mobility_score','unit' => '',    'bigLabel' => 'Mobility Score',    'sub' => []],
        ['key' => 'recovery',   'label' => 'Recovery Score',       'color' => '#fb923c', 'metric' => 'recovery_score','unit' => '',    'bigLabel' => 'Recovery Score',    'sub' => [['Sleep', 'sleep_hours', 'hrs']]],
    ];

    public function forTeam(string $teamId): array
    {
        $playerIds = PlayerTeam::where('team_id', $teamId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();

        if (empty($playerIds)) {
            return ['categories' => $this->emptyCategories()];
        }

        $players = $this->buildPlayers($teamId, $playerIds);

        $categories = array_map(fn (array $cat) => $this->buildCategory($cat, $players), self::CATEGORIES);

        return ['categories' => $categories];
    }

    /** Per-player enriched record keyed by user id. */
    private function buildPlayers(string $teamId, array $ids): array
    {
        $users = User::whereIn('id', $ids)->with(['profile', 'player'])->get();

        $fitness = PlayerFitness::whereIn('user_id', $ids)
            ->orderByDesc('fitness_date')->orderByDesc('created_at')
            ->get()->unique('user_id')->keyBy('user_id');

        // Exit velocity — combine batting swings + EV-training results per player.
        $bat = BattingPracticeResult::whereIn('batter_id', $ids)->where('velocity', '>', 0)
            ->selectRaw('batter_id, COUNT(*) c, SUM(velocity) s, MAX(velocity) m')->groupBy('batter_id')->get()->keyBy('batter_id');
        $ev = ExitVelocityPractice::whereIn('user_id', $ids)->where('velocity', '>', 0)
            ->selectRaw('user_id, COUNT(*) c, SUM(velocity) s, MAX(velocity) m')->groupBy('user_id')->get()->keyBy('user_id');

        // Fastball — bullpen velocities per pitcher.
        $fb = BullpenPracticeResult::whereIn('pitcher_id', $ids)->where('miles_per_hour', '>', 0)
            ->selectRaw('pitcher_id, AVG(miles_per_hour) avg_v, MAX(miles_per_hour) max_v')->groupBy('pitcher_id')->get()->keyBy('pitcher_id');

        // Cage exit velocity.
        $cage = CagePracticeResult::whereIn('user_id', $ids)->where('launch_angle_velocity', '>', 0)
            ->selectRaw('user_id, MAX(launch_angle_velocity) max_v')->groupBy('user_id')->get()->keyBy('user_id');

        // Long toss distance.
        $lt = LongTossPractice::whereIn('user_id', $ids)->where('distance', '>', 0)
            ->selectRaw('user_id, MAX(distance) max_d, AVG(distance) avg_d')->groupBy('user_id')->get()->keyBy('user_id');

        $num = static fn ($v) => is_numeric($v) && (float) $v > 0 ? round((float) $v, 1) : null;
        $out = [];

        foreach ($users as $user) {
            $id = (string) $user->id;
            $p = $user->player;
            $f = $fitness->get($id);

            $batRow = $bat->get($id);
            $evRow = $ev->get($id);
            $count = (int) (($batRow->c ?? 0) + ($evRow->c ?? 0));
            $sum = (float) (($batRow->s ?? 0) + ($evRow->s ?? 0));
            $maxEv = max((float) ($batRow->m ?? 0), (float) ($evRow->m ?? 0));

            $born = $p?->born_date;
            $age = null;
            if ($born) {
                try { $a = Carbon::parse((string) $born)->age; $age = $a > 0 && $a < 80 ? $a : null; } catch (\Throwable $e) {}
            }

            $out[$id] = [
                'id'    => $id,
                'name'  => trim(($user->profile?->first_name ?? '') . ' ' . ($user->profile?->last_name ?? '')) ?: 'Player',
                'avatar' => $user->profile?->picture,
                'level'  => $user->profile?->level,
                'age'    => $age,
                'throws' => $p?->throw_side ? strtoupper(substr((string) $p->throw_side, 0, 1)) : null,
                'bats'   => $p?->hit_side ? strtoupper(substr((string) $p->hit_side, 0, 1)) : null,
                'height' => $p && $p->height_in_ft ? ($p->height_in_ft . "'" . ($p->height_in_inch ?? 0) . '"') : null,
                'metrics' => [
                    'max_ev'         => $num($maxEv),
                    'avg_ev'         => $count > 0 ? round($sum / $count, 1) : null,
                    'max_fb'         => $num($fb->get($id)->max_v ?? null),
                    'avg_fb'         => $num($fb->get($id)->avg_v ?? null),
                    'cage_max_ev'    => $num($cage->get($id)->max_v ?? null),
                    'long_toss_max'  => $num($lt->get($id)->max_d ?? null),
                    'long_toss_avg'  => $num($lt->get($id)->avg_d ?? null),
                    'strength_score' => $num($f?->strength_score),
                    'mobility_score' => $num($f?->mobility_score),
                    'recovery_score' => $num($f?->recovery_score),
                    'exit_velo'      => $num($f?->exit_velo),
                    'bat_speed'      => $num($f?->bat_speed),
                    'throwing_velo'  => $num($f?->throwing_velo),
                    'pitch_velo'     => $num($f?->pitch_velo),
                    'bench_press'    => $num($f?->bench_press),
                    'front_squat'    => $num($f?->front_squat),
                    'dead_lift'      => $num($f?->dead_lift),
                    'power_clean'    => $num($f?->power_clean),
                    'hand_strength'  => $num($f?->hand_strength),
                    'body_weight'    => $num($f?->body_weight),
                    'sleep_hours'    => $num($f?->sleep_hours),
                ],
            ];
        }

        return $out;
    }

    private function buildCategory(array $cat, array $players): array
    {
        $base = [
            'key' => $cat['key'], 'label' => $cat['label'], 'subtitle' => 'Based on ' . $cat['label'],
            'color' => $cat['color'], 'unit' => $cat['unit'], 'bigLabel' => $cat['bigLabel'],
            'placeholder' => $cat['placeholder'] ?? false, 'rows' => [], 'featured' => null,
        ];

        if (empty($cat['metric'])) {
            return $base; // placeholder category — no source yet
        }

        $ranked = array_values(array_filter($players, fn ($p) => $p['metrics'][$cat['metric']] !== null));
        usort($ranked, fn ($a, $b) => ($b['metrics'][$cat['metric']] <=> $a['metrics'][$cat['metric']]));
        $top = array_slice($ranked, 0, 10);

        $base['rows'] = array_map(function (array $p) use ($cat) {
            return [
                'player_id' => $p['id'],
                'name'      => $p['name'],
                'avatar'    => $p['avatar'],
                'subtitle'  => $this->subtitle($p),
                'value'     => $p['metrics'][$cat['metric']],
                'trend'     => null,  // placeholder — fill from per-player history later
                'spark'     => null,  // placeholder — last-N sparkline
            ];
        }, $top);

        if (! empty($top)) {
            $lead = $top[0];
            // Bio + sub-metric slots are ALWAYS present (null where unrecorded) so the
            // card renders every placeholder in the design.
            $base['featured'] = [
                'name' => $lead['name'], 'avatar' => $lead['avatar'], 'subtitle' => $this->subtitle($lead),
                'bigValue' => $lead['metrics'][$cat['metric']],
                'trend' => null, 'spark' => null,
                'bio' => [
                    ['k' => 'Throws', 'v' => $lead['throws']],
                    ['k' => 'Bats', 'v' => $lead['bats']],
                    ['k' => 'Ht', 'v' => $lead['height']],
                    ['k' => 'Wt', 'v' => $lead['metrics']['body_weight'] ? (int) round($lead['metrics']['body_weight']) . ' lb' : null],
                ],
                'subMetrics' => array_map(fn (array $s) => ['label' => $s[0], 'value' => $lead['metrics'][$s[1]] ?? null, 'unit' => $s[2]], $cat['sub']),
            ];
        }

        return $base;
    }

    private function subtitle(array $p): string
    {
        $parts = [];
        if ($p['throws']) {
            $parts[] = $p['throws'] . 'HP';
        }
        if ($p['level']) {
            $parts[] = $p['level'];
        } elseif ($p['age']) {
            $parts[] = $p['age'] . ' yrs';
        }
        return implode(' • ', $parts);
    }

    private function emptyCategories(): array
    {
        return array_map(fn (array $cat) => [
            'key' => $cat['key'], 'label' => $cat['label'], 'subtitle' => 'Based on ' . $cat['label'],
            'color' => $cat['color'], 'unit' => $cat['unit'], 'bigLabel' => $cat['bigLabel'],
            'placeholder' => $cat['placeholder'] ?? false, 'rows' => [], 'featured' => null,
        ], self::CATEGORIES);
    }
}
