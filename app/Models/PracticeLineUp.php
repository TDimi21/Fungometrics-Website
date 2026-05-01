<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PracticeLineUp extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'practice_id',
        'user_id',
        'sort',
        'is_pitching',
        'is_batting',
    ];

    protected $casts = [
        'is_pitching' => 'boolean',
        'is_batting' => 'boolean',
        'id' => 'string',
    ];

    public function practice(): BelongsTo
    {
        return $this->belongsTo(Practice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


}
