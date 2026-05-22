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
            ['email' => 'user@solar-app.test'],
            [
                'name' => 'Usuario',
                'password' => 'password',
                'role' => 'user',
            ],
        );
    }
}
