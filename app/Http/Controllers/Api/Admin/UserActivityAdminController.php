<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserActivityAdminController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => ['nullable', Rule::in(['day', 'week', 'month'])],
            'role' => ['nullable', Rule::in(['all', 'coach', 'player'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $range = (string) ($validated['range'] ?? 'day');
        $role = (string) ($validated['role'] ?? 'all');
        $perPage = (int) ($validated['per_page'] ?? 50);
        $until = now();
        $since = match ($range) {
            'week' => $until->copy()->subDays(7),
            'month' => $until->copy()->subDays(30),
            default => $until->copy()->subDay(),
        };

        $allRecentUsers = $this->userScope($since);
        $visibleUsers = clone $allRecentUsers;
        if ('all' !== $role) {
            $visibleUsers->where('u.type', $role);
        }

        $users = (clone $visibleUsers)
            ->leftJoin('profiles as profile', 'profile.user_id', '=', 'u.id')
            ->select([
                'u.id',
                'u.type',
                'u.email',
                'u.phone',
                'u.last_login_at',
                'profile.first_name',
                'profile.last_name',
            ])
            ->selectSub(function (Builder $query) use ($since, $until): void {
                $query->from('practices')
                    ->selectRaw('count(*)')
                    ->whereColumn('practices.user_id', 'u.id')
                    ->whereNull('practices.deleted_at')
                    ->whereBetween('practices.created_at', [$since, $until]);
            }, 'sessions_recorded')
            ->orderByDesc('u.last_login_at')
            ->paginate($perPage);

        $users->getCollection()->transform(static function ($user): array {
            $name = trim((string) $user->first_name.' '.(string) $user->last_name);

            return [
                'id' => (string) $user->id,
                'name' => '' !== $name ? $name : ((string) $user->email ?: 'Unnamed user'),
                'role' => (string) $user->type,
                'email' => $user->email,
                'phone' => $user->phone,
                'last_login_at' => Carbon::parse($user->last_login_at)->toIso8601String(),
                'sessions_recorded' => (int) $user->sessions_recorded,
            ];
        });

        $sessionCreators = DB::table('users as session_users')
            ->select('session_users.id')
            ->whereIn('session_users.type', 'all' === $role ? ['coach', 'player'] : [$role])
            ->whereNull('session_users.deleted_at')
            ->where('session_users.is_dummy', false);

        $sessionsRecorded = DB::table('practices')
            ->whereNull('practices.deleted_at')
            ->whereBetween('practices.created_at', [$since, $until])
            ->whereIn('practices.user_id', $sessionCreators)
            ->count();

        return response()->json([
            'status' => 'success',
            'message' => 'admin user activity',
            'data' => [
                'range' => $range,
                'role' => $role,
                'since' => $since->toIso8601String(),
                'until' => $until->toIso8601String(),
                'summary' => [
                    'active_users' => (clone $visibleUsers)->count(),
                    'coach_logins' => (clone $allRecentUsers)->where('u.type', 'coach')->count(),
                    'player_logins' => (clone $allRecentUsers)->where('u.type', 'player')->count(),
                    'sessions_recorded' => $sessionsRecorded,
                ],
                'users' => $users,
            ],
        ]);
    }

    private function userScope(CarbonInterface $since): Builder
    {
        return DB::table('users as u')
            ->whereIn('u.type', ['coach', 'player'])
            ->where('u.is_dummy', false)
            ->whereNull('u.deleted_at')
            ->whereNotNull('u.last_login_at')
            ->where('u.last_login_at', '>=', $since);
    }
}
