<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BallFlightPredictionEvaluation extends Model
{
    use HasUuid;

    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['assumptions' => 'array', 'created_at' => 'datetime'];

    public function observation(): BelongsTo
    {
        return $this->belongsTo(BallFlightReferenceObservation::class, 'reference_observation_id');
    }
}
