<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\WeeklyReportNotesService;
use Illuminate\Console\Command;

class WeeklyReportNotesAudit extends Command
{
    protected $signature = 'planner:weekly-report-notes
        {teamId : Team id}
        {--start= : Start date YYYY-MM-DD}
        {--end= : End date YYYY-MM-DD}
        {--days=7 : Days to include when start/end are omitted}
        {--audience=coach : coach, staff, players, or parents}
        {--type= : Filter or create note type}
        {--visibility= : Filter or create visibility}
        {--add= : Create a simple note body before listing}
        {--title= : Optional note title when using --add}
        {--json : Output structured JSON}';

    protected $description = 'Audit and optionally create FMTRX weekly report notes.';

    public function handle(WeeklyReportNotesService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $options = [
            'start_date' => $this->option('start'),
            'end_date' => $this->option('end'),
            'days' => (int) $this->option('days'),
            'audience' => (string) $this->option('audience'),
            'note_type' => $this->option('type'),
            'visibility' => $this->option('visibility'),
        ];

        if ($this->option('add') !== null) {
            $service->saveNote($teamId, [
                ...$options,
                'note_type' => (string) ($this->option('type') ?: 'coach_comment'),
                'visibility' => (string) ($this->option('visibility') ?: 'staff'),
                'title' => $this->option('title'),
                'body' => (string) $this->option('add'),
            ]);
        }

        $notes = $service->listNotes($teamId, $options);
        $exportNotes = $service->buildNotesForExport($teamId, (string) $this->option('audience'), $options);
        $payload = [
            'team_id' => $teamId,
            'date_window' => $service->dateWindow($options),
            'audience' => (string) $this->option('audience'),
            'notes_count' => count($notes),
            'export_visible_count' => count($exportNotes),
            'notes' => $notes,
            'export_visible_notes' => $exportNotes,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX WEEKLY REPORT NOTES');
        $this->line('Team ID: '.$teamId);
        $this->line('Window: '.$payload['date_window']['start'].' to '.$payload['date_window']['end']);
        $this->line('Audience: '.$this->value($payload['audience']));
        $this->line('Stored notes: '.$payload['notes_count']);
        $this->line('Visible in export: '.$payload['export_visible_count']);

        $this->newLine();
        $this->line('NOTES');
        $this->line('-----');
        if (empty($notes)) {
            $this->line('- none');
        }
        foreach ($notes as $note) {
            $this->line(sprintf(
                '- %s | %s | %s | %s%s',
                $this->value($note['note_type'] ?? null),
                $this->value($note['visibility'] ?? null),
                $this->value($note['title'] ?? null),
                $this->value($note['body'] ?? null),
                ! empty($note['player_name'] ?? null) ? ' | '.$note['player_name'] : '',
            ));
        }

        $this->newLine();
        $this->line('EXPORT-VISIBLE NOTES');
        $this->line('--------------------');
        if (empty($exportNotes)) {
            $this->line('- none');
        }
        foreach ($exportNotes as $note) {
            $this->line(sprintf(
                '- %s | %s | %s',
                $this->value($note['note_type'] ?? null),
                $this->value($note['title'] ?? null),
                $this->value($note['body'] ?? null),
            ));
        }

        return self::SUCCESS;
    }

    private function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }
}
