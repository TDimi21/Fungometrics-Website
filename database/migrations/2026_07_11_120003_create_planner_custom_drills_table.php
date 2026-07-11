<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach-created custom drills and lifts (a lift is just a strength-bucket drill).
 * These merge into the in-app seeded library. `visibility` is here from day one so
 * the future "browse other coaches' drills" area is a query, not a migration:
 *   private → only the author · team → the author's teams · public → community.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_custom_drills', function (Blueprint $table): void {
            // Client-generated string id so the app/web can upsert their own drills.
            $table->string('id')->primary();
            $table->string('created_by')->nullable()->index(); // coach (users.id)
            $table->uuid('team_id')->nullable()->index();
            $table->string('name');
            $table->string('bucket')->index();                 // throwing / hitting / strength_primary / …
            $table->string('category_group')->nullable()->index();
            $table->string('equipment')->nullable();
            $table->string('visibility')->default('private')->index(); // private | team | public
            $table->string('source')->nullable()->default('custom');
            // longText (not json) — see the daily_plans migration note. Holds the rest
            // of the normalized drill shape (description, cues, defaults, tags, …).
            $table->longText('data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_custom_drills');
    }
};
