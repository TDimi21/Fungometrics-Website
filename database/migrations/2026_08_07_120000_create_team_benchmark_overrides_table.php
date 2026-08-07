<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_benchmark_overrides', function (Blueprint $table): void {
            $table->id();
            $table->uuid('team_id')->index();
            $table->string('metric_key', 100);
            $table->string('age_group', 40);
            $table->decimal('p5', 10, 3);
            $table->decimal('p25', 10, 3);
            $table->decimal('p50', 10, 3);
            $table->decimal('p75', 10, 3);
            $table->decimal('p95', 10, 3);
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'metric_key', 'age_group'], 'team_benchmark_override_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_benchmark_overrides');
    }
};
