<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\User;
use App\Models\WeeklyReportNote;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class WeeklyReportNotesService
{
    public const AUDIENCES = ['coach', 'staff', 'players', 'parents'];

    public const NOTE_TYPES = [
        'staff_note',
        'coach_comment',
        'parent_summary',
        'player_message',
        'next_week_emphasis',
        'player_follow_up',
        'internal_context',
    ];

    public const VISIBILITIES = ['staff', 'coach', 'parents', 'players', 'private'];

    private const COACH_STAFF_TYPES = [
        'staff_note',
        'coach_comment',
        'next_week_emphasis',
        'player_follow_up',
        'internal_context',
        'parent_summary',
        'player_message',
    ];

    private const STAFF_TYPES = [
        'staff_note',
        'coach_comment',
        'next_week_emphasis',
        'player_follow_up',
        'internal_context',
        'parent_summary',
        'player_message',
    ];

    private const PLAYER_TYPES = ['player_message', 'next_week_emphasis'];

    private const PARENT_TYPES = ['parent_summary', 'next_week_emphasis'];

    public function listNotes(string $teamId, array $options = []): array
    {
        $window = $this->dateWindow($options);

        $query = WeeklyReportNote::query()
            ->with(['player.profile'])
            ->where('team_id', $teamId)
            ->whereDate('week_start_date', $window['start'])
            ->whereDate('week_end_date', $window['end'])
            ->orderBy('note_type')
            ->orderBy('created_at');

        $this->applyOptionalFilters($query, $options);

        return $query->get()
            ->map(fn (WeeklyReportNote $note): array => $this->normalizeNote($note))
            ->values()
            ->all();
    }

    public function saveNote(string $teamId, array $data, ?string $createdByUserId = null): array
    {
        $window = $this->dateWindow($data);

        $note = WeeklyReportNote::query()->create([
            'team_id' => $teamId,
            'created_by_user_id' => $createdByUserId,
            'week_start_date' => $window['start'],
            'week_end_date' => $window['end'],
            'audience' => $this->allowed((string) ($data['audience'] ?? 'coach'), self::AUDIENCES, 'coach'),
            'note_type' => $this->allowed((string) ($data['note_type'] ?? 'coach_comment'), self::NOTE_TYPES, 'coach_comment'),
            'title' => $this->nullableString($data['title'] ?? null),
            'body' => trim((string) ($data['body'] ?? '')),
            'visibility' => $this->allowed((string) ($data['visibility'] ?? 'staff'), self::VISIBILITIES, 'staff'),
            'player_id' => $this->nullableString($data['player_id'] ?? null),
            'payload' => Arr::wrap($data['payload'] ?? []),
        ]);

        return $this->normalizeNote($note->load(['player.profile']));
    }

    public function updateNote(string $noteId, array $data, ?string $userId = null): array
    {
        $note = WeeklyReportNote::query()->findOrFail($noteId);

        $payload = [];
        foreach (['audience', 'note_type', 'title', 'body', 'visibility', 'player_id', 'payload'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $payload[$field] = match ($field) {
                'audience' => $this->allowed((string) $data[$field], self::AUDIENCES, (string) $note->audience),
                'note_type' => $this->allowed((string) $data[$field], self::NOTE_TYPES, (string) $note->note_type),
                'visibility' => $this->allowed((string) $data[$field], self::VISIBILITIES, (string) $note->visibility),
                'title', 'player_id' => $this->nullableString($data[$field]),
                'body' => trim((string) $data[$field]),
                'payload' => Arr::wrap($data[$field] ?? []),
                default => $data[$field],
            };
        }

        if (array_key_exists('start_date', $data) || array_key_exists('end_date', $data) || array_key_exists('days', $data)) {
            $window = $this->dateWindow($data);
            $payload['week_start_date'] = $window['start'];
            $payload['week_end_date'] = $window['end'];
        }

        $note->fill($payload)->save();

        return $this->normalizeNote($note->refresh()->load(['player.profile']));
    }

    public function deleteNote(string $noteId, ?string $userId = null): array
    {
        $note = WeeklyReportNote::query()->with(['player.profile'])->findOrFail($noteId);
        $normalized = $this->normalizeNote($note);
        $note->delete();

        return $normalized;
    }

    public function buildNotesForExport(string $teamId, string $audience, array $options = []): array
    {
        $audience = $this->allowed($audience, self::AUDIENCES, 'coach');
        $viewerId = $this->nullableString($options['current_user_id'] ?? null);
        $includePrivate = $this->bool($options['include_private_notes'] ?? false);
        $listOptions = Arr::except($options, ['audience', 'visibility', 'note_type', 'player_id']);

        return collect($this->listNotes($teamId, $listOptions))
            ->filter(fn (array $note): bool => $this->noteAllowedForAudience($note, $audience, $viewerId, $includePrivate))
            ->values()
            ->all();
    }

    public function mergeNotesIntoReport(array $report, array $notes, string $audience = 'coach'): array
    {
        $audience = $this->allowed($audience, self::AUDIENCES, 'coach');
        $sections = $this->sectionsForAudience($notes, $audience);

        $report['report_notes'] = [
            'audience' => $audience,
            'count' => count($notes),
            'sections' => $sections,
            'notes' => $notes,
        ];

        return $report;
    }

    public function dateWindow(array $options = []): array
    {
        $end = ! empty($options['end_date'] ?? $options['end'] ?? null)
            ? CarbonImmutable::parse((string) ($options['end_date'] ?? $options['end']))->startOfDay()
            : CarbonImmutable::now()->startOfDay();

        $start = ! empty($options['start_date'] ?? $options['start'] ?? null)
            ? CarbonImmutable::parse((string) ($options['start_date'] ?? $options['start']))->startOfDay()
            : $end->subDays(max(1, min(365, (int) ($options['days'] ?? 7))) - 1);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ];
    }

    private function applyOptionalFilters(Builder $query, array $options): void
    {
        foreach (['audience', 'visibility', 'note_type'] as $field) {
            $value = $this->nullableString($options[$field] ?? null);
            if ($value !== null) {
                $query->where($field, $value);
            }
        }

        $playerId = $this->nullableString($options['player_id'] ?? null);
        if ($playerId !== null) {
            $query->where('player_id', $playerId);
        }
    }

    private function noteAllowedForAudience(array $note, string $audience, ?string $viewerId, bool $includePrivate): bool
    {
        $type = (string) ($note['note_type'] ?? '');
        $visibility = (string) ($note['visibility'] ?? 'staff');

        if ($visibility === 'private') {
            return $audience === 'coach'
                && $includePrivate
                && $viewerId !== null
                && $viewerId === (string) ($note['created_by_user_id'] ?? '');
        }

        if (in_array($audience, ['coach', 'staff'], true)) {
            $allowedTypes = $audience === 'coach' ? self::COACH_STAFF_TYPES : self::STAFF_TYPES;

            return in_array($type, $allowedTypes, true);
        }

        if ($audience === 'players') {
            return in_array($type, self::PLAYER_TYPES, true)
                && in_array($visibility, ['players'], true);
        }

        if ($audience === 'parents') {
            return in_array($type, self::PARENT_TYPES, true)
                && in_array($visibility, ['parents'], true);
        }

        return false;
    }

    private function sectionsForAudience(array $notes, string $audience): array
    {
        $sectionMap = match ($audience) {
            'parents' => [
                'coach_summary' => ['title' => 'Coach Summary', 'types' => ['parent_summary']],
                'next_week_themes' => ['title' => 'Next Week Themes', 'types' => ['next_week_emphasis']],
            ],
            'players' => [
                'message_from_coach' => ['title' => 'Message from Coach', 'types' => ['player_message']],
                'next_week_focus' => ['title' => 'Next Week Focus', 'types' => ['next_week_emphasis']],
            ],
            default => [
                'staff_notes' => ['title' => 'Staff Notes', 'types' => ['staff_note']],
                'coach_comments' => ['title' => 'Coach Comments', 'types' => ['coach_comment']],
                'player_follow_ups' => ['title' => 'Player Follow-Ups', 'types' => ['player_follow_up']],
                'next_week_emphasis' => ['title' => 'Next Week Emphasis', 'types' => ['next_week_emphasis']],
                'internal_context' => ['title' => 'Internal Context', 'types' => ['internal_context']],
            ],
        };

        return collect($sectionMap)
            ->map(function (array $section, string $key) use ($notes): array {
                $items = collect($notes)
                    ->filter(fn (array $note): bool => in_array((string) ($note['note_type'] ?? ''), $section['types'], true))
                    ->values()
                    ->all();

                return [
                    'key' => $key,
                    'title' => $section['title'],
                    'items' => $items,
                    'count' => count($items),
                ];
            })
            ->filter(fn (array $section): bool => $section['count'] > 0)
            ->values()
            ->all();
    }

    private function normalizeNote(WeeklyReportNote $note): array
    {
        $player = $note->player;

        return [
            'id' => (string) $note->id,
            'team_id' => (string) $note->team_id,
            'week_start_date' => optional($note->week_start_date)->format('Y-m-d') ?: (string) $note->week_start_date,
            'week_end_date' => optional($note->week_end_date)->format('Y-m-d') ?: (string) $note->week_end_date,
            'audience' => (string) $note->audience,
            'note_type' => (string) $note->note_type,
            'title' => $note->title,
            'body' => (string) $note->body,
            'visibility' => (string) $note->visibility,
            'player_id' => $note->player_id,
            'player_name' => $player ? $this->playerName($player) : null,
            'created_by_user_id' => $note->created_by_user_id,
            'created_at' => optional($note->created_at)->toIso8601String(),
            'updated_at' => optional($note->updated_at)->toIso8601String(),
        ];
    }

    private function playerName(User $player): string
    {
        $profile = $player->profile;
        $name = trim((string) ($profile?->first_name ?? '').' '.(string) ($profile?->last_name ?? ''));

        return $name !== '' ? $name : ($player->email ?: 'Player');
    }

    private function allowed(string $value, array $allowed, string $fallback): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, $allowed, true) ? $normalized : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
