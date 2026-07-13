<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('weekly_report_deliveries')) {
            return;
        }

        Schema::create('weekly_report_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('team_id')->index();
            $table->uuid('created_by_user_id')->nullable()->index();
            $table->uuid('sent_by_user_id')->nullable()->index();
            $table->date('week_start_date')->nullable()->index();
            $table->date('week_end_date')->nullable()->index();
            $table->string('template_key')->nullable()->index();
            $table->string('audience')->nullable()->index();
            $table->string('channel')->nullable()->index();
            $table->string('format')->nullable();
            $table->string('delivery_status')->index();
            $table->string('subject')->nullable();
            $table->text('message_preview')->nullable();
            // Production MariaDB rejects native JSON DDL; model casts handle JSON in text.
            $table->longText('recipient_summary')->nullable();
            $table->longText('recipients')->nullable();
            $table->longText('privacy_warnings')->nullable();
            $table->longText('delivery_warnings')->nullable();
            $table->longText('send_blockers')->nullable();
            $table->longText('export_payload')->nullable();
            $table->longText('draft_payload')->nullable();
            $table->longText('send_result')->nullable();
            $table->timestamp('copied_at')->nullable();
            $table->timestamp('draft_created_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at'], 'weekly_report_deliveries_team_created_idx');
            $table->index(['team_id', 'delivery_status'], 'weekly_report_deliveries_team_status_idx');
            $table->index(['team_id', 'audience', 'channel'], 'weekly_report_deliveries_team_audience_channel_idx');
            $table->index(['team_id', 'week_start_date', 'week_end_date'], 'weekly_report_deliveries_team_week_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_report_deliveries');
    }
};
