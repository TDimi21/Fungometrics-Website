<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class TeamInvitation extends Model
{
    use HasUuid;
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $attributes = ['generation' => 1,'delivery_status' => 'queued'];
    protected $hidden = ['token_hash'];
    protected $casts = ['generation' => 'integer', 'expires_at' => 'datetime', 'sent_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime', 'email_verified_at' => 'datetime'];

    public function state(): string
    {
        if ($this->accepted_at) {
            return 'accepted';
        }
        if ($this->revoked_at) {
            return 'revoked';
        }
        return $this->expires_at->isPast() ? 'expired' : 'pending';
    }
}
