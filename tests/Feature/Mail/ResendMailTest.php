<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Mail\FmtrxTestMail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Resend\Laravel\Transport\ResendTransportFactory;
use Tests\TestCase;

class ResendMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'mail.default' => 'resend',
            'resend.api_key' => 'test-key-never-sent',
            'mail.from.address' => 'notifications@mail.fmtrx.com',
            'mail.from.name' => 'FMTRX',
            'app.url' => 'https://app.fmtrx.com',
        ]);
    }

    public function test_transport_resolves_without_network_or_extra_webhook_routes(): void
    {
        $this->assertInstanceOf(ResendTransportFactory::class, Mail::mailer('resend')->getSymfonyTransport());
        $this->assertFalse(Route::has('resend.webhook'));
    }

    public function test_default_command_only_renders_without_key(): void
    {
        Mail::fake();
        config(['resend.api_key' => null]);
        $this->artisan('fmtrx:mail-test', ['recipient' => 'tester@example.com'])
            ->expectsOutputToContain('Dry run: template rendered')->assertExitCode(0);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_explicit_send_uses_one_recipient_and_configured_sender(): void
    {
        Mail::fake();
        $this->artisan('fmtrx:mail-test', ['recipient' => 'tester@example.com', '--send' => true])->assertExitCode(0);
        Mail::assertSent(FmtrxTestMail::class, fn ($mail) => $mail->hasTo('tester@example.com') && count($mail->to) === 1);
        Mail::assertSent(FmtrxTestMail::class, 1);
        Mail::assertNothingQueued();
        $mail = (new FmtrxTestMail())->build();
        $this->assertSame('FMTRX email delivery test', $mail->subject);
        $this->assertTrue($mail->afterCommit);
        // From is intentionally inherited from Laravel's global mail config.
        $this->assertSame([], $mail->from);
        $this->assertSame('notifications@mail.fmtrx.com', config('mail.from.address'));
    }

    public function test_queue_uses_existing_connection_without_sending_inline(): void
    {
        Mail::fake();
        config(['queue.default' => 'redis']);
        $this->artisan('fmtrx:mail-test', ['recipient' => 'tester@example.com', '--send' => true, '--queue' => true])
            ->expectsOutputToContain('Test email queued')->assertExitCode(0);
        Mail::assertQueued(FmtrxTestMail::class, fn ($mail) => $mail->hasTo('tester@example.com') && $mail->afterCommit);
        Mail::assertNothingSent();
    }

    public function test_missing_key_and_wrong_mailer_cannot_send(): void
    {
        Mail::fake();
        config(['resend.api_key' => '']);
        $this->artisan('fmtrx:mail-test', ['recipient' => 'tester@example.com', '--send' => true])->assertExitCode(1);
        config(['resend.api_key' => 'test-key', 'mail.default' => 'smtp']);
        $this->artisan('fmtrx:mail-test', ['recipient' => 'tester@example.com', '--send' => true])->assertExitCode(1);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_invalid_and_multiple_recipients_cannot_send(): void
    {
        Mail::fake();
        foreach (['invalid', 'one@example.com,two@example.com', "one@example.com\r\nBcc: two@example.com"] as $recipient) {
            $this->artisan('fmtrx:mail-test', ['recipient' => $recipient, '--send' => true])->assertExitCode(1);
        }
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_queue_requires_send_and_cannot_use_sync(): void
    {
        Mail::fake();
        $this->artisan('fmtrx:mail-test', ['recipient' => 'tester@example.com', '--queue' => true])->assertExitCode(1);
        config(['queue.default' => 'sync']);
        $this->artisan('fmtrx:mail-test', ['recipient' => 'tester@example.com', '--send' => true, '--queue' => true])->assertExitCode(1);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_missing_database_jobs_table_cannot_queue(): void
    {
        Mail::fake();
        config(['queue.default' => 'database', 'queue.connections.database.table' => 'missing_resend_test_jobs']);
        $this->artisan('fmtrx:mail-test', ['recipient' => 'tester@example.com', '--send' => true, '--queue' => true])
            ->expectsOutputToContain('The configured jobs table does not exist')->assertExitCode(1);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_provider_errors_do_not_echo_secret_details(): void
    {
        Mail::shouldReceive('to')->once()->with('tester@example.com')
            ->andThrow(new \RuntimeException('sensitive-provider-request-detail'));
        $this->artisan('fmtrx:mail-test', ['recipient' => 'tester@example.com', '--send' => true])
            ->expectsOutputToContain('Email test failed (RuntimeException)')
            ->doesntExpectOutputToContain('sensitive-provider-request-detail')->assertExitCode(1);
    }

    public function test_branded_template_renders_html_text_and_action(): void
    {
        $mail = new FmtrxTestMail();
        $html = $mail->render();
        $this->assertStringContainsString('FMTRX', $html);
        $this->assertStringContainsString('Your FMTRX email connection is ready', $html);
        $this->assertStringContainsString('https://app.fmtrx.com', $html);
        $this->assertStringContainsString('#c71929', $html);
        $text = (string) app(Markdown::class)->renderText('mail.fmtrx-notification', $mail->build()->viewData);
        $this->assertStringContainsString('FMTRX', $text);
        $this->assertStringContainsString('No account changes were made.', $text);
        $this->assertStringContainsString('https://app.fmtrx.com', $text);
    }

    public function test_existing_password_reset_notification_is_preserved_and_branded(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()->assertJsonPath('status', 'success');
        Notification::assertSentTo($user, ResetPassword::class, function ($notification, $channels) use ($user) {
            $this->assertSame(['mail'], $channels);
            $message = $notification->toMail($user);
            $html = (string) $message->render();
            $this->assertStringContainsString('FMTRX', $html);
            $this->assertStringContainsString('Reset Password', $html);
            $this->assertStringContainsString($notification->token, $html);
            return true;
        });
    }
}
