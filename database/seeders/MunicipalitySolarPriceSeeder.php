<?php

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;

class MunicipalitySolarPriceSeeder extends Seeder
{
    public function run(): void
    {
        $prices = [
            ['Riohacha', 'Base urbana', 'urbana', 4000000, 1.00, 3800000, 4500000],
            ['Riohacha', 'Riohacha rural', 'rural', 4000000, 1.10, 4000000, 5000000],
            ['Maicao', 'Base urbana', 'urbana', 4000000, 1.00, 3800000, 4500000],
            ['Albania', 'Base urbana/media', 'urbana', 4100000, 1.03, 3900000, 4600000],
            ['Hatonuevo', 'Media Guajira', 'urbana', 4200000, 1.05, 4000000, 4700000],
            ['Barrancas', 'Media Guajira', 'urbana', 4200000, 1.05, 4000000, 4700000],
            ['Fonseca', 'Sur de La Guajira', 'urbana', 4200000, 1.05, 4000000, 4700000],
            ['Distracción', 'Sur de La Guajira', 'urbana', 4250000, 1.06, 4000000, 4800000],
            ['San Juan del Cesar', 'Sur de La Guajira', 'urbana', 4250000, 1.06, 4000000, 4800000],
            ['El Molino', 'Sur de La Guajira', 'urbana', 4300000, 1.08, 4100000, 4900000],
            ['Villanueva', 'Sur de La Guajira', 'urbana', 4300000, 1.08, 4100000, 4900000],
            ['Urumita', 'Sur de La Guajira', 'urbana', 4350000, 1.09, 4100000, 5000000],
            ['La Jagua del Pilar', 'Sur extremo', 'urbana', 4400000, 1.10, 4200000, 5000000],
            ['Dibulla', 'Costa occidental', 'urbana', 4300000, 1.08, 4100000, 4900000],
            ['Manaure', 'Norte medio', 'urbana', 4500000, 1.13, 4300000, 5200000],
            ['Manaure', 'Manaure rural', 'rural', 4500000, 1.18, 4500000, 5400000],
            ['Uribia', 'Alta Guajira urbana', 'urbana', 4700000, 1.18, 4500000, 5500000],
            ['Uribia', 'Uribia rural', 'rural', 4700000, 1.22, 4800000, 5700000],
            ['Uribia', 'Alta Guajira rural dispersa', 'alta_guajira', 5200000, 1.30, 5200000, 6000000],
        ];

        foreach ($prices as [$municipalityName, $zoneName, $locationType, $base, $factor, $min, $max]) {
            $municipality = Municipality::query()
                ->where('department', 'La Guajira')
                ->where('name', $municipalityName)
                ->firstOrFail();

            $municipality->solarPrices()->updateOrCreate(
                ['zone_name' => $zoneName, 'location_type' => $locationType],
                [
                    'base_price_per_kw' => $base,
                    'logistic_factor' => $factor,
                    'min_price_per_kw' => $min,
                    'max_price_per_kw' => $max,
                    'notes' => 'Precio preliminar por kW instalado para cotizaciones solares en La Guajira.',
                    'active' => true,
                ],
            );
        }
    }
}
