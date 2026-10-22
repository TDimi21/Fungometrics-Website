<?php
namespace App\Console\Commands;

use App\Jobs\CheckCommunicationsWorker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, DB, Schema};
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class CommunicationsStatus extends Command
{
    protected $signature = 'fmtrx:communications-status {--probe-worker : Queue a harmless probe; does not send mail} {--check : Fail unless scheduler and worker evidence is fresh} {--expect-probe= : Require this completed probe UUID}';
    protected $description = 'Show safe communications configuration and queue health without exposing secrets.';
    public function handle(): int
    {
        $connection = config('queue.default');
        $expected = $this->option('expect-probe');
        if (($expected && ! Str::isUuid($expected)) || ($this->option('probe-worker') && ($this->option('check') || $expected))) {
            $this->error('Use --probe-worker separately; --expect-probe requires a valid UUID.');
            return self::FAILURE;
        }
        $scheduler = Cache::get('fmtrx.communications.scheduler_seen');
        $probe = Cache::get('fmtrx.communications.worker_probe');
        $healthy = config('queue.connections.'.$connection.'.driver') === 'database'
            && ! in_array(config('cache.default'), ['array', 'null'])
            && $this->isRecent($scheduler)
            && is_array($probe) && $this->isRecent($probe['completed_at'] ?? null)
            && (! $expected || ($probe['id'] ?? null) === $expected);

        if ($this->option('probe-worker')) {
            if (config('queue.connections.'.$connection.'.driver') !== 'database' || in_array(config('cache.default'), ['array', 'null'])) {
                $this->error('The probe requires the database queue and persistent cache.');
                return self::FAILURE;
            }
            $id = (string) Str::uuid();
            CheckCommunicationsWorker::dispatch($id);
            $this->info('Probe queued: '.$id.'. Run this command again without --probe-worker and compare the completed probe ID.');
        }
        $counts = [];
        foreach (['team_invitations' => 'delivery_status', 'communication_deliveries' => 'status'] as $table => $column) {
            $counts[$table] = Schema::hasTable($table) ? DB::table($table)->select($column)->selectRaw('COUNT(*) AS total')->groupBy($column)->pluck('total', $column)->all() : 'table_missing';
        }
        $this->line(json_encode([
            'mailer' => config('mail.default'),
            'resend_key_configured' => filled(config('resend.api_key')),
            'queue' => $connection,
            'cache' => config('cache.default'),
            'engine_enabled' => config('communications.enabled'),
            'send_enabled' => config('communications.send_enabled'),
            'invitations_enabled' => config('communications.invitations_enabled'),
            'weekly_enabled' => config('communications.weekly_enabled'),
            'pilot_mode' => config('communications.pilot_mode'),
            'allowlist_count' => count(config('communications.pilot_allowlist', [])),
            'categories' => config('communications.categories'),
            'scheduler_last_run' => $scheduler,
            'scheduler_and_worker_recent' => $healthy,
            'freshness_limit_seconds' => 180,
            'last_completed_probe' => Cache::get('fmtrx.communications.worker_probe'),
            'database_queue_counts' => Schema::hasTable('jobs') ? DB::table('jobs')->select('queue')->selectRaw('COUNT(*) AS total')->groupBy('queue')->pluck('total', 'queue')->all() : 'table_missing',
            'unprocessed_events' => Schema::hasTable('communication_events') ? DB::table('communication_events')->whereNull('processed_at')->count() : 'table_missing',
            'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 'table_missing',
            'ledger_counts' => $counts,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if (($this->option('check') || $expected) && ! $healthy) {
            $this->error('STOP: scheduler/worker evidence is missing, stale, mismatched, or uses unsupported queue/cache configuration.');
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    private function isRecent($timestamp): bool
    {
        if (! is_string($timestamp) || $timestamp === '') return false;
        try {
            $age = now()->timestamp - Carbon::parse($timestamp)->timestamp;
            return $age >= 0 && $age <= 180;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
