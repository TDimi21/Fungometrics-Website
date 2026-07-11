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
            if (! Schema::hasColumn('benchmark_collection_tasks', 'review_status')) {
                $table->string('review_status')->nullable()->index()->after('status');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'submitted_by_user_id')) {
                $table->uuid('submitted_by_user_id')->nullable()->index()->after('review_status');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'reviewed_by_user_id')) {
                $table->uuid('reviewed_by_user_id')->nullable()->index()->after('submitted_by_user_id');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('completed_at');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('review_notes');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'correction_message')) {
                $table->text('correction_message')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'submitted_payload')) {
                $table->json('submitted_payload')->nullable()->after('payload');
            }

            if (! Schema::hasColumn('benchmark_collection_tasks', 'approved_payload')) {
                $table->json('approved_payload')->nullable()->after('submitted_payload');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'review_status',
            'submitted_by_user_id',
            'reviewed_by_user_id',
            'submitted_at',
            'reviewed_at',
            'review_notes',
            'rejection_reason',
            'correction_message',
            'submitted_payload',
            'approved_payload',
        ], fn (string $column): bool => Schema::hasColumn('benchmark_collection_tasks', $column)));

        if (empty($columns)) {
            return;
        }

        Schema::table('benchmark_collection_tasks', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
