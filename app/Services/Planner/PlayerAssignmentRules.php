<?php
namespace App\Services\Planner;

use Illuminate\Validation\ValidationException;

final class PlayerAssignmentRules
{
    public static function isLiveAB(array $item): bool
    {
        $type = strtolower(str_replace([' ', '-'], '_', (string) ($item['metadata']['session_type'] ?? $item['linked_session']['type'] ?? $item['session_type'] ?? '')));
        return in_array($type, ['live_ab', 'liveab', 'game'], true)
            || ($item['practice_type'] ?? $item['linked_session']['practice_type'] ?? null) === 'L';
    }
    public static function validate(array $buckets): void
    {
        foreach ($buckets as $bucket) foreach ($bucket['items'] ?? [] as $item) {
            if (self::isLiveAB($item)) throw ValidationException::withMessages(['buckets' => 'Live AB games are coach-controlled and cannot be assigned for player scoring.']);
        }
    }
}
