{{--
    Cuánto le saca una celda a la mejor de las demás (ADR-0029): el `+13 / −13` del cara a cara.

    En dinero va en millones, porque "+7,6 M" se lee de un vistazo debajo del precio y
    "$7.600.000" no cabe. Las filas que no son números —los checks, las fechas en texto— no
    tienen ventaja que mostrar y el componente no pinta nada.

    Props: $advantage (positivo cuando va ganando) y $format (App\Domain\Installers\QuoteComparison).
--}}
@props(['advantage', 'format'])

@php
    use App\Domain\Installers\QuoteComparison;

    $text = null;

    if ($advantage !== null && abs($advantage) >= 0.005) {
        $sign = $advantage > 0 ? '+' : '−';
        $size = abs($advantage);

        $text = match ($format) {
            QuoteComparison::MONEY => $size >= 1_000_000
                ? $sign.number_format($size / 1_000_000, 1, ',', '.').' M'
                : $sign.number_format(round($size, -3), 0, ',', '.'),
            QuoteComparison::PAYBACK => $sign.number_format($size, 1, ',', '.').' años',
            QuoteComparison::YEARS => $sign.number_format($size, 0, ',', '.').' años',
            QuoteComparison::DAYS, QuoteComparison::DATE => $sign.number_format($size, 0, ',', '.').' días',
            QuoteComparison::PERCENT => $sign.number_format($size, 0, ',', '.').' pts',
            default => null,
        };
    }
@endphp

@if ($text)
    <span @class(['solar-compare__delta', 'is-up' => $advantage > 0, 'is-down' => $advantage < 0])>{{ $text }}</span>
@endif
