<?php

namespace App\Http\Controllers;

use App\Actions\Installers\AnswerQuoteRequest;
use App\Actions\Installers\DescribeInstallerInbox;
use App\Actions\Installers\DescribeQuoteRequest;
use App\Actions\Installers\DescribeReferencePrices;
use App\Domain\Installers\QuoteNotPossible;
use App\Models\QuoteRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What the installer sees (ADR-0023): the quote requests their company received, with the estimate
 * the client already calculated, and the answer they give to each one.
 */
class InstallerInboxController extends Controller
{
    public function index(Request $request, DescribeInstallerInbox $describeInstallerInbox): View
    {
        $installer = $request->user()->installer;

        return view('installers.inbox', [
            'installer' => $installer,
            ...$describeInstallerInbox($installer),
        ]);
    }

    /** The prices the app quotes with (ADR-0023): where the client's reference budget comes from. */
    public function prices(Request $request, DescribeReferencePrices $describeReferencePrices): View
    {
        return view('installers.reference-prices', $describeReferencePrices($request->user()->installer));
    }

    public function show(Request $request, QuoteRequest $quoteRequest, DescribeQuoteRequest $describeQuoteRequest): View
    {
        $this->belongsToTheirCompany($request, $quoteRequest);

        return view('installers.quote-request', $describeQuoteRequest($quoteRequest));
    }

    public function update(Request $request, QuoteRequest $quoteRequest, AnswerQuoteRequest $answerQuoteRequest): RedirectResponse
    {
        $this->belongsToTheirCompany($request, $quoteRequest);

        $validated = $request->validate([
            'status' => ['required', 'string'],
            'contract_value_cop' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ]);

        try {
            $answerQuoteRequest(
                $quoteRequest,
                $validated['status'],
                isset($validated['contract_value_cop']) ? (float) $validated['contract_value_cop'] : null,
            );
        } catch (QuoteNotPossible $exception) {
            return back()->withErrors(['quote_request' => $exception->getMessage()]);
        }

        return redirect()
            ->route('installer-inbox.show', $quoteRequest)
            ->with('status', 'Se actualizó la solicitud de '.$quoteRequest->solarProject->name.'.');
    }

    /** One installer never reads or answers for another, whatever id arrives in the URL. */
    private function belongsToTheirCompany(Request $request, QuoteRequest $quoteRequest): void
    {
        abort_unless($quoteRequest->installer_id === $request->user()->installer_id, 403);
    }
}
