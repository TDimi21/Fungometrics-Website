<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('player_daily_readiness', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->date('date');
            $table->string('timezone', 64);
            $table->unsignedTinyInteger('scale_version')->default(2);
            // MariaDB 10.1 has no native JSON column type. The model's array
            // cast serializes answers as JSON in text, as with other planner data.
            $table->longText('answers');
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }
    public function down(): void { Schema::dropIfExists('player_daily_readiness'); }
};
