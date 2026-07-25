<?php

declare(strict_types=1);

namespace App\Services\DataHub\Dictionary;

final class ConceptCompatibilityService
{
    /** @var array<string, array<int, string>> */
    private const DESTINATION_DOMAINS = [
        'live_ab' => ['hitting', 'pitching', 'game_outcome', 'session_context'],
        'cage' => ['hitting', 'session_context'],
        'batting_practice' => ['hitting', 'session_context'],
        'bullpen' => ['pitching', 'session_context'],
        'pitching_practice' => ['pitching', 'session_context'],
        'long_toss' => ['throwing', 'session_context'],
        'weighted_balls' => ['throwing', 'session_context'],
        'exit_velocity' => ['hitting', 'session_context'],
        'assessment' => ['assessment', 'hitting', 'pitching', 'throwing', 'strength', 'mobility', 'speed_agility', 'body_composition', 'recovery', 'session_context'],
        'strength' => ['strength', 'body_composition', 'speed_agility', 'session_context'],
        'mobility' => ['mobility', 'session_context'],
        'speed_agility' => ['speed_agility', 'strength', 'session_context'],
        'recovery' => ['recovery', 'session_context'],
    ];

    public function classify(string $destination, ?string $domain): string
    {
        if (null === $domain) {
            return 'incompatible';
        }
        if (in_array($domain, self::DESTINATION_DOMAINS[$destination] ?? [], true)) {
            return 'compatible';
        }
        if (in_array($domain, ['pitching', 'game_outcome'], true)
            && in_array($destination, ['cage', 'batting_practice'], true)) {
            return 'warning';
        }
        if (in_array($domain, ['assessment', 'body_composition', 'speed_agility'], true)) {
            return 'warning';
        }

        return 'incompatible';
    }
}
