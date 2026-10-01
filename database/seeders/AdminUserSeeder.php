<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@solar-app.test'],
            [
                'name' => 'Administrador',
                'username' => 'admin',
                'password' => '12345',
                'role' => 'admin',
            ],
        );

        // Project routes require a verified email; development accounts start verified.
        $admin->forceFill(['email_verified_at' => $admin->email_verified_at ?? now()])->save();
    }
}
