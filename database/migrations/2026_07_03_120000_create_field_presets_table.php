<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_presets', function (Blueprint $table): void {
            // Client-generated string id so the app can upsert its own presets.
            $table->string('id')->primary();
            // Owner of the preset — personal saved fields, not team-shared.
            $table->string('user_id')->index();
            $table->string('name');
            // longText (not json) — the prod MariaDB build rejects native json DDL;
            // the model's 'config' => 'array' cast stores/reads JSON in text just fine.
            $table->longText('config')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_presets');
    }
};
