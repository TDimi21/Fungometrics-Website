<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\WeeklyReportDeliveryHistoryService;
use Illuminate\Console\Command;

class WeeklyReportDeliveryHistoryAudit extends Command
{
    protected $signature = 'planner:weekly-report-deliveries
        {teamId : Team id}
        {--status= : Filter by delivery status}
        {--audience= : Filter by audience}
        {--channel= : Filter by channel}
        {--days=30 : History lookback window}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX weekly report delivery history for a team.';

    public function handle(WeeklyReportDeliveryHistoryService $service): int
    {
        $teamId = (string) $this->argument('teamId');
        $filters = [
            'status' => $this->option('status') ?: null,
            'audience' => $this->option('audience') ?: null,
            'channel' => $this->option('channel') ?: null,
            'days' => (int) $this->option('days'),
        ];
        $payload = [
            'summary' => $service->buildDeliverySummary($teamId, $filters),
            'deliveries' => $service->listTeamDeliveries($teamId, $filters),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX WEEKLY REPORT DELIVERY HISTORY');
        $this->line('Team ID: '.$teamId);
        $this->line('Days: '.max(1, min(365, (int) $this->option('days'))));
        $this->line('Status filter: '.$this->value($filters['status']));
        $this->line('Audience filter: '.$this->value($filters['audience']));
        $this->line('Channel filter: '.$this->value($filters['channel']));

        $summary = $payload['summary'];
        $this->newLine();
        $this->line('SUMMARY');
        $this->line('-------');
        $this->line('Total: '.$this->value($summary['total_deliveries'] ?? 0));
        $this->line('Sent: '.$this->value($summary['sent_count'] ?? 0));
        $this->line('Partial: '.$this->value($summary['partial_count'] ?? 0));
        $this->line('Copy-only: '.$this->value($summary['copy_only_count'] ?? 0));
        $this->line('Blocked: '.$this->value($summary['blocked_count'] ?? 0));
        $this->line('Unsupported: '.$this->value($summary['unsupported_count'] ?? 0));
        $this->line('Failed: '.$this->value($summary['failed_count'] ?? 0));
        $this->line('Last sent: '.$this->value($summary['last_sent_at'] ?? null));

        $this->newLine();
        $this->line('RECENT DELIVERIES');
        $this->line('-----------------');
        if (empty($payload['deliveries'])) {
            $this->line('- none');
        }
        foreach ($payload['deliveries'] as $delivery) {
            $recipientSummary = $delivery['recipient_summary'] ?? [];
            $this->line('- '.$this->value($delivery['subject'] ?? null));
            $this->line('  id: '.$this->value($delivery['delivery_id'] ?? null));
            $this->line('  template: '.$this->value($delivery['template_key'] ?? null));
            $this->line('  audience/channel: '.$this->value($delivery['audience'] ?? null).' / '.$this->value($delivery['channel'] ?? null));
            $this->line('  status: '.$this->value($delivery['delivery_status'] ?? null));
            $this->line('  recipients: '.$this->value($recipientSummary['total_recipients'] ?? 0));
            $this->line('  created: '.$this->value($delivery['created_at'] ?? null));
            $this->line('  sent/copied/blocked: '.$this->value($delivery['sent_at'] ?? $delivery['copied_at'] ?? $delivery['blocked_at'] ?? null));
            $firstWarning = ($delivery['send_blockers'][0] ?? null)
                ?: ($delivery['delivery_warnings'][0] ?? null)
                ?: ($delivery['privacy_warnings'][0] ?? null);
            if ($firstWarning) {
                $this->line('  reason: '.$this->value($firstWarning));
            }
        }

        return self::SUCCESS;
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
}
