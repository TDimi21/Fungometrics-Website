<?php
namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PlayerVideoShare extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    public function team() { return $this->belongsTo(Team::class); }
    public function coach() { return $this->belongsTo(User::class, 'coach_id'); }
}
