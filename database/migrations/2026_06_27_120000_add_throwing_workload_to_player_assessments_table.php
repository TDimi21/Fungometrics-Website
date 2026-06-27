<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist the throwing-workload and arm-health portion of a player assessment.
 *
 * The mobile assessment screen already computes and sends these values, but the
 * player_assessments table had no columns for them so they were silently dropped
 * on save. Storing them lets the assessment report show arm-health/workload.
 *
 * Existing rows default to NULL so the migration is non-breaking.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('player_assessments', function (Blueprint $table): void {
            if (! Schema::hasColumn('player_assessments', 'body_weight_lbs')) {
                $table->decimal('body_weight_lbs', 5, 1)->nullable();
            }
            if (! Schema::hasColumn('player_assessments', 'throwing_workload_score')) {
                $table->unsignedTinyInteger('throwing_workload_score')->nullable();
            }
            if (! Schema::hasColumn('player_assessments', 'throwing_workload_level')) {
                $table->string('throwing_workload_level', 20)->nullable();
            }
            if (! Schema::hasColumn('player_assessments', 'throwing_workload_data')) {
                $table->longText('throwing_workload_data')->nullable();
            }
            if (! Schema::hasColumn('player_assessments', 'arm_health_score')) {
                $table->unsignedTinyInteger('arm_health_score')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('player_assessments', function (Blueprint $table): void {
            $table->dropColumn([
                'body_weight_lbs',
                'throwing_workload_score',
                'throwing_workload_level',
                'throwing_workload_data',
                'arm_health_score',
            ]);
        });
    }
};
