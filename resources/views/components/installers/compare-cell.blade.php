{{--
    One cell of the comparison table (ADR-0028): the value of one quote in one row, written the way
    its row is written.

    What is missing is as visible as what is there: a cell nobody filled says *No lo dice*, never a
    zero and never a discreet dash. A quote that does not declare warranties is information.

    Props: $cell (value, missing, best) and $format (App\Domain\Installers\QuoteComparison).
--}}
@props(['cell', 'format'])

@php
    use App\Domain\Installers\QuoteComparison;
    use App\Domain\Solar\Profitability;

    $value = $cell['value'];
    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    $decimal = fn (float $amount, int $places = 2): string => number_format($amount, $places, ',', '.');
    $years = fn (int $amount): string => $amount === 1 ? '1 año' : $amount.' años';
@endphp

@if ($cell['missing'])
    <span class="solar-compare__missing">No lo dice</span>
@elseif ($format === QuoteComparison::FLAG)
    @if ($value)
        <span class="solar-compare__flag is-in">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg>
            Sí
        </span>
    @else
        <span class="solar-compare__flag is-out">
            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
            No
        </span>
    @endif
@else
    {{ match ($format) {
        QuoteComparison::MONEY => $money((float) $value),
        QuoteComparison::PAYBACK => Profitability::paybackText((float) $value),
        QuoteComparison::YEARS => $years((int) $value),
        QuoteComparison::PERCENT => $decimal((float) $value, 0).' %',
        QuoteComparison::DAYS => $decimal((float) $value, 0).' días',
        QuoteComparison::DATE => \Illuminate\Support\Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
        QuoteComparison::KW => $decimal((float) $value).' kW',
        QuoteComparison::KWH => $decimal((float) $value, (float) $value < 10 ? 1 : 0).' kWh',
        default => (string) $value,
    } }}
@endif
