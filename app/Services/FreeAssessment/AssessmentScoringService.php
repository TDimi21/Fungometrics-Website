<?php

declare(strict_types=1);

namespace App\Services\FreeAssessment;

use App\Models\{FreeAssessment, FreeAssessmentResult, FreeAssessmentAttempt, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentScoringService
{
    public function save(FreeAssessment $event, User $coach, string $playerId, string $station, array $data): FreeAssessmentResult
    {
        $definition = Stations::all()[$station] ?? null;
        abort_unless($definition, 404);
        validator($data, [
            'revision' => 'required|integer|min:0',
            'values' => 'required|array|min:1|max:'.$definition['count'],
            'values.*' => ['nullable', 'reps' === $definition['unit'] ? 'integer' : 'numeric', 'min:'.$definition['min'], 'max:'.$definition['max']],
            'notes' => 'nullable|string|max:2000',
            'protocol' => 'pull_strength' === $station ? 'required|string|max:120' : 'nullable|string|max:120',
        ])->validate();
        if ( ! array_is_list($data['values'])) {
            throw ValidationException::withMessages(['values' => 'Attempts must be an ordered list.']);
        }
        $data['values'] = array_pad(array_map(fn ($value) => $value === null || $value === '' ? null : (float) $value, $data['values']), $definition['count'], null);
        if (!count(array_filter($data['values'], fn ($value) => $value !== null))) {
            throw ValidationException::withMessages(['values' => 'Enter at least one result, or skip this station without saving.']);
        }
        if ('pitching_velocity' === $station && ! in_array($data['protocol'] ?? null, ['fastball', 'mixed'], true)) {
            throw ValidationException::withMessages(['protocol' => 'Select fastball-only or mixed pitches.']);
        }
        return DB::transaction(function () use ($event, $coach, $playerId, $station, $data, $definition) {
            $event = FreeAssessment::whereKey($event->id)->sharedLock()->firstOrFail();
            abort_if('completed' === $event->status, 409, 'Reopen the assessment before editing results.');
            $enrollment = DB::table('free_assessment_players')->where('assessment_id', $event->id)->where('player_id', $playerId)->lockForUpdate()->first();
            abort_unless($enrollment, 404);
            $result = FreeAssessmentResult::firstOrNew(['assessment_id' => $event->id, 'player_id' => $playerId, 'station' => $station]);
            $currentRevision = $result->revision ?? 0;
            // A retry of the exact accepted payload is safe, even after a lost response.
            if ($result->exists && $currentRevision !== (int) $data['revision']) {
                $old = Stations::attemptValues($station, $result->attempts()->where('revision', $currentRevision)->get());
                if ($old === $data['values'] && ($result->notes ?? '') === ($data['notes'] ?? '') && ($result->protocol ?? '') === ($data['protocol'] ?? '')) {
                    return $result;
                }
                abort(409, 'Another coach updated this result. Your draft is preserved. Reload the saved result before replacing it.');
            }
            $result->fill(['revision' => $currentRevision + 1, 'summary' => Stations::summarize($station, $data['values']), 'notes' => $data['notes'] ?? null, 'protocol' => $data['protocol'] ?? null, 'entered_by' => $coach->id])->save();
            foreach ($data['values'] as $i => $value) {
                if ($value === null) continue;
                FreeAssessmentAttempt::create([
                    'result_id' => $result->id, 'revision' => $result->revision,
                    'attempt_number' => 'grip_strength' === $station ? ($i % 3) + 1 : $i + 1,
                    'side' => 'grip_strength' === $station ? ($i < 3 ? 'left' : 'right') : 'none',
                    'value' => $value, 'unit' => $definition['unit'], 'entered_by' => $coach->id,
                ]);
            }
            app(AssessmentMetricSyncService::class)->sync($event, $result);
            return $result;
        }, 3);
    }
}
