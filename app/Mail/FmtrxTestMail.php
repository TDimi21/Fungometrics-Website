<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FmtrxTestMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->afterCommit();
    }

    public function build(): self
    {
        return $this->subject('FMTRX email delivery test')->markdown('mail.fmtrx-notification', [
            'heading' => 'Your FMTRX email connection is ready',
            'lines' => [
                'This is a test of FMTRX email delivery. No account changes were made.',
                'FMTRX helps coaches and players track training, assessments, and development.',
            ],
            'actionText' => 'Open FMTRX',
            'actionUrl' => config('app.url'),
        ]);
    }
}
