<?php
namespace App\Models;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
class PlayerDailyReadiness extends Model {
    use HasUuid;
    protected $table = 'player_daily_readiness';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['answers'=>'array', 'scale_version'=>'integer'];
}
