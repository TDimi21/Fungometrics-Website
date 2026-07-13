<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\WeeklyReportDeliveryReviewService;
use Illuminate\Console\Command;

class WeeklyReportDeliveryReviewAudit extends Command
{
    protected $signature = 'planner:weekly-report-delivery-review
        {teamId : Team id}
        {--template= : Template key}
        {--audience=coach : coach, staff, players, or parents}
        {--channel=copy : copy, email, message, announcement, or notification}
        {--format=text : text or html}
        {--days=7 : Days to include}
        {--confirm-send : Attempt send with explicit confirmation}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX weekly report delivery draft review and send-confirmation behavior.';

    public function handle(WeeklyReportDeliveryReviewService $service): int
    {
        $options = [
            'days' => (int) $this->option('days'),
            'template' => (string) ($this->option('template') ?: ''),
            'audience' => (string) $this->option('audience'),
            'channel' => (string) $this->option('channel'),
            'format' => (string) $this->option('format'),
        ];

        $payload = $this->option('confirm-send')
            ? $service->sendDraft((string) $this->argument('teamId'), [
                ...$options,
                'confirm_send' => true,
            ], null, ['confirm_send' => true])
            : $service->buildDraftReview((string) $this->argument('teamId'), $options);

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($this->option('confirm-send')) {
            $this->printSendResult($payload);

            return self::SUCCESS;
        }

        $this->printReview($payload);

        return self::SUCCESS;
    }

    private function printReview(array $review): void
    {
        $this->info('FMTRX WEEKLY REPORT DELIVERY REVIEW');
        $this->line('Team ID: '.$this->value($review['team_id'] ?? null));
        $this->line('Template: '.$this->value($review['template'] ?? null));
        $this->line('Audience: '.$this->value($review['audience'] ?? null));
        $this->line('Channel: '.$this->value($review['channel'] ?? null));
        $this->line('Delivery status: '.$this->value($review['delivery_status'] ?? null));
        $this->line('Can send: '.$this->value($review['can_send'] ?? false));
        $this->line('Requires confirmation: '.$this->value($review['requires_confirmation'] ?? true));

        $this->newLine();
        $this->line('SUBJECT');
        $this->line('-------');
        $this->line($this->value($review['subject'] ?? null));

        $summary = $review['recipient_summary'] ?? [];
        $this->newLine();
        $this->line('RECIPIENTS');
        $this->line('----------');
        $this->line('Total: '.$this->value($summary['total_recipients'] ?? 0));
        $this->line('Safe: '.$this->value($summary['safe_recipients'] ?? 0));
        $this->line('Missing contact: '.$this->value($summary['missing_contact_count'] ?? 0));
        $this->line('Unsafe: '.$this->value($summary['unsafe_recipient_count'] ?? 0));

        $this->newLine();
        $this->line('MESSAGE PREVIEW');
        $this->line('---------------');
        $this->line(mb_substr((string) ($review['message_text'] ?? $review['message_html'] ?? '- no message returned -'), 0, 1500));

        $this->printList('SEND BLOCKERS', $review['send_blockers'] ?? []);
        $this->printList('PRIVACY WARNINGS', $review['privacy_warnings'] ?? []);
        $this->printList('DELIVERY WARNINGS', $review['delivery_warnings'] ?? []);
    }

    private function printSendResult(array $result): void
    {
        $this->info('FMTRX WEEKLY REPORT SEND RESULT');
        $this->line('Team ID: '.$this->value($result['team_id'] ?? null));
        $this->line('Audience: '.$this->value($result['audience'] ?? null));
        $this->line('Channel: '.$this->value($result['channel'] ?? null));
        $this->line('Send status: '.$this->value($result['send_status'] ?? null));
        $this->line('Sent: '.$this->value($result['sent_count'] ?? 0));
        $this->line('Failed: '.$this->value($result['failed_count'] ?? 0));
        $this->line('Skipped: '.$this->value($result['skipped_count'] ?? 0));
        $this->line('Sent at: '.$this->value($result['sent_at'] ?? null));
        $this->printList('WARNINGS', $result['warnings'] ?? []);
        $this->printList('EVIDENCE', $result['evidence'] ?? []);
    }

    private function printList(string $title, mixed $items): void
    {
        if (empty($items)) {
            return;
        }

        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', strlen($title)));
        foreach ((array) $items as $item) {
            $this->line('- '.$this->value($item));
        }
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
