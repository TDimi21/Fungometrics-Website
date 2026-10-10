<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('daily_plans', function(Blueprint $t) { $t->unsignedInteger('version')->default(1); $t->longText('settings')->nullable(); });
        Schema::table('daily_plan_assignments', function(Blueprint $t) { $t->date('scheduled_date')->nullable()->index(); $t->string('schedule_status')->default('active'); $t->longText('schedule_adjustments')->nullable(); $t->longText('prescription_override')->nullable(); });
        Schema::table('daily_plan_progress', function(Blueprint $t) { $t->unsignedInteger('version')->default(1); $t->longText('post_training')->nullable(); $t->longText('actual_history')->nullable(); $t->longText('alert_review')->nullable(); });
    }
    public function down(): void {
        Schema::table('daily_plans', fn(Blueprint $t) => $t->dropColumn(['version','settings']));
        Schema::table('daily_plan_assignments', fn(Blueprint $t) => $t->dropColumn(['scheduled_date','schedule_status','schedule_adjustments','prescription_override']));
        Schema::table('daily_plan_progress', fn(Blueprint $t) => $t->dropColumn(['version','post_training','actual_history','alert_review']));
    }
};
