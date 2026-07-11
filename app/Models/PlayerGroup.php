<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerGroup extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'team_id',
        'created_by',
        'name',
        'member_ids',
    ];

    protected $casts = [
        'member_ids' => 'array',
    ];
}
