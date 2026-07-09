<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class IntelligenceSignalEngine
{
    public function buildSignals(array $assembled): array
    {
        $signals = [];

        foreach ($assembled['data_gaps'] ?? [] as $gap) {
            $signals[] = [
                'id' => 'gap-' . $this->slug((string) ($gap['source'] ?? 'source')) . '-' . $this->slug((string) ($gap['missing_field'] ?? 'field')),
                'category' => 'data_gap',
                'severity' => 'info',
                'title' => 'Missing ' . str_replace('_', ' ', (string) ($gap['missing_field'] ?? 'data')),
                'message' => $gap['impact'] ?? 'Important data is missing.',
                'evidence' => [$gap],
                'metric_keys' => [$gap['missing_field'] ?? null],
                'source_modules' => [$gap['source'] ?? null],
            ];
        }

        $this->scoreSignal($signals, 'batting', 'BP Score', $assembled['batting_summary']['score'] ?? null);
        $this->scoreSignal($signals, 'bullpen', 'Bullpen Score', $assembled['bullpen_summary']['score'] ?? null);
        $this->scoreSignal($signals, 'cage', 'Cage Score', $assembled['cage_summary']['score'] ?? null);
        $this->scoreSignal($signals, 'exit_velocity', 'Exit Velocity Score', $assembled['exit_velocity_summary']['score'] ?? null);
        $this->scoreSignal($signals, 'physical', 'Strength Score', $assembled['physical_development']['strength_score'] ?? null);
        $this->scoreSignal($signals, 'physical', 'Recovery Score', $assembled['physical_development']['recovery_score'] ?? null);
        $this->scoreSignal($signals, 'physical', 'Mobility Score', $assembled['physical_development']['mobility_score'] ?? null);

        return array_values(array_filter($signals, fn ($signal) => ! empty($signal['id'])));
    }

    public function buildTeamSignals(array $assembled, array $playerSnapshots): array
    {
        $signals = [];
        $rosterCount = (int) ($assembled['roster_count'] ?? count($playerSnapshots));

        if ($rosterCount === 0) {
            $signals[] = [
                'id' => 'team-roster-empty',
                'category' => 'data_gap',
                'severity' => 'warning',
                'title' => 'No players on roster',
                'message' => 'Team intelligence cannot be generated without rostered players.',
                'evidence' => [],
                'metric_keys' => ['roster_count'],
                'source_modules' => ['player_team'],
            ];
        }

        $playersWithGaps = collect($playerSnapshots)
            ->filter(fn ($snapshot) => ! empty($snapshot['data_gaps']))
            ->count();

        if ($playersWithGaps > 0) {
            $signals[] = [
                'id' => 'team-player-data-gaps',
                'category' => 'data_gap',
                'severity' => 'info',
                'title' => 'Player data gaps found',
                'message' => "{$playersWithGaps} player(s) have missing data that limits intelligence quality.",
                'evidence' => ['players_with_gaps' => $playersWithGaps],
                'metric_keys' => ['data_gaps'],
                'source_modules' => ['intelligence'],
            ];
        }

        return $signals;
    }

    private function scoreSignal(array &$signals, string $category, string $label, mixed $score): void
    {
        if (! is_numeric($score)) {
            return;
        }

        $score = (float) $score;
        if ($score >= 85) {
            $signals[] = [
                'id' => $this->slug($label) . '-strong',
                'category' => $category,
                'severity' => 'positive',
                'title' => "{$label} is strong",
                'message' => "{$label} is currently {$score}.",
                'evidence' => ['score' => $score],
                'metric_keys' => [$this->slug($label)],
                'source_modules' => [$category],
            ];
        } elseif ($score < 60) {
            $signals[] = [
                'id' => $this->slug($label) . '-needs-work',
                'category' => $category,
                'severity' => 'warning',
                'title' => "{$label} needs work",
                'message' => "{$label} is currently {$score}.",
                'evidence' => ['score' => $score],
                'metric_keys' => [$this->slug($label)],
                'source_modules' => [$category],
            ];
        }
    }

    private function slug(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)) ?? '', '-');
    }
}
