<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Membuat akun demo superadmin dan operator.
 * Ganti / hapus akun ini sebelum deploy ke jaringan publik.
 */
class AccountSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name'     => 'Admin RailSafe',
            'email'    => 'superadmin@railsafe.test',
            'password' => 'password',
            'role'     => 'superadmin',
        ]);

        User::create([
            'name'     => 'Budi Santoso',
            'email'    => 'operator@railsafe.test',
            'password' => 'password',
            'role'     => 'operator',
        ]);
    }
}

