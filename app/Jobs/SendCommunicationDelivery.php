<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\{CommunicationDelivery,CommunicationPreference,User};
use App\Services\Communications\CommunicationContent;
use App\Notifications\FmtrxActivityNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class SendCommunicationDelivery implements ShouldQueue, \Illuminate\Contracts\Queue\ShouldBeUnique
{
    use Dispatchable;
    use Queueable;
    public $tries = 1;
    public $timeout = 60;
    public $uniqueFor = 3600;
    public function uniqueId(): string { return $this->deliveryId; }
    public function __construct(public string $deliveryId)
    {
        $this->onQueue('communications');
    }
    public function handle(CommunicationContent $content): void
    {
        if ( ! config('communications.enabled') || ! config('communications.send_enabled')) {
            return;
        }
        $delivery = DB::transaction(function () use ($content) {
            $d = CommunicationDelivery::lockForUpdate()->find($this->deliveryId);
            if ( ! $d || 'pending' !== $d->status) {
                return null;
            }
            $u = User::find($d->recipient_id);
            $reason = null;
            if ( ! $u || ! $u->status || $u->is_dummy || blank($u->password) || ! filter_var($u->email, FILTER_VALIDATE_EMAIL)) {
                $reason = 'recipient_unavailable';
            } elseif ( ! $content->allowed($d)) {
                $reason = 'access_removed';
            } elseif (($category = CommunicationContent::CATEGORIES[$d->event_type] ?? null) && ! CommunicationPreference::forUser($u->id)[$category]) {
                $reason = 'preference_disabled';
            }
            if ($reason) {
                $d->update(['status' => 'suppressed','failure_code' => $reason]);
                return null;
            }
            $policy = app(\App\Services\Communications\CommunicationDeliveryPolicy::class);
            $category = $policy->category($d->event_type);
            if (!$category || !$policy->allows($category, $u->email)) return null;
            // Validate the stored destination again before any external side effect.
            $url = $d->payload['action_url'] ?? '';
            if ( ! str_starts_with($url, 'https://app.fmtrx.com/') || $content->url(mb_substr($url, mb_strlen('https://app.fmtrx.com'))) !== $url) {
                throw new InvalidArgumentException('Invalid destination.');
            }
            $d->update(['status' => 'sending','attempted_at' => now()]);
            return [$d,$u];
        });
        if ( ! $delivery) {
            return;
        }
        [$d,$user] = $delivery;
        try {
            $user->notify(new FmtrxActivityNotification($d->payload, isset(CommunicationContent::CATEGORIES[$d->event_type])));
            $d->update(['status' => 'sent','sent_at' => now(),'failure_code' => null]);
        } catch(Throwable) {
            $d->update(['status' => 'unconfirmed','failure_code' => 'provider_delivery_unconfirmed']);
        }
    }
}
