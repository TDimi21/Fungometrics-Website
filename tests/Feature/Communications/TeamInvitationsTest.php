<?php

declare(strict_types=1);

namespace Tests\Feature\Communications;

use App\Http\Controllers\Api\Coach\CoachUtils;
use App\Jobs\SendTeamInvitation;
use App\Models\{CoachTeam, Player, PlayerTeam, Profile, Team, TeamInvitation, User};
use App\Notifications\TeamInvitationNotification;
use App\Services\Communications\InvitationService;
use Illuminate\Support\Facades\{Hash, Mail, Notification, Queue};
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use RuntimeException;

class TeamInvitationsTest extends TestCase
{
    private User $coach;
    private User $player;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        config(['communications.enabled' => true, 'communications.send_enabled' => true, 'communications.pilot_mode' => false, 'communications.categories.invitations' => true, 'communications.invitations_enabled' => true,'queue.default' => 'database']);
        Mail::fake();
        Notification::fake();
        Queue::fake();
        $this->coach = User::factory()->create(['type' => 'coach','status' => true,'password' => Hash::make('Password123')]);
        Profile::factory()->create(['user_id' => $this->coach->id]);
        $this->team = Team::factory()->create();
        CoachTeam::factory()->create(['coach_id' => $this->coach->id,'team_id' => $this->team->id,'is_main' => true]);
        $this->player = User::factory()->create(['type' => 'player','status' => true,'password' => null]);
        Profile::factory()->create(['user_id' => $this->player->id]);
        Player::factory()->create(['user_id' => $this->player->id,'born_date' => '2010-01-01']);
        PlayerTeam::factory()->create(['user_id' => $this->player->id,'team_id' => $this->team->id,'actual' => true]);
    }

    private function issue(?array $data = null): TeamInvitation
    {
        return app(InvitationService::class)->send($this->coach, $this->team->id, $data ?? ['role' => 'player','user_id' => $this->player->id,'email' => $this->player->email]);
    }
    private function deliver(TeamInvitation $invite): string
    {
        (new SendTeamInvitation($invite->id, $invite->generation ?? 1))->handle(app(InvitationService::class));
        $token = '';
        Notification::assertSentOnDemand(TeamInvitationNotification::class, function ($notice, $channels, $recipient) use (&$token, $invite) {
            if ($recipient->routes['mail'] !== $invite->email) {
                return false;
            }
            parse_str(parse_url($notice->acceptUrl, PHP_URL_FRAGMENT), $fragment);
            $token = $fragment['token'];
            return str_starts_with($notice->acceptUrl, 'https://app.fmtrx.com/invitations/accept#token=');
        });
        return $token;
    }
    private function accept(TeamInvitation $invite, string $token, ?User $actor = null): string
    {
        return app(InvitationService::class)->accept(['token' => $token,'email' => $invite->email,'password' => 'Password123','first_name' => 'New','last_name' => 'Coach'], $actor);
    }
    private function rejects(callable $call): void
    {
        try {
            $call();
            $this->fail('Expected validation rejection');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('invitation', $e->errors());
        }
    }

    /** @dataProvider claimMethods */
    public function test_new_player_claim_preserves_identity_profile_membership_and_history(bool $manual): void
    {
        $profile = $this->player->profile->getAttributes();
        $detail = $this->player->player->getAttributes();
        $history = \App\Models\PlayerFitness::factory()->create(['user_id' => $this->player->id]);
        $before = $history->fresh()->getAttributes();
        // Acceptance must preserve actual training and assessment foreign keys, not just the roster.
        $practice = \App\Models\Practice::factory()->create(['team_id' => $this->team->id, 'user_id' => $this->player->id]);
        $result = \App\Models\BullpenPracticeResult::factory()->create(['practice_id' => $practice->id, 'team_id' => $this->team->id, 'pitcher_id' => $this->player->id]);
        $assessment = \App\Models\PlayerAssessment::create(['team_id' => $this->team->id, 'user_id' => $this->player->id, 'assessed_by' => $this->coach->id, 'type' => 'strength', 'assessment_date' => now()->toDateString(), 'bench_lbs' => 100]);
        $plan = \App\Models\DailyPlan::create(['id' => (string) \Illuminate\Support\Str::uuid(), 'team_id' => $this->team->id, 'created_by' => $this->coach->id, 'name' => 'Historical plan', 'date' => now()->toDateString(), 'status' => 'published', 'version' => 1, 'buckets' => []]);
        $assignment = \App\Models\DailyPlanAssignment::create(['plan_id' => $plan->id, 'user_id' => $this->player->id]);
        $progress = \App\Models\DailyPlanProgress::create(['plan_id' => $plan->id, 'user_id' => $this->player->id, 'readiness' => ['energy' => 4], 'items' => [], 'reflection' => ['comments' => 'Recorded before invitation'], 'completed_at' => now()]);
        $records = [$practice, $result, $assessment, $plan, $assignment, $progress, PlayerTeam::where('user_id', $this->player->id)->firstOrFail()];
        $snapshots = array_map(fn ($row) => $row->fresh()->getAttributes(), $records);
        $users = User::count();
        $invite = $this->issue();
        $token = $this->deliver($invite);
        $this->assertSame('player', $manual ? app(InvitationService::class)->accept(['claim_code' => $this->codeFor($invite), 'email' => $invite->email, 'password' => 'Password123'], null) : $this->accept($invite, $token));
        $this->assertSame($users, User::count());
        foreach ($records as $index => $record) {
            $this->assertSame($snapshots[$index], $record->fresh()->getAttributes(), get_class($record).' changed during acceptance');
        }
        $this->assertSame($profile, $this->player->profile->fresh()->getAttributes());
        $this->assertSame($detail, $this->player->player->fresh()->getAttributes());
        $this->assertSame($before, $history->fresh()->getAttributes());
        $this->assertTrue(Hash::check('Password123', $this->player->fresh()->password));
        $this->assertSame(1, PlayerTeam::where('user_id', $this->player->id)->count());
        $this->assertNotNull($invite->fresh()->email_verified_at);
        $this->assertNull($invite->fresh()->token_hash);
    }
    public function test_existing_player_must_prove_account_ownership(): void
    {
        $this->player->update(['password' => Hash::make('Password123')]);
        $invite = $this->issue();
        $token = $this->deliver($invite);
        $this->rejects(fn () => app(InvitationService::class)->accept(['token' => $token,'email' => $invite->email,'password' => 'WrongPassword'], null));
        $this->assertSame('player', $this->accept($invite, $token));
    }
    public function test_existing_coach_acceptance_adds_only_assistant_membership(): void
    {
        $target = User::factory()->create(['type' => 'coach','status' => true,'password' => Hash::make('Password123')]);
        $invite = $this->issue(['role' => 'coach','email' => $target->email]);
        $token = $this->deliver($invite);
        $this->accept($invite, $token);
        $this->assertTrue(CoachUtils::isCoachOnTeam($target->id, $this->team->id));
        $this->assertFalse(CoachUtils::isHeadCoach($target->id, $this->team->id));
        $this->rejects(fn () => $this->accept($invite, $token));
        $this->assertSame(1, CoachTeam::where('coach_id', $target->id)->count());
    }
    public function test_new_coach_is_created_only_at_acceptance(): void
    {
        $invite = $this->issue(['role' => 'coach','email' => 'new.coach@example.com']);
        $this->assertFalse(User::where('email', $invite->email)->exists());
        $this->accept($invite, $this->deliver($invite));
        $target = User::where('email', $invite->email)->firstOrFail();
        $this->assertSame('New', $target->profile->first_name);
        $this->assertFalse(CoachUtils::isHeadCoach($target->id, $this->team->id));
    }
    public function test_expired_revoked_and_consumed_tokens_cannot_be_accepted(): void
    {
        $invite = $this->issue();
        $token = $this->deliver($invite);
        $invite->update(['expires_at' => now()->subMinute()]);
        $this->rejects(fn () => $this->accept($invite, $token));
        $invite->update(['expires_at' => now()->addDay()]);
        app(InvitationService::class)->manage($this->coach, $invite->id, false);
        $this->rejects(fn () => $this->accept($invite, $token));
        $this->assertNull($this->player->fresh()->password);
    }
    public function test_repeated_send_and_worker_retry_do_not_duplicate_notifications(): void
    {
        $invite = $this->issue();
        $this->assertSame($invite->id, $this->issue()->id);
        Queue::assertPushed(SendTeamInvitation::class, 1);
        $raw = $this->deliver($invite);
        (new SendTeamInvitation($invite->id, 1))->handle(app(InvitationService::class));
        Notification::assertCount(1);
        $this->assertSame(hash('sha256', $raw), $invite->fresh()->token_hash);
        $this->assertStringNotContainsString($raw, serialize(new SendTeamInvitation($invite->id, 1)));
        $this->assertStringNotContainsString($raw, json_encode($invite->fresh()->getAttributes()));
    }
    public function test_resend_invalidates_old_link_and_stale_jobs(): void
    {
        $invite = $this->issue();
        $old = $this->deliver($invite);
        $this->travel(2)->minutes();
        $next = app(InvitationService::class)->manage($this->coach, $invite->id, true);
        $this->assertSame(2, $next->generation);
        $this->rejects(fn () => $this->accept($invite, $old));
        Notification::fake();
        (new SendTeamInvitation($invite->id, 1))->handle(app(InvitationService::class));
        Notification::assertNothingSent();
        $this->accept($next, $this->deliver($next));
    }
    public function test_cross_team_and_non_head_coach_invites_are_forbidden(): void
    {
        $outsider = User::factory()->create(['type' => 'coach','status' => true]);
        Sanctum::actingAs($outsider, ['coach']);
        $this->postJson('/api/coach/teams/'.$this->team->id.'/invitations', ['role' => 'player','user_id' => $this->player->id,'email' => $this->player->email])->assertNotFound();
        CoachTeam::factory()->create(['coach_id' => $outsider->id,'team_id' => $this->team->id,'is_main' => false]);
        $this->postJson('/api/coach/teams/'.$this->team->id.'/invitations', ['role' => 'coach','email' => 'assistant@example.com'])->assertForbidden();
    }
    public function test_public_inspection_exposes_no_personal_information(): void
    {
        $invite = $this->issue();
        $token = $this->deliver($invite);
        $response = $this->postJson('/api/invitations/inspect', ['token' => $token])->assertOk();
        $this->assertSame(['valid','expires_at'], array_keys($response->json()));
        $response->assertDontSee($this->player->email)->assertHeader('Cache-Control', 'no-store, private');
    }
    public function test_acceptance_endpoint_is_single_use_and_uses_actual_frontend_links(): void
    {
        $invite = $this->issue();
        $token = $this->deliver($invite);
        $data = ['token' => $token,'email' => $invite->email,'password' => 'Password123'];
        $this->postJson('/api/invitations/accept', $data)->assertOk()->assertJsonPath('dashboard_path', '/player-dashboard');
        $this->postJson('/api/invitations/accept', $data)->assertUnprocessable();
    }
    public function test_missing_email_different_account_and_redirected_email_are_rejected(): void
    {
        Sanctum::actingAs($this->coach, ['coach']);
        $this->postJson('/api/coach/teams/'.$this->team->id.'/invitations', ['role' => 'player','user_id' => $this->player->id])->assertUnprocessable();
        $this->rejects(fn () => $this->issue(['role' => 'player','user_id' => $this->player->id,'email' => 'attacker@example.com']));
        $invite = $this->issue();
        $token = $this->deliver($invite);
        $this->rejects(fn () => $this->accept($invite, $token, $this->coach));
    }
    public function test_disabled_feature_sync_queue_and_invalid_frontend_are_blocked(): void
    {
        Sanctum::actingAs($this->coach, ['coach']);
        $url = '/api/coach/teams/'.$this->team->id.'/invitations';
        $data = ['role' => 'player','user_id' => $this->player->id,'email' => $this->player->email];
        config(['communications.invitations_enabled' => false]);
        $this->postJson($url, $data)->assertStatus(503);
        config(['communications.enabled' => true, 'communications.send_enabled' => true, 'communications.pilot_mode' => false, 'communications.categories.invitations' => true, 'communications.invitations_enabled' => true,'queue.default' => 'sync']);
        $this->postJson($url, $data)->assertStatus(503);
        config(['queue.default' => 'database','communications.frontend_url' => 'https://attacker.example/api']);
        $this->postJson($url, $data)->assertStatus(503);
        Notification::assertNothingSent();
    }
    public function test_removed_membership_rolls_back_account_completion(): void
    {
        $invite = $this->issue();
        $token = $this->deliver($invite);
        PlayerTeam::where('user_id', $this->player->id)->update(['actual' => false]);
        $this->postJson('/api/invitations/accept', ['token' => $token,'email' => $invite->email,'password' => 'Password123'])->assertStatus(409);
        $this->assertNull($this->player->fresh()->password);
        $this->assertNull($invite->fresh()->accepted_at);
    }
    public function test_player_without_email_can_verify_invited_email_without_new_user(): void
    {
        $this->player->update(['email' => null]);
        $invite = $this->issue(['role' => 'player','user_id' => $this->player->id,'email' => 'claimed@example.com']);
        $this->accept($invite, $this->deliver($invite));
        $this->assertSame('claimed@example.com', $this->player->fresh()->email);
    }
    public function test_existing_other_identity_is_never_merged(): void
    {
        $other = User::factory()->create(['type' => 'player','status' => true]);
        $this->player->update(['email' => null]);
        $this->rejects(fn () => $this->issue(['role' => 'player','user_id' => $this->player->id,'email' => $other->email]));
        $this->assertNull($this->player->fresh()->email);
        $this->assertSame(0, TeamInvitation::count());
    }
    public function test_coach_cannot_invite_player_account_as_another_coach(): void
    {
        $this->rejects(fn () => $this->issue(['role' => 'coach','email' => $this->player->email]));
        $this->assertSame('player', $this->player->fresh()->type);
    }
    public function test_restricted_claim_token_does_not_authenticate_completed_account(): void
    {
        $this->player->update(['password' => Hash::make('Password123')]);
        $invite = $this->issue();
        $token = $this->deliver($invite);
        Sanctum::actingAs($this->player, ['profile-claim']);
        $this->postJson('/api/invitations/accept', ['token' => $token,'email' => $invite->email])->assertUnprocessable();
        $this->assertNull($invite->fresh()->accepted_at);
    }
    public function test_accept_endpoint_rate_limits_invalid_attempts(): void
    {
        $data = ['token' => str_repeat('x', 64),'email' => 'nobody@example.com','password' => 'Password123'];
        for($i = 0;$i < 5;$i++) {
            $this->postJson('/api/invitations/accept', $data)->assertUnprocessable();
        }
        $this->postJson('/api/invitations/accept', $data)->assertStatus(429);
    }
    public function test_invitation_template_renders_branding_and_frontend_button(): void
    {
        $notice = new TeamInvitationNotification('https://app.fmtrx.com/invitations/accept#token='.str_repeat('a', 64), 'Coach Example', 'Team Example', 'Oct 23, 2026', 'player');
        $mail = $notice->toMail(new \Illuminate\Notifications\AnonymousNotifiable());
        $html = (string)app(\Illuminate\Mail\Markdown::class)->render($mail->markdown, $mail->data());
        $this->assertStringContainsString('FMTRX', $html);
        $this->assertStringContainsString('Coach Example', $html);
        $this->assertStringContainsString('Team Example', $html);
        $this->assertStringContainsString('https://app.fmtrx.com/invitations/accept#token=', $html);
        $this->assertStringNotContainsString('/api/', $html);
    }
    public function test_phone_only_completed_account_gets_its_verified_email_after_password_check(): void
    {
        $this->player->update(['email' => null, 'password' => Hash::make('Password123')]);
        $invite = $this->issue(['role' => 'player', 'user_id' => $this->player->id, 'email' => 'phone.only@example.com']);
        $this->accept($invite, $this->deliver($invite));
        $this->assertSame('phone.only@example.com', $this->player->fresh()->email);
    }

    public function test_provider_error_is_sanitized_and_not_automatically_retried(): void
    {
        $invite = $this->issue();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Bearer secret-value'));
        (new SendTeamInvitation($invite->id, 1))->handle(app(InvitationService::class));
        $this->assertSame('delivery_unconfirmed', $invite->fresh()->failure_code);
        $this->assertStringNotContainsString('secret-value', $invite->fresh()->toJson());
        (new SendTeamInvitation($invite->id, 1))->handle(app(InvitationService::class));
    }
    public function test_under_13_and_unknown_age_cannot_accept_or_complete_existing_identity(): void
    {
        $invite = $this->issue(); $token = $this->deliver($invite);
        foreach ([now()->subYears(12)->toDateString(), null, now()->addDay()->toDateString()] as $born) {
            $this->player->player->update(['born_date' => $born]);
            $this->rejects(fn () => $this->accept($invite, $token));
            $this->assertNull($this->player->fresh()->password);
            $this->assertNull($invite->fresh()->accepted_at);
        }
        $this->player->player->update(['born_date' => now()->subYears(13)->toDateString()]);
        $this->assertSame('player', $this->accept($invite, $token));
    }

    public function test_queued_invitation_respects_pilot_global_and_category_controls(): void
    {
        $invite = $this->issue();
        foreach (['enabled','send_enabled','categories.invitations'] as $gate) {
            config(['communications.'.$gate => false]);
            (new SendTeamInvitation($invite->id, 1))->handle(app(InvitationService::class));
            Notification::assertNothingSent();
            $this->assertSame('queued', $invite->fresh()->delivery_status);
            config(['communications.'.$gate => true]);
        }
        config(['communications.pilot_mode' => true, 'communications.pilot_allowlist' => []]);
        (new SendTeamInvitation($invite->id, 1))->handle(app(InvitationService::class));
        Notification::assertNothingSent();
        config(['communications.pilot_allowlist' => [strtolower($invite->email)]]);
        $this->deliver($invite);
        Notification::assertCount(1);
    }

    public function test_worker_suppresses_removed_or_now_underage_player(): void
    {
        $invite = $this->issue();
        PlayerTeam::where('user_id', $this->player->id)->update(['actual' => false]);
        (new SendTeamInvitation($invite->id, 1))->handle(app(InvitationService::class));
        Notification::assertNothingSent();
        $this->assertSame('invitation_unavailable', $invite->fresh()->failure_code);
        PlayerTeam::where('user_id', $this->player->id)->update(['actual' => true]);
        $this->travel(2)->minutes();
        $this->player->player->update(['born_date' => now()->subYears(12)->toDateString()]);
        $this->rejects(fn () => app(InvitationService::class)->manage($this->coach, $invite->id, true));
    }

    public function test_expiry_does_not_change_on_delivery_or_acceptance_and_resend_renews_it(): void
    {
        $invite = $this->issue(); $original = $invite->expires_at->toDateTimeString();
        $this->travel(2)->minutes(); $this->deliver($invite);
        $this->assertSame($original, $invite->fresh()->expires_at->toDateTimeString());
        $this->travel(2)->minutes();
        $resent = app(InvitationService::class)->manage($this->coach, $invite->id, true);
        $renewed = $resent->expires_at->toDateTimeString();
        $this->assertNotSame($original, $renewed);
        Notification::fake(); $token = $this->deliver($resent);
        $this->assertSame($renewed, $resent->fresh()->expires_at->toDateTimeString());
        $this->accept($resent, $token);
        $this->assertSame($renewed, $resent->fresh()->expires_at->toDateTimeString());
    }

    public function test_email_over_storage_limit_returns_validation_error(): void
    {
        Sanctum::actingAs($this->coach, ['coach']);
        $email = str_repeat('a', 60).'@'.str_repeat('b',60).'.'.str_repeat('c',60).'.example.com';
        $this->postJson('/api/coach/teams/'.$this->team->id.'/invitations', ['role'=>'coach','email'=>$email])->assertUnprocessable();
        Notification::assertNothingSent();
    }


    public static function claimMethods(): array { return [[false], [true]]; }

    private function codeFor(TeamInvitation $invite): string
    {
        $code = '';
        Notification::assertSentOnDemand(TeamInvitationNotification::class, function ($notice, $channels, $recipient) use ($invite, &$code) {
            if ($recipient->routes['mail'] !== $invite->email) return false;
            $code = $notice->claimCode;
            return true;
        });
        return $code;
    }

    public function test_manual_code_uses_same_invitation_and_rejects_wrong_recipient_and_duplicate_claims(): void
    {
        $invite = $this->issue();
        $token = $this->deliver($invite);
        $code = $this->codeFor($invite);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{4}(-[A-F0-9]{4}){4}$/', $code);
        $this->assertSame(hash('sha256', str_replace('-', '', $code)), $invite->fresh()->claim_code_hash);
        $this->assertArrayNotHasKey('claim_code_hash', $invite->fresh()->toArray());
        $data = ['claim_code' => strtolower($code), 'email' => $invite->email, 'password' => 'Password123'];
        $this->postJson('/api/invitations/accept', array_replace($data, ['email' => 'wrong@example.com']))->assertUnprocessable();
        $this->postJson('/api/invitations/accept', $data + ['token' => $token])->assertUnprocessable();
        $this->postJson('/api/invitations/accept', $data)->assertOk()->assertJsonPath('accepted', true);
        $this->assertNull($invite->fresh()->claim_code_hash);
        $this->assertNull($invite->fresh()->token_hash);
        $this->postJson('/api/invitations/accept', $data)->assertUnprocessable();
        $this->rejects(fn () => $this->accept($invite, $token));
    }

    public function test_manual_codes_expire_rotate_and_revoke_with_the_link(): void
    {
        $invite = $this->issue();
        $token = $this->deliver($invite);
        $code = $this->codeFor($invite);
        $data = ['claim_code' => $code, 'email' => $invite->email, 'password' => 'Password123'];
        $invite->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/invitations/accept', $data)->assertUnprocessable();
        $this->travel(2)->minutes();
        $renewed = app(InvitationService::class)->manage($this->coach, $invite->id, true);
        $this->assertNull($renewed->claim_code_hash);
        $this->postJson('/api/invitations/accept', $data)->assertUnprocessable();
        $this->rejects(fn () => $this->accept($invite, $token));
        Notification::fake();
        $this->deliver($renewed);
        $newCode = $this->codeFor($renewed);
        $this->assertNotSame($code, $newCode);
        app(InvitationService::class)->manage($this->coach, $invite->id, false);
        $this->postJson('/api/invitations/accept', array_replace($data, ['claim_code' => $newCode]))->assertUnprocessable();
        $this->assertNull($this->player->fresh()->password);
    }

    public function test_manual_code_requires_existing_account_password_and_updates_roster_status(): void
    {
        $this->player->update(['password' => Hash::make('Password123')]);
        $invite = $this->issue(); $this->deliver($invite);
        $data = ['claim_code' => $this->codeFor($invite), 'email' => $invite->email, 'password' => 'WrongPassword'];
        $this->postJson('/api/invitations/accept', $data)->assertUnprocessable();
        $this->postJson('/api/invitations/accept', array_replace($data, ['password' => 'Password123']))->assertOk();
        Sanctum::actingAs($this->coach, ['coach']);
        $this->getJson('/api/coach/teams/'.$this->team->id)->assertOk()->assertJsonPath('data.0.profile_claim_status', 'claimed');
    }

    public function test_creation_saves_profile_and_queues_optional_invitation_without_duplicate_player(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\UserCreated::class, \App\Events\UserChanged::class]);
        Sanctum::actingAs($this->coach, ['coach']);
        $payload = ['team' => $this->team->id, 'name' => ['first' => 'New', 'last' => 'Athlete'], 'email' => 'new.athlete@example.com', 'player' => ['born_date' => '2010-01-01', 'position' => 'SS'], 'send_invitation' => true];
        $response = $this->postJson('/api/coach/add/players', $payload)->assertOk()->assertJsonPath('invitation.status', 'queued');
        $id = $response->json('data.id');
        $this->assertDatabaseHas('players', ['user_id' => $id, 'born_date' => '2010-01-01']);
        $this->assertDatabaseHas('player_positions', ['player_id' => $id, 'position' => 'SS']);
        $this->assertDatabaseHas('team_invitations', ['user_id' => $id, 'team_id' => $this->team->id]);
        $this->postJson('/api/coach/add/players', $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(1, User::where('email', $payload['email'])->count());
        Queue::assertPushed(SendTeamInvitation::class, 1);
        Notification::assertNothingSent();
    }

    public function test_creation_survives_missing_contact_disabled_delivery_and_ineligible_age(): void
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\UserCreated::class, \App\Events\UserChanged::class]);
        Sanctum::actingAs($this->coach, ['coach']);
        $payload = ['team' => $this->team->id, 'name' => ['first' => 'Saved', 'last' => 'Athlete'], 'send_invitation' => true];
        $this->postJson('/api/coach/add/players', $payload)->assertOk()->assertJsonPath('invitation.status', 'not_queued');
        foreach ([null, now()->subYears(12)->toDateString()] as $dob) {
            $this->postJson('/api/coach/add/players', $payload + ['email' => uniqid().'@example.com', 'player' => ['born_date' => $dob]])->assertOk()->assertJsonPath('invitation.status', 'not_queued');
        }
        config(['communications.invitations_enabled' => false]);
        $this->postJson('/api/coach/add/players', $payload + ['email' => 'disabled@example.com', 'player' => ['born_date' => '2010-01-01']])->assertOk()->assertJsonPath('invitation.status', 'not_queued');
        $this->assertDatabaseHas('users', ['email' => 'disabled@example.com']);
        Queue::assertNothingPushed(); Notification::assertNothingSent();
    }

    public function test_legacy_claim_paths_cannot_bypass_age_restrictions(): void
    {
        $this->team->update(['join_code' => 'SAFE23']);
        foreach ([null, now()->subYears(12)->toDateString()] as $dob) {
            $this->player->player->update(['born_date' => $dob]);
            $this->postJson('/api/player/join', ['contact' => $this->player->email, 'team_code' => 'SAFE23'])->assertUnprocessable();
            $code = app(\App\Services\Security\AccountClaimService::class)->issue($this->player);
            $this->postJson('/api/player/join', ['contact' => $this->player->email, 'claim_code' => $code])->assertUnprocessable();
            $this->assertNull($this->player->fresh()->password);
        }
    }

    public function test_invitation_email_contains_link_and_manual_code_but_no_stored_hash(): void
    {
        $invite = $this->issue(); $token = $this->deliver($invite);
        $code = $this->codeFor($invite);
        $notice = new TeamInvitationNotification('https://app.fmtrx.com/invitations/accept#token='.$token, 'Coach', 'Team', 'Oct 23', 'player', $code);
        $html = (string) $notice->toMail(null)->render();
        $this->assertStringContainsString('Claim Profile', $html);
        $this->assertStringContainsString($code, $html);
        $this->assertStringNotContainsString($invite->fresh()->claim_code_hash, $html);
        $this->assertStringNotContainsString($invite->fresh()->token_hash, $html);
        $this->player->player->update(['born_date' => now()->subYears(12)->toDateString()]);
        $this->postJson('/api/invitations/accept', ['claim_code' => $code, 'email' => $invite->email, 'password' => 'Password123'])->assertUnprocessable();
        $this->assertNull($invite->fresh()->accepted_at);
    }

    public function test_player_creation_cannot_write_another_coachs_roster(): void
    {
        Sanctum::actingAs($this->coach, ['coach']);
        $other = Team::factory()->create();
        $before = User::count();
        $this->postJson('/api/coach/add/players', ['team' => $other->id, 'name' => ['first' => 'Unauthorized', 'last' => 'Player'], 'send_invitation' => true])->assertForbidden();
        $this->assertSame($before, User::count());
        Queue::assertNothingPushed();
    }
}
