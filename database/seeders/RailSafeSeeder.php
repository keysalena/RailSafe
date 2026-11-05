<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\GateState;
use App\Models\SensorTelemetry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RailSafeSeeder extends Seeder
{
    public function run(): void
    {
        // unguarded: kolom 'role' belum ada di $fillable model User bawaan
        User::unguarded(function () {
            User::updateOrCreate(['email' => 'admin@railsafe.test'],
                ['name' => 'Admin RailSafe', 'password' => Hash::make('password'), 'role' => 'admin']);
            User::updateOrCreate(['email' => 'operator@railsafe.test'],
                ['name' => 'Operator Pos PJL', 'password' => Hash::make('password'), 'role' => 'operator']);
        });

        // API key DEV saja -> ganti di produksi!
        $node1 = Device::updateOrCreate(['device_id' => 'NODE-01'], [
            'name' => 'Node 1 - Sensor Rel', 'type' => 'node1', 'location' => '±1 km sebelum pos',
            'api_key_hash' => Device::hashKey('railsafe-node01-dev-key'),
        ]);
        $node2 = Device::updateOrCreate(['device_id' => 'NODE-02'], [
            'name' => 'Node 2 - Pos PJL', 'type' => 'node2', 'location' => 'Pos Perlintasan',
            'api_key_hash' => Device::hashKey('railsafe-node02-dev-key'),
        ]);

        GateState::updateOrCreate(['device_id' => $node2->id], ['state' => 'open', 'mode' => 'auto']);

        if (SensorTelemetry::count() === 0) {
            for ($i = 20; $i >= 1; $i--) {
                SensorTelemetry::create([
                    'device_id' => $node1->id, 'vibration_class' => 'idle',
                    'confidence' => rand(88, 99), 'battery_percent' => 82, 'battery_voltage' => 7.9,
                    'lora_rssi' => rand(-95, -80), 'lora_snr' => 8.5, 'distance_cm' => rand(180, 220),
                    'recorded_at' => now()->subSeconds($i * 3),
                ]);
            }
        }
        $node1->forceFill(['last_seen_at' => now()])->save();
        $node2->forceFill(['last_seen_at' => now()])->save();
    }
}
