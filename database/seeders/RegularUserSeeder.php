<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RegularUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $client = User::updateOrCreate(
            ['email' => 'cliente@solar-app.test'],
            [
                'name' => 'Cliente',
                'username' => 'cliente',
                'password' => '12345',
                'role' => 'user',
            ],
        );

        // Project routes require a verified email; development accounts start verified.
        $client->forceFill(['email_verified_at' => $client->email_verified_at ?? now()])->save();
    }
}
