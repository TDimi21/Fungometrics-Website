<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Training\Result;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class BullpenSyncCapabilities extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Fail closed during a rollout until the database migration is present.
        if ( ! Schema::hasColumn('bullpen_practice_results', 'client_request_id')) {
            return response()->json(['message' => 'Pitch synchronization update is pending.'], 503);
        }
        return response()->json(['idempotency_version' => 1]);
    }
}
