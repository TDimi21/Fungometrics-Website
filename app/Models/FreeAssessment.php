<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class FreeAssessment extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = ['id'];
    protected $casts = ['assessment_date' => 'date'];
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
    public function participants()
    {
        return $this->belongsToMany(User::class, 'free_assessment_players', 'assessment_id', 'player_id');
    }
    public function results()
    {
        return $this->hasMany(FreeAssessmentResult::class, 'assessment_id');
    }
}
