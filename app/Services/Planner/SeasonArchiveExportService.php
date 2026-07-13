<?php

declare(strict_types=1);

namespace App\Services\Planner;

use App\Models\Team;
use Illuminate\Support\Arr;

class SeasonArchiveExportService
{
    private const AUDIENCES = ['coach', 'staff', 'director', 'players', 'parents'];

    private const NOTE_AUDIENCES = [
        'coach' => 'coach',
        'staff' => 'staff',
        'director' => 'staff',
        'players' => 'players',
        'parents' => 'parents',
    ];

    private const FORMATS = ['summary', 'text', 'html', 'pdf'];

    public function __construct(
        private readonly SeasonDevelopmentArchiveService $archiveService,
        private readonly WeeklyReportNotesService $notesService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildExport(string $teamId, array $options = []): array
    {
        $format = $this->optionIn((string) ($options['format'] ?? 'summary'), self::FORMATS, 'summary');
        $audience = $this->optionIn((string) ($options['audience'] ?? 'staff'), self::AUDIENCES, 'staff');
        $warnings = [];

        $archive = $this->archiveService->buildTeamSeasonArchive($teamId, [
            'season_start_date' => $options['season_start_date'] ?? $options['start_date'] ?? null,
            'season_end_date' => $options['season_end_date'] ?? $options['end_date'] ?? null,
            'weeks' => $options['weeks'] ?? 12,
            'include_player_rows' => $this->bool($options['include_player_rows'] ?? true),
            'include_benchmark_progress' => $this->bool($options['include_benchmark_progress'] ?? true),
            'include_report_delivery' => $this->bool($options['include_report_delivery'] ?? ($options['include_communication_summary'] ?? true)),
            'include_communication_rhythm' => $this->bool($options['include_communication_summary'] ?? true),
            'include_weekly_reports' => $this->bool($options['include_weekly_timeline'] ?? true),
        ]);
        $archive['team_name'] = $this->teamName($teamId);
        $archive['staff_notes'] = $this->buildSeasonNotes($teamId, $archive, $audience, $options);

        $filteredArchive = $this->buildAudienceFilteredArchive($archive, $audience, $options);
        $packet = $this->buildStaffPacket($filteredArchive, [
            ...$options,
            'audience' => $audience,
        ]);
        $shareText = in_array($format, ['summary', 'text', 'pdf'], true)
            ? $this->buildShareText($packet, ['audience' => $audience, ...$options])
            : null;
        $html = $format === 'html'
            ? $this->buildHtmlPacket($packet, ['audience' => $audience, ...$options])
            : null;
        $pdf = $format === 'pdf'
            ? $this->buildPdfPacket($packet, ['audience' => $audience, ...$options])
            : $this->emptyPdf();

        foreach ($this->audienceWarnings($audience, $options) as $warning) {
            $warnings[] = $warning;
        }
        if ($format === 'pdf' && ($pdf['available'] ?? false) === false) {
            $warnings[] = 'PDF export is not configured yet. Use printable HTML or copy text.';
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'format' => $format,
            'audience' => $audience,
            'archive' => $filteredArchive,
            'packet' => $packet,
            'share_text' => $shareText,
            'html' => $html,
            'pdf' => $pdf,
            'warnings' => array_values(array_unique(array_filter([
                ...$warnings,
                ...Arr::wrap($pdf['warnings'] ?? []),
                ...Arr::wrap($filteredArchive['privacy_warnings'] ?? []),
            ]))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStaffPacket(array $archive, array $options = []): array
    {
        $audience = $this->optionIn((string) ($options['audience'] ?? 'staff'), self::AUDIENCES, 'staff');
        $includeTimeline = $this->bool($options['include_weekly_timeline'] ?? true);
        $includeBenchmark = $this->bool($options['include_benchmark_progress'] ?? true);
        $includePlanner = $this->bool($options['include_planner_progress'] ?? true);
        $includeCommunication = $this->bool($options['include_communication_summary'] ?? true);
        $includeNextSteps = $this->bool($options['include_next_steps'] ?? true);

        return $this->stripInternalIds([
            'title' => 'FMTRX Season Development Review',
            'subtitle' => $this->subtitleForAudience($audience),
            'team_name' => $archive['team_name'] ?? 'Team',
            'season_range' => $archive['season_label'] ?? (($archive['season_start_date'] ?? '-').' - '.($archive['season_end_date'] ?? '-')),
            'audience' => $audience,
            'executive_summary' => Arr::wrap($archive['executive_summary'] ?? []),
            'season_scorecard' => $this->seasonScorecard(Arr::wrap($archive['season_totals'] ?? [])),
            'weekly_timeline' => $includeTimeline ? Arr::wrap($archive['weekly_timeline'] ?? []) : [],
            'benchmark_progress' => $includeBenchmark ? Arr::wrap($archive['benchmark_progress'] ?? []) : [],
            'planner_progress' => $includePlanner ? Arr::wrap($archive['planner_progress'] ?? []) : [],
            'communication_summary' => $includeCommunication ? Arr::wrap($archive['communication_summary'] ?? []) : [],
            'player_development_summary' => $this->includePlayerRows($audience, $options) ? Arr::wrap($archive['player_development_summary'] ?? []) : [],
            'staff_notes' => $this->includeStaffNotes($audience, $options) ? Arr::wrap($archive['staff_notes'] ?? []) : [],
            'season_highlights' => Arr::wrap($archive['season_highlights'] ?? []),
            'season_concerns' => Arr::wrap($archive['season_concerns'] ?? []),
            'recommended_next_steps' => $includeNextSteps ? Arr::wrap($archive['recommended_next_steps'] ?? []) : [],
            'appendix' => $this->appendix($audience),
        ]);
    }

    public function buildShareText(array $packet, array $options = []): string
    {
        $audience = $this->optionIn((string) ($options['audience'] ?? ($packet['audience'] ?? 'staff')), self::AUDIENCES, 'staff');
        $summary = Arr::wrap($packet['executive_summary'] ?? []);
        $scorecard = Arr::wrap($packet['season_scorecard'] ?? []);
        $benchmark = Arr::wrap($packet['benchmark_progress'] ?? []);
        $communication = Arr::wrap($packet['communication_summary'] ?? []);
        $lines = [
            'FMTRX Season Development Review',
            'Team: '.$this->text($packet['team_name'] ?? 'Team'),
            'Season: '.$this->text($packet['season_range'] ?? '-'),
            'Audience: '.$this->display($audience),
            '',
            'Executive Summary:',
            $this->text($summary['headline'] ?? 'Season archive is not available yet.'),
            $this->text($summary['summary_text'] ?? ''),
            '',
            'Season Scorecard',
            '- Weeks analyzed: '.$this->text($scorecard['weeks_analyzed']['value'] ?? 0),
            '- Plans published: '.$this->text($scorecard['daily_plans_published']['value'] ?? 0),
            '- Average completion: '.$this->percent($scorecard['average_completion_percentage']['value'] ?? 0),
            '- Benchmark values approved: '.$this->text($scorecard['benchmark_values_approved']['value'] ?? 0),
            '- Trusted values promoted: '.$this->text($scorecard['trusted_values_promoted']['value'] ?? 0),
            '- Reports shared: '.$this->text($scorecard['reports_sent_or_copied']['value'] ?? 0),
        ];

        $this->appendList($lines, 'Top Wins', Arr::wrap($packet['season_highlights'] ?? []), 'No season highlights are available yet.');
        $this->appendList($lines, $audience === 'parents' ? 'Needs Attention' : 'Season Concerns', Arr::wrap($packet['season_concerns'] ?? []), 'No urgent season concerns are surfaced.');

        if (! in_array($audience, ['parents', 'players'], true)) {
            $this->appendList($lines, 'Benchmark Progress', [
                'Trusted values added: '.$this->text($benchmark['trusted_values_added'] ?? 0),
                'Current benchmark confidence: '.$this->display((string) ($benchmark['current_benchmark_confidence'] ?? 'unknown')),
                'Remaining missing metrics: '.$this->metricNames(Arr::wrap($benchmark['remaining_missing_metrics'] ?? [])),
            ], 'No benchmark progress recorded in this date range.');
            $this->appendPlayerSummaryText($lines, Arr::wrap($packet['player_development_summary'] ?? []));
            $this->appendNotesText($lines, Arr::wrap($packet['staff_notes'] ?? []));
        }

        $this->appendList($lines, 'Communication Summary', [
            'Parent updates: '.$this->text($communication['parent_updates'] ?? 0),
            'Staff reports: '.$this->text($communication['staff_reports'] ?? 0),
            'Reports shared: '.$this->text($communication['reports_shared'] ?? 0),
            'Rhythm: '.$this->display((string) ($communication['communication_rhythm_label'] ?? 'unknown')).' · '.$this->text($communication['communication_rhythm_score'] ?? '-'),
        ], 'No communication history recorded in this date range.');
        $this->appendList($lines, 'Next Steps', Arr::wrap($packet['recommended_next_steps'] ?? []), 'Generate the next development block from FMTRX Intelligence.');

        $lines[] = '';
        $lines[] = $this->privacyFooter($audience);
        $lines[] = 'Generated by FMTRX.';

        return trim(implode("\n", array_filter($lines, fn ($line): bool => $line !== null)));
    }

    public function buildHtmlPacket(array $packet, array $options = []): string
    {
        $audience = $this->optionIn((string) ($options['audience'] ?? ($packet['audience'] ?? 'staff')), self::AUDIENCES, 'staff');
        $summary = Arr::wrap($packet['executive_summary'] ?? []);
        $scorecard = Arr::wrap($packet['season_scorecard'] ?? []);
        $timeline = Arr::wrap($packet['weekly_timeline'] ?? []);
        $benchmark = Arr::wrap($packet['benchmark_progress'] ?? []);
        $planner = Arr::wrap($packet['planner_progress'] ?? []);
        $communication = Arr::wrap($packet['communication_summary'] ?? []);
        $players = Arr::wrap($packet['player_development_summary'] ?? []);

        return '<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>FMTRX Season Development Review</title>
<style>
  :root { color-scheme: light; }
  body { margin:0; background:#f3f6fb; color:#0f172a; font-family:Arial, Helvetica, sans-serif; }
  .packet { max-width:1040px; margin:0 auto; padding:28px; }
  .header { display:flex; justify-content:space-between; gap:18px; border-bottom:4px solid #e11d48; padding-bottom:18px; margin-bottom:20px; }
  h1 { margin:0; font-size:31px; letter-spacing:.02em; }
  h2 { margin:0 0 10px; font-size:15px; letter-spacing:.08em; text-transform:uppercase; color:#be123c; }
  h3 { margin:0 0 6px; font-size:17px; }
  p { line-height:1.5; }
  .muted { color:#64748b; font-size:13px; }
  .grid { display:grid; grid-template-columns:repeat(4, minmax(0, 1fr)); gap:10px; margin:18px 0; }
  .section-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-top:12px; }
  .card, section { background:#fff; border:1px solid #dbe3ef; border-radius:14px; padding:14px; box-shadow:0 8px 24px rgba(15,23,42,.06); }
  .value { font-size:25px; font-weight:900; margin-top:5px; }
  ul { margin:8px 0 0; padding-left:20px; }
  li { margin:4px 0; }
  table { width:100%; border-collapse:collapse; font-size:12.5px; }
  th, td { text-align:left; border-bottom:1px solid #e2e8f0; padding:8px 6px; vertical-align:top; }
  th { color:#475569; text-transform:uppercase; font-size:10.5px; letter-spacing:.06em; }
  .timeline { display:grid; gap:10px; }
  .week { border-left:4px solid #e11d48; }
  .footer { color:#64748b; font-size:12px; margin-top:18px; border-top:1px solid #dbe3ef; padding-top:12px; }
  @media (max-width:760px) { .grid, .section-grid { grid-template-columns:1fr; } .header { flex-direction:column; } .packet { padding:16px; } }
  @media print { @page { size:A4 portrait; margin:10mm; } body { background:#fff; } .packet { padding:0; } .card, section { box-shadow:none; break-inside:avoid; } }
</style>
</head>
<body>
<main class="packet">
  <div class="header">
    <div>
      <h1>FMTRX Season Development Review</h1>
      <div class="muted">'.$this->esc($packet['team_name'] ?? 'Team').' · '.$this->esc($packet['season_range'] ?? '-').'</div>
    </div>
    <div class="muted">Audience: '.$this->esc($this->display($audience)).'<br>Generated: '.$this->esc(now()->format('M j, Y g:i A')).'</div>
  </div>

  <section>
    <h2>Executive Summary</h2>
    <h3>'.$this->esc($summary['headline'] ?? 'Season archive is not available yet.').'</h3>
    <p>'.$this->esc($summary['season_story'] ?? $summary['summary_text'] ?? '').'</p>
    <div class="section-grid">
      '.$this->htmlList('Top Wins', Arr::wrap($packet['season_highlights'] ?? []), 'No season highlights are available yet.').'
      '.$this->htmlList($audience === 'parents' ? 'Needs Attention' : 'Season Concerns', Arr::wrap($packet['season_concerns'] ?? []), 'No urgent season concerns are surfaced.').'
    </div>
  </section>

  <div class="grid">
    '.$this->statCard('Weeks', $scorecard['weeks_analyzed']['value'] ?? 0, 'analyzed').'
    '.$this->statCard('Plans Published', $scorecard['daily_plans_published']['value'] ?? 0, 'development plans').'
    '.$this->statCard('Completion', $this->percent($scorecard['average_completion_percentage']['value'] ?? 0), 'average').'
    '.$this->statCard('Trusted Values', $scorecard['trusted_values_promoted']['value'] ?? 0, 'promoted').'
    '.$this->statCard('Reports Shared', $scorecard['reports_sent_or_copied']['value'] ?? 0, 'sent or copied').'
    '.$this->statCard('Rhythm Score', $scorecard['communication_rhythm_score']['value'] ?? '-', $this->display((string) ($communication['communication_rhythm_label'] ?? 'unknown'))).'
  </div>

  '.$this->htmlTimeline($timeline).'
  <div class="section-grid">
    '.$this->htmlBenchmarkSection($benchmark).'
    '.$this->htmlPlannerSection($planner).'
    '.$this->htmlCommunicationSection($communication).'
    '.$this->htmlList('Recommended Next Steps', Arr::wrap($packet['recommended_next_steps'] ?? []), 'Generate the next development block from FMTRX Intelligence.').'
  </div>
  '.$this->htmlNotes(Arr::wrap($packet['staff_notes'] ?? [])).'
  '.$this->htmlPlayerTable($players, ! in_array($audience, ['parents', 'players'], true)).'
  <div class="footer">'.$this->esc($this->privacyFooter($audience)).'</div>
</main>
</body>
</html>';
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPdfPacket(array $packet, array $options = []): array
    {
        return [
            'available' => false,
            'file_path' => null,
            'download_url' => null,
            'warnings' => [
                'PDF export is not configured yet. Use printable HTML or copy text.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAudienceFilteredArchive(array $archive, string $audience = 'staff', array $options = []): array
    {
        $audience = $this->optionIn($audience, self::AUDIENCES, 'staff');
        $filtered = $archive;
        $warnings = [];

        if (! $this->bool($options['include_weekly_timeline'] ?? true)) {
            $filtered['weekly_timeline'] = [];
        }
        if (! $this->bool($options['include_benchmark_progress'] ?? true)) {
            $filtered['benchmark_progress'] = [];
        }
        if (! $this->bool($options['include_planner_progress'] ?? true)) {
            $filtered['planner_progress'] = [];
        }
        if (! $this->bool($options['include_communication_summary'] ?? true)) {
            $filtered['communication_summary'] = [];
        }
        if (! $this->bool($options['include_next_steps'] ?? true)) {
            $filtered['recommended_next_steps'] = [];
        }

        if (! $this->includePlayerRows($audience, $options)) {
            $filtered['player_development_summary'] = [];
        }

        if (! $this->includeStaffNotes($audience, $options)) {
            $filtered['staff_notes'] = [];
        }

        if ($audience === 'director') {
            $filtered['warnings'] = [];
            $filtered['evidence'] = [];
            $filtered['weekly_timeline'] = $this->stripWeeklyRawDetails(Arr::wrap($filtered['weekly_timeline'] ?? []), true);
        }

        if ($audience === 'players') {
            $filtered['player_development_summary'] = [];
            $filtered['staff_notes'] = [];
            $filtered['warnings'] = [];
            $filtered['evidence'] = [];
            $filtered['weekly_timeline'] = $this->stripWeeklyRawDetails(Arr::wrap($filtered['weekly_timeline'] ?? []), false);
            $filtered['benchmark_progress'] = $this->publicBenchmarkProgress(Arr::wrap($filtered['benchmark_progress'] ?? []));
            $filtered['season_concerns'] = $this->publicConcerns(Arr::wrap($filtered['season_concerns'] ?? []));
        }

        if ($audience === 'parents') {
            $filtered['player_development_summary'] = [];
            $filtered['staff_notes'] = [];
            $filtered['warnings'] = [];
            $filtered['evidence'] = [];
            $filtered['weekly_timeline'] = $this->stripWeeklyRawDetails(Arr::wrap($filtered['weekly_timeline'] ?? []), false);
            $filtered['benchmark_progress'] = $this->publicBenchmarkProgress(Arr::wrap($filtered['benchmark_progress'] ?? []));
            $filtered['planner_progress']['missed_work_trends'] = [];
            $filtered['season_concerns'] = $this->publicConcerns(Arr::wrap($filtered['season_concerns'] ?? []));
            $filtered['executive_summary']['summary_text'] = 'This parent-safe season review summarizes team development habits, benchmark collection progress, and communication rhythm without private player review details.';
        }

        $filtered['privacy_warnings'] = array_values(array_filter($warnings));

        return $this->stripInternalIds($filtered);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSeasonNotes(string $teamId, array $archive, string $audience, array $options): array
    {
        if (! $this->includeStaffNotes($audience, $options)) {
            return [];
        }

        $noteAudience = self::NOTE_AUDIENCES[$audience] ?? 'staff';
        $includePrivate = $audience === 'coach'
            && $this->bool($options['include_private_notes'] ?? false);
        $includeInternal = $this->bool($options['include_internal_qa'] ?? false)
            && in_array($audience, ['coach', 'staff', 'director'], true);

        $notes = collect(Arr::wrap($archive['weekly_timeline'] ?? []))
            ->flatMap(function (array $week) use ($teamId, $noteAudience, $includePrivate, $options): array {
                return $this->notesService->buildNotesForExport($teamId, $noteAudience, [
                    ...$options,
                    'start_date' => $week['week_start_date'] ?? null,
                    'end_date' => $week['week_end_date'] ?? null,
                    'include_private_notes' => $includePrivate,
                ]);
            })
            ->filter(function (array $note) use ($includeInternal): bool {
                return $includeInternal || (string) ($note['note_type'] ?? '') !== 'internal_context';
            })
            ->values()
            ->all();

        return Arr::wrap($this->notesService->mergeNotesIntoReport([], $notes, $noteAudience)['report_notes']['sections'] ?? []);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function seasonScorecard(array $totals): array
    {
        return [
            'weeks_analyzed' => $this->score('Weeks Analyzed', $totals['weeks_analyzed'] ?? 0, 'weeks'),
            'daily_plans_published' => $this->score('Plans Published', $totals['daily_plans_published'] ?? 0, 'plans'),
            'average_completion_percentage' => $this->score('Average Completion', $totals['average_completion_percentage'] ?? 0, '%'),
            'benchmark_values_submitted' => $this->score('Benchmark Values Submitted', $totals['benchmark_values_submitted'] ?? 0, 'values'),
            'benchmark_values_approved' => $this->score('Benchmark Values Approved', $totals['benchmark_values_approved'] ?? 0, 'values'),
            'trusted_values_promoted' => $this->score('Trusted Values Promoted', $totals['trusted_values_promoted'] ?? 0, 'values'),
            'reports_created' => $this->score('Reports Created', $totals['reports_created'] ?? 0, 'reports'),
            'reports_sent_or_copied' => $this->score('Reports Shared', $totals['reports_sent_or_copied'] ?? 0, 'reports'),
            'communication_rhythm_score' => $this->score('Communication Rhythm Score', $totals['communication_rhythm_score'] ?? null, 'score'),
        ];
    }

    private function score(string $label, mixed $value, string $unit): array
    {
        return [
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
        ];
    }

    private function includePlayerRows(string $audience, array $options): bool
    {
        return $this->bool($options['include_player_rows'] ?? true)
            && in_array($audience, ['coach', 'staff', 'director'], true);
    }

    private function includeStaffNotes(string $audience, array $options): bool
    {
        return in_array($audience, ['coach', 'staff', 'director'], true);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function stripWeeklyRawDetails(array $rows, bool $keepOperationalCounts): array
    {
        return collect($rows)
            ->map(function (array $row) use ($keepOperationalCounts): array {
                unset($row['player_rollups'], $row['evidence'], $row['warnings']);
                if (! $keepOperationalCounts) {
                    unset($row['pending_review_count'], $row['top_remaining_gaps']);
                }

                return $row;
            })
            ->values()
            ->all();
    }

    private function publicBenchmarkProgress(array $benchmark): array
    {
        unset(
            $benchmark['pending_review_count'],
            $benchmark['correction_requested_count'],
            $benchmark['players_with_new_trusted_data'],
            $benchmark['remaining_missing_metrics'],
            $benchmark['population_learning_status'],
            $benchmark['evidence'],
        );

        return $benchmark;
    }

    private function publicConcerns(array $concerns): array
    {
        return collect($concerns)
            ->reject(function ($concern): bool {
                $text = strtolower((string) $concern);

                return str_contains($text, 'pending')
                    || str_contains($text, 'correction')
                    || str_contains($text, 'rejected')
                    || str_contains($text, 'missed');
            })
            ->values()
            ->all() ?: ['No urgent team development concerns are surfaced.'];
    }

    private function appendix(string $audience): array
    {
        $items = [
            [
                'title' => 'Trusted Data',
                'body' => 'Trusted values are benchmark submissions that have been reviewed and approved or promoted into FMTRX development intelligence.',
            ],
            [
                'title' => 'Benchmark Sources',
                'body' => 'FMTRX uses research benchmarks and guarded population learning when enough valid internal data exists.',
            ],
            [
                'title' => 'Communication Rhythm',
                'body' => 'Communication rhythm summarizes how consistently weekly reports and updates were created or shared.',
            ],
        ];

        if (in_array($audience, ['parents', 'players'], true)) {
            $items[] = [
                'title' => 'Privacy',
                'body' => 'This version hides private player review details, staff notes, raw payloads, and internal QA.',
            ];
        }

        return $items;
    }

    private function subtitleForAudience(string $audience): string
    {
        return match ($audience) {
            'parents' => 'Parent-safe team development summary',
            'players' => 'Player-safe team development summary',
            'director' => 'Director review packet',
            'coach' => 'Coach operational review packet',
            default => 'Staff review packet',
        };
    }

    private function appendPlayerSummaryText(array &$lines, array $rows): void
    {
        $lines[] = '';
        $lines[] = 'Player Development Summary:';
        if (empty($rows)) {
            $lines[] = '- No player development rows are included for this audience.';

            return;
        }

        foreach (array_slice($rows, 0, 16) as $row) {
            $lines[] = '- '.$this->text($row['player_name'] ?? 'Player').': '.$this->percent($row['completion_percentage'] ?? 0)
                .' completion, '.$this->text($row['benchmark_values_approved'] ?? 0).' approved values, '
                .$this->text(count(Arr::wrap($row['trusted_metrics_added'] ?? []))).' trusted metrics';
        }
    }

    private function appendNotesText(array &$lines, array $sections): void
    {
        foreach ($sections as $section) {
            if (! is_array($section) || empty($section['items'])) {
                continue;
            }

            $lines[] = '';
            $lines[] = $this->text($section['title'] ?? 'Staff Notes').':';
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

    private function htmlTimeline(array $timeline): string
    {
        if (empty($timeline)) {
            return '<section><h2>Weekly Timeline</h2><p class="muted">No weekly timeline is available for this season range.</p></section>';
        }

        $rows = collect($timeline)->take(16)->map(fn (array $row): string => '<section class="week">'
            .'<h3>'.$this->esc($row['week_label'] ?? 'Week').'</h3>'
            .'<p>'.$this->esc($row['headline'] ?? '').'</p>'
            .'<div class="muted">Status: '.$this->esc($this->display((string) ($row['status_label'] ?? 'unknown')))
            .' · Completion: '.$this->esc($this->percent($row['team_completion_percentage'] ?? 0))
            .' · Approved benchmarks: '.$this->esc($row['benchmark_values_approved'] ?? 0)
            .' · Reports shared: '.$this->esc($row['reports_shared'] ?? 0).'</div>'
            .($row['primary_focus'] ?? null ? '<div class="muted">Focus: '.$this->esc($row['primary_focus']).'</div>' : '')
            .'</section>')->implode('');

        return '<section><h2>Weekly Timeline</h2><div class="timeline">'.$rows.'</div></section>';
    }

    private function htmlBenchmarkSection(array $benchmark): string
    {
        return '<section><h2>Benchmark Progress</h2><ul>'
            .'<li>Trusted values added: '.$this->esc($benchmark['trusted_values_added'] ?? 0).'</li>'
            .'<li>Current confidence: '.$this->esc($this->display((string) ($benchmark['current_benchmark_confidence'] ?? 'unknown'))).'</li>'
            .'<li>Metrics improved: '.$this->esc($this->metricNames(Arr::wrap($benchmark['metrics_improved'] ?? []))).'</li>'
            .'<li>Remaining missing metrics: '.$this->esc($this->metricNames(Arr::wrap($benchmark['remaining_missing_metrics'] ?? []))).'</li>'
            .'</ul></section>';
    }

    private function htmlPlannerSection(array $planner): string
    {
        return '<section><h2>Planner Progress</h2><ul>'
            .'<li>Plans created: '.$this->esc($planner['plans_created'] ?? 0).'</li>'
            .'<li>Plans published: '.$this->esc($planner['plans_published'] ?? 0).'</li>'
            .'<li>Completion: '.$this->esc($this->percent($planner['completion_percentage'] ?? 0)).'</li>'
            .'<li>Players needing follow-up: '.$this->esc($planner['players_needing_follow_up_count'] ?? 0).'</li>'
            .'</ul></section>';
    }

    private function htmlCommunicationSection(array $communication): string
    {
        return '<section><h2>Communication Summary</h2><ul>'
            .'<li>Reports created: '.$this->esc($communication['reports_created'] ?? 0).'</li>'
            .'<li>Reports shared: '.$this->esc($communication['reports_shared'] ?? 0).'</li>'
            .'<li>Parent updates: '.$this->esc($communication['parent_updates'] ?? 0).'</li>'
            .'<li>Staff reports: '.$this->esc($communication['staff_reports'] ?? 0).'</li>'
            .'<li>Rhythm: '.$this->esc($this->display((string) ($communication['communication_rhythm_label'] ?? 'unknown'))).' · '.$this->esc($communication['communication_rhythm_score'] ?? '-').'</li>'
            .'</ul></section>';
    }

    private function htmlNotes(array $sections): string
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

                return '<section><h2>'.$this->esc($section['title'] ?? 'Staff Notes').'</h2><ul>'.$items.'</ul></section>';
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
            return '<section><h2>Player Development Summary</h2><p class="muted">No player development data is available yet.</p></section>';
        }

        $body = collect($rows)->take(24)->map(fn (array $row): string => '<tr>'
            .'<td>'.$this->esc($row['player_name'] ?? 'Player').'</td>'
            .'<td>'.$this->esc($this->percent($row['completion_percentage'] ?? 0)).'</td>'
            .'<td>'.$this->esc(($row['plans_completed'] ?? 0).' / '.($row['plans_assigned'] ?? 0)).'</td>'
            .'<td>'.$this->esc($row['benchmark_values_approved'] ?? 0).'</td>'
            .'<td>'.$this->esc($this->metricNames(Arr::wrap($row['trusted_metrics_added'] ?? []))).'</td>'
            .'<td>'.$this->esc($row['next_recommended_action'] ?? '-').'</td>'
            .'</tr>')->implode('');

        return '<section><h2>Player Development Summary</h2><table><thead><tr><th>Player</th><th>Completion</th><th>Plans</th><th>Approved</th><th>Trusted Metrics</th><th>Next Action</th></tr></thead><tbody>'.$body.'</tbody></table></section>';
    }

    private function htmlList(string $title, array $items, string $empty): string
    {
        $rows = empty($items)
            ? '<li>'.$this->esc($empty).'</li>'
            : collect($items)->take(8)->map(fn ($item): string => '<li>'.$this->esc($item).'</li>')->implode('');

        return '<section><h2>'.$this->esc($title).'</h2><ul>'.$rows.'</ul></section>';
    }

    private function statCard(string $label, mixed $value, mixed $detail): string
    {
        return '<div class="card"><div class="muted">'.$this->esc($label).'</div><div class="value">'.$this->esc($value).'</div><div class="muted">'.$this->esc($detail).'</div></div>';
    }

    private function metricNames(array $rows): string
    {
        $names = collect($rows)
            ->map(function ($row): string {
                if (is_array($row)) {
                    return $this->text($row['display_name'] ?? $row['metric_key'] ?? 'Metric');
                }

                return $this->display((string) $row);
            })
            ->filter(fn (string $value): bool => $value !== '' && $value !== '-')
            ->take(8)
            ->values()
            ->all();

        return empty($names) ? '-' : implode(', ', $names);
    }

    private function privacyFooter(string $audience): string
    {
        return match ($audience) {
            'parents' => 'Parent version hides private player review details, staff notes, internal QA, and raw benchmark payloads.',
            'players' => 'Player version hides other-player private details, staff notes, internal QA, and raw benchmark payloads.',
            'director' => 'Director version hides raw payloads and private system identifiers.',
            default => 'Staff review packet hides raw payloads and keeps private data inside coach/staff workflows.',
        };
    }

    private function audienceWarnings(string $audience, array $options): array
    {
        $warnings = [];
        if ($audience === 'parents') {
            $warnings[] = 'Parent version hides private player details, staff notes, internal QA, and raw benchmark payloads.';
        }
        if ($audience === 'players') {
            $warnings[] = 'Player version hides other-player details, staff notes, internal QA, and raw benchmark payloads.';
        }
        if (in_array($audience, ['parents', 'players'], true) && $this->bool($options['include_player_rows'] ?? false)) {
            $warnings[] = 'Player rows were requested but removed for this audience.';
        }
        if (in_array($audience, ['parents', 'players'], true) && ($this->bool($options['include_private_notes'] ?? false) || $this->bool($options['include_internal_qa'] ?? false))) {
            $warnings[] = 'Private notes and internal QA were requested but removed for this audience.';
        }

        return $warnings;
    }

    private function stripInternalIds(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            $keyString = is_string($key) ? $key : '';
            if ($this->isInternalIdKey($keyString) || in_array($keyString, ['payload', 'submitted_payload', 'approved_payload', 'export_payload', 'draft_payload', 'evidence'], true)) {
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

    private function emptyPdf(): array
    {
        return [
            'available' => false,
            'file_path' => null,
            'download_url' => null,
            'warnings' => [],
        ];
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
        return number_format((float) ($value ?? 0), 1).'%';
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
