<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CagePracticeResult;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CageDistanceCompare extends Command
{
    protected $signature = 'cage:distance-compare
        {--days=365 : Only include rows created within the last N days}
        {--limit=100 : Maximum number of rows to compare}
        {--json : Output structured JSON instead of the formatted report}';

    protected $description = 'Compare stored v1 distance_travel against v2 estimated_carry_v2 for rows that have both.';

    private const EV_BANDS = [
        ['label' => 'under 70', 'min' => -INF, 'max' => 69.9],
        ['label' => '70-79.9', 'min' => 70.0, 'max' => 79.9],
        ['label' => '80-89.9', 'min' => 80.0, 'max' => 89.9],
        ['label' => '90-99.9', 'min' => 90.0, 'max' => 99.9],
        ['label' => '100+', 'min' => 100.0, 'max' => INF],
    ];

    private const LAUNCH_BANDS = [
        ['label' => 'below 0', 'min' => -INF, 'max' => -0.1],
        ['label' => '0-9', 'min' => 0.0, 'max' => 9.9],
        ['label' => '10-19', 'min' => 10.0, 'max' => 19.9],
        ['label' => '20-29', 'min' => 20.0, 'max' => 29.9],
        ['label' => '30-39', 'min' => 30.0, 'max' => 39.9],
        ['label' => '40+', 'min' => 40.0, 'max' => INF],
    ];

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');

        $rows = CagePracticeResult::query()
            ->whereNotNull('distance_travel')
            ->whereNotNull('estimated_carry_v2')
            ->where('created_at', '>=', now()->subDays($days))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['distance_travel', 'estimated_carry_v2', 'launch_angle_velocity', 'launch_angle']);

        if ($rows->isEmpty()) {
            $message = "No rows found with both distance_travel and estimated_carry_v2 set in the last {$days} day(s). "
                . 'This is expected until FMTRX_CAGE_DISTANCE_V2_ENABLED has been on for a while.';
            if ($this->option('json')) {
                $this->line((string) json_encode(['row_count' => 0, 'message' => $message], JSON_PRETTY_PRINT));
            } else {
                $this->warn($message);
            }

            return self::SUCCESS;
        }

        $diffs = $rows->map(fn ($row) => (float) $row->estimated_carry_v2 - (float) $row->distance_travel);

        $summary = [
            'row_count' => $rows->count(),
            'avg_v1_distance_ft' => round((float) $rows->avg('distance_travel'), 1),
            'avg_v2_distance_ft' => round((float) $rows->avg('estimated_carry_v2'), 1),
            'avg_difference_ft' => round((float) $diffs->avg(), 1),
            'median_difference_ft' => round($this->median($diffs), 1),
            'largest_positive_difference_ft' => round((float) $diffs->max(), 1),
            'largest_negative_difference_ft' => round((float) $diffs->min(), 1),
        ];

        $evBreakdown = $this->breakdown($rows, 'launch_angle_velocity', self::EV_BANDS);
        $laBreakdown = $this->breakdown($rows, 'launch_angle', self::LAUNCH_BANDS);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                ...$summary,
                'by_ev_band' => $evBreakdown,
                'by_launch_angle_band' => $laBreakdown,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX CAGE DISTANCE v1 vs v2 COMPARISON');
        $this->line("Window: last {$days} day(s), up to {$limit} rows");
        $this->newLine();
        $this->line('Row count: '.$summary['row_count']);
        $this->line('Average v1 distance: '.$summary['avg_v1_distance_ft'].' ft');
        $this->line('Average v2 distance: '.$summary['avg_v2_distance_ft'].' ft');
        $this->line('Average difference (v2 - v1): '.$this->signed($summary['avg_difference_ft']).' ft');
        $this->line('Median difference: '.$this->signed($summary['median_difference_ft']).' ft');
        $this->line('Largest positive difference: '.$this->signed($summary['largest_positive_difference_ft']).' ft');
        $this->line('Largest negative difference: '.$this->signed($summary['largest_negative_difference_ft']).' ft');

        $this->newLine();
        $this->line('BREAKDOWN BY EXIT VELOCITY BAND');
        $this->table(['Band', 'Rows', 'Avg v1', 'Avg v2', 'Avg diff'], $this->tableRows($evBreakdown));

        $this->newLine();
        $this->line('BREAKDOWN BY LAUNCH ANGLE BAND');
        $this->table(['Band', 'Rows', 'Avg v1', 'Avg v2', 'Avg diff'], $this->tableRows($laBreakdown));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int,CagePracticeResult>  $rows
     * @param  list<array{label:string,min:float,max:float}>  $bands
     * @return list<array{label:string,row_count:int,avg_v1_ft:?float,avg_v2_ft:?float,avg_difference_ft:?float}>
     */
    private function breakdown(Collection $rows, string $bandField, array $bands): array
    {
        $result = [];
        foreach ($bands as $band) {
            // Filtering directly (rather than collecting matching indices and
            // calling ->only()) sidesteps a real footgun here: Eloquent
            // Collection overrides only() to filter by primary key, not by
            // positional index, so an index-based ->only() call silently
            // returns an empty collection for a model collection.
            $bandRows = $rows->filter(fn ($row) => (float) $row->{$bandField} >= $band['min'] && (float) $row->{$bandField} <= $band['max']);
            $bandDiffs = $bandRows->map(fn ($row) => (float) $row->estimated_carry_v2 - (float) $row->distance_travel);

            $result[] = [
                'label' => $band['label'],
                'row_count' => $bandRows->count(),
                'avg_v1_ft' => $bandRows->isEmpty() ? null : round((float) $bandRows->avg('distance_travel'), 1),
                'avg_v2_ft' => $bandRows->isEmpty() ? null : round((float) $bandRows->avg('estimated_carry_v2'), 1),
                'avg_difference_ft' => $bandDiffs->isEmpty() ? null : round((float) $bandDiffs->avg(), 1),
            ];
        }

        return $result;
    }

    /** @param  list<array{label:string,row_count:int,avg_v1_ft:?float,avg_v2_ft:?float,avg_difference_ft:?float}>  $breakdown */
    private function tableRows(array $breakdown): array
    {
        return array_map(fn ($b) => [
            $b['label'],
            $b['row_count'],
            $b['avg_v1_ft'] ?? '-',
            $b['avg_v2_ft'] ?? '-',
            $b['avg_difference_ft'] !== null ? $this->signed($b['avg_difference_ft']) : '-',
        ], $breakdown);
    }

    /** @param  Collection<int,float>  $values */
    private function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();
        if ($count === 0) {
            return 0.0;
        }
        $mid = intdiv($count, 2);

        return $count % 2 === 0
            ? (($sorted[$mid - 1] + $sorted[$mid]) / 2)
            : $sorted[$mid];
    }

    private function signed(float $value): string
    {
        return ($value >= 0 ? '+' : '').$value;
    }
}
