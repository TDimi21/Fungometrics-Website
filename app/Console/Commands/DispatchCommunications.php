<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\{CommunicationEvent,CommunicationDelivery};
use App\Jobs\{ProcessCommunicationEvent,SendCommunicationDelivery};
use Illuminate\Console\Command;

class DispatchCommunications extends Command
{
    protected $signature = 'fmtrx:communications-dispatch {--limit=100 : Maximum events and deliveries queued per run} {--dry-run : Show counts without queueing}';
    protected $description = 'Queue committed FMTRX communication outbox records.';
    public function handle(): int
    {
        if ( ! config('communications.enabled')) {
            $this->info('Communications are disabled.');
            return self::SUCCESS;
        }
        $limit = max(1, min(1000, (int)$this->option('limit')));
        $events = CommunicationEvent::whereNull('processed_at')->where('attempts', '<', 5)->orderBy('created_at')->limit($limit)->pluck('id');
        $policy = app(\App\Services\Communications\CommunicationDeliveryPolicy::class);
        $types = array_filter(array_merge(array_keys(\App\Services\Communications\CommunicationContent::CATEGORIES), ['CoachRegistered','SubscriptionStatusChanged']),
            fn ($event) => config('communications.categories.'.$policy->category($event), false)
                && ($event !== 'WeeklyProgressReady' || config('communications.weekly_enabled')));
        $query = CommunicationDelivery::where('status', 'pending')->whereIn('event_type', $types);
        if (config('communications.pilot_mode', true)) {
            $query->whereIn('recipient_id', \App\Models\User::whereIn(\Illuminate\Support\Facades\DB::raw('LOWER(TRIM(email))'), config('communications.pilot_allowlist', []))->select('id'));
        }
        $deliveries = $query->orderBy('created_at')->limit($limit)->pluck('id');
        $this->info($events->count().' events; '.$deliveries->count().' pending deliveries.');
        if($this->option('dry-run')) {
            return self::SUCCESS;
        }
        if( ! in_array(config('queue.connections.'.config('queue.default').'.driver'), ['database','redis','sqs','beanstalkd'], true)) {
            $this->error('A supervised asynchronous queue is required.');
            return self::FAILURE;
        }
        foreach($events as $id) {
            ProcessCommunicationEvent::dispatch($id);
        }
        if(config('communications.send_enabled')) {
            foreach($deliveries as $id) {
                SendCommunicationDelivery::dispatch($id);
            }
        }
        return self::SUCCESS;
    }
}
