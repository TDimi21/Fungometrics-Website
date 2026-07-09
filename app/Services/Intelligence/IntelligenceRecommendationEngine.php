<?php

declare(strict_types=1);

namespace App\Services\Intelligence;

class IntelligenceRecommendationEngine
{
    public function buildRecommendations(array $assembled, array $signals, array $trends = [], array $limiters = [], array $dna = []): array
    {
        $recommendations = [];

        foreach ($limiters as $limiter) {
            $recommendations[] = $this->recommendationFromLimiter($limiter, $trends, $dna);
        }

        foreach ($trends as $metric => $trend) {
            if (($trend['direction'] ?? null) !== 'declining') {
                continue;
            }

            $recommendations[] = [
                'id' => 'trend-decline-' . $this->slug((string) $metric),
                'category' => 'trend',
                'priority' => ($trend['confidence'] ?? 'low') === 'high' ? 'high' : 'medium',
                'title' => 'Address Declining ' . str_replace('_', ' ', (string) $metric),
                'why' => 'The metric is declining based on the current comparison window.',
                'action' => 'Review recent workload, mechanics, and session quality for this metric.',
                'evidence' => ['metric' => $metric, 'trend' => $trend],
                'expected_gain' => null,
                'confidence' => $trend['confidence'] ?? 'low',
            ];
        }

        foreach ($assembled['data_gaps'] ?? [] as $gap) {
            $recommendations[] = [
                'id' => 'collect-' . $this->slug((string) ($gap['source'] ?? 'source')) . '-' . $this->slug((string) ($gap['missing_field'] ?? 'field')),
                'category' => 'data_collection',
                'priority' => 'medium',
                'title' => 'Collect ' . str_replace('_', ' ', (string) ($gap['missing_field'] ?? 'missing data')),
                'why' => $gap['impact'] ?? 'This data improves intelligence quality.',
                'action' => $gap['recommended_collection_action'] ?? 'Collect the missing data.',
                'evidence' => [$gap],
                'expected_gain' => null,
                'confidence' => 'medium',
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
                'expected_gain' => null,
                'confidence' => 'medium',
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
                'expected_gain' => null,
                'confidence' => 'medium',
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
                'expected_gain' => null,
                'confidence' => 'medium',
            ];
        }

        return $this->uniqueById($recommendations);
    }

    private function recommendationFromLimiter(array $limiter, array $trends, array $dna): array
    {
        $id = (string) ($limiter['id'] ?? 'limiter');

        return match ($id) {
            'command' => [
                'id' => 'improve-fastball-command',
                'category' => 'pitching',
                'priority' => 'high',
                'title' => 'Improve Fastball Command',
                'why' => 'Strike percentage is below target while velocity is stable or improving.',
                'action' => 'Fastball-only edge command bullpen.',
                'evidence' => $limiter['evidence'] ?? [],
                'expected_gain' => '+5-8% strike percentage',
                'confidence' => $limiter['confidence'] ?? 'medium',
            ],
            'long-toss-to-mound-transfer' => [
                'id' => 'transfer-arm-strength-to-mound',
                'category' => 'throwing',
                'priority' => 'medium',
                'title' => 'Transfer Arm Strength to the Mound',
                'why' => 'Long toss distance is improving but bullpen velocity is flat.',
                'action' => 'Add pulldown-to-bullpen progression and lower-half sequencing work.',
                'evidence' => $limiter['evidence'] ?? [],
                'expected_gain' => '+1-2 mph mound velocity',
                'confidence' => $limiter['confidence'] ?? 'medium',
            ],
            'barrel-control' => [
                'id' => 'improve-barrel-control',
                'category' => 'hitting',
                'priority' => 'high',
                'title' => 'Improve Barrel Control',
                'why' => 'Exit velocity is strong, but contact or launch quality is below target.',
                'action' => 'Run line-drive constraint rounds with middle-field targets before max EV rounds.',
                'evidence' => $limiter['evidence'] ?? [],
                'expected_gain' => '+5-10% line-drive or quality-contact rate',
                'confidence' => $limiter['confidence'] ?? 'medium',
            ],
            'mobility-restriction' => [
                'id' => 'restore-mobility',
                'category' => 'physical',
                'priority' => 'medium',
                'title' => 'Restore Mobility',
                'why' => 'Strength score is strong, but mobility score is limiting movement quality.',
                'action' => 'Add shoulder, hip, and thoracic mobility work before high-intent throwing or hitting.',
                'evidence' => $limiter['evidence'] ?? [],
                'expected_gain' => 'Better movement quality and reduced compensation risk',
                'confidence' => $limiter['confidence'] ?? 'medium',
            ],
            'recovery-workload-risk' => [
                'id' => 'reduce-workload-risk',
                'category' => 'recovery',
                'priority' => 'high',
                'title' => 'Reduce Recovery / Workload Risk',
                'why' => 'Throwing workload or intent is rising while recovery is low or declining.',
                'action' => 'Reduce high-intent throws for 24-48 hours and require recovery check-in before next mound or pulldown work.',
                'evidence' => $limiter['evidence'] ?? [],
                'expected_gain' => 'Improved readiness before next high-intent session',
                'confidence' => $limiter['confidence'] ?? 'medium',
            ],
            default => [
                'id' => 'review-' . $this->slug($id),
                'category' => $limiter['category'] ?? 'development',
                'priority' => $limiter['priority'] ?? 'medium',
                'title' => $limiter['title'] ?? 'Review Development Limiter',
                'why' => $limiter['why'] ?? 'A development limiter was detected.',
                'action' => 'Review the evidence and adjust the next training block.',
                'evidence' => $limiter['evidence'] ?? [],
                'expected_gain' => null,
                'confidence' => $limiter['confidence'] ?? 'low',
            ],
        };
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
