<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\SeasonArchiveDeliveryReviewService;
use Illuminate\Console\Command;

class SeasonArchiveDeliveryReviewAudit extends Command
{
    protected $signature = 'planner:season-archive-delivery-review
        {teamId : Team id}
        {--start= : Season start date YYYY-MM-DD}
        {--end= : Season end date YYYY-MM-DD}
        {--template= : Template key}
        {--audience=staff : coach, staff, director, players, or parents}
        {--channel=copy : copy, email, message, announcement, or notification}
        {--format=text : text or html}
        {--weeks=12 : Number of weeks to include}
        {--confirm-send : Attempt send after review if supported}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX season archive delivery review and explicit send confirmation.';

    public function handle(SeasonArchiveDeliveryReviewService $service): int
    {
        $options = [
            'season_start_date' => $this->option('start') ?: null,
            'season_end_date' => $this->option('end') ?: null,
            'weeks' => (int) $this->option('weeks'),
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

        $this->info('FMTRX SEASON ARCHIVE DELIVERY REVIEW');
        $this->line('Team ID: '.$this->value($payload['team_id'] ?? null));
        $this->line('Template: '.$this->value($payload['template'] ?? null));
        $this->line('Audience: '.$this->value($payload['audience'] ?? null));
        $this->line('Channel: '.$this->value($payload['channel'] ?? null));
        $this->line('Format: '.$this->value($payload['format'] ?? null));
        $this->line('Delivery status: '.$this->value($payload['delivery_status'] ?? $payload['send_status'] ?? null));
        $this->line('Can send: '.$this->value($payload['can_send'] ?? false));
        $this->line('Requires confirmation: '.$this->value($payload['requires_confirmation'] ?? true));

        $this->newLine();
        $this->line('SUBJECT');
        $this->line('-------');
        $this->line($this->value($payload['subject'] ?? null));

        $summary = $payload['recipient_summary'] ?? [];
        $this->newLine();
        $this->line('RECIPIENT REVIEW');
        $this->line('----------------');
        $this->line('Total: '.$this->value($summary['total_recipients'] ?? 0));
        $this->line('Safe: '.$this->value($summary['safe_recipients'] ?? 0));
        $this->line('Missing contact: '.$this->value($summary['missing_contact_count'] ?? 0));
        $this->line('Unsafe: '.$this->value($summary['unsafe_recipient_count'] ?? 0));
        foreach (array_slice($payload['recipients'] ?? $payload['recipients_skipped'] ?? [], 0, 12) as $recipient) {
            if (! is_array($recipient)) {
                continue;
            }
            $this->line(sprintf(
                '- %s | %s | %s | safe %s%s',
                $this->value($recipient['recipient_type'] ?? null),
                $this->value($recipient['name'] ?? null),
                $this->value($recipient['email'] ?? null),
                $this->value($recipient['safe_to_send'] ?? false),
                empty($recipient['warning']) ? '' : ' | '.$this->value($recipient['warning'])
            ));
        }

        $this->printWarnings('PRIVACY WARNINGS', $payload['privacy_warnings'] ?? []);
        $this->printWarnings('DELIVERY WARNINGS', $payload['delivery_warnings'] ?? []);
        $this->printWarnings('SEND BLOCKERS', $payload['send_blockers'] ?? []);
        $this->printWarnings('SEND WARNINGS', $payload['warnings'] ?? []);

        $this->newLine();
        $this->line('MESSAGE PREVIEW');
        $this->line('---------------');
        $text = (string) ($payload['message_text'] ?? $payload['message_html'] ?? '');
        $this->line($text !== '' ? mb_substr($text, 0, 1600) : '- no message returned -');

        if ($this->option('confirm-send')) {
            $this->newLine();
            $this->line('SEND RESULT');
            $this->line('-----------');
            $this->line('Status: '.$this->value($payload['send_status'] ?? null));
            $this->line('Sent: '.$this->value($payload['sent_count'] ?? 0));
            $this->line('Skipped: '.$this->value($payload['skipped_count'] ?? 0));
            $this->line('Failed: '.$this->value($payload['failed_count'] ?? 0));
            $this->line('History: '.$this->value($payload['delivery_history'] ?? []));
        }

        return self::SUCCESS;
    }

    private function printWarnings(string $title, mixed $warnings): void
    {
        if (empty($warnings)) {
            return;
        }

        $this->newLine();
        $this->line($title);
        $this->line(str_repeat('-', strlen($title)));
        foreach ((array) $warnings as $warning) {
            $this->line('- '.$this->value($warning));
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
