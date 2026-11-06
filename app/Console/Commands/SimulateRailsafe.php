<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\LogKontrol;
use App\Models\LogPerlintasan;
use App\Models\LogSensor;
use App\Models\Setting;
use Illuminate\Console\Command;

class SimulateRailsafe extends Command
{
    protected $signature = 'railsafe:simulate {--train : Simulasikan kereta datang} {--no-response : Petugas tidak merespons} {--obstacle : Ada kendaraan terjebak (dengan --no-response)}';
    protected $description = 'Simulasi heartbeat dan kejadian perlintasan untuk demo tanpa perangkat keras';

    public function handle(): int
    {
        $n1 = Device::where('type', 'SENSOR_NODE')->first();
        $n2 = Device::where('type', 'GATE_CONTROLLER')->first();
        if (! $n1 || ! $n2) {
            $this->error('Perangkat belum ada. Jalankan: php artisan migrate --seed');
            return self::FAILURE;
        }

        $beat = function (string $label, float $conf) use ($n1, $n2) {
            $n1->update(['ml_label' => $label, 'ml_confidence' => $conf, 'battery_level' => 82, 'rssi' => -71, 'connection_status' => 'ACTIVE', 'last_seen_at' => now()]);
            $n2->update(['distance_cm' => round(rand(1200, 1800) / 10, 1), 'rssi' => -68, 'connection_status' => 'ACTIVE', 'last_seen_at' => now()]);
        };

        if (! $this->option('train')) {
            $this->info('Mengirim heartbeat setiap 5 detik. Tekan Ctrl+C untuk berhenti.');
            while (true) {
                $beat('IDLE', rand(950, 990) / 10);
                sleep(5);
            }
        }

        $countdown = (int) Setting::get('countdown_seconds');
        $eventId = 'EVT-SIM-'.now()->format('Ymd-His');
        LogPerlintasan::create([
            'event_id' => $eventId, 'crossing_id' => $n2->crossing_id, 'device_id' => $n1->id,
            'detected_at' => now(), 'confidence' => 93.4,
        ]);
        $this->info("Kereta terdeteksi ($eventId). Hitung mundur $countdown detik, lihat banner merah di dashboard.");

        $noResp = $this->option('no-response');
        $wait = $noResp ? $countdown : 6;
        for ($i = 0; $i < $wait; $i++) {
            $beat('KERETA', 93.4);
            sleep(1);
        }

        $obstacle = $noResp && $this->option('obstacle');
        $type = ! $noResp ? 'MANUAL_OK' : ($obstacle ? 'CRITICAL_OBSTACLE' : 'FAIL_SAFE_AUTOGATE');
        LogKontrol::create([
            'event_id' => $eventId, 'device_id' => $n2->id,
            'control_mode' => $noResp ? 'AUTO' : 'MANUAL',
            'guard_response' => $noResp ? 'NO_RESPONSE' : 'CONFIRMED',
            'response_time_ms' => $noResp ? null : $wait * 1000,
            'obstacle_checked' => $noResp, 'obstacle_detected' => $noResp ? $obstacle : null,
            'action' => LogKontrol::ACTIONS[$type], 'emergency_alarm' => $obstacle,
            'status_label' => $type, 'completed_at' => now(),
        ]);
        $beat('IDLE', 97.0);
        $this->info("Selesai: $type");

        return self::SUCCESS;
    }
}
