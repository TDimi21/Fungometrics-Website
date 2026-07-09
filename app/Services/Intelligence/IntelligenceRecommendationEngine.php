<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class IntelligenceRecommendationEngine
{
    public function buildRecommendations(array $assembled, array $signals): array
    {
        $recommendations = [];

        foreach ($assembled['data_gaps'] ?? [] as $gap) {
            $recommendations[] = [
                'id' => 'collect-' . $this->slug((string) ($gap['source'] ?? 'source')) . '-' . $this->slug((string) ($gap['missing_field'] ?? 'field')),
                'category' => 'data_collection',
                'priority' => 'medium',
                'title' => 'Collect ' . str_replace('_', ' ', (string) ($gap['missing_field'] ?? 'missing data')),
                'why' => $gap['impact'] ?? 'This data improves intelligence quality.',
                'action' => $gap['recommended_collection_action'] ?? 'Collect the missing data.',
                'evidence' => [$gap],
                'related_signals' => $this->matchingSignalIds($signals, $gap),
            ];
        }

        foreach ($signals as $signal) {
            if (($signal['severity'] ?? null) !== 'warning') {
                continue;
            }

            $recommendations[] = [
                'id' => 'act-on-' . $this->slug((string) ($signal['id'] ?? $signal['title'] ?? 'signal')),
                'category' => $signal['category'] ?? 'development',
                'priority' => 'high',
                'title' => $signal['title'] ?? 'Review development signal',
                'why' => $signal['message'] ?? 'This signal needs coach attention.',
                'action' => 'Review the related session data and choose the next development focus.',
                'evidence' => $signal['evidence'] ?? [],
                'related_signals' => [$signal['id'] ?? null],
            ];
        }

        return $this->uniqueById($recommendations);
    }

    public function buildTeamRecommendations(array $assembled, array $signals, array $playerSnapshots): array
    {
        $recommendations = [];

        foreach ($signals as $signal) {
            $recommendations[] = [
                'id' => 'team-' . $this->slug((string) ($signal['id'] ?? $signal['title'] ?? 'signal')),
                'category' => $signal['category'] ?? 'team',
                'priority' => ($signal['severity'] ?? null) === 'warning' ? 'high' : 'medium',
                'title' => $signal['title'] ?? 'Review team signal',
                'why' => $signal['message'] ?? 'This team signal needs review.',
                'action' => 'Review the affected players in the development command center.',
                'evidence' => $signal['evidence'] ?? [],
                'related_signals' => [$signal['id'] ?? null],
            ];
        }

        if (count($playerSnapshots) > 0) {
            $recommendations[] = [
                'id' => 'team-review-player-intelligence',
                'category' => 'workflow',
                'priority' => 'medium',
                'title' => 'Review player intelligence cards',
                'why' => 'Team intelligence is built from individual player snapshots.',
                'action' => 'Open each player profile to review signals, recommendations, and data gaps.',
                'evidence' => ['player_count' => count($playerSnapshots)],
                'related_signals' => [],
            ];
        }

        return $this->uniqueById($recommendations);
    }

    private function matchingSignalIds(array $signals, array $gap): array
    {
        $source = $gap['source'] ?? null;
        $field = $gap['missing_field'] ?? null;

        return collect($signals)
            ->filter(function ($signal) use ($source, $field) {
                return in_array($source, $signal['source_modules'] ?? [], true)
                    || in_array($field, $signal['metric_keys'] ?? [], true);
            })
            ->pluck('id')
            ->filter()
            ->values()
            ->all();
    }

    private function uniqueById(array $recommendations): array
    {
        return collect($recommendations)
            ->filter(fn ($item) => ! empty($item['id']))
            ->unique('id')
            ->values()
            ->all();
    }

    private function slug(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)) ?? '', '-');
    }
}
