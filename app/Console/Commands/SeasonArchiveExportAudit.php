<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\SeasonArchiveExportService;
use Illuminate\Console\Command;

class SeasonArchiveExportAudit extends Command
{
    protected $signature = 'planner:season-archive-export
        {teamId : Team id}
        {--start= : Season start date YYYY-MM-DD}
        {--end= : Season end date YYYY-MM-DD}
        {--weeks=12 : Number of weeks to analyze}
        {--format=text : summary, text, html, or pdf}
        {--audience=staff : coach, staff, director, players, or parents}
        {--json : Output structured JSON}';

    protected $description = 'Build a read-only FMTRX season archive export packet for a team.';

    public function handle(SeasonArchiveExportService $service): int
    {
        $export = $service->buildExport((string) $this->argument('teamId'), [
            'season_start_date' => $this->option('start') ?: null,
            'season_end_date' => $this->option('end') ?: null,
            'weeks' => (int) $this->option('weeks'),
            'format' => (string) $this->option('format'),
            'audience' => (string) $this->option('audience'),
            'include_player_rows' => true,
            'include_benchmark_progress' => true,
            'include_planner_progress' => true,
            'include_communication_summary' => true,
            'include_weekly_timeline' => true,
            'include_next_steps' => true,
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX SEASON ARCHIVE EXPORT');
        $this->line('Team ID: '.$this->value($export['team_id'] ?? null));
        $this->line('Format: '.$this->value($export['format'] ?? null));
        $this->line('Audience: '.$this->value($export['audience'] ?? null));
        $this->line('Generated: '.$this->value($export['generated_at'] ?? null));

        if (($export['format'] ?? null) === 'html') {
            $this->newLine();
            $this->line('HTML PACKET');
            $this->line('-----------');
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
