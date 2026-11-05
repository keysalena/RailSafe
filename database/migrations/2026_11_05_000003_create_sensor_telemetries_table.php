<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sensor_telemetries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();

            // Hasil klasifikasi TinyML (MPU6050): idle | truk | kereta
            $table->string('vibration_class', 10);
            $table->decimal('confidence', 5, 2);             // 0 - 100 (%)

            // Kesehatan hardware Node 1
            $table->unsignedTinyInteger('battery_percent')->nullable(); // baterai 2S2P
            $table->decimal('battery_voltage', 4, 2)->nullable();
            $table->smallInteger('lora_rssi')->nullable();               // dBm
            $table->decimal('lora_snr', 5, 2)->nullable();
            $table->decimal('distance_cm', 6, 1)->nullable();            // HC-SR04

            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['device_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensor_telemetries');
    }
};
