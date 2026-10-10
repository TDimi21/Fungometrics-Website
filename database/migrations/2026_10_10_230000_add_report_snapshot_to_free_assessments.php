<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('free_assessments', fn(Blueprint $table) => $table->longText('report_snapshot')->nullable()); }
    public function down(): void { Schema::table('free_assessments', fn(Blueprint $table) => $table->dropColumn('report_snapshot')); }
};
