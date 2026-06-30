<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArmCareSession extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $casts = [
        'id' => 'string',
        'user_id' => 'string',
        'team_id' => 'string',
        'breakdown' => 'array',
        'score' => 'integer',
        'assigned' => 'integer',
        'completed' => 'integer',
        'completed_total' => 'integer',
        'skipped' => 'integer',
        'duration_seconds' => 'integer',
        'performed_at' => 'datetime',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'deleted_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $fillable = [
        'user_id',
        'team_id',
        'routine_key',
        'routine_label',
        'score',
        'grade',
        'assigned',
        'completed',
        'completed_total',
        'skipped',
        'duration_seconds',
        'breakdown',
        'client_id',
        'performed_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class, 'user_id', 'user_id');
    }
}
