<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        DB::table('sms_logs')->whereNull('user_id')->delete();
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });
        Schema::table('sms_logs', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnUpdate();
        });
    }
};
