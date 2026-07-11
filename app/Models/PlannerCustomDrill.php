<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlannerCustomDrill extends Model
{
    use HasFactory;
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'created_by',
        'team_id',
        'name',
        'bucket',
        'category_group',
        'equipment',
        'visibility',
        'source',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Flatten to the app's normalized drill shape: the JSON `data` blob plus the
     * promoted identity/ownership columns.
     *
     * @return array<string, mixed>
     */
    public function toDrillArray(): array
    {
        return array_merge((array) ($this->data ?? []), [
            'id'             => $this->id,
            'created_by'     => $this->created_by,
            'team_id'        => $this->team_id,
            'name'           => $this->name,
            'bucket'         => $this->bucket,
            'category_group' => $this->category_group,
            'categoryGroup'  => $this->category_group,
            'equipment'      => $this->equipment,
            'visibility'     => $this->visibility,
            'source'         => $this->source ?? 'custom',
        ]);
    }
}
