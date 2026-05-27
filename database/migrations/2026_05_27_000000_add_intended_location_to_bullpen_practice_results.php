<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bullpen_practice_results', function (Blueprint $table): void {
            $table->unsignedInteger('intended_location')->nullable()->after('pitch_mark');
            $table->string('intended_pitch_type')->nullable()->after('intended_location');
        });
    }

    public function down(): void
    {
        Schema::table('bullpen_practice_results', function (Blueprint $table): void {
            $table->dropColumn(['intended_location', 'intended_pitch_type']);
        });
    }
};
