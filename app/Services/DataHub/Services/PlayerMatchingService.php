<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Models\User;

final class PlayerMatchingService
{
    /** @return array<int, array<string, mixed>> */
    public function suggestions(string $teamId, string $externalName): array
    {
        $players = User::query()
            ->select('users.id')
            ->join('player_teams', 'player_teams.user_id', '=', 'users.id')
            ->where('player_teams.team_id', $teamId)
            ->whereNull('player_teams.deleted_at')
            ->where('users.type', 'player')
            ->with('profile:user_id,first_name,last_name')
            ->get();
        $target = $this->normalize($externalName);
        $targetParts = explode(' ', $target);
        $matches = [];
        foreach ($players as $player) {
            $display = trim((string) ($player->profile?->first_name.' '.$player->profile?->last_name));
            if ('' === $display) {
                continue;
            }
            $candidate = $this->normalize($display);
            $confidence = $this->similarity($target, $candidate);
            $matchType = 'fuzzy';
            if ($target === $candidate) {
                $confidence = 100;
                $matchType = 'exact';
            } elseif ($this->initialLastMatch($targetParts, explode(' ', $candidate)) || $this->nicknameMatch($target, $candidate)) {
                $confidence = max(90, $confidence);
                $matchType = 'normalized';
            }
            if ($confidence >= 60) {
                $matches[] = [
                    'player_id' => (string) $player->id,
                    'display_name' => $display,
                    'match_type' => $matchType,
                    'confidence' => $confidence,
                ];
            }
        }
        usort($matches, fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);

        return array_slice($matches, 0, 3);
    }

    public function normalize(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim($name)) ?: $name;

        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower((string) preg_replace('/[^a-z0-9\s]/i', ' ', $ascii))));
    }

    private function similarity(string $left, string $right): int
    {
        $max = max(mb_strlen($left), mb_strlen($right));

        return 0 === $max ? 0 : max(0, (int) round((1 - levenshtein($left, $right) / $max) * 100));
    }

    private function initialLastMatch(array $left, array $right): bool
    {
        return count($left) >= 2 && count($right) >= 2
            && end($left) === end($right)
            && mb_substr($left[0], 0, 1) === mb_substr($right[0], 0, 1);
    }

    private function nicknameMatch(string $left, string $right): bool
    {
        $nicknames = [
            'thomas' => ['tom', 'tommy'], 'william' => ['will', 'bill', 'billy'],
            'robert' => ['rob', 'bob', 'bobby'], 'james' => ['jim', 'jimmy'],
            'michael' => ['mike'], 'joseph' => ['joe', 'joey'], 'christopher' => ['chris'],
        ];
        $a = explode(' ', $left);
        $b = explode(' ', $right);
        if (count($a) < 2 || count($b) < 2 || end($a) !== end($b)) {
            return false;
        }
        foreach ($nicknames as $formal => $short) {
            if (($a[0] === $formal && in_array($b[0], $short, true)) || ($b[0] === $formal && in_array($a[0], $short, true))) {
                return true;
            }
        }

        return false;
    }
}
