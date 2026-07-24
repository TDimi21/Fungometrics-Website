<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BallFlightReferenceObservation extends Model
{
    use HasUuid;

    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = [
        'raw_metadata' => 'array',
        'event_date' => 'date',
        'eligible_for_primary_calibration' => 'boolean',
        'eligible_for_external_validation' => 'boolean',
    ];

    public function evaluations(): HasMany
    {
        return $this->hasMany(BallFlightPredictionEvaluation::class, 'reference_observation_id');
    }
}
