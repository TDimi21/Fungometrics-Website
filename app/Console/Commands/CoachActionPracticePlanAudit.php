<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\CoachActionPracticePlanner;
use Illuminate\Console\Command;

class CoachActionPracticePlanAudit extends Command
{
    protected $signature = 'intelligence:coach-action-practice-plan {teamId} {--days=365 : Intelligence lookback window in days} {--max-minutes=90 : Maximum minutes for today\'s suggested practice}';

    protected $description = 'Print the FMTRX coach action practice plan for a team.';

    public function handle(CoachActionPracticePlanner $planner): int
    {
        $teamId = (string) $this->argument('teamId');
        $days = max(7, min(365, (int) $this->option('days')));
        $maxMinutes = max(30, min(180, (int) $this->option('max-minutes')));
        $plan = $planner->buildPracticePlanFromCoachActions($teamId, $days, [
            'max_minutes' => $maxMinutes,
        ]);

        $this->info('FMTRX COACH ACTION PRACTICE PLAN');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.$days);
        $this->line('Max minutes: '.$maxMinutes);
        $this->kv('Plan title', $plan['plan_title'] ?? '-');
        $this->kv('Priority focus', $plan['priority_focus'] ?? '-');
        $this->kv('Estimated total minutes', ($plan['estimated_total_minutes'] ?? 0).' min');
        $this->kv('Practice blocks', count($plan['practice_blocks'] ?? []));
        $this->kv('Next session overflow', count($plan['next_session_blocks'] ?? []));

        $this->section('PRACTICE BLOCKS');
        $this->printBlocks($plan['practice_blocks'] ?? []);

        $this->section('DATA COLLECTION BLOCKS');
        $this->printBlocks($plan['data_collection_blocks'] ?? []);

        $this->section('NEXT SESSION OVERFLOW');
        $this->printBlocks($plan['next_session_blocks'] ?? []);

        $this->section('PLAYER ASSIGNMENTS');
        $assignments = $plan['player_assignments'] ?? [];
        if (empty($assignments)) {
            $this->line('- none');
        } else {
            foreach ($assignments as $assignment) {
                if (! is_array($assignment)) {
                    continue;
                }

                $this->line(sprintf(
                    '- %s | %s min | blocks: %s | metrics: %s',
                    $assignment['player_name'] ?? $assignment['player_id'] ?? 'Unknown Player',
                    $assignment['estimated_minutes'] ?? 0,
                    $this->list($assignment['blocks'] ?? []),
                    $this->list($assignment['metrics'] ?? []),
                ));
            }
        }

        $this->section('COACH NOTES');
        $this->printList($plan['coach_notes'] ?? []);

        $this->section('SOURCE ACTIONS');
        $this->printRows($plan['source_actions'] ?? [], fn (array $action): string => sprintf(
            '%s | %s | %s | %s',
            $action['source'] ?? '-',
            $action['title'] ?? 'Action',
            $action['priority'] ?? '-',
            $action['why'] ?? $action['action'] ?? '-',
        ));

        $this->section('EVIDENCE');
        foreach (($plan['evidence'] ?? []) as $key => $value) {
            $this->kv($this->human($key), $value);
        }

        return self::SUCCESS;
    }

    private function printBlocks(array $blocks): void
    {
        $this->printRows($blocks, function (array $block): string {
            $players = collect($block['players'] ?? [])
                ->map(fn ($player) => is_array($player) ? ($player['player_name'] ?? $player['name'] ?? $player['player_id'] ?? null) : null)
                ->filter()
                ->take(8)
                ->implode(', ');

            return sprintf(
                '%s | %s | %s | %s min | source: %s | metrics: %s | players: %s',
                $block['title'] ?? 'Practice Block',
                $block['category'] ?? '-',
                $block['priority'] ?? 'low',
                $block['duration_minutes'] ?? 0,
                $block['source'] ?? '-',
                $this->list($block['metrics_to_collect'] ?? []),
                $players !== '' ? $players : '-',
            );
        });
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function kv(string $label, mixed $value): void
    {
        $this->line($label.': '.$this->wrap($value));
    }

    private function printRows(array $rows, callable $formatter): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$formatter((array) $row));
        }
    }

    private function printList(array $rows): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('- '.$this->wrap($row));
        }
    }

    private function list(array $values): string
    {
        $list = collect($values)
            ->map(fn ($value) => is_array($value) ? ($value['display_name'] ?? $value['metric_key'] ?? null) : $value)
            ->filter()
            ->implode(', ');

        return $list !== '' ? $list : '-';
    }

    private function wrap(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }

    private function human(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
