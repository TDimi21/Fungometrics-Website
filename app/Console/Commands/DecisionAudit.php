<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Intelligence\DecisionEngine;
use Illuminate\Console\Command;

class DecisionAudit extends Command
{
    protected $signature = 'intelligence:decision {teamId} {--days=365 : Intelligence lookback window in days}';

    protected $description = 'Print the FMTRX team decision brief for a coach.';

    public function handle(DecisionEngine $decisionEngine): int
    {
        $teamId = (string) $this->argument('teamId');
        $days = max(7, min(365, (int) $this->option('days')));
        $brief = $decisionEngine->buildTeamDecisionBrief($teamId, $days);

        $primary = $brief['primary_focus'] ?? [];
        $concern = $brief['biggest_concern'] ?? [];
        $plan = $brief['recommended_practice_plan'] ?? [];
        $dataPriority = $brief['data_collection_priority'] ?? [];

        $this->info('FMTRX DECISION BRIEF');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.$days);
        $this->newLine();

        $this->section('PRIMARY FOCUS');
        $this->kv('Focus', $primary['title'] ?? 'No focus');
        $this->kv('Why', $primary['why'] ?? 'No explanation available.');
        $this->kv('Action', $primary['action'] ?? 'No action available.');
        $this->kv('Affected players', (string) ($primary['affected_player_count'] ?? 0));

        $this->section('BIGGEST CONCERN');
        $this->kv('Concern', $concern['title'] ?? 'No concern');
        $this->kv('Why', $concern['why'] ?? 'No explanation available.');

        $this->section('EXPECTED GAIN');
        $this->kv('Expected gain', $brief['expected_gain'] ?? 'No projected gain yet.');
        $this->kv('Confidence', $brief['confidence'] ?? 'low');

        $this->section('DATA COLLECTION PRIORITY');
        $this->kv('Level', $dataPriority['level'] ?? 'none');

        $this->line('Critical missing data:');
        $this->printMissingRows($dataPriority['missing_critical'] ?? []);

        $this->line('Supporting missing data:');
        $this->printMissingRows($dataPriority['missing_supporting'] ?? []);

        $this->line('Collection plan:');
        $planRows = $dataPriority['recommended_collection_plan'] ?? [];
        if (empty($planRows)) {
            $this->line('- none');
        } else {
            foreach ($planRows as $row) {
                $metrics = collect($row['metrics'] ?? [])
                    ->pluck('display_name')
                    ->filter()
                    ->implode(', ');
                $this->line('- '.($row['title'] ?? 'Collection Plan').' | '.($row['priority'] ?? 'low').' | '.$metrics);
            }
        }

        $this->section('PLAYERS NEEDING ATTENTION');
        $players = $brief['players_needing_attention'] ?? [];
        if (empty($players)) {
            $this->line('None listed for this focus.');
        } else {
            foreach ($players as $player) {
                $this->line('- '.($player['name'] ?? $player['player_id'] ?? 'Unknown Player').' | '.($player['reason'] ?? 'Needs review'));
            }
        }

        $this->section('PRACTICE PLAN');
        $this->kv('Title', $plan['title'] ?? 'No plan');
        $this->kv('Duration', isset($plan['duration_minutes']) ? $plan['duration_minutes'].' min' : 'Not set');

        foreach ($plan['blocks'] ?? [] as $block) {
            $this->line('- '.($block['name'] ?? 'Block').' ('.($block['duration_minutes'] ?? 0).' min)');
            $this->line('  '.$this->wrapValue($block['description'] ?? ''));
            $this->line('  Why: '.$this->wrapValue($block['why'] ?? ''));
        }

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->info($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function kv(string $label, mixed $value): void
    {
        $this->line($label.': '.$this->wrapValue($value));
    }

    private function printMissingRows(array $rows): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach (array_slice($rows, 0, 8) as $row) {
            $this->line('- '.($row['display_name'] ?? $row['metric_key'] ?? 'Metric').' | missing '.($row['missing_count'] ?? 0).' of '.($row['player_count'] ?? 0).' | '.($row['reason'] ?? $row['classification'] ?? 'missing'));
        }
    }

    private function wrapValue(mixed $value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }
}
