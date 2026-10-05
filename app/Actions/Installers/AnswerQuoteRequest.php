<?php

namespace App\Actions\Installers;

use App\Domain\Installers\QuoteNotPossible;
use App\Domain\Installers\QuoteRequestStatus;
use App\Models\QuoteRequest;

/**
 * Use case: the installer says how a request went (ADR-0023). A won deal carries the value it closed
 * for; no commission is computed from it, because ADR-0005 has no percentage yet.
 */
final class AnswerQuoteRequest
{
    public function __invoke(QuoteRequest $quoteRequest, string $status, ?float $contractValueCop = null): QuoteRequest
    {
        if (! array_key_exists($status, QuoteRequestStatus::answers())) {
            throw QuoteNotPossible::unknownAnswer();
        }

        $won = QuoteRequestStatus::requiresContractValue($status);

        if ($won && ($contractValueCop === null || $contractValueCop <= 0)) {
            throw QuoteNotPossible::withoutContractValue();
        }

        $quoteRequest->forceFill([
            'status' => $status,
            // A deal that stops being won keeps no value: it would read as money that came in.
            'contract_value_cop' => $won ? $contractValueCop : null,
            'answered_at' => now(),
        ])->save();

        return $quoteRequest;
    }
}
