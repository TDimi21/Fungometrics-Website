<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\SecurityAudit;
use Illuminate\Http\Request;

class SecurityAuditLogger
{
    /** @param array<string, mixed> $metadata */
    public function record(
        string $action,
        ?string $userId = null,
        ?string $teamId = null,
        ?Request $request = null,
        array $metadata = []
    ): SecurityAudit {
        return SecurityAudit::create([
            'action' => $action,
            'user_id' => $userId,
            'team_id' => $teamId,
            'ip_hash' => $request?->ip() ? hash('sha256', $request->ip()) : null,
            'metadata' => $metadata,
        ]);
    }
}
