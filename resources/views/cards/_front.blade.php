{{-- Same markup as components/cards/membership-card.tsx (front); styles in resources/css/membership-card.css. --}}
<section class="card front mc mc-print" data-revealed="true" data-card="{{ $card['card_code'] }}">
    <div class="mc-face mc-front">
        <div class="mc-sheen"></div>
        @if ($tenantLogo)<div class="mc-logo"><img src="{{ $tenantLogo }}" alt=""></div>@endif
        <div class="mc-top">
            <div>
                <div class="mc-tenant">{{ $tenantName }}</div>
                <div class="mc-tagline">{{ __('cards.face.tagline') }}</div>
            </div>
            <div class="mc-marks">
                <div class="mc-chip">CHIP</div>
                <div class="mc-nfc" aria-hidden="true">))))</div>
            </div>
        </div>
        <div class="mc-bottom">
            {{-- Print always shows the full number (the screen masks it). --}}
            <div class="mc-number"><span class="mc-num-full">{{ $card['customer_no'] }}</span></div>
            <div class="mc-holder">
                <div>
                    <div class="mc-label">{{ __('cards.face.cardholder') }}</div>
                    <div class="mc-name">{{ $card['name'] }}</div>
                    <div class="mc-name-bn">{{ $card['name_bn'] ?? '' }}</div>
                </div>
                <div class="mc-right">
                    <div class="mc-label">{{ __('cards.face.club_tier') }}</div>
                    <div class="mc-tier">{{ __('cards.face.tier.'.$card['tier']) }}</div>
                    <div class="mc-valid">{{ __('cards.face.valid', ['date' => $card['since']]) }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
