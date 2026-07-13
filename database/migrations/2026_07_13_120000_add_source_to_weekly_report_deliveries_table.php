<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weekly_report_deliveries')) {
            return;
        }

        Schema::table('weekly_report_deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('weekly_report_deliveries', 'source')) {
                $table->string('source')->nullable()->index()->after('sent_by_user_id');
            }
            if (! Schema::hasColumn('weekly_report_deliveries', 'archive_type')) {
                $table->string('archive_type')->nullable()->index()->after('source');
            }
            if (! Schema::hasColumn('weekly_report_deliveries', 'season_start_date')) {
                $table->date('season_start_date')->nullable()->index()->after('week_end_date');
            }
            if (! Schema::hasColumn('weekly_report_deliveries', 'season_end_date')) {
                $table->date('season_end_date')->nullable()->index()->after('season_start_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('weekly_report_deliveries')) {
            return;
        }

        Schema::table('weekly_report_deliveries', function (Blueprint $table): void {
            foreach (['season_end_date', 'season_start_date', 'archive_type', 'source'] as $column) {
                if (Schema::hasColumn('weekly_report_deliveries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
