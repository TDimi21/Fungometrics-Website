<?php
namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class PlayerVideo extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $hidden = ['path'];
    public function shares() { return $this->hasMany(PlayerVideoShare::class, 'video_id'); }
}
