<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('practices', function (Blueprint $table): void {
            $table->boolean('is_scripted')->default(false)->after('modes')->index();
        });
    }

    public function down(): void
    {
        Schema::table('practices', function (Blueprint $table): void {
            $table->dropIndex(['is_scripted']);
            $table->dropColumn('is_scripted');
        });
    }
};
