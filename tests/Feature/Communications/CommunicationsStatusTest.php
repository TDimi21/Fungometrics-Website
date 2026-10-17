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
}
