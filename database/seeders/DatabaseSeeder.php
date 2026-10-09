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
            // Example allied installers, not a real network (ADR-0022).
            InstallerSeeder::class,
            // Una cuenta por instalador, para entrar a cualquier bandeja (ADR-0023).
            InstallerAccountSeeder::class,
            // Lo que ya les pidieron y lo que respondieron: varios precios por proyecto, que es lo
            // que el comparador necesita para tener algo que comparar (ADR-0028, ADR-0029).
            InstallerQuoteSeeder::class,
        ]);
    }
}
