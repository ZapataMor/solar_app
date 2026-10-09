<?php

namespace Database\Seeders;

use App\Actions\Installers\SendInstallerQuote;
use App\Domain\Installers\QuoteRequestStatus;
use App\Models\Installer;
use App\Models\QuoteRequest;
use App\Models\SolarProject;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Las solicitudes y las cotizaciones de la demo (ADR-0022, ADR-0026, ADR-0027).
 *
 * Dos proyectos del cliente llegan con **varias** cotizaciones, que es lo que hace falta para que
 * el comparador y la recomendación (ADR-0028, ADR-0029) tengan algo que decir: una barata que deja
 * la legalización afuera, una completa con baterías y una intermedia ya vencida. El tercero se
 * queda con una sola, porque ese caso también existe y la app tiene que resolverlo sin pantalla de
 * comparación.
 *
 * Los precios no son cifras sueltas: salen del presupuesto de referencia de cada proyecto
 * (ADR-0024) con un factor, así que siguen teniendo sentido si el proyecto cambia de tamaño o si
 * cambian los precios por municipio. Cada aliado solo cotiza municipios que cubre, como exige el
 * ADR-0022.
 *
 * Corre después de InstallerSeeder, InstallerAccountSeeder y UserSolarProjectSeeder.
 */
class InstallerQuoteSeeder extends Seeder
{
    /** Si un proyecto todavía no tiene presupuesto calculado, con esto los precios siguen siendo creíbles. */
    private const FALLBACK_COST_COP = 18_000_000.0;

    public function run(): void
    {
        $client = User::query()->where('username', 'cliente')->first();

        if ($client === null) {
            return;
        }

        $send = app(SendInstallerQuote::class);

        foreach ($this->plan() as $projectName => $rows) {
            $project = SolarProject::query()
                ->where('user_id', $client->id)
                ->where('name', $projectName)
                ->first();

            if ($project === null) {
                continue;
            }

            foreach ($rows as $row) {
                $installer = Installer::query()->where('name', $row['installer'])->first();

                if ($installer === null) {
                    continue;
                }

                $request = $this->request($project, $installer, $row);

                if (! isset($row['quote'])) {
                    continue;
                }

                $send($request, $this->quote($project, $row));

                // SendInstallerQuote mueve la solicitud a *cotizada*: el estado y las fechas de la
                // demo se reponen después para que cada tarjeta se lea en orden.
                $request->forceFill([
                    'status' => $row['status'],
                    'answered_at' => now()->subDays($row['answered']),
                    // El contrato sale del precio que cotizó, con lo que se suele rebajar al
                    // cerrar: una cifra fija se contradiría con la cotización que el cliente ve.
                    'contract_value_cop' => $row['status'] === QuoteRequestStatus::WON
                        ? round((float) $request->installerQuote->amount_cop * 0.97, -3)
                        : null,
                ])->save();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function request(SolarProject $project, Installer $installer, array $row): QuoteRequest
    {
        $request = QuoteRequest::query()->updateOrCreate(
            ['solar_project_id' => $project->id, 'installer_id' => $installer->id],
            [
                'status' => $row['status'],
                'contract_value_cop' => null,
                'note' => $row['note'] ?? null,
                // Congelado al pedirla (ADR-0024), igual que lo haría RequestInstallerQuote.
                'quoted_cost_cop' => $project->estimated_installation_cost,
                'quoted_price_per_kw_cop' => $project->final_price_per_kw_used,
            ],
        );

        $request->forceFill(['created_at' => now()->subDays($row['asked'])])->save();

        return $request;
    }

    /**
     * El cuerpo de la cotización, con el tamaño del proyecto detrás: el precio sale del presupuesto
     * de referencia por un factor, y los paneles de la potencia que pide el consumo.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function quote(SolarProject $project, array $row): array
    {
        $profile = $row['quote'];
        $reference = (float) $project->estimated_installation_cost ?: self::FALLBACK_COST_COP;
        $powerKw = round(((float) $project->required_power_kw ?: 5.5) * $profile['power'], 2);
        $panelWatts = $profile['panelWatts'];

        return [
            // A los miles, como escribiría el precio un instalador.
            'amount_cop' => round($reference * $profile['price'], -3),
            'panel_count' => max(1, (int) round($powerKw * 1000 / $panelWatts)),
            'panel_watts' => $panelWatts,
            'panel_model' => $profile['panelModel'],
            'inverter_model' => $profile['inverterModel'],
            'monthly_generation_kwh' => round($project->monthlyConsumption() * $profile['coverage']),
            'includes_battery' => $profile['battery'],
            'battery_kwh' => $profile['battery'] ? $profile['batteryKwh'] : null,
            'includes_retie' => $profile['retie'],
            'includes_grid_paperwork' => $profile['paperwork'],
            'includes_bidirectional_meter' => $profile['meter'],
            'includes_maintenance' => $profile['maintenance'],
            'panel_warranty_years' => $profile['panelWarranty'],
            'inverter_warranty_years' => $profile['inverterWarranty'],
            'workmanship_warranty_years' => $profile['workmanshipWarranty'],
            'vat_included' => $profile['vat'],
            'down_payment_percentage' => $profile['downPayment'],
            'delivery_days' => $profile['deliveryDays'],
            'scope' => $profile['scope'],
            'exclusions' => $profile['exclusions'],
            // Negativo = ya venció. Se manda así a propósito: el formulario no deja escribir una
            // fecha pasada, pero el tiempo sí la deja atrás, y la demo necesita ese caso.
            'valid_until' => now()->addDays($profile['validDays'])->format('Y-m-d'),
        ];
    }

    /**
     * Qué pidió el cliente y quién respondió qué. Cada aliado aparece solo en proyectos de los
     * municipios que cubre (ADR-0022): Riohacha es de Sol de Riohacha, Caribe y Guajira Renovable;
     * Maicao de Energía Wayúu, Caribe y Guajira Renovable; Uribia de Energía Wayúu y Guajira
     * Renovable.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function plan(): array
    {
        return [
            // Tres precios para la misma casa: el caso que estrena el comparador (ADR-0028).
            'Vivienda familiar Riohacha' => [
                [
                    'installer' => 'Sol de Riohacha',
                    'status' => QuoteRequestStatus::QUOTED,
                    'asked' => 12,
                    'answered' => 10,
                    'note' => 'Queremos saber si el techo aguanta sin refuerzo.',
                    'quote' => $this->cheapAndShort(),
                ],
                [
                    'installer' => 'Caribe Solar Ingeniería',
                    'status' => QuoteRequestStatus::QUOTED,
                    'asked' => 12,
                    'answered' => 8,
                    'quote' => $this->completeWithBatteries(),
                ],
                [
                    'installer' => 'Guajira Renovable',
                    'status' => QuoteRequestStatus::QUOTED,
                    'asked' => 40,
                    'answered' => 38,
                    'quote' => $this->expired(),
                ],
            ],

            // El negocio ya cerró con uno, y conserva las otras para comparar lo que pagó.
            'Local comercial centro' => [
                [
                    'installer' => 'Energía Wayúu',
                    'status' => QuoteRequestStatus::WON,
                    'asked' => 20,
                    'answered' => 12,
                    'note' => 'Nos urge por el aire del local.',
                    'quote' => $this->closedDeal(),
                ],
                [
                    'installer' => 'Caribe Solar Ingeniería',
                    'status' => QuoteRequestStatus::QUOTED,
                    'asked' => 20,
                    'answered' => 14,
                    'quote' => $this->engineered(),
                ],
                [
                    'installer' => 'Guajira Renovable',
                    'status' => QuoteRequestStatus::SENT,
                    'asked' => 3,
                ],
            ],

            // Una sola cotización y otra sin responder: sin dos no hay comparación (ADR-0028), y la
            // bandeja del instalador necesita una solicitud pendiente (ADR-0023).
            'Institución educativa rural' => [
                [
                    'installer' => 'Energía Wayúu',
                    'status' => QuoteRequestStatus::SENT,
                    'asked' => 1,
                    'note' => 'Queremos empezar por las aulas; el comedor puede esperar.',
                ],
                [
                    'installer' => 'Guajira Renovable',
                    'status' => QuoteRequestStatus::QUOTED,
                    'asked' => 9,
                    'answered' => 6,
                    'quote' => $this->school(),
                ],
            ],
        ];
    }

    /**
     * La más barata, y la que deja afuera lo que legaliza la instalación: el caso que el comparador
     * existe para destapar.
     *
     * @return array<string, mixed>
     */
    private function cheapAndShort(): array
    {
        return [
            'price' => 0.82, 'power' => 1.0, 'coverage' => 0.78,
            'panelWatts' => 550, 'panelModel' => 'Trina Vertex S 550 W', 'inverterModel' => 'Deye SUN-5K-G',
            'battery' => false, 'batteryKwh' => null,
            'retie' => false, 'paperwork' => false, 'meter' => false, 'maintenance' => false,
            'panelWarranty' => null, 'inverterWarranty' => null, 'workmanshipWarranty' => null,
            'vat' => false, 'downPayment' => 60, 'deliveryDays' => 25,
            'scope' => 'Paneles, estructura, inversor, cableado y mano de obra.',
            'exclusions' => null,
            'validDays' => 18,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function completeWithBatteries(): array
    {
        return [
            'price' => 1.28, 'power' => 1.2, 'coverage' => 0.96,
            'panelWatts' => 575, 'panelModel' => 'Jinko Tiger Neo 575 W', 'inverterModel' => 'Growatt SPH 6000 híbrido',
            'battery' => true, 'batteryKwh' => 9.6,
            'retie' => true, 'paperwork' => true, 'meter' => true, 'maintenance' => true,
            'panelWarranty' => 25, 'inverterWarranty' => 10, 'workmanshipWarranty' => 3,
            'vat' => true, 'downPayment' => 40, 'deliveryDays' => 45,
            'scope' => 'Sistema híbrido con respaldo de baterías, legalización completa ante el operador de red, medidor bidireccional y puesta en marcha.',
            'exclusions' => 'Obra civil y refuerzo estructural del techo si la cubierta lo necesita.',
            'validDays' => 45,
        ];
    }

    /**
     * Intermedia y con el precio vencido: se sigue leyendo, pero no compite ni se recomienda
     * (ADR-0026, ADR-0029).
     *
     * @return array<string, mixed>
     */
    private function expired(): array
    {
        return [
            'price' => 1.04, 'power' => 1.0, 'coverage' => 0.88,
            'panelWatts' => 550, 'panelModel' => 'Canadian Solar 550 W', 'inverterModel' => 'Huawei SUN2000-5KTL',
            'battery' => false, 'batteryKwh' => null,
            'retie' => true, 'paperwork' => true, 'meter' => false, 'maintenance' => true,
            'panelWarranty' => 12, 'inverterWarranty' => 5, 'workmanshipWarranty' => 1,
            'vat' => true, 'downPayment' => 50, 'deliveryDays' => 40,
            'scope' => 'Paneles, inversor, estructura, protecciones, RETIE y trámite de conexión.',
            'exclusions' => 'Medidor bidireccional.',
            'validDays' => -4,
        ];
    }

    /**
     * La que ganó el negocio del local, con el detalle de una cotización real (ADR-0027).
     *
     * @return array<string, mixed>
     */
    private function closedDeal(): array
    {
        return [
            'price' => 0.98, 'power' => 1.0, 'coverage' => 0.92,
            'panelWatts' => 575, 'panelModel' => 'Jinko Tiger Neo 575 W', 'inverterModel' => 'Growatt MOD 9000TL3-X',
            'battery' => false, 'batteryKwh' => null,
            'retie' => true, 'paperwork' => true, 'meter' => false, 'maintenance' => true,
            'panelWarranty' => 25, 'inverterWarranty' => 10, 'workmanshipWarranty' => 2,
            'vat' => true, 'downPayment' => 40, 'deliveryDays' => 45,
            'scope' => 'Paneles, inversor, estructura, cableado, protecciones, mano de obra, certificación RETIE y trámite con la electrificadora.',
            'exclusions' => 'Medidor bidireccional, obra civil y refuerzo del techo si la cubierta lo necesita.',
            'validDays' => 21,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function engineered(): array
    {
        return [
            'price' => 1.14, 'power' => 1.08, 'coverage' => 0.99,
            'panelWatts' => 600, 'panelModel' => 'Longi Hi-MO 6 600 W', 'inverterModel' => 'Fronius Symo 10.0-3-M',
            'battery' => false, 'batteryKwh' => null,
            'retie' => true, 'paperwork' => true, 'meter' => true, 'maintenance' => true,
            'panelWarranty' => 25, 'inverterWarranty' => 12, 'workmanshipWarranty' => 2,
            'vat' => true, 'downPayment' => 50, 'deliveryDays' => 60,
            'scope' => 'Diseño eléctrico firmado, paneles, inversor trifásico, legalización, medidor bidireccional y mantenimiento del primer año.',
            'exclusions' => 'Adecuaciones del tablero general si no cumple RETIE.',
            'validDays' => 30,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function school(): array
    {
        return [
            'price' => 0.95, 'power' => 1.0, 'coverage' => 0.9,
            'panelWatts' => 550, 'panelModel' => 'Trina Vertex S 550 W', 'inverterModel' => 'Sungrow SG10RT',
            'battery' => false, 'batteryKwh' => null,
            'retie' => true, 'paperwork' => true, 'meter' => false, 'maintenance' => false,
            'panelWarranty' => 20, 'inverterWarranty' => 8, 'workmanshipWarranty' => null,
            'vat' => null, 'downPayment' => 45, 'deliveryDays' => 50,
            'scope' => 'Paneles, estructura para cubierta de zinc, inversor, protecciones y trámite ante el operador de red.',
            'exclusions' => null,
            'validDays' => 28,
        ];
    }
}
