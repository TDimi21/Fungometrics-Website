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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
