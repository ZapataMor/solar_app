<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            RegularUserSeeder::class,
            LaGuajiraMunicipalitySeeder::class,
            MunicipalitySolarPriceSeeder::class,
            // Before the projects: they follow the reference tariff (ADR-0015).
            ReferenceValueSeeder::class,
            UserSolarProjectSeeder::class,
            // Example allied installers, not a real network (ADR-0021).
            InstallerSeeder::class,
        ]);
    }
}
