<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bullpen_practice_results', function (Blueprint $table): void {
            $table->uuid('recorded_by')->nullable();
            $table->string('client_request_id', 64)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->unique(['recorded_by', 'client_request_id'], 'bullpen_request_identity_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bullpen_practice_results', function (Blueprint $table): void {
            $table->dropUnique('bullpen_request_identity_unique');
            $table->dropColumn(['recorded_by', 'client_request_id', 'request_hash']);
        });
    }
};
