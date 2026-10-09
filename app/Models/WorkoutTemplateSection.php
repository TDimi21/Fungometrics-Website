<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;

class WorkoutTemplateSection extends Model
{
    use HasUuid;
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $guarded = [];
    public function exercises()
    {
        return $this->hasMany(WorkoutTemplateExercise::class)->orderBy('sort_order');
    }
}
