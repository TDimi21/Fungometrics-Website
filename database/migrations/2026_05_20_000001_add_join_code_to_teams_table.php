<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('join_code', 8)->nullable()->unique()->after('zip');
        });

        // Backfill any existing teams that don't have a code yet
        DB::table('teams')->whereNull('join_code')->chunkById(100, function ($teams): void {
            foreach ($teams as $team) {
                DB::table('teams')
                    ->where('id', $team->id)
                    ->update(['join_code' => self::generateUniqueCode()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn('join_code');
        });
    }

    private static function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (DB::table('teams')->where('join_code', $code)->exists());

        return $code;
    }
};
