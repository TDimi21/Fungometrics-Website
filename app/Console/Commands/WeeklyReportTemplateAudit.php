<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\CoachWeeklyReportExportService;
use App\Services\Planner\WeeklyReportTemplateService;
use Illuminate\Console\Command;

class WeeklyReportTemplateAudit extends Command
{
    protected $signature = 'planner:weekly-report-template
        {teamId : Team id}
        {--template= : Template key}
        {--audience=coach : coach, staff, players, or parents}
        {--format=text : summary, text, html, or pdf}
        {--days=7 : Days to include}
        {--list : List available templates}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX weekly report template output.';

    public function handle(WeeklyReportTemplateService $templateService, CoachWeeklyReportExportService $exportService): int
    {
        if ($this->option('list')) {
            $templates = $templateService->listTemplates();
            if ($this->option('json')) {
                $this->line((string) json_encode(['templates' => $templates], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;
            }

            $this->info('FMTRX WEEKLY REPORT TEMPLATES');
            foreach ($templates as $template) {
                $this->line(sprintf(
                    '- %s | %s | audience %s | %s',
                    $this->value($template['template_key'] ?? null),
                    $this->value($template['display_name'] ?? null),
                    $this->value($template['audience'] ?? null),
                    $this->value($template['description'] ?? null),
                ));
            }

            return self::SUCCESS;
        }

        $export = $exportService->buildExport((string) $this->argument('teamId'), [
            'days' => (int) $this->option('days'),
            'format' => (string) $this->option('format'),
            'audience' => (string) $this->option('audience'),
            'template' => (string) ($this->option('template') ?: ''),
        ]);

        if ($this->option('json')) {
            $this->line((string) json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $template = $export['template'] ?? [];
        $this->info('FMTRX WEEKLY REPORT TEMPLATE AUDIT');
        $this->line('Team ID: '.$this->value($export['team_id'] ?? null));
        $this->line('Template: '.$this->value($template['display_name'] ?? null).' ('.$this->value($template['template_key'] ?? null).')');
        $this->line('Audience: '.$this->value($export['audience'] ?? null));
        $this->line('Format: '.$this->value($export['format'] ?? null));

        $this->newLine();
        $this->line('TEMPLATE RULES');
        $this->line('--------------');
        $this->line('Sections: '.$this->value($template['sections'] ?? []));
        $this->line('Player detail: '.$this->value($template['max_player_detail_level'] ?? null));
        $this->line('Benchmark detail: '.$this->value($template['benchmark_detail_level'] ?? null));
        $this->line('Include staff notes: '.$this->value($template['include_staff_notes'] ?? false));
        $this->line('Include parent notes: '.$this->value($template['include_parent_notes'] ?? false));
        $this->line('Include player messages: '.$this->value($template['include_player_messages'] ?? false));

        $this->newLine();
        $this->line('OUTPUT');
        $this->line('------');
        if (($export['format'] ?? null) === 'html') {
            $this->line((string) ($export['html'] ?? ''));
        } else {
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
            return implode(', ', array_map(fn ($item): string => $this->value($item), $value));
        }

        return (string) $value;
    }
}
