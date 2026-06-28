<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('player_assessments', function (Blueprint $table): void {
            if (! Schema::hasColumn('player_assessments', 'hitting_score')) {
                $table->unsignedTinyInteger('hitting_score')->nullable();
            }
            if (! Schema::hasColumn('player_assessments', 'hitting_data')) {
                $table->longText('hitting_data')->nullable();
            }
            if (! Schema::hasColumn('player_assessments', 'pitching_score')) {
                $table->unsignedTinyInteger('pitching_score')->nullable();
            }
            if (! Schema::hasColumn('player_assessments', 'pitching_data')) {
                $table->longText('pitching_data')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('player_assessments', function (Blueprint $table): void {
            $table->dropColumn([
                'hitting_score',
                'hitting_data',
                'pitching_score',
                'pitching_data',
            ]);
        });
    }
};
