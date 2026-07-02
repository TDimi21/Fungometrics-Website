<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            if (! Schema::hasColumn('players', 'grad_year')) {
                $table->unsignedSmallInteger('grad_year')->nullable()->after('born_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            if (Schema::hasColumn('players', 'grad_year')) {
                $table->dropColumn('grad_year');
            }
        });
    }
};
