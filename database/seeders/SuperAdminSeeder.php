<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('almara.super_admin');
        $email = strtolower($cfg['email']);

        if (User::where('email', $email)->exists()) {
            $this->command?->info("Super Admin {$email} sudah ada — dilewati.");

            return;
        }

        $password = $cfg['password'] ?: Str::password(16, symbols: false);
        $user = new User(['name' => $cfg['name'], 'email' => $email, 'password' => $password]);
        $user->is_super_admin = true;
        $user->is_active = true;
        $user->email_verified_at = now();
        $user->save();
        $user->roles()->sync([Role::whereNull('tenant_id')->where('slug', 'super_admin')->value('id')]);

        $this->command?->warn('Super Admin dibuat: '.$email);
        if (! $cfg['password']) {
            $this->command?->warn('Password sementara (simpan & segera ganti): '.$password);
        }
    }
}
