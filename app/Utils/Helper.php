<?php

declare(strict_types=1);

namespace App\Utils;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

final class Helper
{
    public static function caseDivide($value1, $value2): float|int
    {

        return 0 === $value2 ? 0 : $value1 / $value2;

    }

    /**
     * @param  int  $range
     * @param  $data
     * @return mixed
     */
    public static function range(int $range, $data)
    {
        $dateCarbon = Carbon::now();
        $subDate1 = $dateCarbon->format('Y-m-d');
        $subSet = $data;

        if (12 === $range) {
            $subDate2 = $dateCarbon->subYear()->format('Y-m-d');
            $subSet = $data->whereDate('created_at', '>=', $subDate2)
                ->whereDate('created_at', '<=', $subDate1);
        }

        if (6 === $range) {
            $subDate2 = $dateCarbon->subMonths(6)->format('Y-m-d');
            $subSet = $data->whereDate('created_at', '>=', $subDate2)
                ->whereDate('created_at', '<=', $subDate1);
        }

        if (3 === $range) {
            $subDate2 = $dateCarbon->subMonths(3)->format('Y-m-d');
            $subSet = $data->whereDate('created_at', '>=', $subDate2)
                ->whereDate('created_at', '<=', $subDate1);
        }

        if(1 === $range) {
            $subDate2 = $dateCarbon->subMonths(1)->format('Y-m-d');
            $subSet = $data->whereDate('created_at', '>=', $subDate2)
                ->whereDate('created_at', '<=', $subDate1);
        }


        return $subSet->get();
    }

    /**
     * @param  Collection|array  $result
     * @return Collection|\Illuminate\Support\Collection
     */
    public static function getSets(
        Collection|array $result
    ): \Illuminate\Support\Collection|Collection {
        $groups = $result->groupBy('user_id');
        $sets = $groups->map(function ($group) {
            $setMAx = $group->max('set');
            $countBallsxSet = $group->where('set', '=', $setMAx)->count();
            $groupCount = $group->count();
            return [
                'set' => $setMAx,
                'bxs' => $countBallsxSet,
                'balls' => $groupCount
            ];
        });
        return $sets;
    }

    /**
     * Per-weight velocity aggregates for a weighted-ball session — the single
     * source of truth the app and web both render.
     *
     * Replicates the canonical web rule (NewStatsSessionView.vue):
     *  - weight  = weight ?? ball_weight ?? weight_oz ?? oz (must be finite)
     *  - velocity= velocity ?? miles_per_hour ?? exit_velocity
     *              ?? launch_angle_velocity ?? weighted_velocity
     *              (dropped only when null/''/non-numeric — 0 is kept)
     *  - bucket by weight (dynamic), avg/top to 1 decimal (string, like
     *    toFixed(1)), throws = count, sorted by weight ascending.
     *
     * @param  Collection|array  $rows
     * @return array{velocity_by_weight: array, velocity_by_weight_by_player: array, team_max_velo: ?string}
     */
    public static function weightBallVelocityByWeight(Collection|array $rows): array
    {
        $collection = ($rows instanceof Collection || $rows instanceof \Illuminate\Support\Collection)
            ? $rows
            : collect($rows);

        $buckets = [];   // weightKey => [velocities]
        $allVelos = [];
        $players = [];   // user_id => ['name'=>, 'buckets'=>[], 'all'=>[]]

        foreach ($collection as $row) {
            $weight = self::wbWeightNumber($row);
            $velo = self::wbVelocityNumber($row);
            if (null === $weight || null === $velo) {
                continue;
            }
            $wKey = self::wbWeightKey($weight);

            $buckets[$wKey][] = $velo;
            $allVelos[] = $velo;

            $uid = (string) ($row->user_id ?? '');
            if ('' === $uid) {
                continue;
            }
            if (! isset($players[$uid])) {
                $players[$uid] = [
                    'user_id' => $uid,
                    'name' => self::wbPlayerName($row),
                    'buckets' => [],
                    'all' => [],
                ];
            }
            $players[$uid]['buckets'][$wKey][] = $velo;
            $players[$uid]['all'][] = $velo;
        }

        $byPlayer = [];
        foreach ($players as $p) {
            $byPlayer[] = [
                'user_id' => $p['user_id'],
                'name' => $p['name'],
                'rows' => self::wbBucketsToRows($p['buckets']),
                'max_velo' => count($p['all']) ? self::wbFmt((float) max($p['all'])) : null,
            ];
        }

        return [
            'velocity_by_weight' => self::wbBucketsToRows($buckets),
            'velocity_by_weight_by_player' => $byPlayer,
            'team_max_velo' => count($allVelos) ? self::wbFmt((float) max($allVelos)) : null,
        ];
    }

    private static function wbWeightNumber($row): ?float
    {
        $raw = $row->weight ?? $row->ball_weight ?? $row->weight_oz ?? $row->oz ?? null;
        if (null === $raw || '' === $raw || ! is_numeric($raw)) {
            return null;
        }
        return (float) $raw;
    }

    private static function wbVelocityNumber($row): ?float
    {
        $raw = $row->velocity
            ?? $row->miles_per_hour
            ?? $row->exit_velocity
            ?? $row->launch_angle_velocity
            ?? $row->weighted_velocity
            ?? null;
        if (null === $raw || '' === $raw || ! is_numeric($raw)) {
            return null;
        }
        return (float) $raw;
    }

    // Match JS String(Number(weight)): 5.0 -> "5", 5.5 -> "5.5".
    private static function wbWeightKey(float $weight): string
    {
        return (floor($weight) === $weight)
            ? (string) (int) $weight
            : (string) $weight;
    }

    // 1-decimal string, matching JS Number.toFixed(1).
    private static function wbFmt(float $v): string
    {
        return number_format(round($v, 1), 1, '.', '');
    }

    private static function wbBucketsToRows(array $buckets): array
    {
        $keys = array_keys($buckets);
        usort($keys, fn ($a, $b) => (float) $a <=> (float) $b);

        $rows = [];
        foreach ($keys as $wKey) {
            $velos = $buckets[$wKey];
            $count = count($velos);
            if (0 === $count) {
                continue;
            }
            $rows[] = [
                'weight' => (string) $wKey,
                'top_velo' => self::wbFmt((float) max($velos)),
                'avg_velo' => self::wbFmt(array_sum($velos) / $count),
                'throws' => $count,
            ];
        }
        return $rows;
    }

    private static function wbPlayerName($row): string
    {
        $profile = $row->profile ?? null;
        $first = $profile->first_name ?? '';
        $last = $profile->last_name ?? '';
        $name = trim($first.' '.$last);
        if ('' !== $name) {
            return $name;
        }
        return (string) ($row->player_name ?? $row->user_id ?? '');
    }
}
