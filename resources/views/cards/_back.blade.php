{{-- Same markup as components/cards/membership-card.tsx (back); styles in resources/css/membership-card.css. --}}
<section class="card back mc mc-print" data-card="{{ $card['card_code'] }}">
    <div class="mc-face mc-back">
        <div class="mc-stripe"></div>
        <div class="mc-backbody">
            <div class="mc-sigrow">
                <div class="mc-sig"><small>{{ __('cards.face.signature') }}</small><span>{{ $card['name'] }}</span></div>
                <div class="mc-cid">{{ $card['cid'] }}</div>
            </div>
            <div class="mc-codes">
                <div class="mc-barcode">{!! $card['barcode'] !!}<div>{{ $card['card_code'] }}</div></div>
                <div class="mc-qr">{!! $card['qr'] !!}<span>SCAN</span></div>
            </div>
            <div class="mc-terms">
                @if ($phone)
                    {{ __('cards.face.terms', ['name' => $tenantName, 'branch' => $branchName ?? $tenantName, 'phone' => $phone]) }}
                @else
                    {{ __('cards.face.terms_no_phone', ['name' => $tenantName, 'branch' => $branchName ?? $tenantName]) }}
                @endif
            </div>
        </div>
    </div>
</section>
