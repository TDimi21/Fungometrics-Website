<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BallFlightPredictionEvaluation;
use App\Services\BallFlight\AccuracyMetrics;
use Illuminate\Console\Command;

class BallFlightAccuracyReport extends Command
{
    protected $signature = 'ball-flight:accuracy-report {--cohort=all} {--spin=estimated} {--engine-version=} {--format=table} {--output=}';
    protected $description = 'Report raw BFI accuracy without activating calibration';

    public function handle(AccuracyMetrics $metrics): int
    {
        $query = BallFlightPredictionEvaluation::query()->with('observation')
            ->where('spin_source', (string) $this->option('spin'))->whereNotNull('distance_error_ft');
        if ($this->option('engine-version')) $query->where('engine_version', $this->option('engine-version'));
        $cohort = (string) $this->option('cohort');
        if ($cohort !== 'all') $query->whereHas('observation', fn ($q) => $q->where('source_type', $cohort));
        $evaluations = $query->get();

        $report = ['cohorts' => []];
        foreach (['trackman', 'statcast'] as $source) {
            $sourceRows = $evaluations->filter(fn ($e) => $e->observation?->source_type === $source);
            if ($sourceRows->isEmpty()) continue;
            $report['cohorts'][$source] = [
                'overall' => $metrics->summarize($sourceRows->pluck('distance_error_ft')->all()),
                'hang_time_mae_seconds' => $this->mae($sourceRows->pluck('hang_time_error_seconds')->filter(fn ($v) => $v !== null)->all()),
                'max_height_mae_ft' => $this->mae($sourceRows->pluck('max_height_error_ft')->filter(fn ($v) => $v !== null)->all()),
                'breakdowns' => [
                    'exit_velocity' => $this->groups($sourceRows, $metrics, fn ($o) => $this->evBand((float) $o->exit_velocity_mph)),
                    'launch_angle' => $this->groups($sourceRows, $metrics, fn ($o) => $this->launchBand((float) $o->launch_angle_deg)),
                    'spray' => $this->groups($sourceRows, $metrics, fn ($o) => $this->sprayBand($o->spray_angle_deg)),
                    'batted_ball_type' => $this->groups($sourceRows, $metrics, fn ($o) => $o->automatic_hit_type ?: $o->tagged_hit_type ?: 'unknown'),
                    'launch_confidence' => $this->groups($sourceRows, $metrics, fn ($o) => $o->launch_confidence ?: 'unknown'),
                    'landing_confidence' => $this->groups($sourceRows, $metrics, fn ($o) => $o->landing_confidence ?: 'unknown'),
                    'source_file' => $this->groups($sourceRows, $metrics, fn ($o) => $o->source_file),
                    'spin_availability' => $this->groups($sourceRows, $metrics, fn ($o) => $o->measured_spin_rpm === null ? 'unavailable' : 'available'),
                ],
            ];
        }
        if ($cohort === 'all' && $evaluations->isNotEmpty()) {
            $report['combined'] = $metrics->summarize($evaluations->pluck('distance_error_ft')->all());
        }
        return $this->render($report);
    }

    private function groups($evaluations, AccuracyMetrics $metrics, callable $key): array
    {
        return $evaluations->groupBy(fn ($e) => $key($e->observation))
            ->map(fn ($rows) => $metrics->summarize($rows->pluck('distance_error_ft')->all()))->all();
    }

    private function mae(array $values): ?float
    {
        return $values === [] ? null : round(array_sum(array_map(fn ($v) => abs((float) $v), $values)) / count($values), 3);
    }

    private function evBand(float $v): string { return match (true) { $v < 60 => '<60', $v < 70 => '60-69.9', $v < 80 => '70-79.9', $v < 90 => '80-89.9', $v < 100 => '90-99.9', default => '100+' }; }
    private function launchBand(float $v): string { return match (true) { $v < 0 => '<0', $v < 10 => '0-9.9', $v < 20 => '10-19.9', $v < 30 => '20-29.9', $v < 40 => '30-39.9', default => '40+' }; }
    private function sprayBand(mixed $v): string { if ($v === null) return 'unknown'; $v = (float) $v; return abs($v) > 45 ? 'extreme/foul' : ($v < -15 ? 'left' : ($v > 15 ? 'right' : 'middle')); }

    private function render(array $report): int
    {
        $format = (string) $this->option('format');
        $output = $format === 'json' ? json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $this->csv($report);
        if ($format === 'table') {
            foreach ($report['cohorts'] as $name => $cohort) {
                $this->info(mb_strtoupper($name));
                $this->table(['Metric', 'Value'], collect($cohort['overall'])->map(fn ($v, $k) => [$k, $v])->values()->all());
                foreach ($cohort['breakdowns'] as $section => $groups) {
                    $this->line($section.': '.json_encode($groups));
                }
            }
            if (isset($report['combined'])) $this->line('Combined: '.json_encode($report['combined']));
            return self::SUCCESS;
        }
        if ($this->option('output')) file_put_contents((string) $this->option('output'), $output);
        else $this->line($output);
        return self::SUCCESS;
    }

    private function csv(array $report): string
    {
        $lines = ['cohort,section,group,metric,value'];
        foreach ($report['cohorts'] as $cohort => $data) {
            foreach ($data['overall'] as $metric => $value) $lines[] = "{$cohort},overall,all,{$metric},{$value}";
            foreach ($data['breakdowns'] as $section => $groups) {
                foreach ($groups as $group => $metrics) foreach ($metrics as $metric => $value) {
                    $lines[] = implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', [$cohort, $section, $group, $metric, $value]));
                }
            }
        }
        return implode(PHP_EOL, $lines).PHP_EOL;
    }
}
