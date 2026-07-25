<?php

declare(strict_types=1);

namespace App\Services\DataHub\Services;

use App\Models\ExternalPlayerMapping;
use App\Models\User;

final class PlayerMatchingService
{
    /** @return array<int, array<string, mixed>> */
    public function roster(string $teamId): array
    {
        return User::query()
            ->select('users.*')
            ->join('player_teams', 'player_teams.user_id', '=', 'users.id')
            ->where('player_teams.team_id', $teamId)
            ->whereNull('player_teams.deleted_at')
            ->where('users.type', 'player')
            ->where('users.status', true)
            ->with(['profile:user_id,first_name,last_name', 'player:user_id,grad_year,hit_side,throw_side', 'positions'])
            ->get()
            ->map(function (User $player): array {
                $name = trim((string) ($player->profile?->first_name.' '.$player->profile?->last_name));

                return [
                    'id' => (string) $player->id,
                    'name' => $name,
                    'graduation_year' => $player->player?->grad_year,
                    'primary_position' => $player->positions->first()?->position,
                    'hit_side' => $player->player?->hit_side,
                    'throw_side' => $player->player?->throw_side,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function suggestions(
        string $teamId,
        string $externalName,
        ?string $externalId = null,
        ?string $platformId = null,
    ): array {
        $target = $this->normalize($externalName);
        if ($platformId) {
            $remembered = $this->remembered($teamId, $platformId, $externalId, $target);
            if ($remembered) {
                return [[
                    'player_id' => (string) $remembered->fmtrx_player_id,
                    'display_name' => $this->rosterName($remembered->fmtrx_player_id),
                    'match_type' => $externalId ? 'remembered_external_id' : 'remembered_name',
                    'resolution_source' => $externalId ? 'remembered_external_id' : 'remembered_name',
                    'confidence' => 100,
                    'auto_select' => true,
                ]];
            }
        }

        $matches = [];
        foreach ($this->roster($teamId) as $player) {
            $candidate = $this->normalize($player['name']);
            $confidence = $this->similarity($target, $candidate);
            $matchType = 'fuzzy';
            $autoSelect = false;
            if (0 === strcasecmp(trim($externalName), $player['name'])) {
                $confidence = 100;
                $matchType = 'exact_name';
                $autoSelect = true;
            } elseif ($target === $candidate) {
                $confidence = 98;
                $matchType = 'normalized_name';
            } elseif ($this->nicknameMatch($target, $candidate)) {
                $confidence = max(90, $confidence);
                $matchType = 'nickname';
            } elseif ($this->initialLastMatch(explode(' ', $target), explode(' ', $candidate))) {
                $confidence = max(85, $confidence);
                $matchType = 'first_initial_last_name';
            }
            if ($confidence >= 60) {
                $matches[] = [
                    'player_id' => $player['id'],
                    'display_name' => $player['name'],
                    'match_type' => $matchType,
                    'resolution_source' => $matchType,
                    'confidence' => $confidence,
                    'auto_select' => $autoSelect,
                ];
            }
        }
        usort($matches, fn (array $a, array $b): int => $b['confidence'] <=> $a['confidence']);

        return array_slice($matches, 0, 3);
    }

    public function normalize(string $name): string
    {
        $name = trim($name);
        if (str_contains($name, ',')) {
            [$last, $first] = array_pad(array_map('trim', explode(',', $name, 2)), 2, '');
            $name = trim($first.' '.$last);
        }
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
        $normalized = mb_strtolower((string) preg_replace('/[^a-z0-9\s]/i', ' ', $ascii));
        $parts = array_values(array_filter(preg_split('/\s+/', trim($normalized)) ?: []));
        $suffixes = ['jr', 'sr', 'ii', 'iii', 'iv'];
        $parts = array_values(array_filter($parts, fn (string $part): bool => ! in_array($part, $suffixes, true)));
        if (count($parts) > 2 && 1 === mb_strlen($parts[1])) {
            unset($parts[1]);
        }

        return implode(' ', array_values($parts));
    }

    private function remembered(string $teamId, string $platformId, ?string $externalId, string $normalized): ?ExternalPlayerMapping
    {
        $base = ExternalPlayerMapping::query()
            ->where('team_id', $teamId)
            ->where('platform_definition_id', $platformId)
            ->where('status', 'active');
        if ($externalId) {
            return (clone $base)->where('source_player_id', $externalId)->first();
        }

        return $base->whereNull('source_player_id')
            ->where('normalized_source_player_name', $normalized)
            ->latest('approved_at')
            ->first();
    }

    private function rosterName(string $playerId): string
    {
        $player = User::query()->with('profile:user_id,first_name,last_name')->find($playerId);

        return trim((string) ($player?->profile?->first_name.' '.$player?->profile?->last_name));
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
