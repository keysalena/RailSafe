<?php

namespace Database\Seeders;

use App\Models\Crossing;
use App\Models\Device;
use App\Models\LogKontrol;
use App\Models\LogPerlintasan;
use App\Models\LogSensor;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- akun demo ---
        User::create(['name' => 'Admin RailSafe', 'email' => 'superadmin@railsafe.test', 'password' => 'password', 'role' => 'superadmin']);
        $op = User::create(['name' => 'Budi Santoso', 'email' => 'operator@railsafe.test', 'password' => 'password', 'role' => 'operator']);

        // --- satu pos dan dua perangkat (token demo, JANGAN dipakai di produksi) ---
        $c = Crossing::create(['code' => 'PJL-MLG-01', 'name' => 'Perlintasan Demo Malang', 'address' => 'Kota Malang, Jawa Timur']);
        $op->crossings()->attach($c->id);

        $n1 = Device::create(['crossing_id' => $c->id, 'device_code' => 'NODE-01', 'name' => 'Node 1 · Deteksi dini', 'type' => 'SENSOR_NODE',
            'api_token_hash' => Device::hashToken('rs_demo_node01_token'), 'firmware_version' => '0.1.0',
            'battery_level' => 82, 'rssi' => -71, 'ml_label' => 'IDLE', 'ml_confidence' => 97.0, 'connection_status' => 'ACTIVE', 'last_seen_at' => now()]);
        $n2 = Device::create(['crossing_id' => $c->id, 'device_code' => 'NODE-02', 'name' => 'Node 2 · Kontroler pos', 'type' => 'GATE_CONTROLLER',
            'api_token_hash' => Device::hashToken('rs_demo_node02_token'), 'firmware_version' => '0.1.0',
            'rssi' => -68, 'distance_cm' => 142.5, 'connection_status' => 'ACTIVE', 'last_seen_at' => now()]);

        foreach ([[$n1, 'MPU6050', 'ACTIVE', 'OK', 'Model TinyML berjalan normal'], [$n1, 'LORA', 'ACTIVE', 'OK', null],
                  [$n2, 'LORA', 'ACTIVE', 'OK', null], [$n2, 'ULTRASONIC_1', 'STANDBY', 'OK', 'Aktif hanya di mode otomatis'],
                  [$n2, 'ULTRASONIC_2', 'STANDBY', 'OK', null], [$n2, 'SERVO', 'ACTIVE', 'OK', 'Palang terbuka']] as [$d, $t, $s, $h, $det]) {
            LogSensor::create(['device_id' => $d->id, 'sensor_type' => $t, 'operational_state' => $s, 'health_status' => $h, 'detail' => $det, 'reported_at' => now()]);
        }

        // --- parameter ---
        foreach (Setting::DEFAULTS as $k => $v) Setting::put($k, $v);

        // --- riwayat contoh 7 hari ---
        mt_srand(42);
        $seq = 1;
        for ($d = 6; $d >= 0; $d--) {
            $day = now()->startOfDay()->subDays($d);
            $count = $d === 0 ? 10 : mt_rand(6, 12);
            for ($i = 0; $i < $count; $i++) {
                $at = $day->copy()->addMinutes(5 * 60 + (int) ($i * (14 * 60) / $count) + mt_rand(0, 30));
                if ($at->isFuture()) continue;
                $roll = mt_rand(1, 100);
                $type = $roll <= 70 ? 'MANUAL_OK' : ($roll <= 92 ? 'FAIL_SAFE_AUTOGATE' : 'CRITICAL_OBSTACLE');
                $auto = $type !== 'MANUAL_OK';
                $id = sprintf('EVT-%s-%02d', $at->format('Ymd'), $seq++);

                LogPerlintasan::create(['event_id' => $id, 'crossing_id' => $c->id, 'device_id' => $n1->id, 'detected_at' => $at, 'confidence' => mt_rand(880, 990) / 10]);
                LogKontrol::create([
                    'event_id' => $id, 'device_id' => $n2->id,
                    'control_mode' => $auto ? 'AUTO' : 'MANUAL', 'guard_response' => $auto ? 'NO_RESPONSE' : 'CONFIRMED',
                    'response_time_ms' => $auto ? null : mt_rand(2000, 9000),
                    'obstacle_checked' => $auto, 'obstacle_detected' => $auto ? $type === 'CRITICAL_OBSTACLE' : null,
                    'action' => LogKontrol::ACTIONS[$type], 'emergency_alarm' => $type === 'CRITICAL_OBSTACLE',
                    'status_label' => $type, 'completed_at' => $at->copy()->addSeconds(15),
                ]);
            }
        }
    }
}
