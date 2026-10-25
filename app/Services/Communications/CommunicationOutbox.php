<?php

declare(strict_types=1);

namespace App\Services\Communications;

use App\Models\{CommunicationEvent,CommunicationDelivery};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunicationOutbox
{
    public function record(string $type, string $resourceType, string $resourceId, string $occurrence = 'initial', ?string $teamId = null, ?string $recipientId = null, array $payload = []): ?CommunicationEvent
    {
        if ( ! config('communications.enabled') && $type !== 'LiveABCompleted') {
            return null;
        }
        $key = hash('sha256', json_encode([$type,$resourceType,$resourceId,$occurrence,$recipientId]));
        // Unique constraint resolves concurrent application/webhook writes, without sending inside a transaction.
        DB::table('communication_events')->insertOrIgnore(['id' => (string)Str::uuid(),'dedupe_key' => $key,'event_type' => $type,'resource_type' => $resourceType,'resource_id' => $resourceId,'occurrence' => $occurrence,'team_id' => $teamId,'recipient_id' => $recipientId,'payload' => json_encode($payload, JSON_THROW_ON_ERROR),'created_at' => now(),'updated_at' => now()]);
        return CommunicationEvent::where('dedupe_key', $key)->first();
    }
    public function process(string $id): void
    {
        if ( ! config('communications.enabled') && CommunicationEvent::find($id)?->event_type !== 'LiveABCompleted') {
            return;
        }
        DB::transaction(function () use ($id): void {
            $event = CommunicationEvent::lockForUpdate()->find($id);
            if ( ! $event || ($event->processed_at && $event->event_type !== 'LiveABCompleted') || $event->attempts >= 5) {
                return;
            }
            $builder = app(CommunicationContent::class);
            foreach($builder->recipients($event) as $user) {
                $content = $builder->build($event, $user);
                if ( ! $content) {
                    continue;
                }
                $key = hash('sha256', json_encode([$event->event_type,$event->resource_type,$event->resource_id,('DevelopmentPlanPublished' === $event->event_type ? 'initial' : $event->occurrence),$user->id,'email']));
                DB::table('communication_deliveries')->insertOrIgnore(['id' => (string)Str::uuid(),'event_id' => $event->id,'dedupe_key' => $key,'recipient_id' => $user->id,'player_id' => 'player' === $user->type ? $user->id : null,'team_id' => $event->team_id,'event_type' => $event->event_type,'resource_type' => $event->resource_type,'resource_id' => $event->resource_id,'payload' => json_encode($content, JSON_THROW_ON_ERROR),'status' => 'pending','channel' => 'email','created_at' => now(),'updated_at' => now()]);
            }
            $event->update(['processed_at' => now(),'failure_code' => null]);
        });
    }
}
