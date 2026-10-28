<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasUuid;
class LongTossGuide extends Model {
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['stage'=>'integer','starting_count'=>'integer','readiness'=>'integer','snapshot'=>'array','review'=>'array','pain'=>'boolean','pain_after'=>'boolean','finished_at'=>'datetime'];
}
