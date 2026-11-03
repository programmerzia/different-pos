<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('cards.title') }}</title>
{{-- A4 10-up: 2 x 5 CR80 cards edge to edge (171.2 x 270 mm), centred: 19.4 mm left/right, 13.5 mm top/bottom. --}}
@php($x0 = 19.4)
@php($y0 = 13.5)
<style>
    @page { size: 210mm 297mm; margin: 0; }
{!! $css !!}
    body { margin: 0; }
    .sheet { position: relative; width: 210mm; height: 297mm; page-break-after: always; break-after: page; overflow: hidden; }
    .sheet:last-child { page-break-after: auto; break-after: auto; }
    .slot { position: absolute; width: 85.6mm; height: 54mm; }
    .slot > .card { --mc-width: 85.6mm; }
    .mark { position: absolute; background: #000000; }
    .mark.v { width: .2mm; height: 8mm; }
    .mark.h { width: 8mm; height: .2mm; }
</style>
</head>
<body>
@foreach ($sheets as $index => $sheet)
    @foreach (['front' => '_front', 'back' => '_back'] as $face => $partial)
        <div class="sheet {{ $face }}" data-sheet="{{ $index + 1 }}">
            @foreach ($sheet[$face] as $row => $pair)
                @foreach ($pair as $col => $card)
                    @if ($card !== null)
                        <div class="slot" style="left: {{ $x0 + $col * 85.6 }}mm; top: {{ $y0 + $row * 54 }}mm">@include('cards.'.$partial, ['card' => $card])</div>
                    @endif
                @endforeach
            @endforeach
            @if ($crop)
                @foreach ([0, 1, 2] as $col)
                    <span class="mark v" style="left: {{ $x0 + $col * 85.6 - 0.1 }}mm; top: {{ $y0 - 10 }}mm"></span>
                    <span class="mark v" style="left: {{ $x0 + $col * 85.6 - 0.1 }}mm; top: {{ $y0 + 270 + 2 }}mm"></span>
                @endforeach
                @foreach ([0, 1, 2, 3, 4, 5] as $row)
                    <span class="mark h" style="top: {{ $y0 + $row * 54 - 0.1 }}mm; left: {{ $x0 - 10 }}mm"></span>
                    <span class="mark h" style="top: {{ $y0 + $row * 54 - 0.1 }}mm; left: {{ $x0 + 171.2 + 2 }}mm"></span>
                @endforeach
            @endif
        </div>
    @endforeach
@endforeach
</body>
</html>
