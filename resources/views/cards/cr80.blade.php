<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('cards.title') }}</title>
{{-- CR80 85.6 x 54 mm; bleed 91.6 x 60 mm (the artwork's edge colour runs 3 mm past the trim);
     bleed + crop marks on a 101.6 x 70 mm page (5 mm slug for the marks). --}}
@php($w = $crop ? '101.6mm' : ($bleed ? '91.6mm' : '85.6mm'))
@php($h = $crop ? '70mm' : ($bleed ? '60mm' : '54mm'))
@php($offset = ($crop ? 5 : 0) + ($bleed ? 3 : 0))
<style>
    @page { size: {{ $w }} {{ $h }}; margin: 0; }
{!! $css !!}
    body { margin: 0; }
    .page { position: relative; width: {{ $w }}; height: {{ $h }}; page-break-after: always; break-after: page; overflow: hidden; }
    .page:last-child { page-break-after: auto; break-after: auto; }
    .page > .card { --mc-width: 85.6mm; position: absolute; left: {{ $offset }}mm; top: {{ $offset }}mm; }
@if ($bleed)
    /* The artwork's background runs 3 mm past the trim on every side. */
    .bleed { position: absolute; left: {{ $offset - 3 }}mm; top: {{ $offset - 3 }}mm; width: 91.6mm; height: 60mm; }
    .page > .card { clip-path: inset(-3mm); }
    .page > .card .mc-face { border-radius: 0; border: 0; background: transparent; overflow: visible; }
@endif
    .mark { position: absolute; background: #000000; }
    .mark.v { width: .2mm; height: 4mm; }
    .mark.h { width: 4mm; height: .2mm; }
</style>
</head>
<body>
@foreach ($cards as $card)
    @foreach (['_front', '_back'] as $side)
        <div class="page">
            @if ($bleed)<div class="bleed {{ $side === '_front' ? 'front' : 'back' }}"></div>@endif
            @include('cards.'.$side, ['card' => $card])
            @if ($crop)
                {{-- Trim box corners at 8 mm / 93.6 mm (x) and 8 mm / 62 mm (y); marks stay in the slug. --}}
                @foreach ([8, 93.6] as $x)
                    <span class="mark v" style="left: {{ $x - 0.1 }}mm; top: 0"></span>
                    <span class="mark v" style="left: {{ $x - 0.1 }}mm; bottom: 0"></span>
                @endforeach
                @foreach ([8, 62] as $y)
                    <span class="mark h" style="top: {{ $y - 0.1 }}mm; left: 0"></span>
                    <span class="mark h" style="top: {{ $y - 0.1 }}mm; right: 0"></span>
                @endforeach
            @endif
        </div>
    @endforeach
@endforeach
</body>
</html>
