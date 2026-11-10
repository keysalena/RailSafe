<?php

namespace Database\Seeders;

use App\Models\Crossing;
use App\Models\Device;
use App\Models\GateLog;
use Illuminate\Database\Seeder;

/**
 * Mengisi riwayat log palang selama 7 hari terakhir dengan pola acak
 * yang menyerupai log nyata dari Node2.ino (78% otomatis, 22% manual).
 *
 * Prasyarat: CrossingSeeder sudah dijalankan (pos & perangkat harus ada).
 */
class SimulationSeeder extends Seeder
{
    public function run(): void
    {
        $crossing = Crossing::where('code', 'PJL-MLG-01')->firstOrFail();
        $n2       = Device::where('device_code', 'NODE-02')->firstOrFail();

        mt_srand(42); // seed tetap agar hasilnya reproducible
        $seq = 1;

        $add = function ($at, $trigger, $action, $conf, $notes) use ($crossing, $n2, &$seq) {
            GateLog::create([
                'event_id'        => sprintf('GL-%s-%03d', $at->format('ymd'), $seq++),
                'crossing_id'     => $crossing->id,
                'device_id'       => $n2->id,
                'trigger_type'    => $trigger,
                'gate_action'     => $action,
                'confidence_score'=> $conf,
                'notes'           => $notes,
                'logged_at'       => $at,
            ]);
        };

        // Buat log untuk 7 hari (hari ini + 6 hari ke belakang)
        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $day   = now()->startOfDay()->subDays($daysAgo);
            $count = $daysAgo === 0 ? 8 : mt_rand(6, 12); // hari ini sedikit lebih sedikit

            for ($i = 0; $i < $count; $i++) {
                // Distribusikan kejadian antara jam 05.00–19.00
                $at = $day->copy()->addMinutes(5 * 60 + (int) ($i * (14 * 60) / $count) + mt_rand(0, 30));

                if ($at->isFuture()) {
                    continue;
                }

                if (mt_rand(1, 100) <= 78) {
                    // 78% otomatis (TinyML mendeteksi kereta)
                    $add($at, 'AUTOMATIC', 'CLOSED', mt_rand(720, 990) / 10, 'Kereta terdeteksi TinyML');
                } else {
                    // 22% manual (operator menutup dan membuka kembali)
                    $add($at, 'MANUAL', 'CLOSED', null, 'Ditutup manual via Arcade Button');

                    $openAt = $at->copy()->addMinutes(mt_rand(1, 3));
                    if (! $openAt->isFuture()) {
                        $add($openAt, 'MANUAL', 'OPENED', null, 'Dibuka manual via Arcade Button');
                    }
                }
            }
        }
    }
}

