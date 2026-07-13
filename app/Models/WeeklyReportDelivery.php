<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyReportDelivery extends Model
{
    use HasFactory;
    use HasUuid;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'team_id',
        'created_by_user_id',
        'sent_by_user_id',
        'source',
        'archive_type',
        'week_start_date',
        'week_end_date',
        'season_start_date',
        'season_end_date',
        'template_key',
        'audience',
        'channel',
        'format',
        'delivery_status',
        'subject',
        'message_preview',
        'recipient_summary',
        'recipients',
        'privacy_warnings',
        'delivery_warnings',
        'send_blockers',
        'export_payload',
        'draft_payload',
        'send_result',
        'copied_at',
        'draft_created_at',
        'sent_at',
        'failed_at',
        'blocked_at',
    ];

    protected $casts = [
        'id' => 'string',
        'team_id' => 'string',
        'created_by_user_id' => 'string',
        'sent_by_user_id' => 'string',
        'week_start_date' => 'date:Y-m-d',
        'week_end_date' => 'date:Y-m-d',
        'season_start_date' => 'date:Y-m-d',
        'season_end_date' => 'date:Y-m-d',
        'recipient_summary' => 'array',
        'recipients' => 'array',
        'privacy_warnings' => 'array',
        'delivery_warnings' => 'array',
        'send_blockers' => 'array',
        'export_payload' => 'array',
        'draft_payload' => 'array',
        'send_result' => 'array',
        'copied_at' => 'datetime',
        'draft_created_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id', 'id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id', 'id');
    }
}
