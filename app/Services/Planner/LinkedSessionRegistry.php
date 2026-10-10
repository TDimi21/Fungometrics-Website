<?php

declare(strict_types=1);
namespace App\Services\Planner;

use App\Models\Practice;

final class LinkedSessionRegistry
{
    public function all(): array
    {
        return [
            'bullpen' => ['label'=>'Bullpen','native_practice_screen'=>'PitchingPractice','practice_type'=>'P','mode'=>null,'web_path'=>'/create/bullpen','native_screen'=>'SessionTabs','execution_type'=>'LAUNCH_SESSION'],
            'long_toss' => ['label'=>'Long Toss','native_practice_screen'=>'LongTossPractice','practice_type'=>'T','mode'=>'LT','web_path'=>'/create/mode','native_screen'=>'SessionTabs','execution_type'=>'LAUNCH_SESSION'],
            'weighted_ball' => ['label'=>'Weighted Balls','native_practice_screen'=>'WeightedPractice','practice_type'=>'T','mode'=>'WB','web_path'=>'/create/mode','native_screen'=>'SessionTabs','execution_type'=>'LAUNCH_SESSION'],
            'exit_velocity' => ['label'=>'Exit Velocity','native_practice_screen'=>'VelocityPractice','practice_type'=>'T','mode'=>'EV','web_path'=>'/create/mode','native_screen'=>'SessionTabs','execution_type'=>'LAUNCH_SESSION'],
            'cage' => ['label'=>'Cage','practice_type'=>'C','mode'=>null,'web_path'=>'/create/cage','native_screen'=>'SessionTabs','execution_type'=>'LAUNCH_SESSION'],
            'live_ab' => ['label'=>'Live AB','practice_type'=>'L','mode'=>null,'web_path'=>'/create/live','native_screen'=>'SessionTabs','execution_type'=>'LAUNCH_SESSION'],
            'strength' => ['label'=>'Strength Log','execution_type'=>'LOG','web_path'=>null,'native_screen'=>null],
            'assessment' => ['label'=>'Assessment','execution_type'=>'LOG','web_path'=>null,'native_screen'=>null],
        ];
    }
    public function matches(string $type, Practice $practice): bool
    {
        $definition = $this->all()[$type] ?? [];
        return isset($definition['practice_type']) && $practice->type === $definition['practice_type'] && (! $definition['mode'] || $practice->modes === $definition['mode']);
    }
    public function item(array $item, string $bucket): array
    {
        $metadata = $item['metadata'] ?? [];
        $type = $metadata['session_type'] ?? null;
        $definition = $this->all()[$type] ?? null;
        $execution = $metadata['execution_type'] ?? $item['execution_type'] ?? null;
        if ($definition) $execution = $definition['execution_type'];
        if (!in_array($execution, ['COMPLETE','LOG','LAUNCH_SESSION'], true)) {
            $execution = isset($item['setList']) || ($item['workloadType'] ?? '') === 'throwing' || str_starts_with($bucket, 'strength') || ($metadata['tracking_type'] ?? '') === 'quick_throws' ? 'LOG' : 'COMPLETE';
        }
        // A session action without an authoritative registered engine cannot launch.
        if ($execution === 'LAUNCH_SESSION' && !$definition) $execution = 'LOG';
        return array_merge($item, ['execution_type'=>$execution,'linked_session'=>$definition ? ['type'=>$type]+$definition : null]);
    }
    public function buckets(array $buckets): array
    {
        return array_map(function ($bucket) {
            $bucket['items'] = array_map(fn($item) => $this->item($item, $bucket['type'] ?? ''), $bucket['items'] ?? []);
            return $bucket;
        }, $buckets);
    }
}
