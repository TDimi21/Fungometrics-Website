<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Models\{CommunicationEvent,CommunicationDelivery,CommunicationPreference,DailyPlan,DailyPlanAssignment,DailyPlanProgress,FreeAssessment,FreeAssessmentResult,PlayerAssessment,WorkoutProgram,PlayerTeam,CoachTeam,User,Profile,Team,PlayerFitness,Subscription};
use App\Services\Communications\{CommunicationOutbox,CommunicationContent};
use App\Services\Intelligence\PlayerIntelligenceService;
use App\Jobs\SendCommunicationDelivery;
use App\Notifications\FmtrxActivityNotification;
use Illuminate\Support\Facades\{DB,Hash,Mail,Notification,Queue};
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use RuntimeException;
use InvalidArgumentException;

class CommunicationEngineTest extends TestCase
{
    private User $coach;
    private User $player;
    private Team $team;
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Queue::fake();
        config(['communications.enabled' => false,'communications.send_enabled' => true,'communications.weekly_enabled' => true,'queue.default' => 'database']);
        $this->coach = User::factory()->create(['type' => 'coach','status' => true,'is_dummy' => false,'password' => Hash::make('Password123')]);
        $this->player = User::factory()->create(['type' => 'player','status' => true,'is_dummy' => false,'password' => Hash::make('Password123')]);
        Profile::factory()->create(['user_id' => $this->coach->id]);
        Profile::factory()->create(['user_id' => $this->player->id]);
        $this->team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $this->coach->id,'team_id' => $this->team->id,'is_main' => true]);
        PlayerTeam::factory()->create(['user_id' => $this->player->id,'team_id' => $this->team->id,'actual' => true]);
        config(['communications.enabled' => true]);
    }
    private function plan(string $status = 'published'): DailyPlan
    {
        return DailyPlan::create(['id' => (string)Str::uuid(),'team_id' => $this->team->id,'created_by' => $this->coach->id,'name' => 'Test workout','date' => now()->toDateString(),'status' => $status,'version' => 1,'buckets' => []]);
    }
    private function assign(DailyPlan $p): DailyPlanAssignment
    {
        return DailyPlanAssignment::create(['plan_id' => $p->id,'user_id' => $this->player->id,'schedule_status' => 'active']);
    }
    private function process(): void
    {
        foreach(CommunicationEvent::whereNull('processed_at')->pluck('id') as $id) {
            app(CommunicationOutbox::class)->process($id);
        }
    }
    private function send(): void
    {
        foreach(CommunicationDelivery::pluck('id') as $id) {
            (new SendCommunicationDelivery($id))->handle(app(CommunicationContent::class));
        }
    }
    public function test_workout_publication_and_assignment_dedupe_and_edits_stay_silent(): void
    {
        $p = $this->plan();
        $this->assign($p);
        $this->process();
        $this->process();
        $this->assertSame(1, CommunicationDelivery::count());
        $this->send();
        $this->send();
        Notification::assertSentToTimes($this->player, FmtrxActivityNotification::class, 1);
        $p->update(['name' => 'Edited workout']);
        $this->process();
        $this->send();
        Notification::assertCount(1);
        $this->assertStringContainsString('?tab=workout&date=', CommunicationDelivery::first()->payload['action_url']);
    }
    public function test_draft_assignments_notify_only_after_publication(): void
    {
        $p = $this->plan('draft');
        $this->assign($p);
        $this->process();
        $this->assertSame(0, CommunicationDelivery::count());
        $p->update(['status' => 'published']);
        $this->process();
        $this->assertSame(1, CommunicationDelivery::count());
    }
    public function test_assignment_added_after_initial_publication_is_not_lost(): void
    {
        $p = $this->plan();
        $this->process();
        $this->assertSame(0, CommunicationDelivery::count());
        $this->assign($p);
        $this->process();
        $this->assertSame(1, CommunicationDelivery::count());
    }
    public function test_rolled_back_publication_leaves_no_outbox_records(): void
    {
        DB::beginTransaction();
        $this->assign($this->plan());
        DB::rollBack();
        $this->assertSame(0, CommunicationEvent::count());
    }
    public function test_preferences_and_removed_membership_checked_at_delivery_time(): void
    {
        $this->assign($this->plan());
        $this->process();
        CommunicationPreference::create(['user_id' => $this->player->id,'workouts' => false]);
        $this->send();
        Notification::assertNothingSent();
        $this->assertSame('preference_disabled', CommunicationDelivery::first()->failure_code);
        CommunicationDelivery::query()->update(['status' => 'pending']);
        CommunicationPreference::query()->update(['workouts' => true]);
        PlayerTeam::where('user_id', $this->player->id)->update(['actual' => false]);
        $this->send();
        Notification::assertNothingSent();
        $this->assertSame('access_removed', CommunicationDelivery::first()->failure_code);
    }
    public function test_missing_email_and_send_disabled_never_send(): void
    {
        $this->assign($this->plan());
        $this->process();
        config(['communications.send_enabled' => false]);
        $this->send();
        $this->assertSame('pending', CommunicationDelivery::first()->status);
        config(['communications.send_enabled' => true]);
        $this->player->update(['email' => null]);
        $this->send();
        $this->assertSame('recipient_unavailable', CommunicationDelivery::first()->failure_code);
        Notification::assertNothingSent();
    }
    public function test_preferences_are_self_only_and_weekly_is_opt_in(): void
    {
        Sanctum::actingAs($this->player, ['player']);
        $this->getJson('/api/me/communication-preferences')->assertOk()->assertJsonPath('preferences.weekly_reports', false);
        $this->putJson('/api/me/communication-preferences', ['weekly_reports' => true,'workouts' => false])->assertOk()->assertJsonPath('preferences.weekly_reports', true);
        $this->putJson('/api/me/communication-preferences', ['user_id' => $this->coach->id,'weekly_reports' => true])->assertUnprocessable();
        $this->assertFalse(CommunicationPreference::forUser($this->coach->id)['weekly_reports']);
    }
    public function test_inbox_is_private_and_removing_assignment_hides_old_message(): void
    {
        $a = $this->assign($this->plan());
        $this->process();
        $id = CommunicationDelivery::first()->id;
        Sanctum::actingAs($this->coach, ['coach']);
        $this->getJson('/api/me/communications')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/me/communications/'.$id.'/read')->assertNotFound();
        Sanctum::actingAs($this->player, ['player']);
        $this->getJson('/api/me/communications')->assertOk()->assertJsonCount(1, 'data');
        $a->delete();
        $this->getJson('/api/me/communications')->assertOk()->assertJsonCount(0, 'data');
    }
    public function test_completed_assessment_notifies_once_and_drafts_do_not(): void
    {
        $event = FreeAssessment::create(['team_id' => $this->team->id,'created_by' => $this->coach->id,'name' => 'Skills day','location' => 'Field','assessment_date' => now()->toDateString(),'status' => 'active']);
        $event->participants()->attach($this->player->id, ['id' => (string) Str::uuid()]);
        FreeAssessmentResult::create(['assessment_id' => $event->id,'player_id' => $this->player->id,'entered_by' => $this->coach->id,'station' => 'push_ups','summary' => ['value' => 10],'revision' => 1]);
        $this->process();
        $this->assertSame(0, CommunicationDelivery::count());
        $event->update(['status' => 'completed','report_snapshot' => ['finalized_at' => now()->toIso8601String()]]);
        $this->process();
        $this->send();
        Notification::assertCount(1);
        $event->update(['name' => 'Updated name']);
        $this->process();
        $this->send();
        Notification::assertCount(1);
        $this->assertStringContainsString('/assessment-reports?', CommunicationDelivery::first()->payload['action_url']);
    }
    public function test_saved_player_assessment_uses_existing_identity(): void
    {
        PlayerAssessment::create(['user_id' => $this->player->id,'team_id' => $this->team->id,'assessed_by' => $this->coach->id,'assessment_date' => now()->toDateString(),'type' => 'strength','bench_lbs' => 100]);
        $this->process();
        $this->assertSame($this->player->id, CommunicationDelivery::firstOrFail()->recipient_id);
    }
    public function test_development_publication_is_deduped_across_batches(): void
    {
        $p = $this->plan();
        $this->assign($p);
        $program = WorkoutProgram::create(['team_id' => $this->team->id,'created_by' => $this->coach->id,'name' => 'Spring development','weeks' => 4,'start_date' => now()->toDateString(),'status' => 'draft','version' => 1,'schedule' => []]);
        $this->assertSame(0, CommunicationEvent::where('event_type', 'DevelopmentPlanPublished')->count());
        $program->update(['status' => 'published','version' => 2,'schedule' => [['published_at' => now()->toIso8601String(),'daily_plan_ids' => [$this->player->id => $p->id]]]]);
        $this->process();
        $program->update(['version' => 3,'schedule' => [['published_at' => now()->toIso8601String(),'daily_plan_ids' => [$this->player->id => $p->id],'note' => 'edit']]]);
        $this->process();
        $this->assertSame(1, CommunicationDelivery::where('event_type', 'DevelopmentPlanPublished')->count());
    }
    public function test_weekly_report_is_opt_in_deduped_and_skips_no_data(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-19 08:00:00'));
        $this->mock(PlayerIntelligenceService::class, fn ($m) => $m->shouldReceive('build')->andReturn(['daily_velocity_averages' => [],'benchmark_profile' => ['metrics' => []]]));
        $this->artisan('fmtrx:weekly-progress')->assertSuccessful();
        $this->assertSame(0, CommunicationEvent::count());
        CommunicationPreference::create(['user_id' => $this->player->id,'weekly_reports' => true]);
        $this->artisan('fmtrx:weekly-progress')->assertSuccessful();
        $this->artisan('fmtrx:weekly-progress')->assertSuccessful();
        $this->assertSame(1, CommunicationEvent::count());
        $this->process();
        $this->assertSame(0, CommunicationDelivery::count());
    }
    public function test_weekly_metrics_are_based_on_recorded_values_and_periods(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-19 08:00:00'));
        $this->mock(PlayerIntelligenceService::class, fn ($m) => $m->shouldReceive('build')->andReturn(['daily_velocity_averages' => [
            ['date' => '2026-10-06','average_hitting_velocity' => 70,'hitting_sample_count' => 2],
            ['date' => '2026-10-13','average_hitting_velocity' => 80,'hitting_sample_count' => 2],
            ['date' => '2026-10-14','average_hitting_velocity' => 90,'hitting_sample_count' => 2],
            ['date' => '2026-10-20','average_hitting_velocity' => 150,'hitting_sample_count' => 200]],'benchmark_profile' => ['metrics' => []]]));
        CommunicationPreference::create(['user_id' => $this->player->id,'weekly_reports' => true]);
        $this->artisan('fmtrx:weekly-progress')->assertSuccessful();
        $this->process();
        $body = implode(' ', CommunicationDelivery::firstOrFail()->payload['lines']);
        $this->assertStringContainsString('85 mph across 4 readings', $body);
        $this->assertStringContainsString('change +15.0 mph', $body);
        $this->assertStringNotContainsString('Pitching velocity:', $body);
        $this->assertStringNotContainsString('150 mph', $body);
    }
    public function test_coach_welcome_uses_same_notification_template(): void
    {
        $u = User::factory()->create(['type' => 'coach','status' => true,'is_dummy' => false,'password' => Hash::make('Password123')]);
        $this->process();
        $this->send();
        Notification::assertSentTo($u, FmtrxActivityNotification::class);
    }
    public function test_explicit_workout_update_is_authorized_and_idempotent(): void
    {
        $p = $this->plan();
        $this->assign($p);
        $this->process();
        Sanctum::actingAs($this->coach, ['coach']);
        $url = '/api/coach/workouts/'.$p->id.'/notify-update';
        $this->postJson($url, ['version' => 1,'confirmed' => true])->assertAccepted();
        $this->postJson($url, ['version' => 1,'confirmed' => true])->assertAccepted();
        $this->process();
        $this->assertSame(2, CommunicationDelivery::count());
        $other = User::factory()->create(['type' => 'coach','status' => true]);
        Sanctum::actingAs($other, ['coach']);
        $this->postJson($url, ['version' => 1,'confirmed' => true])->assertForbidden();
    }
    public function test_verified_billing_retries_do_not_send_duplicate_or_false_expiration(): void
    {
        config(['billing.revenuecat.webhook_auth' => 'Bearer test-hook','billing.revenuecat.environment' => 'test','billing.revenuecat.sandbox_stores' => ['TEST_STORE']]);
        $payload = ['api_version' => '1.0','event' => ['id' => 'billing-once','type' => 'INITIAL_PURCHASE','event_timestamp_ms' => now()->valueOf(),'app_user_id' => $this->player->id,'product_id' => 'fmtrx_player_pro_monthly','environment' => 'SANDBOX','store' => 'TEST_STORE','transaction_id' => 'txn-comms','original_transaction_id' => 'original-comms','purchased_at_ms' => now()->subDay()->valueOf(),'expiration_at_ms' => now()->addMonth()->valueOf()]];
        $url = '/api/billing/revenuecat/webhook';
        $this->postJson($url, $payload, ['Authorization' => 'wrong'])->assertUnauthorized();
        $this->assertSame(0, CommunicationEvent::count());
        $this->postJson($url, $payload, ['Authorization' => 'Bearer test-hook'])->assertOk();
        $this->postJson($url, $payload, ['Authorization' => 'Bearer test-hook'])->assertOk();
        $this->process();
        $this->send();
        Notification::assertCount(1);
        $payload['event']['id'] = 'cancel-once';
        $payload['event']['type'] = 'CANCELLATION';
        $this->postJson($url, $payload, ['Authorization' => 'Bearer test-hook'])->assertOk();
        $this->process();
        $d = CommunicationDelivery::latest()->where('event_type', 'SubscriptionStatusChanged')->get()->first(fn ($d) => str_contains(implode(' ', $d->payload['lines']), 'Automatic renewal'));
        $this->assertNotNull($d);
        $this->assertStringContainsString('remains active', implode(' ', $d->payload['lines']));
        $this->assertStringNotContainsString('has expired', implode(' ', $d->payload['lines']));
    }
    public function test_dispatch_requires_async_queue_and_dry_run_does_not_queue(): void
    {
        $this->assign($this->plan());
        $this->artisan('fmtrx:communications-dispatch', ['--dry-run' => true])->assertSuccessful();
        Queue::assertNothingPushed();
        config(['queue.default' => 'sync']);
        $this->artisan('fmtrx:communications-dispatch')->assertFailed();
        Queue::assertNothingPushed();
    }
    public function test_provider_exception_is_sanitized_and_retry_cannot_duplicate(): void
    {
        $this->assign($this->plan());
        $this->process();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('secret provider key'));
        $this->send();
        $this->send();
        $this->assertSame('unconfirmed', CommunicationDelivery::first()->status);
        $this->assertStringNotContainsString('secret', CommunicationDelivery::first()->toJson());
    }
    public function test_invalid_frontend_url_is_rejected_before_delivery(): void
    {
        $this->assign($this->plan());
        config(['communications.frontend_url' => 'https://bad.example/api']);
        $this->expectException(InvalidArgumentException::class);
        $this->process();
    }
    public function test_team_announcements_obey_preferences_scope_and_deduplication(): void
    {
        Sanctum::actingAs($this->player, ['player']);
        $url = '/api/coach/teams/'.$this->team->id.'/communications/announcements';
        $request = ['request_id' => (string)Str::uuid(),'message' => 'Training moves to field two.'];
        $this->postJson($url, $request)->assertForbidden();
        Sanctum::actingAs($this->coach, ['coach']);
        $this->postJson($url, $request)->assertAccepted();
        $this->postJson($url, $request)->assertAccepted();
        $this->process();
        $this->assertSame(2, CommunicationDelivery::count());
        CommunicationPreference::create(['user_id' => $this->player->id,'team_announcements' => false]);
        $this->send();
        Notification::assertNotSentTo($this->player, FmtrxActivityNotification::class);
        Notification::assertSentToTimes($this->coach, FmtrxActivityNotification::class, 1);
    }
    public function test_coach_weekly_summary_uses_only_their_team(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-19 08:00:00'));
        CommunicationPreference::create(['user_id' => $this->coach->id,'weekly_reports' => true]);
        PlayerAssessment::create(['user_id' => $this->player->id,'team_id' => $this->team->id,'assessed_by' => $this->coach->id,'assessment_date' => '2026-10-13','type' => 'strength','bench_lbs' => 100]);
        $this->artisan('fmtrx:weekly-progress')->assertSuccessful();
        $this->process();
        $report = CommunicationDelivery::where('event_type', 'WeeklyProgressReady')->firstOrFail();
        $this->assertSame($this->coach->id, $report->recipient_id);
        $this->assertContains('Saved assessment updates: 1', $report->payload['lines']);
        $this->assertSame('https://app.fmtrx.com/dashboard', $report->payload['action_url']);
    }
    public function test_billing_lifecycle_and_sync_are_deduped_and_essential(): void
    {
        config(['billing.revenuecat.webhook_auth' => 'Bearer test-hook','billing.revenuecat.environment' => 'test','billing.revenuecat.sandbox_stores' => ['TEST_STORE']]);
        CommunicationPreference::create(['user_id' => $this->player->id,'workouts' => false,'assessments' => false,'development_plans' => false,'weekly_reports' => false,'team_announcements' => false]);
        $event = ['app_user_id' => $this->player->id,'product_id' => 'fmtrx_player_pro_monthly','environment' => 'SANDBOX','store' => 'TEST_STORE','transaction_id' => 'lifecycle','original_transaction_id' => 'lifecycle','purchased_at_ms' => now()->subDay()->valueOf()];
        foreach(['INITIAL_PURCHASE','RENEWAL','BILLING_ISSUE','EXPIRATION','UNCANCELLATION'] as $i => $type) {
            $event = array_merge($event, ['id' => 'lifecycle-'.$i,'type' => $type,'event_timestamp_ms' => now()->addSeconds($i)->valueOf(),'expiration_at_ms' => 'EXPIRATION' === $type ? now()->subSecond()->valueOf() : now()->addMonths($i + 1)->valueOf()]);
            $this->postJson('/api/billing/revenuecat/webhook', ['api_version' => '1.0','event' => $event], ['Authorization' => 'Bearer test-hook'])->assertOk();
            $this->postJson('/api/billing/revenuecat/webhook', ['api_version' => '1.0','event' => $event], ['Authorization' => 'Bearer test-hook'])->assertOk();
        }
        $this->process();
        $this->send();
        Notification::assertSentToTimes($this->player, FmtrxActivityNotification::class, 5);
        $text = CommunicationDelivery::all()->map(fn ($d) => implode(' ', $d->payload['lines']))->implode(' ');
        foreach(['renewed','billing problem','has expired','restored'] as $phrase) {
            $this->assertStringContainsString($phrase, $text);
        }
        app(\App\Services\Billing\RevenueCatReconciler::class)->reconcileCustomer($this->player, []);
        app(\App\Services\Billing\RevenueCatReconciler::class)->reconcileCustomer($this->player, []);
        $this->process();
        $this->send();
        Notification::assertSentToTimes($this->player, FmtrxActivityNotification::class, 6);
    }
    public function test_notification_renders_brand_and_optional_preferences(): void
    {
        $this->assign($this->plan());
        $this->process();
        $d = CommunicationDelivery::firstOrFail();
        $mail = (new FmtrxActivityNotification($d->payload))->toMail($this->player);
        $html = (string)app(\Illuminate\Mail\Markdown::class)->render($mail->markdown, $mail->data());
        $this->assertStringContainsString('FMTRX', $html);
        $this->assertStringContainsString('https://app.fmtrx.com/settings', $html);
        $this->assertStringNotContainsString('/api/', $html);
        Mail::assertNothingSent();
    }

    public function test_weekly_fastball_trend_excludes_other_pitches_and_other_teams(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-19 08:00:00'));
        $this->mock(PlayerIntelligenceService::class, fn ($m) => $m->shouldReceive('build')->andReturn(['daily_velocity_averages' => []]));
        foreach([['2026-10-06',70,'FB'],['2026-10-13',80,'FB'],['2026-10-13',50,'CB']] as [$day,$velocity,$pitch]) {
            $practice = \App\Models\Practice::factory()->create(['team_id' => $this->team->id,'user_id' => $this->player->id,'started' => $day.' 12:00:00']);
            \App\Models\BullpenPracticeResult::factory()->create(['practice_id' => $practice->id,'team_id' => $this->team->id,'pitcher_id' => $this->player->id,'type_throw' => $pitch,'miles_per_hour' => $velocity]);
        }
        $lines = app(\App\Services\Communications\WeeklyProgressContent::class)->build($this->player->id, $this->team->id, '2026-10-12');
        $this->assertStringContainsString('80 mph across 1 readings', implode(' ', $lines));
        $this->assertStringContainsString('change +10.0 mph', implode(' ', $lines));
    }

}
