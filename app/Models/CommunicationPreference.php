<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunicationPreference extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'user_id';
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['workouts' => 'boolean','assessments' => 'boolean','development_plans' => 'boolean','weekly_reports' => 'boolean','team_announcements' => 'boolean'];
    public const DEFAULTS = ['workouts' => true,'assessments' => true,'development_plans' => true,'weekly_reports' => false,'team_announcements' => true];
    public static function forUser(string $id): array
    {
        $p = static::find($id);
        return $p ? array_replace(self::DEFAULTS, $p->only(array_keys(self::DEFAULTS))) : self::DEFAULTS;
    }
}
