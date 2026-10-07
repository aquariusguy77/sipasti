<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun demonstrasi, satu untuk setiap peran (NFR2).
 *
 * Kata sandi seluruh akun adalah "password". Akun ini hanya untuk purwarupa
 * dan tidak boleh dipakai pada lingkungan yang memuat data sebenarnya.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('sipasti.roles') as $role => $label) {
            User::updateOrCreate(
                ['email' => "{$role}@sipasti.test"],
                ['name' => 'Akun Demo '.ucfirst($role), 'role' => $role, 'active' => true, 'password' => 'password'],
            );
        }
    }
}
