<?php

namespace App\Actions\Installers;

use App\Domain\Installers\QuoteNotPossible;
use App\Domain\Installers\QuoteRequestStatus;
use App\Domain\Installers\QuoteSystem;
use App\Models\InstallerQuote;
use App\Models\QuoteRequest;

/**
 * Use case: the installer answers a request with their quote (ADR-0026, ADR-0027).
 *
 * One quote per request: sending it again corrects the one before, with its new date. The request
 * moves to `quoted` only while it is still open, so editing the figure of a deal already won or
 * lost does not drag it back to waiting.
 */
final class SendInstallerQuote
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(QuoteRequest $quoteRequest, array $data): InstallerQuote
    {
        if ((float) $data['amount_cop'] <= 0) {
            throw QuoteNotPossible::withoutAmount();
        }

        $quote = InstallerQuote::query()->updateOrCreate(
            ['quote_request_id' => $quoteRequest->id],
            [
                'amount_cop' => $data['amount_cop'],
                // The panels and the power are the same number twice: whichever they wrote is enough.
                'power_kw' => QuoteSystem::powerKw(
                    $this->float($data, 'power_kw'),
                    $this->int($data, 'panel_count'),
                    $this->int($data, 'panel_watts'),
                ),
                'panel_count' => $this->int($data, 'panel_count'),
                'panel_watts' => $this->int($data, 'panel_watts'),
                'panel_model' => $this->text($data, 'panel_model'),
                'inverter_model' => $this->text($data, 'inverter_model'),
                'includes_battery' => (bool) ($data['includes_battery'] ?? false),
                'battery_kwh' => $this->float($data, 'battery_kwh'),
                'monthly_generation_kwh' => $this->float($data, 'monthly_generation_kwh'),
                'includes_retie' => (bool) ($data['includes_retie'] ?? false),
                'includes_grid_paperwork' => (bool) ($data['includes_grid_paperwork'] ?? false),
                'includes_bidirectional_meter' => (bool) ($data['includes_bidirectional_meter'] ?? false),
                'includes_maintenance' => (bool) ($data['includes_maintenance'] ?? false),
                'panel_warranty_years' => $this->int($data, 'panel_warranty_years'),
                'inverter_warranty_years' => $this->int($data, 'inverter_warranty_years'),
                'workmanship_warranty_years' => $this->int($data, 'workmanship_warranty_years'),
                // Null while they do not say: equipment for non-conventional sources can be
                // VAT-excluded, so neither answer can be assumed.
                'vat_included' => isset($data['vat_included']) && $data['vat_included'] !== ''
                    ? (bool) $data['vat_included']
                    : null,
                'down_payment_percentage' => $this->int($data, 'down_payment_percentage'),
                'delivery_days' => $this->int($data, 'delivery_days'),
                'scope' => $this->text($data, 'scope'),
                'exclusions' => $this->text($data, 'exclusions'),
                'valid_until' => $data['valid_until'],
            ],
        );

        $quoteRequest->forceFill([
            'status' => QuoteRequestStatus::afterQuoting($quoteRequest->status),
            'answered_at' => now(),
        ])->save();

        return $quote;
    }

    /**
     * An empty field clears what was there: correcting a quote also means taking something out.
     *
     * @param  array<string, mixed>  $data
     */
    private function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function float(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;

        return $value === null || $value === '' ? null : (float) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function text(array $data, string $key): ?string
    {
        $value = trim((string) ($data[$key] ?? ''));

        return $value === '' ? null : $value;
    }
}
