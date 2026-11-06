<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Satu baris per aksi palang. Kolom inti sama dengan payload firmware Node 2:
        // trigger_type, confidence_score, notes. Kolom lain opsional (untuk alur fail-safe di proposal).
        Schema::create('gate_logs', function (Blueprint $t) {
            $t->id();
            $t->string('event_id', 40)->unique();
            $t->foreignId('crossing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('device_id')->constrained()->cascadeOnDelete();
            $t->string('trigger_type', 10);               // AUTOMATIC | MANUAL
            $t->string('gate_action', 10)->default('CLOSED'); // CLOSED | OPENED | HELD (ditahan karena rintangan)
            $t->decimal('confidence_score', 5, 2)->nullable(); // persen, hanya untuk AUTOMATIC
            $t->string('notes')->nullable();              // catatan dari firmware
            $t->string('guard_response', 15)->nullable(); // opsional: CONFIRMED | NO_RESPONSE
            $t->unsignedInteger('response_time_ms')->nullable();
            $t->boolean('obstacle_detected')->nullable();
            $t->timestamp('logged_at');
            $t->timestamps();
            $t->index(['crossing_id', 'logged_at']);
            $t->index(['trigger_type', 'gate_action']);
        });

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
            $t->foreignId('gate_log_id')->constrained('gate_logs')->cascadeOnDelete();
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
        foreach (['settings', 'audit_logs', 'event_notes', 'log_sensor', 'gate_logs'] as $tb) {
            Schema::dropIfExists($tb);
        }
    }
};
