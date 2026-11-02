{{-- Shop header (thermal: centred). --}}
<header class="{{ $paper === 'a4' ? '' : 'center' }}">
    <div class="shop-name">{{ $r['shop']['name'] }}</div>
    @if ($r['shop']['legal_name'] && $r['shop']['legal_name'] !== $r['shop']['name'])<div class="small">{{ $r['shop']['legal_name'] }}</div>@endif
    <div class="small">{{ $r['shop']['branch'] }}</div>
    @if ($r['shop']['branch_address'] ?? $r['shop']['address'])<div class="small">{{ $r['shop']['branch_address'] ?? $r['shop']['address'] }}</div>@endif
    @if ($r['shop']['branch_phone'] ?? $r['shop']['phone'])<div class="small num">{{ __('receipts.phone') }}: {{ $r['shop']['branch_phone'] ?? $r['shop']['phone'] }}</div>@endif
    @if ($r['shop']['vat_bin'])<div class="small num">{{ __('receipts.seller_bin') }}: {{ $r['shop']['vat_bin'] }}</div>@endif
    @if ($r['shop']['header'])<div class="small">{{ $r['shop']['header'] }}</div>@endif
</header>
