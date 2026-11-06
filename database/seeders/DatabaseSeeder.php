<?php

namespace Database\Seeders;

use App\Models\Crossing;
use App\Models\Device;
use App\Models\GateLog;
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
            'battery_level' => 82, 'connection_status' => 'ACTIVE', 'last_seen_at' => now()]);
        $n2 = Device::create(['crossing_id' => $c->id, 'device_code' => 'NODE-02', 'name' => 'Node 2 · Kontroler pos', 'type' => 'GATE_CONTROLLER',
            'api_token_hash' => Device::hashToken('rs_demo_node02_token'), 'firmware_version' => '0.1.0',
            'rssi' => -68, 'distance_cm' => 142.5, 'connection_status' => 'ACTIVE', 'last_seen_at' => now()]);

        foreach ([[$n1, 'MPU6050', 'ACTIVE', 'OK', 'Model TinyML berjalan'], [$n1, 'LORA', 'ACTIVE', 'OK', null],
                  [$n2, 'LORA', 'ACTIVE', 'OK', null], [$n2, 'ULTRASONIC_1', 'ACTIVE', 'OK', null],
                  [$n2, 'SERVO', 'ACTIVE', 'OK', 'Palang terbuka']] as [$d, $t, $s, $h, $det]) {
            LogSensor::create(['device_id' => $d->id, 'sensor_type' => $t, 'operational_state' => $s, 'health_status' => $h, 'detail' => $det, 'reported_at' => now()]);
        }

        foreach (Setting::DEFAULTS as $k => $v) Setting::put($k, $v);

        // --- riwayat contoh 7 hari (pola sama dengan log yang dikirim Node2.ino) ---
        mt_srand(42);
        $seq = 1;
        $add = function ($at, $trigger, $action, $conf, $notes) use ($c, $n2, &$seq) {
            GateLog::create([
                'event_id' => sprintf('GL-%s-%03d', $at->format('ymd'), $seq++), 'crossing_id' => $c->id, 'device_id' => $n2->id,
                'trigger_type' => $trigger, 'gate_action' => $action, 'confidence_score' => $conf, 'notes' => $notes, 'logged_at' => $at,
            ]);
        };
        for ($d = 6; $d >= 0; $d--) {
            $day = now()->startOfDay()->subDays($d);
            $count = $d === 0 ? 8 : mt_rand(6, 12);
            for ($i = 0; $i < $count; $i++) {
                $at = $day->copy()->addMinutes(5 * 60 + (int) ($i * (14 * 60) / $count) + mt_rand(0, 30));
                if ($at->isFuture()) continue;
                if (mt_rand(1, 100) <= 78) {
                    $add($at, 'AUTOMATIC', 'CLOSED', mt_rand(720, 990) / 10, 'Kereta terdeteksi TinyML');
                } else {
                    $add($at, 'MANUAL', 'CLOSED', null, 'Ditutup manual via Arcade Button');
                    $open = $at->copy()->addMinutes(mt_rand(1, 3));
                    if (! $open->isFuture()) $add($open, 'MANUAL', 'OPENED', null, 'Dibuka manual via Arcade Button');
                }
            }
        }
    }
}
