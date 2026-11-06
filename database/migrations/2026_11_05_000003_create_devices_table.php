<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('crossing_id')->constrained()->cascadeOnDelete();
            $t->string('device_code', 30)->unique();     // NODE-01, NODE-02
            $t->string('name', 100)->nullable();
            $t->string('type', 20);                      // SENSOR_NODE | GATE_CONTROLLER
            $t->string('api_token_hash', 64);            // sha256 dari token perangkat
            $t->boolean('is_active')->default(true);
            $t->string('firmware_version', 20)->nullable();
            // data heartbeat terakhir
            $t->unsignedTinyInteger('battery_level')->nullable();
            $t->smallInteger('rssi')->nullable();
            $t->decimal('distance_cm', 7, 1)->nullable();
            $t->string('ml_label', 10)->nullable();      // IDLE | TRUK | KERETA
            $t->decimal('ml_confidence', 5, 2)->nullable();
            $t->string('connection_status', 15)->default('UNKNOWN'); // ACTIVE | FAULT | UNKNOWN
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
            $t->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
