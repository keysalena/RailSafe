<?php

namespace Database\Seeders;

use App\Models\Crossing;
use App\Models\Device;
use App\Models\LogSensor;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Membuat satu pos demo beserta dua perangkat (Node 1 & Node 2),
 * log sensor awal, dan pengaturan sistem default.
 *
 * Prasyarat: AccountSeeder sudah dijalankan (operator harus ada).
 */
class CrossingSeeder extends Seeder
{
    public function run(): void
    {
        $op = User::where('email', 'operator@railsafe.test')->firstOrFail();

        // --- pos perlintasan demo ---
        $crossing = Crossing::create([
            'code'    => 'PJL-MLG-01',
            'name'    => 'Perlintasan Demo Malang',
            'address' => 'Kota Malang, Jawa Timur',
        ]);
        $op->crossings()->attach($crossing->id);

        // --- perangkat: Node 1 (sensor) ---
        $n1 = Device::create([
            'crossing_id'      => $crossing->id,
            'device_code'      => 'NODE-01',
            'name'             => 'Node 1 · Deteksi dini',
            'type'             => 'SENSOR_NODE',
            'api_token_hash'   => Device::hashToken('rs_demo_node01_token'), // JANGAN dipakai di produksi
            'firmware_version' => '0.1.0',
            'battery_level'    => 82,
            'connection_status'=> 'ACTIVE',
            'last_seen_at'     => now(),
        ]);

        // --- perangkat: Node 2 (kontroler palang) ---
        $n2 = Device::create([
            'crossing_id'      => $crossing->id,
            'device_code'      => 'NODE-02',
            'name'             => 'Node 2 · Kontroler pos',
            'type'             => 'GATE_CONTROLLER',
            'api_token_hash'   => Device::hashToken('rs_demo_node02_token'), // JANGAN dipakai di produksi
            'firmware_version' => '0.1.0',
            'rssi'             => -68,
            'distance_cm'      => 142.5,
            'connection_status'=> 'ACTIVE',
            'last_seen_at'     => now(),
        ]);

        // --- log sensor awal ---
        $sensors = [
            [$n1, 'MPU6050',      'ACTIVE', 'OK',  'Model TinyML berjalan'],
            [$n1, 'LORA',         'ACTIVE', 'OK',  null],
            [$n2, 'LORA',         'ACTIVE', 'OK',  null],
            [$n2, 'ULTRASONIC_1', 'ACTIVE', 'OK',  null],
            [$n2, 'SERVO',        'ACTIVE', 'OK',  'Palang terbuka'],
        ];

        foreach ($sensors as [$device, $type, $state, $health, $detail]) {
            LogSensor::create([
                'device_id'         => $device->id,
                'sensor_type'       => $type,
                'operational_state' => $state,
                'health_status'     => $health,
                'detail'            => $detail,
                'reported_at'       => now(),
            ]);
        }

        // --- pengaturan sistem default ---
        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::put($key, $value);
        }
    }
}

