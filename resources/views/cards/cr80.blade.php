<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('cards.title') }}</title>
@php($w = $bleed ? '91.6mm' : '85.6mm')
@php($h = $bleed ? '60mm' : '54mm')
<style>
    @page { size: {{ $w }} {{ $h }}; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: sans-serif; color: #111; }
    .card { width: {{ $w }}; height: {{ $h }}; padding: {{ $bleed ? '8mm 8mm' : '5mm 5mm' }}; page-break-after: always; overflow: hidden; }
    .tenant { font-size: 9pt; font-weight: bold; letter-spacing: .5pt; }
    .name { font-size: 12pt; font-weight: bold; margin-top: 7mm; }
    .name-bn { font-size: 11pt; }
    .shop { font-size: 9pt; }
    .no { font-size: 13pt; font-family: monospace; margin-top: 4mm; }
    .meta { font-size: 7pt; margin-top: 2mm; }
    .back { text-align: center; }
    .back svg { width: 100%; height: 14mm; margin-top: 8mm; }
    .code { font-family: monospace; font-size: 9pt; margin-top: 2mm; }
    .terms { font-size: 6pt; margin-top: 4mm; }
</style>
</head>
<body>
@foreach ($cards as $card)
    <section class="card front">
        <div class="tenant">{{ $tenantName }}</div>
        <div class="name">{{ $card['name'] }}</div>
        @if ($card['name_bn'])<div class="name-bn">{{ $card['name_bn'] }}</div>@endif
        @if ($card['shop_name'])<div class="shop">{{ $card['shop_name'] }}</div>@endif
        <div class="no">{{ $card['customer_no'] }}</div>
        @if ($card['member_since'])<div class="meta">{{ __('cards.member_since') }} {{ $card['member_since'] }}</div>@endif
    </section>
    <section class="card back">
        {!! $card['barcode'] !!}
        <div class="code">{{ $card['card_code'] }}</div>
        <div class="terms">{{ __('cards.terms') }}</div>
    </section>
@endforeach
</body>
</html>
