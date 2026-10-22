<?php
namespace Tests\Feature\Communications;
use App\Jobs\CheckCommunicationsWorker;
use Illuminate\Support\Facades\{Cache, Notification, Queue};
use Tests\TestCase;

class CommunicationsStatusTest extends TestCase
{
    public function test_status_is_read_only_and_probe_records_health_without_mail(): void
    {
        Queue::fake(); Notification::fake();
        config(['queue.default' => 'database', 'cache.default' => 'file', 'resend.api_key' => 'never-print-this-test-key']);
        $this->artisan('fmtrx:communications-status')->doesntExpectOutputToContain('never-print-this-test-key')->assertSuccessful();
        Queue::assertNothingPushed();
        $this->artisan('fmtrx:communications-status', ['--probe-worker' => true])->assertSuccessful();
        Queue::assertPushed(CheckCommunicationsWorker::class, function ($job) {
            $this->assertSame('communications', $job->queue);
            $job->handle();
            $this->assertSame($job->probeId, Cache::get('fmtrx.communications.worker_probe')['id']);
            return true;
        });
        Notification::assertNothingSent();
        Cache::forget('fmtrx.communications.worker_probe');
    }
    public function test_probe_refuses_sync_queue(): void
    {
        Queue::fake(); config(['queue.default' => 'sync']);
        $this->artisan('fmtrx:communications-status', ['--probe-worker' => true])->assertFailed();
        Queue::assertNothingPushed();
    }

    public function test_health_gate_requires_fresh_matching_evidence_without_sending(): void
    {
        Queue::fake(); Notification::fake();
        \Illuminate\Support\Facades\Mail::fake();
        config(['queue.default' => 'database', 'cache.default' => 'array']);
        // Keep this test isolated in memory while emulating a persistent store.
        Cache::shouldReceive('get')->with('fmtrx.communications.scheduler_seen')->andReturnUsing(fn () => $this->schedulerEvidence);
        Cache::shouldReceive('get')->with('fmtrx.communications.worker_probe')->andReturnUsing(fn () => $this->workerEvidence);
        config(['cache.default' => 'file']);
        $id = '12345678-1234-4234-8234-123456789abc';
        $this->schedulerEvidence = null;
        $this->workerEvidence = null;
        $this->artisan('fmtrx:communications-status', ['--check' => true])->assertFailed();
        $this->schedulerEvidence = now()->toIso8601String();
        $this->workerEvidence = ['id' => $id, 'completed_at' => now()->toIso8601String()];
        $this->artisan('fmtrx:communications-status', ['--check' => true, '--expect-probe' => $id])->assertSuccessful();
        $this->artisan('fmtrx:communications-status', ['--expect-probe' => '87654321-1234-4234-8234-123456789abc'])->assertFailed();
        foreach ([now()->subMinutes(4)->toIso8601String(), now()->addMinute()->toIso8601String(), 'not-a-date', null] as $bad) {
            $this->schedulerEvidence = $bad;
            $this->artisan('fmtrx:communications-status', ['--check' => true])->assertFailed();
        }
        $this->schedulerEvidence = now()->toIso8601String();
        $this->workerEvidence['completed_at'] = now()->subMinutes(4)->toIso8601String();
        $this->artisan('fmtrx:communications-status', ['--check' => true])->assertFailed();
        Queue::assertNothingPushed(); Notification::assertNothingSent();
        \Illuminate\Support\Facades\Mail::assertNothingSent();
    }

    private $schedulerEvidence;
    private $workerEvidence;

    public function test_probe_and_check_cannot_be_combined_or_use_invalid_id(): void
    {
        Queue::fake();
        $this->artisan('fmtrx:communications-status', ['--probe-worker' => true, '--check' => true])->assertFailed();
        $this->artisan('fmtrx:communications-status', ['--expect-probe' => 'invalid'])->assertFailed();
        Queue::assertNothingPushed();
    }
}
