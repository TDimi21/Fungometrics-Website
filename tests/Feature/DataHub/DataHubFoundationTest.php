<?php

declare(strict_types=1);

namespace Tests\Feature\DataHub;

use App\Models\CoachTeam;
use App\Models\Team;
use App\Models\User;
use App\Services\Access\EntitlementResolver;
use App\Services\DataHub\Contracts\ImportDestinationContract;
use App\Services\DataHub\Enums\ImportPlatform;
use App\Services\DataHub\Enums\ImportSessionType;
use App\Services\DataHub\Services\DestinationRegistry;
use App\Services\DataHub\Services\PlatformRegistry;
use Tests\TestCase;

class DataHubFoundationTest extends TestCase
{
    public function test_platform_registry_exposes_all_five_platforms(): void
    {
        $registry = app(PlatformRegistry::class);

        $this->assertSame(
            array_map(static fn (ImportPlatform $platform): string => $platform->value, ImportPlatform::cases()),
            array_map(static fn ($platform): string => $platform->key()->value, $registry->all())
        );
    }

    public function test_platform_file_and_session_capabilities_are_exact(): void
    {
        $registry = app(PlatformRegistry::class);
        foreach ($registry->all() as $platform) {
            $this->assertSame(['csv', 'xlsx'], $platform->supportedFileTypes());
        }

        $this->assertSame(
            ['cage', 'live_ab', 'batting_practice', 'pitching_practice'],
            $this->sessionValues($registry->get(ImportPlatform::TrackMan)->supportedSessionTypes())
        );
        $this->assertSame(
            ['cage', 'live_ab', 'batting_practice'],
            $this->sessionValues($registry->get(ImportPlatform::HitTrax)->supportedSessionTypes())
        );
        $this->assertSame(
            ['cage', 'bullpen', 'batting_practice', 'pitching_practice'],
            $this->sessionValues($registry->get(ImportPlatform::Rapsodo)->supportedSessionTypes())
        );
        $this->assertSame(
            ['cage', 'batting_practice', 'assessment'],
            $this->sessionValues($registry->get(ImportPlatform::BlastMotion)->supportedSessionTypes())
        );
        $this->assertSame(
            $this->sessionValues(ImportSessionType::cases()),
            $this->sessionValues($registry->get(ImportPlatform::GenericCsv)->supportedSessionTypes())
        );
    }

    public function test_data_hub_entitlement_is_assigned_only_to_coach_pro_and_administrators(): void
    {
        $resolver = app(EntitlementResolver::class);

        foreach (['free', 'coach_basic', 'player_basic', 'player_pro'] as $plan) {
            $type = str_starts_with($plan, 'player_') ? 'player' : 'coach';
            $user = User::factory()->create(['type' => $type, 'subscription_plan' => $plan]);
            $this->assertFalse($resolver->hasEntitlement($user, 'data_hub_import'), "{$plan} unexpectedly received Data Hub.");
        }

        $coachPro = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $this->assertTrue($resolver->hasEntitlement($coachPro, 'data_hub_import'));

        $admin = User::factory()->create([
            'type' => 'coach',
            'email' => 'admin@fungometrics.com',
            'subscription_plan' => 'free',
        ]);
        $this->assertTrue($resolver->hasEntitlement($admin, 'data_hub_import'));
    }

    public function test_player_and_unentitled_coach_are_denied_but_entitled_team_coach_is_allowed(): void
    {
        $freeTeam = Team::factory()->create();
        $paidTeam = Team::factory()->create();
        $player = User::factory()->create(['type' => 'player', 'subscription_plan' => 'player_pro']);
        $freeCoach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'free']);
        $proCoach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        $assistant = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'free']);

        CoachTeam::factory()->create(['coach_id' => $freeCoach->id, 'team_id' => $freeTeam->id, 'is_main' => true]);
        CoachTeam::factory()->create(['coach_id' => $proCoach->id, 'team_id' => $paidTeam->id, 'is_main' => true]);
        CoachTeam::factory()->create(['coach_id' => $assistant->id, 'team_id' => $paidTeam->id, 'is_main' => false]);

        $registry = app(DestinationRegistry::class);
        $this->assertFalse($registry->allows($player, $paidTeam, ImportSessionType::Cage));
        $this->assertFalse($registry->allows($freeCoach, $freeTeam, ImportSessionType::Cage));
        $this->assertTrue($registry->allows($proCoach, $paidTeam, ImportSessionType::Cage));
        $this->assertTrue($registry->allows($assistant, $paidTeam, ImportSessionType::Cage));
    }

    public function test_team_scope_is_enforced_for_entitled_coach_and_admin_is_allowed(): void
    {
        $ownTeam = Team::factory()->create();
        $otherTeam = Team::factory()->create();
        $coach = User::factory()->create(['type' => 'coach', 'subscription_plan' => 'coach_pro']);
        CoachTeam::factory()->create(['coach_id' => $coach->id, 'team_id' => $ownTeam->id, 'is_main' => false]);

        $registry = app(DestinationRegistry::class);
        $this->assertTrue($registry->allows($coach, $ownTeam, ImportSessionType::Assessment));
        $this->assertFalse($registry->allows($coach, $otherTeam, ImportSessionType::Assessment));

        $admin = User::factory()->create([
            'type' => 'coach',
            'email' => 'admin@fungometrics.com',
            'subscription_plan' => 'free',
        ]);
        $this->assertTrue($registry->allows($admin, $otherTeam, ImportSessionType::Assessment));
    }

    public function test_destination_contract_is_bound_with_inspection_only_endpoint(): void
    {
        $this->assertInstanceOf(ImportDestinationContract::class, app(ImportDestinationContract::class));
        $this->assertTrue(collect(app('router')->getRoutes())->contains(
            fn ($route): bool => 'api/data-hub/inspect' === $route->uri()
        ));
    }

    /** @param array<int, ImportSessionType> $sessions @return array<int, string> */
    private function sessionValues(array $sessions): array
    {
        return array_map(static fn (ImportSessionType $session): string => $session->value, $sessions);
    }
}
