<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\GateLog;
use App\Models\Setting;
use Illuminate\Console\Command;

class SimulateRailsafe extends Command
{
    protected $signature = 'railsafe:simulate {--train : Kereta terdeteksi, palang ditutup otomatis} {--manual : Petugas menutup palang dengan tombol, lalu membukanya}';
    protected $description = 'Simulasi heartbeat dan log palang untuk demo tanpa perangkat keras';

    public function handle(): int
    {
        $n1 = Device::where('type', 'SENSOR_NODE')->first();
        $n2 = Device::where('type', 'GATE_CONTROLLER')->first();
        if (! $n1 || ! $n2) {
            $this->error('Perangkat belum ada. Jalankan: php artisan migrate --seed');
            return self::FAILURE;
        }

        $beat = function () use ($n1, $n2) {
            $n1->update(['battery_level' => 82, 'connection_status' => 'ACTIVE', 'last_seen_at' => now()]);
            $n2->update(['distance_cm' => round(rand(1200, 1800) / 10, 1), 'rssi' => -68, 'connection_status' => 'ACTIVE', 'last_seen_at' => now()]);
        };
        $log = fn (string $trigger, string $action, ?float $conf, string $notes) => GateLog::create([
            'event_id' => sprintf('GL-%s-%04d', now()->format('ymd-His'), random_int(0, 9999)),
            'crossing_id' => $n2->crossing_id, 'device_id' => $n2->id, 'trigger_type' => $trigger,
            'gate_action' => $action, 'confidence_score' => $conf, 'notes' => $notes, 'logged_at' => now(),
        ]);

        if ($this->option('train')) {
            $log('AUTOMATIC', 'CLOSED', 93.4, 'Kereta terdeteksi TinyML (simulasi)');
            $secs = (int) Setting::get('gate_reopen_seconds');
            $this->info("Palang ditutup otomatis. Banner di dashboard aktif selama $secs detik.");
            for ($i = 0; $i < $secs; $i++) { $beat(); sleep(1); }
            return self::SUCCESS;
        }

        if ($this->option('manual')) {
            $log('MANUAL', 'CLOSED', null, 'Ditutup manual via Arcade Button (simulasi)');
            $this->info('Palang ditutup petugas. Dibuka lagi dalam 8 detik.');
            for ($i = 0; $i < 8; $i++) { $beat(); sleep(1); }
            $log('MANUAL', 'OPENED', null, 'Dibuka manual via Arcade Button (simulasi)');
            return self::SUCCESS;
        }

        $this->info('Mengirim heartbeat tiap 5 detik. Tekan Ctrl+C untuk berhenti.');
        while (true) { $beat(); sleep(5); }
    }
}
