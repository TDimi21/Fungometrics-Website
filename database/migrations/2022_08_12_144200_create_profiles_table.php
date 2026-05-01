<?php

declare(strict_types=1);

use App\Models\Concerns\UserTypes;
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
        Schema::create('profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary()->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('picture')->nullable();
            $table->string('city')->nullable();
            $table->string('zip')->nullable();
            $table->string('state')->nullable();
            $table->string('country_code')->default('+1');
            $table->string('level')->default(UserTypes::PLAYER->value);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
