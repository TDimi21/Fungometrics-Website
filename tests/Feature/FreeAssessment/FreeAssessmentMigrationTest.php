<?php

declare(strict_types=1);

namespace Tests\Feature\FreeAssessment;

use App\Models\FreeAssessmentResult;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FreeAssessmentMigrationTest extends TestCase
{
    private function isolatedSchema(callable $test): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.assessment_migration_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('assessment_migration_test');
        try {
            Schema::create('users', fn (Blueprint $t) => $t->uuid('id')->primary());
            Schema::create('teams', fn (Blueprint $t) => $t->uuid('id')->primary());
            Schema::create('player_fitnesses', function (Blueprint $t): void {
                $t->uuid('id')->primary();
                $t->uuid('user_id');
            });
            $migration = require database_path('migrations/2026_10_05_000001_create_free_assessment_tables.php');
            $test($migration);
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('assessment_migration_test');
        }
    }

    public function test_clean_install_uses_text_storage_and_preserves_array_casts(): void
    {
        $this->isolatedSchema(function ($migration): void {
            $migration->up();
            $this->assertSame('text', Schema::getColumnType('free_assessment_results', 'summary'));
            $result = new FreeAssessmentResult();
            $result->summary = ['best' => 88.4, 'average' => 82.1];
            $this->assertEquals(['best' => 88.4, 'average' => 82.1], json_decode($result->getAttributes()['summary'], true));
            $this->assertSame(88.4, $result->summary['best']);
            $migration->up(); // Safe if a process dies after DDL but before migration bookkeeping.
            $this->assertTrue(Schema::hasColumn('player_fitnesses', 'pull_strength'));
        });
    }

    public function test_resume_after_reported_failure_keeps_existing_events_and_enrollments(): void
    {
        $this->isolatedSchema(function ($migration): void {
            $migration->up();
            DB::table('users')->insert(['id' => 'player']);
            DB::table('teams')->insert(['id' => 'team']);
            DB::table('free_assessments')->insert(['id' => 'event', 'team_id' => 'team', 'created_by' => 'player', 'name' => 'Preserve me', 'assessment_date' => '2026-10-05']);
            DB::table('free_assessment_players')->insert(['id' => 'enrollment', 'assessment_id' => 'event', 'player_id' => 'player']);
            // Reproduce production's state: the first two CREATEs succeeded;
            // the unsupported JSON column prevented the third CREATE.
            Schema::drop('free_assessment_attempts');
            Schema::drop('free_assessment_results');
            Schema::drop('player_fitnesses');
            Schema::create('player_fitnesses', function (Blueprint $t): void {
                $t->uuid('id')->primary();
                $t->uuid('user_id');
            });
            DB::table('player_fitnesses')->insert(['id' => 'existing-fitness', 'user_id' => 'player']);
            $migration->up();
            $this->assertSame('Preserve me', DB::table('free_assessments')->value('name'));
            $this->assertSame('player', DB::table('free_assessment_players')->value('player_id'));
            $this->assertSame('existing-fitness', DB::table('player_fitnesses')->value('id'));
            $this->assertTrue(Schema::hasTable('free_assessment_attempts'));
            $this->assertTrue(Schema::hasColumn('player_fitnesses', 'free_assessment_id'));
            $migration->up();
            $this->assertSame(1, DB::table('free_assessments')->count());
        });
    }
}
