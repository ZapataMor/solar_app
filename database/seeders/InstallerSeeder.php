<?php

namespace Database\Seeders;

use App\Models\Installer;
use App\Models\Municipality;
use Illuminate\Database\Seeder;

/**
 * Example installers for the first stage (ADR-0021). They are NOT a real network: the directory says
 * so with a visible notice, the phones are placeholders and the domains are reserved for examples.
 * Delete them the day real allies sign up.
 */
class InstallerSeeder extends Seeder
{
    public function run(): void
    {
        $installers = [
            [
                'name' => 'Sol de Riohacha',
                'tagline' => 'Instalación residencial y de pequeños negocios',
                'description' => 'Equipo de la capital. Trabajan sobre techo de losa y de zinc, y hacen el trámite de conexión con la electrificadora.',
                'contact_name' => 'Oficina comercial',
                'phone' => '300 000 0001',
                'email' => 'contacto@sol-de-riohacha.example.com',
                'years_experience' => 8,
                'municipalities' => ['Riohacha', 'Dibulla', 'Albania'],
            ],
            [
                'name' => 'Energía Wayúu',
                'tagline' => 'Sistemas aislados para la Alta Guajira',
                'description' => 'Especialistas en lugares sin red: bombeo de agua, alumbrado comunitario y sistemas con batería.',
                'contact_name' => 'Coordinación de proyectos',
                'phone' => '300 000 0002',
                'email' => 'proyectos@energia-wayuu.example.com',
                'years_experience' => 5,
                'municipalities' => ['Uribia', 'Manaure', 'Maicao'],
            ],
            [
                'name' => 'Caribe Solar Ingeniería',
                'tagline' => 'Proyectos comerciales e industriales',
                'description' => 'Para consumos grandes: hoteles, supermercados y bodegas. Entregan diseño eléctrico firmado y mantenimiento anual.',
                'contact_name' => 'Dirección técnica',
                'phone' => '300 000 0003',
                'email' => 'ingenieria@caribe-solar.example.com',
                'years_experience' => 12,
                'municipalities' => ['Riohacha', 'Maicao', 'Albania', 'Hatonuevo', 'Barrancas'],
            ],
            [
                'name' => 'Soluciones FV del Sur',
                'tagline' => 'Cobertura del sur de La Guajira',
                'description' => 'Atienden fincas y viviendas del sur del departamento. Financian la instalación hasta en 24 meses.',
                'contact_name' => 'Atención al cliente',
                'phone' => '300 000 0004',
                'email' => 'hola@fv-del-sur.example.com',
                'years_experience' => 6,
                'municipalities' => [
                    'San Juan del Cesar', 'Fonseca', 'Distracción', 'Villanueva',
                    'El Molino', 'Urumita', 'La Jagua del Pilar',
                ],
            ],
            [
                'name' => 'Guajira Renovable',
                'tagline' => 'Cubren todo el departamento',
                'description' => 'Red de técnicos en los quince municipios. Hacen la visita técnica sin costo antes de cotizar.',
                'contact_name' => 'Línea de atención',
                'phone' => '300 000 0005',
                'email' => 'citas@guajira-renovable.example.com',
                'years_experience' => 3,
                'municipalities' => null,
            ],
        ];

        $municipalityIds = Municipality::query()->active()->pluck('id', 'name');

        foreach ($installers as $data) {
            $names = $data['municipalities'];
            unset($data['municipalities']);

            $installer = Installer::query()->updateOrCreate(
                ['name' => $data['name']],
                [...$data, 'active' => true],
            );

            // null covers every municipality the app offers.
            $installer->municipalities()->sync($names === null
                ? $municipalityIds->values()->all()
                : $municipalityIds->only($names)->values()->all());
        }
    }
}
