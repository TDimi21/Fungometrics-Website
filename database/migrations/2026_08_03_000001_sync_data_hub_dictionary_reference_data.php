<?php

declare(strict_types=1);

use Database\Seeders\BaseballDictionarySeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Data Hub platforms and their official aliases are application
        // reference data, not optional demo data. Keep production in sync
        // when new supported platforms (such as Blast Motion) are deployed.
        app(BaseballDictionarySeeder::class)->run();
    }

    public function down(): void
    {
        // Reference rows may already be used by imports and mapping templates.
        // Removing them during rollback would destroy valid user relationships.
    }
};
