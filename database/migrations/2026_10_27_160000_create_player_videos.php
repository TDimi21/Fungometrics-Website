<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('player_videos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('player_id')->index();
            $table->uuid('uploaded_by')->index();
            $table->string('title', 120);
            $table->string('category', 30);
            $table->text('notes')->nullable();
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('bytes');
            $table->timestamps();
        });
        Schema::create('player_video_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('video_id')->index();
            $table->foreign('video_id')->references('id')->on('player_videos')->cascadeOnDelete();
            $table->uuid('team_id')->index();
            $table->uuid('coach_id')->nullable()->index();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('player_video_shares');
        Schema::dropIfExists('player_videos');
    }
};
