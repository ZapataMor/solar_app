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
        User::updateOrCreate(
            ['email' => 'cliente@solar-app.test'],
            [
                'name' => 'Cliente',
                'username' => 'cliente',
                'password' => '12345',
                'role' => 'user',
            ],
        );
    }
}
