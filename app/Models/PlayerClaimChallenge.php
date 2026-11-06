<?php
namespace App\Models;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
class PlayerClaimChallenge extends Model {
    use HasUuid;
    public $incrementing=false;
    protected $keyType='string';
    protected $guarded=[];
    protected $hidden=['contact','contact_hash','code_hash','selection_hash'];
    protected $casts=['contact'=>'encrypted','expires_at'=>'datetime','verified_at'=>'datetime','used_at'=>'datetime'];
}
