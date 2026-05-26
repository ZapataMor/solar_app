<?php

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;

class LaGuajiraMunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        $municipalities = [
            ['name' => 'Riohacha', 'latitude' => 11.5444, 'longitude' => -72.9072, 'zone' => 'Base urbana'],
            ['name' => 'Maicao', 'latitude' => 11.3778, 'longitude' => -72.2389, 'zone' => 'Media Guajira'],
            ['name' => 'Uribia', 'latitude' => 11.7149, 'longitude' => -72.2660, 'zone' => 'Alta Guajira'],
            ['name' => 'Manaure', 'latitude' => 11.7751, 'longitude' => -72.4445, 'zone' => 'Norte medio'],
            ['name' => 'Albania', 'latitude' => 11.1605, 'longitude' => -72.5924, 'zone' => 'Base urbana/media'],
            ['name' => 'Fonseca', 'latitude' => 10.8861, 'longitude' => -72.8487, 'zone' => 'Sur de La Guajira'],
            ['name' => 'Barrancas', 'latitude' => 10.9567, 'longitude' => -72.7922, 'zone' => 'Media Guajira'],
            ['name' => 'Hatonuevo', 'latitude' => 11.0694, 'longitude' => -72.7669, 'zone' => 'Media Guajira'],
            ['name' => 'Dibulla', 'latitude' => 11.2725, 'longitude' => -73.3096, 'zone' => 'Costa occidental'],
            ['name' => 'San Juan del Cesar', 'latitude' => 10.7711, 'longitude' => -73.0031, 'zone' => 'Sur de La Guajira'],
            ['name' => 'Distracción', 'latitude' => 10.8978, 'longitude' => -72.8865, 'zone' => 'Sur de La Guajira'],
            ['name' => 'El Molino', 'latitude' => 10.6536, 'longitude' => -72.9256, 'zone' => 'Sur de La Guajira'],
            ['name' => 'Villanueva', 'latitude' => 10.6053, 'longitude' => -72.9801, 'zone' => 'Sur de La Guajira'],
            ['name' => 'Urumita', 'latitude' => 10.5589, 'longitude' => -73.0128, 'zone' => 'Sur de La Guajira'],
            ['name' => 'La Jagua del Pilar', 'latitude' => 10.5108, 'longitude' => -73.0711, 'zone' => 'Sur extremo'],
        ];

        foreach ($municipalities as $municipality) {
            Municipality::query()->updateOrCreate(
                ['name' => $municipality['name'], 'department' => 'La Guajira'],
                [...$municipality, 'active' => true],
            );
        }
    }
}
