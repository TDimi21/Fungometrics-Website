# FMTRX email delivery with Resend

## Inspection and compatibility

- Application: Laravel 9.52.21; PHP requirement ^8.1 (local CLI 8.2).
- Previous example configuration used SendGrid SMTP. The default transport is now Resend; SMTP/log/array remain available as explicit alternatives.
- API password recovery uses Laravel's `Password::sendResetLink` and standard `Illuminate\Auth\Notifications\ResetPassword`. Its tokens, routes, validation, and delivery timing are unchanged. Web password recovery uses Laravel's standard controller trait.
- No existing app mailables/custom mail notifications were present. SMS invitations and result notifications are not converted to email by this change.
- Queue default is `sync`. Existing connections include database, Redis, SQS, and Beanstalkd; only a `failed_jobs` migration is present, not a `jobs` migration. No queue settings or database tables were changed.
- Installed and locked `resend/resend-laravel` 0.10.0 and `resend/resend-php` 0.12.0. The integration's [0.10 package requirements](https://github.com/resend/resend-laravel/blob/v0.10.0/composer.json) support Laravel 9/PHP 8.1. Newer releases require Laravel 10+. Production must use `composer install`, not an unbounded `composer require` or `composer update`.
- `ResendMailServiceProvider` reuses the official provider and transport, suppressing only its optional webhook routes. Auto-discovery is disabled for this package to prevent duplicate registration. Delivery webhooks can be implemented separately later.

## Production configuration

Verify `mail.fmtrx.com` in the Resend dashboard using the DNS records Resend supplies for that domain. Verify that the API key is authorized to send from it. No DNS changes or domain verification were performed by this integration.

Set these values in the server's secret/environment configuration (never commit the actual API key):

```dotenv
RESEND_API_KEY=<set the real key securely on the server>
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=notifications@mail.fmtrx.com
MAIL_FROM_NAME=FMTRX
```

Ensure `APP_URL=https://app.fmtrx.com` so email links point at production. Keep the current queue connection unless asynchronous infrastructure has separately been provisioned. Do not select `database` without an existing jobs table.

After deploying the changed application files and composer.lock, run:

```bash
cd ~/app.fmtrx.com
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart
```

The Composer install script runs Laravel package discovery. No database migration, frontend build, or asset publish is required for this integration. `queue:restart` gracefully signals any existing workers; it does not start new workers. If PHP OPcache is configured not to check timestamps, reload the existing PHP-FPM service through the deployment process as well.

Safe verification (renders only; no email or queue job):

```bash
php artisan fmtrx:mail-test your-test-recipient@example.com
```

Only when an operator explicitly chooses to send a real test, substitute a recipient they control:

```bash
php artisan fmtrx:mail-test your-test-recipient@example.com --send
```

The command requires one valid recipient, a configured Resend default mailer and key, and a valid sender. It never prints the API key or raw provider exceptions. Submission success is not an inbox-delivery guarantee; check Resend's delivery status and the recipient inbox/spam folder.

For an already configured asynchronous queue:

```bash
php artisan fmtrx:mail-test your-test-recipient@example.com --send --queue
```

This rejects `sync`, `null`, unsupported drivers, and a missing configured database jobs table. Existing workers process the default queue. If provisioning workers separately, the standard command is `php artisan queue:work --sleep=3 --tries=3 --timeout=60`, managed by the existing process supervisor. Do not start a worker just to run a dry-run test; it could process unrelated pending jobs.

## Reuse for future messages

Use Laravel `Mail`, `Notification`, `Mailable`, and existing queues. Do not call the Resend API directly from controllers. Password resets already pick up the configured transport automatically.

`resources/views/mail/fmtrx-notification.blade.php` is a reusable Markdown body. Pass `heading`, an array of `lines`, and optional `actionText`/`actionUrl` from a future mailable. Use application-generated HTTPS action URLs and minimal recipient-specific information. For example, in a new mailable's `build()` method:

```php
return $this->subject('Your FMTRX workout is ready')
    ->markdown('mail.fmtrx-notification', [
        'heading' => 'Your workout is ready',
        'lines' => ['Your coach has assigned your next workout. Sign in to view it.'],
        'actionText' => 'Open FMTRX',
        'actionUrl' => config('app.url'),
    ]);
```

The shared HTML/plain-text `mail::message` frame and `fmtrx` CSS theme provide the FMTRX header/footer, readable light body, and red buttons. The notification view override is presentation-only, preserving action links and fallback links. Standard `MailMessage` notifications inherit the same branding.

Use `Queueable` and `SerializesModels`; choose `Mail::to(...)->queue($mail)` or `ShouldQueue` explicitly when asynchronous delivery is desired, and use `afterCommit()` for events originating in database transactions. The sample `FmtrxTestMail` supports either explicit send or queue; it does not force synchronous authentication notifications onto a new queue. Future invitations, assessments, assignments, and development reports need their own event triggers and recipient/authorization rules.

## Automated verification

```bash
php artisan test --filter='ResendMailTest|AccountClaimSecurityTest|PasswordChangeTest'
composer validate --no-check-publish
```

Tests use Laravel mail/notification fakes. They cover dry runs, explicit sending, queued sending, recipient validation, missing credentials, synchronous queue rejection, branding in HTML and plain text, registered transport/no webhook routes, and the existing password-reset notification. No real email is sent.

The application's existing unrelated Composer security advisories are not resolved by this narrowly scoped integration. No framework or authentication upgrade is included.
