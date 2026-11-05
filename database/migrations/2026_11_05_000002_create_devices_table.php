<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 30)->unique();      // NODE-01, NODE-02
            $table->string('name');
            $table->string('type', 10);                      // node1 (sensor rel) | node2 (pos / gerbang)
            $table->string('location')->nullable();
            $table->string('api_key_hash', 64)->nullable();  // sha256 dari API key perangkat
            $table->timestamp('last_seen_at')->nullable();   // heartbeat terakhir
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
