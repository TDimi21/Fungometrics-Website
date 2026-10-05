<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class FreeAssessmentResult extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id'];
    protected $casts = ['summary' => 'array', 'revision' => 'integer'];
    public function assessment()
    {
        return $this->belongsTo(FreeAssessment::class);
    }
    public function player()
    {
        return $this->belongsTo(User::class, 'player_id');
    }
    public function coach()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
    public function currentAttempts()
    {
        return $this->hasMany(FreeAssessmentAttempt::class, 'result_id')
            ->join('free_assessment_results as current_result', 'current_result.id', '=', 'free_assessment_attempts.result_id')
            ->whereColumn('free_assessment_attempts.revision', 'current_result.revision')
            ->select('free_assessment_attempts.*');
    }
    public function attempts()
    {
        return $this->hasMany(FreeAssessmentAttempt::class, 'result_id');
    }
}
