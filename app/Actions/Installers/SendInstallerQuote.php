<?php

namespace App\Actions\Installers;

use App\Domain\Installers\QuoteNotPossible;
use App\Domain\Installers\QuoteRequestStatus;
use App\Models\InstallerQuote;
use App\Models\QuoteRequest;

/**
 * Use case: the installer answers a request with their price (ADR-0026).
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
                'power_kw' => $data['power_kw'] ?? null,
                'includes_battery' => (bool) ($data['includes_battery'] ?? false),
                'scope' => $data['scope'] ?? null,
                'valid_until' => $data['valid_until'],
            ],
        );

        $quoteRequest->forceFill([
            'status' => QuoteRequestStatus::afterQuoting($quoteRequest->status),
            'answered_at' => now(),
        ])->save();

        return $quote;
    }
}
