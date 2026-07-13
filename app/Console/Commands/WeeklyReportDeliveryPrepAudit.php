<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Planner\WeeklyReportDeliveryPrepService;
use Illuminate\Console\Command;

class WeeklyReportDeliveryPrepAudit extends Command
{
    protected $signature = 'planner:weekly-report-delivery-prep
        {teamId : Team id}
        {--template= : Template key}
        {--audience=coach : coach, staff, players, or parents}
        {--channel=copy : copy, email, message, announcement, or notification}
        {--format=text : text or html}
        {--days=7 : Days to include}
        {--create-draft : Prepare a draft payload if supported}
        {--json : Output structured JSON}';

    protected $description = 'Audit FMTRX weekly report delivery preparation payloads.';

    public function handle(WeeklyReportDeliveryPrepService $service): int
    {
        $options = [
            'days' => (int) $this->option('days'),
            'template' => (string) ($this->option('template') ?: ''),
            'audience' => (string) $this->option('audience'),
            'channel' => (string) $this->option('channel'),
            'format' => (string) $this->option('format'),
        ];

        $payload = $this->option('create-draft')
            ? $service->createDraftDelivery((string) $this->argument('teamId'), $options)
            : $service->prepareDelivery((string) $this->argument('teamId'), $options);

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('FMTRX WEEKLY REPORT DELIVERY PREP');
        $this->line('Team ID: '.$this->value($payload['team_id'] ?? null));
        $this->line('Template: '.$this->value($payload['template'] ?? null));
        $this->line('Audience: '.$this->value($payload['audience'] ?? null));
        $this->line('Channel: '.$this->value($payload['channel'] ?? null));
        $this->line('Format: '.$this->value($payload['format'] ?? null));
        $this->line('Delivery status: '.$this->value($payload['delivery_status'] ?? null));
        $this->line('Requires coach approval: '.$this->value($payload['requires_coach_approval'] ?? true));

        $this->newLine();
        $this->line('SUBJECT');
        $this->line('-------');
        $this->line($this->value($payload['subject'] ?? null));

        $summary = $payload['recipient_summary'] ?? [];
        $this->newLine();
        $this->line('RECIPIENT PREVIEW');
        $this->line('-----------------');
        $this->line('Total: '.$this->value($summary['total_recipients'] ?? 0));
        $this->line('Safe: '.$this->value($summary['safe_recipients'] ?? 0));
        $this->line('Missing contact: '.$this->value($summary['missing_contact_count'] ?? 0));
        $this->line('Unsafe: '.$this->value($summary['unsafe_recipient_count'] ?? 0));
        foreach (array_slice($payload['recipients'] ?? [], 0, 12) as $recipient) {
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
        if (empty($payload['recipients'])) {
            $this->line('- none');
        }

        $this->newLine();
        $this->line('MESSAGE PREVIEW');
        $this->line('---------------');
        $text = (string) ($payload['message_text'] ?? $payload['message_html'] ?? '');
        $this->line($text !== '' ? mb_substr($text, 0, 1600) : '- no message returned -');

        $this->printWarnings('PRIVACY WARNINGS', $payload['privacy_warnings'] ?? []);
        $this->printWarnings('DELIVERY WARNINGS', $payload['delivery_warnings'] ?? []);

        if ($this->option('create-draft')) {
            $this->newLine();
            $this->line('DRAFT');
            $this->line('-----');
            $this->line($this->value($payload['draft'] ?? []));
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
