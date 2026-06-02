<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_assessments', function (Blueprint $table): void {
            $table->json('team_percentiles')->nullable()->after('overall_score');
            $table->json('age_group_percentiles')->nullable()->after('team_percentiles');
            $table->unsignedTinyInteger('overall_team_percentile')->nullable()->after('age_group_percentiles');
            $table->unsignedTinyInteger('overall_age_percentile')->nullable()->after('overall_team_percentile');
            $table->unsignedTinyInteger('age_group_years')->nullable()->after('overall_age_percentile');
        });
    }

    public function down(): void
    {
        Schema::table('player_assessments', function (Blueprint $table): void {
            $table->dropColumn([
                'team_percentiles',
                'age_group_percentiles',
                'overall_team_percentile',
                'overall_age_percentile',
                'age_group_years',
            ]);
        });
    }
};
