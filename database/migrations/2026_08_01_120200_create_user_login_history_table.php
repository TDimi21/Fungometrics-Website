<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_login_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('user_id');
            $table->timestamp('logged_in_at');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'logged_in_at']);
        });

        // Preserve the one login timestamp that was tracked before history
        // existed, so existing coaches do not start with an empty panel.
        DB::table('user_login_history')->insertUsing(
            ['user_id', 'logged_in_at'],
            DB::table('users')
                ->select('id', 'last_login_at')
                ->whereNotNull('last_login_at')
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_history');
    }
};
