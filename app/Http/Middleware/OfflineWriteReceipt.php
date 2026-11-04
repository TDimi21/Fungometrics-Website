<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Commit a write and its receipt together. Lost responses are safe to retry. */
class OfflineWriteReceipt
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Offline-Request-ID');
        if (!$key) return $next($request);
        abort_unless(preg_match('/^[a-zA-Z0-9_-]{16,80}$/', $key), 422, 'Invalid offline request ID.');
        $path = $request->path();
        $supported = $request->isMethod('POST') && in_array($path, [
            'api/coach/add/teams', 'api/coach/add/players', 'api/training',
            'api/result/longtoss', 'api/result/exitvelocity', 'api/result/weightball',
            'api/result/bullpen', 'api/result/batting', 'api/result/cage',
        ], true);
        $supported = $supported || ($request->isMethod('PUT') && preg_match('#^api/training/[a-f0-9-]+$#i', $path));
        abort_unless($supported && $request->isJson(), 422, 'Unsupported offline operation.');
        $request->validate(['offline_recorded_at' => ['sometimes', 'date', 'before_or_equal:'.now()->addDay()->toIso8601String()]]);
        $user = Auth::guard('sanctum')->user();
        abort_unless($user, 401);
        $hash = hash('sha256', $request->method().' '.$path.' '.$request->getContent());
        DB::beginTransaction();
        try {
            // A per-account lock serializes retries, including simultaneous devices.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $receipt = DB::table('offline_write_receipts')->where('owner_id', $user->id)->where('request_id', $key)->first();
            if ($receipt) {
                abort_unless(hash_equals($receipt->payload_hash, $hash), 409, 'Offline request ID was reused with different data.');
                DB::commit();
                return response()->json(json_decode($receipt->response_json, true), $receipt->response_status);
            }
            // All existing authorization, entitlement and validation middleware still run.
            $response = $next($request);
            $body = json_decode($response->getContent(), true);
            if (!$response->isSuccessful() || !is_array($body) || ($body['status'] ?? null) !== 'success') {
                DB::rollBack();
                return $response;
            }
            $this->preserveRecordedTime($request, $body);
            $body['offline_receipt'] = $key;
            DB::table('offline_write_receipts')->insert([
                'owner_id' => $user->id, 'request_id' => $key, 'payload_hash' => $hash,
                'response_json' => json_encode($body, JSON_THROW_ON_ERROR),
                'response_status' => $response->getStatusCode(), 'created_at' => now(),
            ]);
            DB::commit();
            $response->setContent(json_encode($body, JSON_THROW_ON_ERROR));
            return $response;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
    private function preserveRecordedTime(Request $request, array $body): void
    {
        if (!$request->filled('offline_recorded_at')) return;
        $time = \Carbon\Carbon::parse($request->input('offline_recorded_at'))->setTimezone(config('app.timezone', 'UTC'));
        if ($request->path() === 'api/training') {
            \App\Models\Practice::whereKey($body['data']['id'])->update(['started' => $time, 'created_at' => $time]);
        } elseif ($request->isMethod('PUT')) {
            \App\Models\Practice::whereKey($body['data']['id'])->update(['finished' => $time]);
        } else {
            $model = match ($request->path()) {
                'api/result/longtoss' => \App\Models\LongTossPractice::class,
                'api/result/exitvelocity' => \App\Models\ExitVelocityPractice::class,
                'api/result/weightball' => \App\Models\WeightBallPractice::class,
                default => null,
            };
            if ($model && isset($body['data']['result']['id'])) $model::whereKey($body['data']['result']['id'])->update(['created_at' => $time]);
        }
    }

}
