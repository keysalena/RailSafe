<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccountSeeder::class,    // 1. Akun demo (superadmin & operator)
            CrossingSeeder::class,   // 2. Pos, perangkat, sensor, & pengaturan default
            SimulationSeeder::class, // 3. Riwayat log palang 7 hari (data simulasi)
        ]);
    }
}

