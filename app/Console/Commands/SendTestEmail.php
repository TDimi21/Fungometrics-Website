<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\FmtrxTestMail;
use Illuminate\Console\Command;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SendTestEmail extends Command
{
    protected $signature = 'fmtrx:mail-test {recipient : One test email address} {--send : Explicitly allow a real test email} {--queue : Use the configured asynchronous queue (requires --send)}';
    protected $description = 'Validate and render an FMTRX test email; delivery requires --send.';

    public function handle(): int
    {
        $recipient = trim((string) $this->argument('recipient'));
        if (Validator::make(['recipient' => $recipient], ['recipient' => 'required|email:rfc|max:254'])->fails()) {
            $this->error('Supply one valid test email address.');
            return self::FAILURE;
        }
        if ($this->option('queue') && !$this->option('send')) {
            $this->error('--queue requires --send. Nothing was queued.');
            return self::FAILURE;
        }
        $mail = new FmtrxTestMail();
        try {
            $preview = $mail->build();
            app(Markdown::class)->render($preview->markdown, $preview->viewData);
            if (!$this->option('send')) {
                $this->info('Dry run: template rendered. No email sent or queued.');
                $this->line('Mailer: '.config('mail.default').'; sender: '.config('mail.from.address'));
                $this->line('Add --send only when ready to send to this recipient.');
                return self::SUCCESS;
            }
            if (config('mail.default') !== 'resend' || !filled(config('resend.api_key'))) {
                $this->error('Set MAIL_MAILER=resend and RESEND_API_KEY, then refresh the configuration cache. Nothing was sent.');
                return self::FAILURE;
            }
            if (!filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL)) {
                $this->error('Configure a valid MAIL_FROM_ADDRESS. Nothing was sent.');
                return self::FAILURE;
            }
            if ($this->option('queue')) {
                $connection = config('queue.default');
                $driver = config("queue.connections.{$connection}.driver");
                if (!in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
                    $this->error('Configure an asynchronous queue before using --queue. Nothing was sent.');
                    return self::FAILURE;
                }
                if ($driver === 'database' && !Schema::connection(config("queue.connections.{$connection}.connection"))->hasTable(config("queue.connections.{$connection}.table", 'jobs'))) {
                    $this->error('The configured jobs table does not exist. Nothing was queued.');
                    return self::FAILURE;
                }
                Mail::to($recipient)->queue($mail);
                $this->info('Test email queued. A worker must process it; delivery is not yet confirmed.');
            } else {
                Mail::to($recipient)->send($mail);
                $this->info('Test email submitted to Resend. Check the recipient inbox and Resend delivery status.');
            }
            return self::SUCCESS;
        } catch (Throwable $exception) {
            // Provider exception messages can contain request details; do not echo them.
            $this->error('Email test failed ('.class_basename($exception).'). Check provider configuration, domain verification, and queue availability.');
            return self::FAILURE;
        }
    }
}
