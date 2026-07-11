<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('benchmark_collection_tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('benchmark_collection_tasks', 'promoted_at')) {
                $table->timestamp('promoted_at')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'promoted_by_user_id')) {
                $table->uuid('promoted_by_user_id')->nullable()->index()->after('promoted_at');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'promotion_status')) {
                $table->string('promotion_status')->nullable()->index()->after('promoted_by_user_id');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'promotion_mode')) {
                $table->string('promotion_mode')->nullable()->after('promotion_status');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'promotion_result')) {
                $table->longText('promotion_result')->nullable()->after('promotion_mode');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'promoted_at',
            'promoted_by_user_id',
            'promotion_status',
            'promotion_mode',
            'promotion_result',
        ], fn (string $column): bool => Schema::hasColumn('benchmark_collection_tasks', $column)));

        if (empty($columns)) {
            return;
        }

        Schema::table('benchmark_collection_tasks', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
