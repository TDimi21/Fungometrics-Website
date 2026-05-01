<?php

declare(strict_types=1);

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        DB::unprepared(file_get_contents(base_path('database/seeders/sql/users.sql')));
        DB::unprepared(file_get_contents(base_path('database/seeders/sql/profiles.sql')));
        DB::unprepared(file_get_contents(base_path('database/seeders/sql/teams.sql')));
        DB::unprepared(file_get_contents(base_path('database/seeders/sql/coach_teams.sql')));
        DB::unprepared(file_get_contents(base_path('database/seeders/sql/players.sql')));
        DB::unprepared(file_get_contents(base_path('database/seeders/sql/player_positions.sql')));
        DB::unprepared(file_get_contents(base_path('database/seeders/sql/player_teams.sql')));
        DB::unprepared(file_get_contents(base_path('database/seeders/sql/practices.sql')));
        DB::unprepared(file_get_contents(base_path('database/seeders/sql/practice_lineups.sql')));
        DB::unprepared(file_get_contents(base_path('database/seeders/sql/battingresults.sql')));
    }
}
