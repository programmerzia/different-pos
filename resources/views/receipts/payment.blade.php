{{-- Money receipt for a due collection (thermal 80/58 or A4). --}}
@extends('receipts.layout')

@section('title', __('receipts.money_receipt').' '.$r['number'])

@section('content')
    @include('receipts._shop')
    <hr>
    <div class="{{ $paper === 'a4' ? '' : 'center' }} title">{{ __('receipts.money_receipt') }}</div>
    @if ($r['voided'])<div class="void-stamp">{{ __('receipts.voided') }}</div>@endif
    <div class="row"><span>{{ __('receipts.receipt_no') }}</span><span class="num strong">{{ $r['number'] }}</span></div>
    <div class="row"><span>{{ __('receipts.date') }}</span><span class="num">{{ $r['date'] }}</span></div>
    @include('receipts._customer')
    <hr>
    <div class="row strong" style="font-size: 1.2em;"><span>{{ __('receipts.amount') }}</span><span class="num">{{ $r['amount'] }}</span></div>
    <div class="row"><span>{{ __('receipts.method') }}</span><span>{{ $r['method'] }}</span></div>
    @if ($r['reference'])<div class="row"><span>{{ __('receipts.reference') }}</span><span class="num">{{ $r['reference'] }}</span></div>@endif
    @if ($r['allocations'])
        <hr>
        <div class="strong">{{ __('receipts.allocated_to') }}</div>
        <table>
            @foreach ($r['allocations'] as $a)
                <tr><td class="num">{{ $a['number'] }}</td><td class="num small">{{ $a['date'] }}</td><td class="right num">{{ $a['amount'] }}</td></tr>
            @endforeach
        </table>
    @endif
    @if ($r['advance'])<div class="row"><span>{{ __('receipts.advance') }}</span><span class="num">{{ $r['advance'] }}</span></div>@endif
    <hr>
    <div class="row strong"><span>{{ __('receipts.remaining_due') }}</span><span class="num">{{ $r['remaining_due'] }}</span></div>
    @if ($r['received_by'])<div class="row"><span>{{ __('receipts.received_by') }}</span><span>{{ $r['received_by'] }}</span></div>@endif
    <div style="margin-top: 12mm; border-top: 1px solid black; width: 50%; padding-top: 1mm;" class="small">{{ __('receipts.signature') }}</div>
@endsection
