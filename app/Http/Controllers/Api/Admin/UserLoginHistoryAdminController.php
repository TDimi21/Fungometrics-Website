<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserLoginHistoryAdminController extends Controller
{
    public function __invoke(User $user): JsonResponse
    {
        abort_unless('coach' === $user->type, 404);

        $logins = DB::table('user_login_history')
            ->where('user_id', $user->id)
            ->orderByDesc('logged_in_at')
            ->limit(20)
            ->get(['logged_in_at'])
            ->map(static fn ($login): array => [
                'logged_in_at' => Carbon::parse($login->logged_in_at)->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $logins,
        ]);
    }
}
