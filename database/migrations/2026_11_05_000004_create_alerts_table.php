<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 50)->unique();         // anti pencatatan ganda saat retry
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('event_type', 30)->default('ALERT_KERETA');
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('status', 15)->default('active');  // active | acknowledged
            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
