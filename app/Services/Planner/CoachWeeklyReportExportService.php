<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\Team;
use Illuminate\Support\Arr;

class CoachWeeklyReportExportService
{
    private const AUDIENCES = ['coach', 'staff', 'players', 'parents'];

    private const FORMATS = ['summary', 'text', 'html', 'pdf'];

    public function __construct(
        private readonly CoachWeeklyTeamReportService $reportService,
        private readonly WeeklyReportNotesService $notesService,
    ) {
    }

    public function buildExport(string $teamId, array $options = []): array
    {
        $format = $this->optionIn((string) ($options['format'] ?? 'summary'), self::FORMATS, 'summary');
        $audience = $this->optionIn((string) ($options['audience'] ?? 'coach'), self::AUDIENCES, 'coach');
        $warnings = [];

        $report = $this->reportService->buildTeamReport($teamId, [
            'start_date' => $options['start_date'] ?? null,
            'end_date' => $options['end_date'] ?? null,
            'days' => $options['days'] ?? 7,
            'include_player_rows' => $options['include_player_rows'] ?? true,
            'include_benchmark_details' => $options['include_benchmark_details'] ?? true,
            'include_next_week_priorities' => $options['include_next_week_priorities'] ?? true,
        ]);
        $report['team_name'] = $this->teamName($teamId);
        $report = $this->notesService->mergeNotesIntoReport(
            $report,
            $this->notesService->buildNotesForExport($teamId, $audience, $options),
            $audience,
        );

        $filteredReport = $this->buildAudienceFilteredReport($report, $audience, $options);
        $shareText = in_array($format, ['summary', 'text', 'pdf'], true)
            ? $this->buildShareText($filteredReport, ['audience' => $audience, ...$options])
            : null;
        $html = $format === 'html'
            ? $this->buildHtmlReport($filteredReport, ['audience' => $audience, ...$options])
            : null;
        $pdf = $format === 'pdf'
            ? $this->buildPdfReport($filteredReport, ['audience' => $audience, ...$options])
            : $this->emptyPdf();

        if (($pdf['available'] ?? false) === false && $format === 'pdf') {
            $warnings[] = 'PDF export is not configured yet. Use copy/share text for now.';
        }

        foreach ($this->audienceWarnings($audience) as $warning) {
            $warnings[] = $warning;
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'format' => $format,
            'audience' => $audience,
            'report' => $filteredReport,
            'share_text' => $shareText,
            'html' => $html,
            'pdf' => $pdf,
            'warnings' => array_values(array_unique(array_filter([
                ...$warnings,
                ...Arr::wrap($pdf['warnings'] ?? []),
            ]))),
        ];
    }

    public function buildShareText(array $report, array $options = []): string
    {
        $audience = $this->optionIn((string) ($options['audience'] ?? 'coach'), self::AUDIENCES, 'coach');
        $summary = Arr::wrap($report['executive_summary'] ?? []);
        $team = Arr::wrap($report['team_completion'] ?? []);
        $benchmark = Arr::wrap($report['benchmark_submission_summary'] ?? []);
        $trusted = Arr::wrap($report['trusted_data_summary'] ?? []);
        $lines = [
            'FMTRX Weekly Team Report',
            'Team: '.$this->text($report['team_name'] ?? 'Team'),
            'Week: '.$this->text($report['week_label'] ?? '-'),
            'Audience: '.$this->display($audience),
            '',
            'Executive Summary',
            $this->text($summary['headline'] ?? 'Weekly report is not available yet.'),
            $this->text($summary['summary_text'] ?? ''),
            '',
            'Weekly Completion',
            '- Plans assigned: '.$this->text($team['plans_assigned'] ?? 0),
            '- Team completion: '.$this->percent($team['team_completion_percentage'] ?? 0),
            '- Completed / in progress / not started: '.$this->text($team['completed_assignments'] ?? 0).' / '.$this->text($team['in_progress_assignments'] ?? 0).' / '.$this->text($team['not_started_assignments'] ?? 0),
            '',
            'Benchmark Collection',
            '- Submitted values: '.$this->text($benchmark['submitted_metric_count'] ?? 0),
            '- Approved values: '.$this->text($benchmark['approved_metric_count'] ?? 0),
            '- Pending review: '.$this->text($benchmark['pending_review_count'] ?? 0),
            '- Trusted values added: '.$this->text($trusted['trusted_values_added'] ?? 0),
        ];

        $this->appendList($lines, 'Wins', Arr::wrap($summary['wins'] ?? []), 'No weekly wins are available yet.');
        $this->appendList($lines, $audience === 'parents' ? 'Needs Attention' : 'Concerns', Arr::wrap($summary['concerns'] ?? []), 'No urgent weekly blockers are surfaced.');
        $this->appendReportNotesText($lines, Arr::wrap($report['report_notes']['sections'] ?? []));

        if (in_array($audience, ['coach', 'staff'], true)) {
            $this->appendPlayerRows($lines, Arr::wrap($report['player_rows'] ?? []));
            $this->appendList($lines, 'Coach Follow-Ups', collect(Arr::wrap($report['coach_follow_ups'] ?? []))
                ->map(fn (array $row): string => $this->text($row['title'] ?? 'Follow-Up').': '.$this->text($row['recommended_action'] ?? ''))
                ->all(), 'No coach follow-ups are surfaced yet.');
        }

        $this->appendList($lines, 'Next Week', collect(Arr::wrap($report['next_week_priorities'] ?? []))
            ->map(fn (array $row): string => $this->text($row['title'] ?? 'Priority').($row['suggested_block'] ?? null ? ' - '.$this->text($row['suggested_block']) : ''))
            ->take(5)
            ->all(), 'No next-week priorities are available yet.');

        $lines[] = '';
        $lines[] = 'Generated by FMTRX.';
        $lines[] = 'Research benchmarks remain active where FMTRX population sample is still growing.';

        return trim(implode("\n", array_filter($lines, fn ($line): bool => $line !== null)));
    }

    public function buildHtmlReport(array $report, array $options = []): string
    {
        $audience = $this->optionIn((string) ($options['audience'] ?? 'coach'), self::AUDIENCES, 'coach');
        $summary = Arr::wrap($report['executive_summary'] ?? []);
        $team = Arr::wrap($report['team_completion'] ?? []);
        $benchmark = Arr::wrap($report['benchmark_submission_summary'] ?? []);
        $trusted = Arr::wrap($report['trusted_data_summary'] ?? []);
        $playerRows = Arr::wrap($report['player_rows'] ?? []);
        $followUps = Arr::wrap($report['coach_follow_ups'] ?? []);
        $priorities = Arr::wrap($report['next_week_priorities'] ?? []);

        return '<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>FMTRX Weekly Team Report</title>
<style>
  :root { color-scheme: light; }
  body { margin:0; background:#f3f6fb; color:#0f172a; font-family:Arial, Helvetica, sans-serif; }
  .report { max-width:980px; margin:0 auto; padding:28px; }
  .header { display:flex; justify-content:space-between; gap:18px; border-bottom:4px solid #e11d48; padding-bottom:18px; margin-bottom:20px; }
  h1 { margin:0; font-size:30px; letter-spacing:.02em; }
  h2 { margin:0 0 10px; font-size:15px; letter-spacing:.08em; text-transform:uppercase; color:#be123c; }
  h3 { margin:0 0 6px; font-size:17px; }
  p { line-height:1.5; }
  .muted { color:#64748b; font-size:13px; }
  .grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px; margin:18px 0; }
  .card, section { background:#fff; border:1px solid #dbe3ef; border-radius:14px; padding:14px; box-shadow:0 8px 24px rgba(15,23,42,.06); }
  .value { font-size:26px; font-weight:900; margin-top:5px; }
  ul { margin:8px 0 0; padding-left:20px; }
  li { margin:4px 0; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  th, td { text-align:left; border-bottom:1px solid #e2e8f0; padding:8px 6px; }
  th { color:#475569; text-transform:uppercase; font-size:11px; letter-spacing:.06em; }
  .section-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px; }
  .footer { color:#64748b; font-size:12px; margin-top:18px; border-top:1px solid #dbe3ef; padding-top:12px; }
  @media (max-width:720px) { .grid, .section-grid { grid-template-columns:1fr; } .header { flex-direction:column; } .report { padding:16px; } }
  @media print { @page { size:A4 portrait; margin:10mm; } body { background:#fff; } .report { padding:0; } .card, section { box-shadow:none; break-inside:avoid; } }
</style>
</head>
<body>
<main class="report">
  <div class="header">
    <div>
      <h1>FMTRX Weekly Team Report</h1>
      <div class="muted">'.$this->esc($report['team_name'] ?? 'Team').' · '.$this->esc($report['week_label'] ?? '-').'</div>
    </div>
    <div class="muted">Audience: '.$this->esc($this->display($audience)).'<br>Generated: '.$this->esc(now()->format('M j, Y g:i A')).'</div>
  </div>

  <section>
    <h2>Executive Summary</h2>
    <h3>'.$this->esc($summary['headline'] ?? 'Weekly report is not available yet.').'</h3>
    <p>'.$this->esc($summary['summary_text'] ?? '').'</p>
    <div class="section-grid">
      '.$this->htmlList('Wins', Arr::wrap($summary['wins'] ?? []), 'No weekly wins are available yet.').'
      '.$this->htmlList($audience === 'parents' ? 'Needs Attention' : 'Concerns', Arr::wrap($summary['concerns'] ?? []), 'No urgent weekly blockers are surfaced.').'
    </div>
  </section>

  <div class="grid">
    '.$this->statCard('Team Completion', $this->percent($team['team_completion_percentage'] ?? 0), 'assigned work').'
    '.$this->statCard('Plans Assigned', $team['plans_assigned'] ?? 0, ($team['plans_published'] ?? 0).' published').'
    '.$this->statCard('Benchmarks Submitted', $benchmark['submitted_metric_count'] ?? 0, ($benchmark['approved_metric_count'] ?? 0).' approved').'
    '.$this->statCard('Trusted Values', $trusted['trusted_values_added'] ?? 0, 'added to intelligence').'
  </div>

  '.$this->htmlReportNotes(Arr::wrap($report['report_notes']['sections'] ?? [])).'

  '.$this->htmlPlayerTable($playerRows, in_array($audience, ['coach', 'staff'], true)).'

  <div class="section-grid">
    '.$this->htmlList('Coach Follow-Ups', collect($followUps)->map(fn (array $row): string => $this->text($row['title'] ?? 'Follow-Up').': '.$this->text($row['recommended_action'] ?? ''))->all(), in_array($audience, ['coach', 'staff'], true) ? 'No coach follow-ups are surfaced yet.' : 'Coach follow-ups are hidden for this audience.').'
    '.$this->htmlList('Next Week Priorities', collect($priorities)->map(fn (array $row): string => $this->text($row['title'] ?? 'Priority').($row['suggested_block'] ?? null ? ' - '.$this->text($row['suggested_block']) : ''))->take(5)->all(), 'No next-week priorities are available yet.').'
  </div>

  <div class="footer">
    Generated by FMTRX. Research benchmarks remain active where FMTRX population sample is still growing.
  </div>
</main>
</body>
</html>';
    }

    public function buildPdfReport(array $report, array $options = []): array
    {
        return [
            'available' => false,
            'file_path' => null,
            'download_url' => null,
            'warnings' => [
                'PDF export is not configured yet. Use copy/share text for now.',
            ],
        ];
    }

    public function buildAudienceFilteredReport(array $report, string $audience = 'coach', array $options = []): array
    {
        $audience = $this->optionIn($audience, self::AUDIENCES, 'coach');
        $filtered = $report;

        if (! $this->bool($options['include_player_rows'] ?? true)) {
            $filtered['player_rows'] = [];
        }
        if (! $this->bool($options['include_benchmark_details'] ?? true)) {
            $filtered['benchmark_submission_summary'] = [];
        }
        if (! $this->bool($options['include_next_week_priorities'] ?? true)) {
            $filtered['next_week_priorities'] = [];
        }

        if (! $this->bool($options['include_pending_reviews'] ?? true)) {
            $filtered = $this->hideReviewDetails($filtered, true);
        }

        if ($audience === 'staff') {
            $filtered['warnings'] = [];
            $filtered['evidence'] = [];
        }

        if ($audience === 'players') {
            $filtered['player_rows'] = [];
            $filtered['coach_follow_ups'] = $this->simplifyFollowUps($filtered['coach_follow_ups'] ?? []);
            $filtered['next_week_priorities'] = $this->simplifyPriorities($filtered['next_week_priorities'] ?? []);
            $filtered['missed_work_summary']['players'] = [];
            $filtered['current_team_intelligence'] = [];
            $filtered['warnings'] = [];
            $filtered['evidence'] = [];
            $filtered = $this->hideReviewDetails($filtered, false);
            $filtered = $this->simplifyPublicSummary($filtered);
        }

        if ($audience === 'parents') {
            $filtered['player_rows'] = [];
            $filtered['coach_follow_ups'] = [];
            $filtered['next_week_priorities'] = $this->simplifyPriorities($filtered['next_week_priorities'] ?? []);
            $filtered['missed_work_summary']['players'] = [];
            $filtered['benchmark_submission_summary']['top_remaining_missing_metrics'] = [];
            $filtered['benchmark_submission_summary']['metrics_submitted'] = [];
            $filtered['trusted_data_summary']['source_mix_after'] = [];
            $filtered['current_team_intelligence'] = [];
            $filtered['warnings'] = [];
            $filtered['evidence'] = [];
            $filtered = $this->hideReviewDetails($filtered, false);
            $filtered = $this->simplifyPublicSummary($filtered);
        }

        return $this->stripInternalIds($filtered);
    }

    private function simplifyPublicSummary(array $report): array
    {
        $benchmark = Arr::wrap($report['benchmark_submission_summary'] ?? []);
        $report['executive_summary']['summary_text'] = 'Players submitted '.(int) ($benchmark['submitted_metric_count'] ?? 0).' benchmark value(s). Coach review keeps development data accurate before it updates FMTRX intelligence.';
        $report['executive_summary']['concerns'] = collect(Arr::wrap($report['executive_summary']['concerns'] ?? []))
            ->reject(function ($item): bool {
                $text = strtolower((string) $item);

                return str_contains($text, 'correction') || str_contains($text, 'rejected');
            })
            ->values()
            ->all();

        if (empty($report['executive_summary']['concerns'])) {
            $report['executive_summary']['concerns'] = ['No urgent weekly blockers are surfaced.'];
        }

        return $report;
    }

    private function hideReviewDetails(array $report, bool $keepCounts): array
    {
        $report['review_summary']['tasks_pending_review'] = [];
        $report['review_summary']['players_needing_correction'] = [];
        $report['benchmark_submission_summary']['tasks_pending_review'] = [];
        $report['benchmark_submission_summary']['players_needing_correction'] = [];

        if (! $keepCounts) {
            unset(
                $report['benchmark_submission_summary']['correction_requested_count'],
                $report['benchmark_submission_summary']['rejected_count'],
                $report['benchmark_submission_summary']['players_needing_correction'],
                $report['review_summary']['correction_requested_count'],
                $report['review_summary']['players_needing_correction'],
            );
        }

        return $report;
    }

    private function simplifyFollowUps(mixed $rows): array
    {
        return collect(Arr::wrap($rows))
            ->map(fn (array $row): array => [
                'title' => $row['title'] ?? 'Follow-Up',
                'priority' => $row['priority'] ?? 'medium',
                'recommended_action' => $row['recommended_action'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function simplifyPriorities(mixed $rows): array
    {
        return collect(Arr::wrap($rows))
            ->map(fn (array $row): array => [
                'rank' => $row['rank'] ?? null,
                'title' => $row['title'] ?? 'Next Week Priority',
                'priority' => $row['priority'] ?? 'medium',
                'category' => $row['category'] ?? null,
                'why' => $row['why'] ?? null,
                'suggested_block' => $row['suggested_block'] ?? null,
                'estimated_minutes' => $row['estimated_minutes'] ?? null,
                'source' => $row['source'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function stripInternalIds(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            $keyString = is_string($key) ? $key : '';
            if ($this->isInternalIdKey($keyString)) {
                continue;
            }
            $clean[$key] = $this->stripInternalIds($item);
        }

        return $clean;
    }

    private function isInternalIdKey(string $key): bool
    {
        return in_array($key, ['id', 'team_id', 'player_id', 'player_ids', 'task_id', 'daily_plan_id', 'created_by_user_id', 'reviewed_by_user_id'], true)
            || str_ends_with($key, '_id')
            || str_ends_with($key, '_ids');
    }

    private function appendPlayerRows(array &$lines, array $rows): void
    {
        $lines[] = '';
        $lines[] = 'Player Summary';
        if (empty($rows)) {
            $lines[] = '- No player completion data is available yet.';

            return;
        }

        foreach (array_slice($rows, 0, 16) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $lines[] = '- '.$this->text($row['player_name'] ?? 'Player').': '.$this->percent($row['completion_percentage'] ?? 0)
                .' complete, '.$this->text($row['benchmark_values_submitted'] ?? 0).' submitted, '
                .$this->text($row['pending_review_count'] ?? 0).' pending review';
        }
    }

    private function appendList(array &$lines, string $title, array $items, string $empty): void
    {
        $lines[] = '';
        $lines[] = $title.':';
        if (empty($items)) {
            $lines[] = '- '.$empty;

            return;
        }

        foreach (array_slice($items, 0, 8) as $item) {
            $lines[] = '- '.$this->text($item);
        }
    }

    private function appendReportNotesText(array &$lines, array $sections): void
    {
        if (empty($sections)) {
            return;
        }

        foreach ($sections as $section) {
            if (! is_array($section) || empty($section['items'])) {
                continue;
            }

            $lines[] = '';
            $lines[] = $this->text($section['title'] ?? 'Report Notes').':';
            foreach (array_slice(Arr::wrap($section['items']), 0, 8) as $note) {
                if (! is_array($note)) {
                    continue;
                }
                $title = $this->text($note['title'] ?? '');
                $body = $this->text($note['body'] ?? '');
                $player = $this->text($note['player_name'] ?? '');
                $prefix = $player !== '-' ? $player.': ' : '';
                $lines[] = '- '.$prefix.($title !== '-' ? $title.' - ' : '').$body;
            }
        }
    }

    private function htmlList(string $title, array $items, string $empty): string
    {
        $rows = empty($items)
            ? '<li>'.$this->esc($empty).'</li>'
            : collect($items)->take(8)->map(fn ($item): string => '<li>'.$this->esc($item).'</li>')->implode('');

        return '<section><h2>'.$this->esc($title).'</h2><ul>'.$rows.'</ul></section>';
    }

    private function htmlReportNotes(array $sections): string
    {
        if (empty($sections)) {
            return '';
        }

        $html = collect($sections)
            ->filter(fn ($section): bool => is_array($section) && ! empty($section['items']))
            ->map(function (array $section): string {
                $items = collect(Arr::wrap($section['items']))
                    ->take(8)
                    ->map(function ($note): string {
                        if (! is_array($note)) {
                            return '';
                        }
                        $title = $this->text($note['title'] ?? '');
                        $body = $this->text($note['body'] ?? '');
                        $player = $this->text($note['player_name'] ?? '');
                        $heading = trim(($player !== '-' ? $player.' · ' : '').($title !== '-' ? $title : ''));

                        return '<li>'.($heading !== '' ? '<strong>'.$this->esc($heading).'</strong><br>' : '').$this->esc($body).'</li>';
                    })
                    ->filter()
                    ->implode('');

                return '<section><h2>'.$this->esc($section['title'] ?? 'Report Notes').'</h2><ul>'.$items.'</ul></section>';
            })
            ->filter()
            ->implode('');

        return $html !== '' ? '<div class="section-grid">'.$html.'</div>' : '';
    }

    private function htmlPlayerTable(array $rows, bool $show): string
    {
        if (! $show) {
            return '';
        }

        if (empty($rows)) {
            return '<section><h2>Player Summary</h2><p class="muted">No player completion data is available yet.</p></section>';
        }

        $body = collect($rows)->take(20)->map(fn (array $row): string => '<tr>'
            .'<td>'.$this->esc($row['player_name'] ?? 'Player').'</td>'
            .'<td>'.$this->esc($this->percent($row['completion_percentage'] ?? 0)).'</td>'
            .'<td>'.$this->esc(($row['plans_completed'] ?? 0).' / '.($row['plans_assigned'] ?? 0)).'</td>'
            .'<td>'.$this->esc($row['benchmark_values_submitted'] ?? 0).'</td>'
            .'<td>'.$this->esc($row['pending_review_count'] ?? 0).'</td>'
            .'<td>'.$this->esc($row['approved_count'] ?? 0).'</td>'
            .'</tr>')->implode('');

        return '<section><h2>Player Summary</h2><table><thead><tr><th>Player</th><th>Completion</th><th>Plans</th><th>Submitted</th><th>Review</th><th>Approved</th></tr></thead><tbody>'.$body.'</tbody></table></section>';
    }

    private function statCard(string $label, mixed $value, mixed $detail): string
    {
        return '<div class="card"><div class="muted">'.$this->esc($label).'</div><div class="value">'.$this->esc($value).'</div><div class="muted">'.$this->esc($detail).'</div></div>';
    }

    private function emptyPdf(): array
    {
        return [
            'available' => false,
            'file_path' => null,
            'download_url' => null,
            'warnings' => [],
        ];
    }

    private function audienceWarnings(string $audience): array
    {
        return match ($audience) {
            'parents' => ['Parent version hides private player review details.'],
            'players' => ['Player version hides other players’ private details.'],
            default => [],
        };
    }

    private function optionIn(string $value, array $allowed, string $fallback): string
    {
        $normalized = strtolower(trim($value));

        return in_array($normalized, $allowed, true) ? $normalized : $fallback;
    }

    private function teamName(string $teamId): string
    {
        return Team::query()->whereKey($teamId)->value('name') ?: 'Team';
    }

    private function display(string $value): string
    {
        return str($value)->replace(['_', '-'], ' ')->title()->toString();
    }

    private function percent(mixed $value): string
    {
        return number_format((float) $value, 1).'%';
    }

    private function text(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($item): string => $this->text($item), $value));
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return trim((string) $value);
    }

    private function esc(mixed $value): string
    {
        return htmlspecialchars($this->text($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value;
    }
}
