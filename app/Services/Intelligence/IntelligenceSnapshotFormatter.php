<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class IntelligenceSnapshotFormatter
{
    public function formatPlayerSnapshot(
        string $teamId,
        string $playerId,
        array $assembled,
        array $signals,
        array $recommendations
    ): array {
        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'player_id' => $playerId,
            'data_sources_used' => $assembled['data_sources_used'] ?? [],
            'data_gaps' => $assembled['data_gaps'] ?? [],
            'summary' => $this->playerSummary($assembled),
            'scores' => $this->scores($assembled),
            'signals' => $signals,
            'recommendations' => $recommendations,
            'trend_blocks' => $assembled['trend_blocks'] ?? [],
            'profile_labels' => $this->profileLabels($assembled),
        ];
    }

    public function formatTeamSnapshot(
        string $teamId,
        array $assembled,
        array $playerSnapshots,
        array $signals,
        array $recommendations
    ): array {
        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'player_id' => null,
            'data_sources_used' => $assembled['data_sources_used'] ?? [],
            'data_gaps' => $assembled['data_gaps'] ?? [],
            'summary' => [
                'team' => $assembled['team_context'] ?? [],
                'roster_count' => $assembled['roster_count'] ?? count($playerSnapshots),
                'player_snapshots' => count($playerSnapshots),
            ],
            'scores' => $this->teamScores($playerSnapshots),
            'signals' => $signals,
            'recommendations' => $recommendations,
            'trend_blocks' => [],
            'profile_labels' => [],
            'players' => $playerSnapshots,
        ];
    }

    private function playerSummary(array $assembled): array
    {
        return [
            'player' => $assembled['player_context'] ?? [],
            'team' => $assembled['team_context'] ?? [],
            'assessment' => $assembled['assessment_summary'] ?? [],
            'session_summary' => $assembled['session_summary'] ?? [],
        ];
    }

    private function scores(array $assembled): array
    {
        return [
            'athletic_performance' => $assembled['physical_development']['overall_api_score'] ?? null,
            'strength' => $assembled['physical_development']['strength_score'] ?? null,
            'recovery' => $assembled['physical_development']['recovery_score'] ?? null,
            'mobility' => $assembled['physical_development']['mobility_score'] ?? null,
            'batting' => $assembled['batting_summary']['score'] ?? null,
            'bullpen' => $assembled['bullpen_summary']['score'] ?? null,
            'cage' => $assembled['cage_summary']['score'] ?? null,
            'exit_velocity' => $assembled['exit_velocity_summary']['score'] ?? null,
        ];
    }

    private function teamScores(array $playerSnapshots): array
    {
        $scoreKeys = ['athletic_performance', 'strength', 'recovery', 'mobility', 'batting', 'bullpen', 'cage', 'exit_velocity'];
        $scores = [];

        foreach ($scoreKeys as $key) {
            $values = collect($playerSnapshots)
                ->map(fn ($snapshot) => $snapshot['scores'][$key] ?? null)
                ->filter(fn ($value) => is_numeric($value));

            $scores[$key] = $values->isNotEmpty() ? round((float) $values->avg(), 1) : null;
        }

        return $scores;
    }

    private function profileLabels(array $assembled): array
    {
        $labels = [];

        $grade = $assembled['physical_development']['grade_label'] ?? null;
        if ($grade) {
            $labels[] = $grade;
        }

        $projection = $assembled['physical_development']['projection_label'] ?? null;
        if ($projection) {
            $labels[] = $projection;
        }

        $weighted = $assembled['weighted_ball_summary']['velocity_by_weight'] ?? [];
        if (count($weighted) >= 3) {
            $labels[] = 'Weighted Ball Profile Available';
        }

        return array_values(array_unique($labels));
    }
}
