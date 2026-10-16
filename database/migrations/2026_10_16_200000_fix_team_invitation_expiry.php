<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Preserve stored values; never revive invitations whose expiry was already shortened.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `team_invitations` MODIFY `expires_at` DATETIME NOT NULL');
        } else {
            Schema::table('team_invitations', fn (Blueprint $table) => $table->dateTime('expires_at')->change());
        }
    }

    public function down(): void
    {
        // Deliberately retain the safe type: rollback must not restore ON UPDATE expiry.
    }
};
