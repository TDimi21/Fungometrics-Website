<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};
return new class extends Migration {
    public function up(): void {
        Schema::create('practice_shared_teams', function (Blueprint $table) {
            $table->uuid('practice_id'); $table->uuid('team_id');
            $table->primary(['practice_id','team_id']); $table->index('team_id');
        });
        DB::table('practices')->where('is_player_shared',true)->whereNotNull('team_id')->orderBy('id')->chunkById(500,function($rows) {
            DB::table('practice_shared_teams')->insertOrIgnore($rows->map(fn($row)=>['practice_id'=>$row->id,'team_id'=>$row->team_id])->all());
        });
    }
    public function down(): void { Schema::dropIfExists('practice_shared_teams'); }
};
