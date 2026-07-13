<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\CoachWeeklyReportExportService;
use Illuminate\Console\Command;

class CoachWeeklyReportExportAudit extends Command
{
    protected $signature = 'planner:coach-weekly-report-export
        {teamId : Team id}
        {--start= : Start date YYYY-MM-DD}
        {--end= : End date YYYY-MM-DD}
        {--days=7 : Days to include when start/end are omitted}
        {--format=text : summary, text, html, or pdf}
        {--audience=coach : coach, staff, players, or parents}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX coach weekly report export/share payloads.';

    public function handle(CoachWeeklyReportExportService $service): int
    {
        $export = $service->buildExport((string) $this->argument('teamId'), [
            'start_date' => $this->option('start'),
            'end_date' => $this->option('end'),
            'days' => (int) $this->option('days'),
            'format' => (string) $this->option('format'),
            'audience' => (string) $this->option('audience'),
            'include_player_rows' => true,
            'include_benchmark_details' => true,
            'include_pending_reviews' => true,
            'include_next_week_priorities' => true,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX COACH WEEKLY REPORT EXPORT');
        $this->line('Team ID: '.$this->value($export['team_id'] ?? null));
        $this->line('Format: '.$this->value($export['format'] ?? null));
        $this->line('Audience: '.$this->value($export['audience'] ?? null));
        $this->line('Generated: '.$this->value($export['generated_at'] ?? null));

        if (($export['format'] ?? null) === 'html') {
            $this->newLine();
            $this->line('HTML PREVIEW');
            $this->line('------------');
            $this->line((string) ($export['html'] ?? ''));
        } elseif (($export['format'] ?? null) === 'pdf') {
            $this->newLine();
            $this->line('PDF STATUS');
            $this->line('----------');
            $pdf = $export['pdf'] ?? [];
            $this->line('Available: '.$this->value($pdf['available'] ?? false));
            $this->line('Download URL: '.$this->value($pdf['download_url'] ?? null));
        } else {
            $this->newLine();
            $this->line('SHARE TEXT');
            $this->line('----------');
            $this->line((string) ($export['share_text'] ?? ''));
        }

        if (! empty($export['warnings'])) {
            $this->newLine();
            $this->line('WARNINGS');
            $this->line('--------');
            foreach ($export['warnings'] as $warning) {
                $this->line('- '.$this->value($warning));
            }
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
