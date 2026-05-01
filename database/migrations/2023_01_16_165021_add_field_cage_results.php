<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('cage_practice_results', function (Blueprint $table): void {
            $table->string('cage_position');
            $table->integer('cage_mark')->unsigned();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('cage_practice_results', function (Blueprint $table): void {
            $table->dropColumn('cage_position');
            $table->dropColumn('cage_mark');
        });
    }
};
