<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPlanAssignment extends Model
{
    use HasFactory;
    use HasUuid;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'plan_id',
        'user_id',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class, 'plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
