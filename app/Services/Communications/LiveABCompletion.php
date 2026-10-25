<?php
namespace App\Services\Communications;

use App\Models\Practice;
use Illuminate\Support\Facades\DB;

final class LiveABCompletion
{
    public static function participantIds(Practice $practice): array
    {
        return $practice->batting()->pluck('batter_id')
            ->merge($practice->bullpen()->pluck('pitcher_id'))->filter()->unique()->values()->all();
    }

    public function finish(string $id, array $data): Practice
    {
        return DB::transaction(function () use ($id, $data) {
            $practice = Practice::lockForUpdate()->findOrFail($id);
            // Results are already saved by the scoring engine; never copy/reinsert them.
            if ($data['is_completed']) {
                abort_if($practice->live()->where(fn ($q) => $q->whereDoesntHave('batting')->orWhereDoesntHave('pitching'))->exists(), 422, 'Repair incomplete pitch results before finalizing.');
            }
            $wasComplete = $practice->is_completed;
            $practice->fill($data);
            $practice->finished = $data['is_completed'] ? ($wasComplete ? $practice->finished : now()) : null;
            $practice->save();
            if ($practice->is_completed) {
                $outbox = app(CommunicationOutbox::class);
                $event = $outbox->record('LiveABCompleted', 'practice', $practice->id);
                if ($event) $outbox->process($event->id);
            }
            return $practice;
        });
    }
}
