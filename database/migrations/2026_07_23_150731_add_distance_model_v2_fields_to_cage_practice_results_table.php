<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('cage_practice_results', function (Blueprint $table): void {
            $table->string('distance_model_version')->nullable()->after('sort');
            // MariaDB 10.1 does not support Laravel's native JSON column DDL.
            // Eloquent's array cast still serializes this JSON payload safely in LONGTEXT.
            $table->longText('distance_model_meta')->nullable()->after('distance_model_version');
            $table->decimal('estimated_carry_v2', 6, 1)->nullable()->after('distance_model_meta');
            $table->decimal('estimated_carry_low_v2', 6, 1)->nullable()->after('estimated_carry_v2');
            $table->decimal('estimated_carry_high_v2', 6, 1)->nullable()->after('estimated_carry_low_v2');
            $table->string('distance_confidence_v2')->nullable()->after('estimated_carry_high_v2');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('cage_practice_results', function (Blueprint $table): void {
            $table->dropColumn([
                'distance_model_version',
                'distance_model_meta',
                'estimated_carry_v2',
                'estimated_carry_low_v2',
                'estimated_carry_high_v2',
                'distance_confidence_v2',
            ]);
        });
    }
};
