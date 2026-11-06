<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crossings', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();          // contoh: PJL-MLG-01
            $t->string('name', 150);
            $t->string('address')->nullable();
            $t->decimal('latitude', 9, 6)->nullable();
            $t->decimal('longitude', 9, 6)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // penugasan operator ke pos
        Schema::create('crossing_user', function (Blueprint $t) {
            $t->foreignId('crossing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['crossing_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crossing_user');
        Schema::dropIfExists('crossings');
    }
};
