<?php

declare(strict_types=1);

namespace App\Services\Planner;

use Illuminate\Support\Arr;

class WeeklyReportTemplateService
{
    public const DEFAULT_TEMPLATE = 'detailed_coach_report';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(): array
    {
        return array_values($this->templates());
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplate(string $templateKey): array
    {
        return $this->templates()[$templateKey] ?? $this->templates()[self::DEFAULT_TEMPLATE];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveTemplate(?string $templateKey, string $audience = 'coach'): array
    {
        $templates = $this->templates();
        $key = $templateKey ? strtolower(trim($templateKey)) : '';

        if ($key !== '' && isset($templates[$key])) {
            return $templates[$key];
        }

        return $this->getTemplate($this->templateForAudience($audience));
    }

    public function templateForAudience(string $audience): string
    {
        return match (strtolower(trim($audience))) {
            'staff' => 'staff_report',
            'parents' => 'parent_update',
            'players' => 'player_development_summary',
            default => self::DEFAULT_TEMPLATE,
        };
    }

    public function effectiveAudience(array $template, string $requestedAudience = 'coach'): string
    {
        $templateKey = (string) ($template['template_key'] ?? '');
        $requested = $this->allowedAudience($requestedAudience);

        if ($templateKey === 'short_text_summary') {
            return $requested;
        }

        $audience = (string) ($template['audience'] ?? $requested);

        return match ($audience) {
            'staff' => 'staff',
            'parents' => 'parents',
            'players' => 'players',
            'internal' => 'coach',
            'coach' => 'coach',
            default => $requested,
        };
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, mixed>
     */
    public function applyTemplate(array $report, string $templateKey, array $options = []): array
    {
        $template = $this->resolveTemplate($templateKey, (string) ($options['audience'] ?? 'coach'));
        $report['report_template'] = $template;
        $report['template_key'] = $template['template_key'];

        $sectionKeys = $this->reportSectionKeys();
        $allowed = collect(Arr::wrap($template['sections'] ?? []))
            ->flatMap(fn ($section): array => $this->sectionAliases((string) $section))
            ->unique()
            ->values()
            ->all();

        foreach ($sectionKeys as $key) {
            if (! in_array($key, $allowed, true)) {
                $report[$key] = $this->emptySectionValue($key);
            }
        }

        if (! (bool) ($template['include_player_rows'] ?? true)) {
            $report['player_rows'] = [];
        }
        if (! (bool) ($template['include_benchmark_details'] ?? true)) {
            $report['benchmark_submission_summary'] = [];
        }
        if (! (bool) ($template['include_pending_reviews'] ?? true)) {
            $report['review_summary']['tasks_pending_review'] = [];
            $report['review_summary']['players_needing_correction'] = [];
            $report['benchmark_submission_summary']['tasks_pending_review'] = [];
            $report['benchmark_submission_summary']['players_needing_correction'] = [];
        }
        if (! (bool) ($template['include_next_week_priorities'] ?? true)) {
            $report['next_week_priorities'] = [];
        }

        $report['template_summary'] = [
            'display_name' => $template['display_name'],
            'tone' => $template['tone'],
            'max_player_detail_level' => $template['max_player_detail_level'],
            'benchmark_detail_level' => $template['benchmark_detail_level'],
            'copy_rules' => Arr::wrap($template['copy_rules'] ?? []),
        ];

        return $report;
    }

    /**
     * @param array<int, array<string, mixed>> $notes
     * @param array<string, mixed> $template
     * @return array<int, array<string, mixed>>
     */
    public function filterNotesForTemplate(array $notes, array $template): array
    {
        return collect($notes)
            ->filter(function (array $note) use ($template): bool {
                $type = (string) ($note['note_type'] ?? '');
                $visibility = (string) ($note['visibility'] ?? '');

                if ($visibility === 'private' && ! (bool) ($template['include_private_notes'] ?? false)) {
                    return false;
                }
                if (in_array($type, ['staff_note', 'internal_context'], true) && ! (bool) ($template['include_staff_notes'] ?? false)) {
                    return false;
                }
                if ($type === 'parent_summary' && ! (bool) ($template['include_parent_notes'] ?? false)) {
                    return false;
                }
                if ($type === 'player_message' && ! (bool) ($template['include_player_messages'] ?? false)) {
                    return false;
                }
                if ($type === 'next_week_emphasis' && ! (bool) ($template['include_next_week_priorities'] ?? true)) {
                    return false;
                }
                if ($type === 'internal_context' && ! (bool) ($template['include_internal_qa'] ?? false)) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTemplatePreview(string $teamId, string $templateKey, array $options = []): array
    {
        $template = $this->resolveTemplate($templateKey, (string) ($options['audience'] ?? 'coach'));

        return [
            'generated_at' => now()->toIso8601String(),
            'team_id' => $teamId,
            'template' => $template,
            'effective_audience' => $this->effectiveAudience($template, (string) ($options['audience'] ?? 'coach')),
            'included_sections' => Arr::wrap($template['sections'] ?? []),
            'copy_rules' => Arr::wrap($template['copy_rules'] ?? []),
            'warnings' => $this->templateWarnings($template),
        ];
    }

    /**
     * @param array<string, mixed> $template
     * @return array<int, string>
     */
    public function templateWarnings(array $template): array
    {
        $key = (string) ($template['template_key'] ?? '');

        return array_values(array_filter([
            in_array($key, ['parent_update', 'player_development_summary'], true)
                ? 'Parent/player templates hide private player review details.'
                : null,
            $key === 'internal_benchmark_qa'
                ? 'Internal Benchmark QA is coach/staff only.'
                : null,
        ]));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function templates(): array
    {
        return [
            'staff_report' => [
                'template_key' => 'staff_report',
                'display_name' => 'Staff Report',
                'audience' => 'staff',
                'description' => 'A detailed weekly operations report for coaches and staff.',
                'tone' => 'professional',
                'sections' => [
                    'executive_summary',
                    'team_completion',
                    'player_rows',
                    'benchmark_submission_summary',
                    'review_summary',
                    'trusted_data_summary',
                    'missed_work_summary',
                    'coach_follow_ups',
                    'next_week_priorities',
                    'staff_notes',
                    'coach_comments',
                ],
                'include_player_rows' => true,
                'include_benchmark_details' => true,
                'include_pending_reviews' => true,
                'include_private_notes' => false,
                'include_staff_notes' => true,
                'include_parent_notes' => false,
                'include_player_messages' => false,
                'include_next_week_priorities' => true,
                'include_internal_qa' => false,
                'max_player_detail_level' => 'full',
                'benchmark_detail_level' => 'standard',
                'copy_rules' => [
                    'Use direct staff operations language.',
                    'Include actionable follow-ups and pending review context.',
                    'Hide raw internal IDs.',
                ],
            ],
            'parent_update' => [
                'template_key' => 'parent_update',
                'display_name' => 'Parent Update',
                'audience' => 'parents',
                'description' => "A parent-safe weekly update focused on development, effort, and next week's focus.",
                'tone' => 'encouraging',
                'sections' => [
                    'executive_summary',
                    'weekly_completion_summary',
                    'team_wins',
                    'next_week_focus',
                    'parent_summary_notes',
                ],
                'include_player_rows' => false,
                'include_benchmark_details' => true,
                'include_pending_reviews' => false,
                'include_private_notes' => false,
                'include_staff_notes' => false,
                'include_parent_notes' => true,
                'include_player_messages' => false,
                'include_next_week_priorities' => true,
                'include_internal_qa' => false,
                'max_player_detail_level' => 'none',
                'benchmark_detail_level' => 'simple',
                'copy_rules' => [
                    'Use positive development language.',
                    'No private player comparisons.',
                    'No pending review, rejected, or correction details.',
                    'No raw metric payloads.',
                ],
            ],
            'player_development_summary' => [
                'template_key' => 'player_development_summary',
                'display_name' => 'Player Development Summary',
                'audience' => 'players',
                'description' => 'A player-facing summary focused on completed work, submitted results, and what to focus on next.',
                'tone' => 'encouraging',
                'sections' => [
                    'player_message',
                    'weekly_completion_summary',
                    'benchmark_results_summary',
                    'approved_results',
                    'next_week_focus',
                ],
                'include_player_rows' => false,
                'include_benchmark_details' => true,
                'include_pending_reviews' => false,
                'include_private_notes' => false,
                'include_staff_notes' => false,
                'include_parent_notes' => false,
                'include_player_messages' => true,
                'include_next_week_priorities' => true,
                'include_internal_qa' => false,
                'max_player_detail_level' => 'none',
                'benchmark_detail_level' => 'simple',
                'copy_rules' => [
                    'No other-player details.',
                    'No team-private review queue.',
                    'Use simple benchmark language.',
                ],
            ],
            'internal_benchmark_qa' => [
                'template_key' => 'internal_benchmark_qa',
                'display_name' => 'Internal Benchmark QA',
                'audience' => 'internal',
                'description' => 'A technical internal QA report for population learning, benchmark trust, missing data, and review status.',
                'tone' => 'technical',
                'sections' => [
                    'executive_summary',
                    'benchmark_submission_summary',
                    'review_summary',
                    'trusted_data_summary',
                    'population_learning_status',
                    'data_quality_flags',
                    'missing_metrics',
                    'source_mix',
                    'trust_policy_status',
                    'internal_context',
                ],
                'include_player_rows' => false,
                'include_benchmark_details' => true,
                'include_pending_reviews' => true,
                'include_private_notes' => false,
                'include_staff_notes' => true,
                'include_parent_notes' => false,
                'include_player_messages' => false,
                'include_next_week_priorities' => false,
                'include_internal_qa' => true,
                'max_player_detail_level' => 'summary',
                'benchmark_detail_level' => 'technical',
                'copy_rules' => [
                    'Use technical QA language.',
                    'Include guardrail, trust, and missing data context when available.',
                    'Never export to parents or players.',
                ],
            ],
            'short_text_summary' => [
                'template_key' => 'short_text_summary',
                'display_name' => 'Short Text Summary',
                'audience' => 'coach',
                'supports_audiences' => ['coach', 'staff', 'parents'],
                'description' => 'A short copy-ready report for text message, email, or group chat.',
                'tone' => 'simple',
                'sections' => [
                    'headline',
                    'wins',
                    'needs_attention',
                    'next_week_focus',
                ],
                'include_player_rows' => false,
                'include_benchmark_details' => false,
                'include_pending_reviews' => false,
                'include_private_notes' => false,
                'include_staff_notes' => false,
                'include_parent_notes' => true,
                'include_player_messages' => false,
                'include_next_week_priorities' => true,
                'include_internal_qa' => false,
                'max_player_detail_level' => 'none',
                'benchmark_detail_level' => 'none',
                'copy_rules' => [
                    'Short paragraphs.',
                    'Use bullet points.',
                    'No tables.',
                    'No raw metric keys.',
                ],
            ],
            self::DEFAULT_TEMPLATE => [
                'template_key' => self::DEFAULT_TEMPLATE,
                'display_name' => 'Detailed Coach Report',
                'audience' => 'coach',
                'description' => 'Full coach report with player completion, benchmark submissions, pending review, trusted data, and next-week priorities.',
                'tone' => 'direct',
                'sections' => [
                    'executive_summary',
                    'team_completion',
                    'player_rows',
                    'benchmark_submission_summary',
                    'review_summary',
                    'trusted_data_summary',
                    'missed_work_summary',
                    'coach_follow_ups',
                    'next_week_priorities',
                    'staff_notes',
                    'coach_comments',
                    'warnings',
                ],
                'include_player_rows' => true,
                'include_benchmark_details' => true,
                'include_pending_reviews' => true,
                'include_private_notes' => false,
                'include_staff_notes' => true,
                'include_parent_notes' => false,
                'include_player_messages' => false,
                'include_next_week_priorities' => true,
                'include_internal_qa' => false,
                'max_player_detail_level' => 'full',
                'benchmark_detail_level' => 'standard',
                'copy_rules' => [
                    'Use direct professional language.',
                    'Include coach-safe review, missed work, and next-week priority context.',
                    'Include warnings.',
                ],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function reportSectionKeys(): array
    {
        return [
            'team_completion',
            'player_rows',
            'benchmark_submission_summary',
            'review_summary',
            'trusted_data_summary',
            'missed_work_summary',
            'coach_follow_ups',
            'next_week_priorities',
            'current_team_intelligence',
            'warnings',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function sectionAliases(string $section): array
    {
        return match ($section) {
            'weekly_completion_summary' => ['team_completion'],
            'team_wins' => ['executive_summary'],
            'next_week_focus' => ['next_week_priorities'],
            'parent_summary_notes', 'player_message', 'staff_notes', 'coach_comments', 'internal_context' => [],
            'benchmark_results_summary', 'approved_results', 'missing_metrics', 'data_quality_flags' => ['benchmark_submission_summary'],
            'population_learning_status', 'source_mix', 'trust_policy_status' => ['current_team_intelligence', 'trusted_data_summary'],
            'all_coach_safe_sections' => $this->reportSectionKeys(),
            default => [$section],
        };
    }

    private function emptySectionValue(string $key): mixed
    {
        return match ($key) {
            'team_completion' => [],
            'player_rows' => [],
            'benchmark_submission_summary' => [],
            'review_summary' => [],
            'trusted_data_summary' => [],
            'missed_work_summary' => [],
            'coach_follow_ups' => [],
            'next_week_priorities' => [],
            'current_team_intelligence' => [],
            'warnings' => [],
            default => [],
        };
    }

    private function allowedAudience(string $audience): string
    {
        $normalized = strtolower(trim($audience));

        return in_array($normalized, ['coach', 'staff', 'players', 'parents'], true) ? $normalized : 'coach';
    }
}
