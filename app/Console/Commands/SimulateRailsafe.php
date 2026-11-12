<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\GateLog;
use App\Models\Setting;
use Illuminate\Console\Command;

class SimulateRailsafe extends Command
{
    protected $signature = 'railsafe:simulate
        {--train : Kereta terdeteksi, petugas tidak merespons, palang menutup otomatis setelah hitung mundur}
        {--respond : Kereta terdeteksi, petugas menutup palang dengan tombol (6 detik)}
        {--obstacle : Kereta terdeteksi, petugas tidak merespons, rel terhalang (palang ditahan)}
        {--cancel : Kereta terdeteksi lalu getaran berhenti sebelum waktu habis (dibatalkan)}
        {--manual : Petugas menutup lalu membuka palang tanpa kereta}';
    protected $description = 'Simulasi heartbeat dan alur hitung mundur untuk demo tanpa perangkat keras';

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
        $wait = function (int $secs) use ($beat) { for ($i = 0; $i < $secs; $i++) { $beat(); sleep(1); } };
        $eid = fn () => sprintf('GL-%s-%04d', now()->format('ymd-His'), random_int(0, 9999));
        $row = fn (array $x) => GateLog::create($x + [
            'event_id' => $eid(), 'crossing_id' => $n2->crossing_id, 'device_id' => $n2->id, 'logged_at' => now(),
        ]);
        $countdown = (int) Setting::get('countdown_seconds');

        if ($this->option('train') || $this->option('respond') || $this->option('obstacle') || $this->option('cancel')) {
            $log = $row(['trigger_type' => 'AUTOMATIC', 'gate_action' => 'PENDING', 'confidence_score' => 93.4,
                'notes' => 'Getaran kereta terdeteksi, menunggu petugas (simulasi)']);
            $this->info("Kereta terdeteksi. Lihat kotak peringatan di dashboard ($countdown detik).");

            if ($this->option('cancel')) {
                $wait(6);
                $log->update(['gate_action' => 'CANCELLED', 'notes' => 'Getaran kereta berhenti sebelum waktu habis, penutupan dibatalkan (simulasi)']);
                $this->info('Dibatalkan: palang tidak ditutup.');
                return self::SUCCESS;
            }

            if ($this->option('respond')) {
                $wait(6);
                $log->update(['trigger_type' => 'MANUAL', 'gate_action' => 'CLOSED', 'guard_response' => 'CONFIRMED',
                    'response_time_ms' => 6000, 'notes' => 'Ditutup petugas saat hitung mundur (simulasi)']);
                $this->info('Petugas menutup palang.');
                return self::SUCCESS;
            }

            $wait($countdown);
            if ($this->option('obstacle')) {
                $log->update(['trigger_type' => 'AUTOMATIC', 'gate_action' => 'HELD', 'guard_response' => 'NO_RESPONSE',
                    'obstacle_detected' => true, 'notes' => 'Rel terhalang, palang ditahan terbuka (simulasi)']);
                $this->info('Rel terhalang. Peringatan tampil 12 detik, lalu rintangan dianggap hilang.');
                $wait(12);
                $row(['trigger_type' => 'AUTOMATIC', 'gate_action' => 'CLOSED', 'confidence_score' => 93.4, 'obstacle_detected' => false,
                    'notes' => 'Rintangan hilang, palang ditutup otomatis (simulasi)']);
                return self::SUCCESS;
            }
            $log->update(['trigger_type' => 'AUTOMATIC', 'gate_action' => 'CLOSED', 'guard_response' => 'NO_RESPONSE',
                'obstacle_detected' => false, 'notes' => 'Petugas tidak merespons, ditutup otomatis (simulasi)']);
            $this->info('Palang ditutup otomatis.');
            $wait((int) Setting::get('gate_reopen_seconds'));
            $row(['trigger_type' => 'AUTOMATIC', 'gate_action' => 'OPENED', 'notes' => 'Dibuka otomatis setelah kereta lewat (simulasi)']);
            return self::SUCCESS;
        }

        if ($this->option('manual')) {
            $row(['trigger_type' => 'MANUAL', 'gate_action' => 'CLOSED', 'notes' => 'Ditutup manual via Arcade Button (simulasi)']);
            $this->info('Palang ditutup petugas. Dibuka lagi dalam 8 detik.');
            $wait(8);
            $row(['trigger_type' => 'MANUAL', 'gate_action' => 'OPENED', 'notes' => 'Dibuka manual via Arcade Button (simulasi)']);
            return self::SUCCESS;
        }

        $this->info('Mengirim heartbeat tiap 5 detik. Tekan Ctrl+C untuk berhenti.');
        while (true) { $beat(); sleep(5); }
    }
}
