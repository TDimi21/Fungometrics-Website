<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practice_plans', function (Blueprint $table): void {
            // Client-generated string id so the app/web can upsert their own plans.
            $table->string('id')->primary();
            $table->uuid('team_id')->nullable()->index();
            $table->string('created_by')->nullable();
            $table->string('title');
            $table->date('date')->nullable();
            $table->string('focus')->nullable();
            $table->text('notes')->nullable();
            $table->integer('total_duration')->nullable();
            $table->integer('scheduled_minutes')->nullable();
            $table->integer('drill_count')->nullable();
            // longText (not json) — the prod MariaDB build rejects native json DDL;
            // the model's 'slots' => 'array' cast stores/reads JSON in text just fine.
            $table->longText('slots')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_plans');
    }
};
