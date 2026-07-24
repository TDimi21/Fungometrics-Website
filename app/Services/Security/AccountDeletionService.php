<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\AccountDeletionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    public function __construct(private SecurityAuditLogger $audit)
    {
    }

    /** @return array<string, mixed> */
    public function authorize(User $user, string $password, Request $request): array
    {
        if (! Hash::check($password, (string) $user->password)) {
            $this->audit->record('account_deletion.authorization_failed', $user->id, null, $request);
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }

        AccountDeletionRequest::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        $token = Str::random(64);
        $deletion = AccountDeletionRequest::create([
            'user_id' => $user->id,
            'confirmation_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->audit->record('account_deletion.authorized', $user->id, null, $request, [
            'request_id' => $deletion->id,
        ]);

        return [
            'confirmation_token' => $token,
            'expires_at' => $deletion->expires_at->toIso8601String(),
            'required_phrase' => 'DELETE',
            'subscription_notice' => 'Deleting FMTRX does not cancel an Apple subscription. Manage or cancel it in Apple Subscriptions.',
            'subscription_management_url' => config('legal.apple_subscriptions_url'),
        ];
    }

    /** @return array<string, mixed> */
    public function status(User $user): array
    {
        $deletion = AccountDeletionRequest::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if (! $deletion) {
            return ['status' => 'not_requested'];
        }

        return [
            'status' => $deletion->used_at
                ? 'completed'
                : ($deletion->expires_at->isPast() ? 'expired' : 'awaiting_confirmation'),
            'expires_at' => $deletion->expires_at->toIso8601String(),
            'used_at' => $deletion->used_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function delete(User $user, string $token, string $phrase, Request $request): array
    {
        if ('DELETE' !== $phrase) {
            throw ValidationException::withMessages(['confirmation' => 'Type DELETE to confirm permanent account deletion.']);
        }

        return DB::transaction(function () use ($user, $token, $request): array {
            $deletion = AccountDeletionRequest::query()
                ->where('confirmation_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if (! $deletion || $deletion->user_id !== $user->id || $deletion->used_at || $deletion->expires_at->isPast()) {
                $this->audit->record('account_deletion.confirmation_rejected', $user->id, null, $request);
                throw ValidationException::withMessages(['confirmation_token' => 'The deletion confirmation is invalid, expired, or already used.']);
            }

            $deletion->update(['used_at' => now(), 'attempts' => $deletion->attempts + 1]);
            $this->audit->record('account_deletion.completed', $user->id, null, $request, [
                'request_id' => $deletion->id,
                'policy_version' => '2026-07-23',
            ]);

            $user->tokens()->delete();
            $this->deleteReferencedPhotos($user->id);
            $this->deleteWhere('coach_teams', 'coach_id', $user->id);
            $this->deleteWhere('player_teams', 'user_id', $user->id);
            $this->deleteWhere('practice_line_ups', 'user_id', $user->id);
            $this->deleteWhere('account_claims', 'user_id', $user->id);
            $this->deleteWhere('team_join_challenges', 'user_id', $user->id);

            $this->anonymizeWhere('profiles', 'user_id', $user->id, [
                'first_name' => 'Deleted', 'last_name' => 'User', 'picture' => null,
            ]);
            $this->anonymizeWhere('players', 'user_id', $user->id, [
                'picture' => null, 'born' => null, 'mobile_number' => null, 'email' => null,
            ]);
            $this->anonymizeWhere('player_assessments', 'user_id', $user->id, [
                'notes' => null, 'coach_insights' => null,
            ]);
            $this->anonymizeWhere('player_fitnesses', 'user_id', $user->id, []);
            $this->anonymizeWhere('subscriptions', 'user_id', $user->id, [
                'metadata' => json_encode(['account_deleted' => true], JSON_THROW_ON_ERROR),
            ]);
            $this->anonymizeWhere('sms_logs', 'user_id', $user->id, [
                'phone' => 'deleted', 'message' => '[deleted]', 'response' => null,
            ]);

            $anonymous = 'deleted+'.hash('sha256', $user->id).'@deleted.fmtrx.invalid';
            $user->forceFill([
                'email' => $anonymous,
                'phone' => 'deleted-'.substr(hash('sha256', $user->id), 0, 24),
                'password' => Hash::make(Str::random(64)),
                'status' => false,
                'subscription_plan' => 'free',
            ])->save();
            $user->delete();

            return [
                'status' => 'deleted',
                'deleted_at' => now()->toIso8601String(),
                'message' => 'Your FMTRX account has been deleted and retained records have been anonymized.',
                'subscription_notice' => 'Apple subscriptions are managed separately and are not canceled by deleting FMTRX.',
                'subscription_management_url' => config('legal.apple_subscriptions_url'),
            ];
        });
    }

    private function deleteReferencedPhotos(string $userId): void
    {
        $pictures = collect();
        foreach (['profiles', 'players'] as $table) {
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, 'user_id')
                && Schema::hasColumn($table, 'picture')) {
                $pictures = $pictures->merge(
                    DB::table($table)->where('user_id', $userId)->pluck('picture')
                );
            }
        }

        $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
        $pictures->filter()->unique()->each(function (string $picture) use ($disk): void {
            $path = (string) (parse_url($picture, PHP_URL_PATH) ?: $picture);
            $playersAt = strpos($path, 'players/');
            if (false === $playersAt) {
                return;
            }
            Storage::disk($disk)->delete(ltrim(substr($path, $playersAt), '/'));
        });
    }

    private function deleteWhere(string $table, string $column, string $userId): void
    {
        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
            DB::table($table)->where($column, $userId)->delete();
        }
    }

    /** @param array<string, mixed> $values */
    private function anonymizeWhere(string $table, string $column, string $userId, array $values): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        $safe = collect($values)->filter(fn ($value, string $key): bool => Schema::hasColumn($table, $key))->all();
        if ([] !== $safe) {
            DB::table($table)->where($column, $userId)->update($safe);
        }
    }
}
