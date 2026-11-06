<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // satu baris per kejadian deteksi kereta
        Schema::create('log_perlintasan', function (Blueprint $t) {
            $t->id();
            $t->string('event_id', 40)->unique();        // mencegah pencatatan ganda saat kirim ulang
            $t->foreignId('crossing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('device_id')->constrained()->cascadeOnDelete();
            $t->timestamp('detected_at');
            $t->decimal('confidence', 5, 2)->nullable();  // hasil TinyML (%)
            $t->unsignedSmallInteger('detection_count')->default(1);
            $t->boolean('train_passed_confirmed')->nullable(); // kosong sampai ada konfirmasi kereta lewat
            $t->timestamps();
            $t->index(['crossing_id', 'detected_at']);
        });

        // respons petugas dan tindakan palang
        Schema::create('log_kontrol', function (Blueprint $t) {
            $t->id();
            $t->string('event_id', 40)->unique();
            $t->foreign('event_id')->references('event_id')->on('log_perlintasan')->cascadeOnDelete();
            $t->foreignId('device_id')->constrained()->cascadeOnDelete();
            $t->string('control_mode', 10);               // MANUAL | AUTO
            $t->string('guard_response', 15);             // CONFIRMED | NO_RESPONSE
            $t->unsignedInteger('response_time_ms')->nullable();
            $t->boolean('obstacle_checked')->default(false);
            $t->boolean('obstacle_detected')->nullable();
            $t->string('action', 30);                     // GATE_CLOSED_MANUAL | GATE_CLOSED_AUTO | GATE_HELD_OBSTACLE
            $t->boolean('emergency_alarm')->default(false);
            $t->string('status_label', 30);               // MANUAL_OK | FAIL_SAFE_AUTOGATE | CRITICAL_OBSTACLE
            $t->string('sensor_status', 30)->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['control_mode', 'guard_response']);
        });

        // status dan kesehatan sensor (dipisah: state vs health)
        Schema::create('log_sensor', function (Blueprint $t) {
            $t->id();
            $t->foreignId('device_id')->constrained()->cascadeOnDelete();
            $t->string('sensor_type', 20);                // MPU6050 | LORA | ULTRASONIC_1 | ULTRASONIC_2 | SERVO
            $t->string('operational_state', 10);          // ACTIVE | STANDBY | OFF
            $t->string('health_status', 10);              // OK | FAULT | UNKNOWN
            $t->string('detail')->nullable();
            $t->timestamp('reported_at');
            $t->index(['device_id', 'sensor_type', 'reported_at']);
        });

        Schema::create('event_notes', function (Blueprint $t) {
            $t->id();
            $t->string('event_id', 40)->index();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('note');
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 50);
            $t->string('target_type', 50)->nullable();
            $t->string('target_id', 50)->nullable();
            $t->json('meta')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 50)->primary();
            $t->string('value');
        });
    }

    public function down(): void
    {
        foreach (['settings', 'audit_logs', 'event_notes', 'log_sensor', 'log_kontrol', 'log_perlintasan'] as $tb) {
            Schema::dropIfExists($tb);
        }
    }
};
