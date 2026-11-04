{{-- Courier delivery chalan (A4): a parcel label to cut off and attach, then the packing list. Quantities
     only: no price, discount, VAT, total, due or payment. Courier details and COD are filled in by hand. --}}
@extends('receipts.layout')

@section('title', __('receipts.chalan.title').' '.$r['number'])

@section('content')
    @php
        $bn = $r['locale'] === 'bn';
        $shop = $r['shop'];
        $address = $shop['branch_address'] ?? $shop['address'];
        $phone = $shop['branch_phone'] ?? $shop['phone'];
    @endphp
    <style>
        .label { border: 2px solid black; break-inside: avoid; page-break-inside: avoid; }
        .label > div + div { border-top: 1px solid black; }
        .label-head { display: flex; justify-content: space-between; gap: 6mm; padding: 3mm; }
        .label-head .shop-logo { max-height: 48px; margin: 0 0 1mm; }
        .label-bars { --mc-white: white; --mc-black: black; }
        .label-bars svg { display: block; width: 80mm; height: 13mm; margin-left: auto; }
        .label-to { display: grid; grid-template-columns: 1fr 34mm; gap: 4mm; padding: 3mm; }
        .label-tag { font-size: .85em; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
        .to-name { font-size: 1.7em; font-weight: 700; line-height: 1.2; }
        .to-phone { font-size: 1.6em; font-weight: 700; }
        .to-address { font-size: 1.25em; }
        .label-qr svg { display: block; width: 30mm; height: 30mm; margin-left: auto; }
        .label-from { padding: 2mm 3mm; }
        .label-fields { display: grid; grid-template-columns: repeat(3, 1fr); }
        .label-fields > div { padding: 2mm 3mm 7mm; border-left: 1px solid black; }
        .label-fields > div:nth-child(3n + 1) { border-left: 0; }
        .label-fields > div:nth-child(n + 4) { border-top: 1px solid black; }
        .fill { border-bottom: 1px solid black; height: 8mm; }
        .cut { display: flex; align-items: center; gap: 2mm; margin: 4mm 0; font-size: .85em; }
        .cut::before, .cut::after { content: ''; flex: 1; border-top: 1px dashed black; }
        .signs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8mm; margin-top: 18mm; break-inside: avoid; page-break-inside: avoid; }
        .signs > div > div { border-top: 1px solid black; padding-top: 1mm; }
    </style>

    @if ($r['voided'])
        <div class="void-stamp">{{ __('receipts.voided') }}@if ($r['void_reason']) · {{ $r['void_reason'] }}@endif</div>
    @endif

    <section class="label">
        <div class="label-head">
            <div>
                @if (! empty($shop['logo_url']))<img class="shop-logo" src="{{ $shop['logo_url'] }}" alt="">@endif
                <div class="shop-name">{{ $shop['name'] }}</div>
            </div>
            <div class="right label-bars">
                <div class="title" style="font-size: 1.3em; margin: 0 0 1mm;">{{ __('receipts.chalan.title') }}</div>
                {!! $r['barcode'] !!}
                <div class="num strong">{{ $r['number'] }}</div>
            </div>
        </div>

        <div class="label-to">
            <div>
                <div class="label-tag">{{ __('receipts.chalan.to') }}</div>
                @if ($r['customer'])
                    <div class="to-name">{{ $r['customer']['name'] }}</div>
                    @if ($r['customer']['shop_name'])<div class="strong">{{ $r['customer']['shop_name'] }}</div>@endif
                    @if ($r['customer']['phone'])<div class="to-phone num">{{ $r['customer']['phone'] }}</div>@endif
                    @if ($r['customer']['address'])
                        <div class="to-address">{{ $r['customer']['address'] }}</div>
                    @else
                        <div class="small">{{ __('receipts.chalan.address') }}:</div><div class="fill"></div>
                    @endif
                @else
                    <div class="small">{{ __('receipts.chalan.name') }}:</div><div class="fill"></div>
                    <div class="small">{{ __('receipts.phone') }}:</div><div class="fill"></div>
                    <div class="small">{{ __('receipts.chalan.address') }}:</div><div class="fill"></div>
                @endif
            </div>
            <div class="label-qr">{!! $r['qr'] !!}</div>
        </div>

        <div class="label-from">
            <span class="label-tag">{{ __('receipts.chalan.from') }}:</span>
            <span class="strong">{{ $shop['name'] }}</span> · {{ $shop['branch'] }}@if ($address), {{ $address }}@endif
            @if ($phone)· <span class="num">{{ __('receipts.phone') }}: {{ $phone }}</span>@endif
        </div>

        <div class="label-fields">
            <div class="small strong">{{ __('receipts.chalan.courier') }}</div>
            <div class="small strong">{{ __('receipts.chalan.tracking_no') }}</div>
            <div class="small strong">{{ __('receipts.chalan.booking_date') }}</div>
            <div class="small strong">{{ __('receipts.chalan.parcels') }}</div>
            <div class="small strong">{{ __('receipts.chalan.weight') }}</div>
            <div class="small strong">{{ __('receipts.chalan.cod') }}</div>
        </div>
    </section>

    <div class="cut">✂ {{ __('receipts.chalan.cut_here') }}</div>

    <div class="grid-2" style="margin-bottom: 2mm;">
        <div class="title" style="margin: 0;">{{ __('receipts.chalan.packing_list') }}</div>
        <div class="right small">
            {{ __('receipts.chalan.chalan_no') }}: <span class="num strong">{{ $r['number'] }}</span><br>
            {{ __('receipts.chalan.invoice_ref') }}: <span class="num">{{ $r['invoice_number'] }}</span><br>
            {{ __('receipts.chalan.date') }}: <span class="num">{{ $r['date'] }}</span>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>{{ __('receipts.chalan.sku') }}</th>
                <th>{{ __('receipts.chalan.product') }}</th>
                <th>{{ __('receipts.chalan.unit') }}</th>
                <th class="right">{{ __('receipts.qty') }}</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($r['lines'] as $i => $line)
            <tr>
                <td class="num">{{ $bn ? \App\Support\Numbers\Digits::toBangla((string) ($i + 1)) : $i + 1 }}</td>
                <td class="num">{{ $line['sku'] }}</td>
                <td>{{ $line['name'] }}</td>
                <td>{{ $line['unit'] }}</td>
                <td class="right num">{{ $line['qty'] }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="right strong">{{ __('receipts.chalan.total_qty') }}</td>
                <td class="right num strong">{{ $r['total_qty'] }}</td>
            </tr>
        </tfoot>
    </table>
    @if ($r['has_returns'])
        <div class="small muted" style="margin-top: 2mm;">{{ __('receipts.chalan.returns_note') }}</div>
    @endif
    <div class="signs">
        @foreach (['packed_by', 'courier_received', 'customer_received'] as $who)
            <div><div>{{ __('receipts.chalan.'.$who) }}</div></div>
        @endforeach
    </div>
@endsection
