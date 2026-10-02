<?php

namespace Database\Seeders;

use App\Actions\ReferenceValues\RecordReferenceValue;
use App\Domain\Reference\ReferenceValueCatalog;
use Illuminate\Database\Seeder;

/**
 * First reference values of the system (ADR-0015). Administrators record the next ones in
 * Administración → Valores de referencia; these stay as the start of the history.
 */
class ReferenceValueSeeder extends Seeder
{
    public function run(RecordReferenceValue $recordReferenceValue): void
    {
        $recordReferenceValue(
            ReferenceValueCatalog::ENERGY_RATE,
            890,
            '2026-08-01',
            'Tarifa de Air-e para La Guajira según la prensa (Infobae, opscolombia), agosto de 2026',
            'Costo unitario sin subsidio ni contribución. Confirmar con las tarifas que publica Air-e.',
            null,
        );

        $recordReferenceValue(
            ReferenceValueCatalog::COMMERCIAL_CONTRIBUTION,
            20,
            '2026-01-01',
            'Ley 142 de 1994 (contribución de los usuarios comerciales)',
            null,
            null,
        );
    }
}
