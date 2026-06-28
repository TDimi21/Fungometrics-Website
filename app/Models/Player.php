<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    use HasFactory;
    use HasUuid;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'height_in_ft',
        'height_in_inch',
        'user_id',
        'number_in_shirt',
        'born_date',
        'hit_side',
        'throw_side'
    ];

    /**
     * Always expose born_date in a single canonical format (YYYY-MM-DD) so every
     * client can parse it the same way, regardless of how it was stored.
     */
    public function getBornDateAttribute($value)
    {
        if (! $value) {
            return $value;
        }

        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return $value;
        }
    }

    /**
     * Normalize any incoming DOB (ISO, slashes, etc.) to YYYY-MM-DD on save.
     */
    public function setBornDateAttribute($value): void
    {
        if (! $value) {
            $this->attributes['born_date'] = $value;

            return;
        }

        try {
            $this->attributes['born_date'] = \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            $this->attributes['born_date'] = $value;
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
