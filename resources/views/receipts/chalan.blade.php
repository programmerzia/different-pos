{{-- Delivery chalan (A4). Quantities only: no price, discount, VAT, total, due or payment. --}}
@extends('receipts.layout')

@section('title', __('receipts.chalan.title').' '.$r['number'])

@section('content')
    @php
        $bn = $r['locale'] === 'bn';
        $blank = '<span style="display:inline-block;min-width:60mm;border-bottom:1px solid black;">&nbsp;</span>';
    @endphp
    <div class="grid-2">
        <div>@include('receipts._shop')</div>
        <div class="right">
            <div class="title" style="font-size: 1.6em;">{{ __('receipts.chalan.title') }}</div>
            <div>{{ __('receipts.chalan.chalan_no') }}: <span class="num strong">{{ $r['number'] }}</span></div>
            <div>{{ __('receipts.chalan.invoice_ref') }}: <span class="num">{{ $r['invoice_number'] }}</span></div>
            <div>{{ __('receipts.chalan.date') }}: <span class="num">{{ $r['date'] }}</span></div>
        </div>
    </div>
    @if ($r['voided'])
        <div class="void-stamp">{{ __('receipts.voided') }}@if ($r['void_reason']) · {{ $r['void_reason'] }}@endif</div>
    @endif
    @if ($r['customer'])
        <div class="box" style="margin: 4mm 0;">
            <div class="strong">{{ __('receipts.chalan.deliver_to') }}</div>
            <div>{{ $r['customer']['name'] }}</div>
            @if ($r['customer']['shop_name'])<div>{{ __('receipts.shop') }}: {{ $r['customer']['shop_name'] }}</div>@endif
            @if ($r['customer']['phone'])<div class="num">{{ __('receipts.phone') }}: {{ $r['customer']['phone'] }}</div>@endif
            @if ($r['customer']['address'])<div>{{ $r['customer']['address'] }}</div>@endif
        </div>
    @endif
    <table style="margin-top: 4mm;">
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
    <div class="grid-2" style="margin-top: 10mm;">
        <div>{{ __('receipts.chalan.vehicle_no') }}: {!! $blank !!}</div>
        <div>{{ __('receipts.chalan.driver') }}: {!! $blank !!}</div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 8mm; margin-top: 22mm;">
        @foreach (['prepared_by', 'authorised_by', 'received_by'] as $who)
            <div>
                <div style="border-top: 1px solid black; padding-top: 1mm;">{{ __('receipts.chalan.'.$who) }}</div>
                @if ($who === 'received_by')
                    <div style="margin-top: 6mm; border-top: 1px solid black; padding-top: 1mm;">{{ __('receipts.chalan.date') }}</div>
                @endif
            </div>
        @endforeach
    </div>
@endsection
