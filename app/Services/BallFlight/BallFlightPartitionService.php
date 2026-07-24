<?php

declare(strict_types=1);

namespace App\Services\BallFlight;

final class BallFlightPartitionService
{
    public static function partition(string $session): string
    {
        $bucket = hexdec(substr(hash('sha256', mb_strtolower(trim($session))), 0, 8)) % 100;

        return $bucket < 60 ? 'training' : ($bucket < 80 ? 'validation' : 'locked_test');
    }

    /** @param list<string> $sessions @return array<string,string> */
    public static function assignSessions(array $sessions): array
    {
        $sessions = array_values(array_unique(array_filter($sessions)));
        usort($sessions, fn (string $a, string $b): int => strcmp(hash('sha256', $a), hash('sha256', $b)));
        $count = count($sessions);
        $trainingCount = (int) ceil($count * 0.60);
        $validationCount = (int) floor($count * 0.20);
        if ($count >= 3 && $validationCount === 0) $validationCount = 1;
        $assignments = [];
        foreach ($sessions as $index => $session) {
            $assignments[$session] = $index < $trainingCount
                ? 'training'
                : ($index < $trainingCount + $validationCount ? 'validation' : 'locked_test');
        }
        return $assignments;
    }
}
