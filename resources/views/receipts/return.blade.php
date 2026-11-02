{{-- Return / exchange receipt (thermal 80/58 or A4). --}}
@extends('receipts.layout')

@section('title', __('receipts.return_receipt').' '.$r['number'])

@section('content')
    @include('receipts._shop')
    <hr>
    <div class="{{ $paper === 'a4' ? '' : 'center' }} title">{{ $r['exchange'] ? __('receipts.exchange_receipt') : __('receipts.return_receipt') }}</div>
    <div class="row"><span>{{ __('receipts.return_no') }}</span><span class="num strong">{{ $r['number'] }}</span></div>
    <div class="row"><span>{{ __('receipts.original_sale') }}</span><span class="num">{{ $r['sale_number'] }}</span></div>
    <div class="row"><span>{{ __('receipts.date') }}</span><span class="num">{{ $r['date'] }}</span></div>
    @if ($r['cashier'])<div class="row"><span>{{ __('receipts.cashier') }}</span><span>{{ $r['cashier'] }}</span></div>@endif
    @include('receipts._customer')
    <hr>
    <table>
        <thead><tr><th>{{ __('receipts.item') }}</th><th class="right">{{ __('receipts.amount') }}</th></tr></thead>
        <tbody>
        @foreach ($r['lines'] as $line)
            <tr><td colspan="2">{{ $line['name'] }} <span class="small muted">{{ $line['sku'] }}</span></td></tr>
            <tr>
                <td class="num small">{{ $line['qty'] }} × {{ $line['unit_price'] }}@if ($line['restock']) · {{ __('receipts.restocked') }}@endif</td>
                <td class="right num">{{ $line['total'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <hr>
    @include('receipts._totals')
    <div class="row"><span>{{ __('receipts.refund_mode') }}</span><span>{{ $r['mode'] }}</span></div>
    @if ($r['exchange'])
        <hr>
        <div class="row"><span>{{ __('receipts.replacement_sale') }}</span><span class="num">{{ $r['exchange']['number'] }}</span></div>
        <div class="row"><span>{{ __('receipts.total') }}</span><span class="num">{{ $r['exchange']['total'] }}</span></div>
        <div class="row strong"><span>{{ __('receipts.difference') }}</span><span class="num">{{ $r['exchange']['difference'] }}</span></div>
    @endif
    <hr>
    <div class="center small">{{ $r['shop']['footer'] ?: __('receipts.thank_you') }}</div>
@endsection
