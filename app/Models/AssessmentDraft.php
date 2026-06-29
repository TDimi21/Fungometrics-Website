<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentDraft extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'user_id',
        'team_id',
        'updated_by',
        'data',
    ];

    public function setDataAttribute($value): void
    {
        $this->attributes['data'] = is_array($value) ? json_encode($value) : $value;
    }

    public function getDataAttribute($value)
    {
        if (is_array($value) || $value === null) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
