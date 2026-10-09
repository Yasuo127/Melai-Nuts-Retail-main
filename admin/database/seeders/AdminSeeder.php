<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Supabase\StaffLinker;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $cfg = config('melai.admin');

        $admin = User::firstOrNew(['email' => strtolower($cfg['email'])]);
        $admin->forceFill([
            'name' => $cfg['name'],
            'password' => $admin->exists ? $admin->password : $cfg['password'],
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'has_access' => true,
        ])->save();

        // Optional: link the first admin to the owner's account in the app (Supabase mode).
        if (config('melai.data_source') === 'supabase' && filled($cfg['staff_uid']) && $admin->staff_uid !== $cfg['staff_uid']) {
            $profile = app(StaffLinker::class)->link($admin, $cfg['staff_uid']);
            $this->command?->info("Linked {$admin->email} to app owner \"{$profile['full_name']}\".");
        }
    }
}
