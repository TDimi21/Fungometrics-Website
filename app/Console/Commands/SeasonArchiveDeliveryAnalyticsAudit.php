<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\SeasonArchiveDeliveryAnalyticsService;
use Illuminate\Console\Command;

class SeasonArchiveDeliveryAnalyticsAudit extends Command
{
    protected $signature = 'planner:season-archive-delivery-analytics
        {--teamId= : Limit analytics to one team}
        {--coachUserId= : Limit analytics to one coach user}
        {--days=365 : Analytics lookback window}
        {--audience= : Filter by audience}
        {--template= : Filter by template key}
        {--channel= : Filter by channel}
        {--status= : Filter by delivery status}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX season archive delivery analytics.';

    public function handle(SeasonArchiveDeliveryAnalyticsService $service): int
    {
        $options = [
            'days' => (int) $this->option('days'),
            'audience' => $this->option('audience') ?: null,
            'template' => $this->option('template') ?: null,
            'channel' => $this->option('channel') ?: null,
            'status' => $this->option('status') ?: null,
        ];
        $teamId = $this->option('teamId') ?: null;
        $coachUserId = $this->option('coachUserId') ?: null;

        if ($teamId) {
            $payload = $service->buildTeamAnalytics((string) $teamId, $options);
        } elseif ($coachUserId) {
            $payload = $service->buildCoachAnalytics((string) $coachUserId, $options);
        } else {
            $this->error('Use --teamId or --coachUserId. Global season archive delivery analytics are not exposed by this audit command.');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX SEASON ARCHIVE DELIVERY ANALYTICS');
        $this->line('Scope: '.$this->value($payload['scope'] ?? '-'));
        $this->line('Team ID: '.$this->value($payload['team_id'] ?? null));
        $this->line('Coach User ID: '.$this->value($payload['coach_user_id'] ?? null));
        $this->line('Window: '.$this->value($payload['start_date'] ?? null).' to '.$this->value($payload['end_date'] ?? null));

        $summary = $payload['summary'] ?? [];
        $this->section('SUMMARY');
        $this->line('Total delivery records: '.$this->value($summary['total_delivery_records'] ?? 0));
        $this->line('Prepared: '.$this->value($summary['prepared_count'] ?? 0));
        $this->line('Draft created: '.$this->value($summary['draft_created_count'] ?? 0));
        $this->line('Sent / partial: '.$this->value((int) ($summary['sent_count'] ?? 0) + (int) ($summary['partial_count'] ?? 0)));
        $this->line('Copy-only: '.$this->value($summary['copy_only_count'] ?? 0));
        $this->line('Blocked: '.$this->value($summary['blocked_count'] ?? 0));
        $this->line('Unsupported: '.$this->value($summary['unsupported_count'] ?? 0));
        $this->line('Failed: '.$this->value($summary['failed_count'] ?? 0));
        $this->line('Recipients attempted: '.$this->value($summary['total_recipients_attempted'] ?? 0));
        $this->line('Recipients sent: '.$this->value($summary['total_recipients_sent'] ?? 0));
        $this->line('Most used template: '.$this->value($summary['most_used_template'] ?? null));

        $this->section('STATUS COUNTS');
        foreach (($payload['status_counts'] ?? []) as $status => $count) {
            $this->line('- '.$this->human((string) $status).': '.$this->value($count));
        }

        $this->section('TEMPLATE USAGE');
        $this->printUsageRows($payload['template_usage'] ?? [], 'display_name', 'template_key');

        $this->section('AUDIENCE USAGE');
        $this->printUsageRows($payload['audience_usage'] ?? [], 'display_name', 'audience');

        $this->section('CHANNEL USAGE');
        $this->printUsageRows($payload['channel_usage'] ?? [], 'display_name', 'channel');

        $health = $payload['delivery_health'] ?? [];
        $this->section('DELIVERY HEALTH');
        $this->line('Send success rate: '.$this->value($health['send_success_rate'] ?? 0).'%');
        $this->line('Block rate: '.$this->value($health['block_rate'] ?? 0).'%');
        $this->line('Unsupported rate: '.$this->value($health['unsupported_rate'] ?? 0).'%');
        $this->line('Copy-only rate: '.$this->value($health['copy_only_rate'] ?? 0).'%');
        $this->line('Failed rate: '.$this->value($health['failed_rate'] ?? 0).'%');
        $this->line('Privacy blocks: '.$this->value($health['privacy_block_count'] ?? 0));
        $this->line('Missing contact warnings: '.$this->value($health['missing_contact_warning_count'] ?? 0));
        $this->line('Unsafe recipients: '.$this->value($health['unsafe_recipient_count'] ?? 0));

        $this->section('RECOMMENDED ACTIONS');
        if (empty($payload['recommended_actions'])) {
            $this->line('- none');
        }
        foreach ($payload['recommended_actions'] ?? [] as $action) {
            $this->line('- ['.$this->human((string) ($action['priority'] ?? 'medium')).'] '.$this->value($action['title'] ?? null));
            $this->line('  why: '.$this->value($action['why'] ?? null));
            $this->line('  action: '.$this->value($action['action'] ?? null));
        }

        return self::SUCCESS;
    }

    private function printUsageRows(array $rows, string $labelKey, string $fallbackKey): void
    {
        if (empty($rows)) {
            $this->line('- none');

            return;
        }

        foreach (array_slice($rows, 0, 8) as $row) {
            $this->line('- '.$this->value($row[$labelKey] ?? $row[$fallbackKey] ?? null).': '.$this->value($row['count'] ?? 0).' total, '.$this->value($row['sent_count'] ?? 0).' sent, '.$this->value($row['blocked_count'] ?? 0).' blocked');
        }
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', strlen($title)));
    }

    private function value(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES) ?: '[]';
        }

        return (string) $value;
    }

    private function human(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value ?: 'unknown'));
    }
}
