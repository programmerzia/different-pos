import JsBarcode from 'jsbarcode';
import QRCode from 'qrcode';
import { useMemo, type CSSProperties } from 'react';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';
import {
    cardCode,
    cid,
    formatNumber,
    maskNumber,
    qrPayload,
    sinceShort,
    tierOf,
    type CardBrand,
    type CardMember,
} from './card-face';

export type CardSide = 'front' | 'back';

/** Code128 bars as a binary string ("1" = bar module), computed without touching the DOM. */
export function code128(text: string): string {
    const target: { encodings?: { data: string }[] } = {};
    try {
        JsBarcode(target, text, { format: 'CODE128' });
    } catch {
        return '';
    }

    return (target.encodings ?? []).map((e) => e.data).join('');
}

function Barcode({ value }: { value: string }) {
    const bars = useMemo(() => {
        const bits = code128(value);
        const out: { x: number; w: number }[] = [];
        for (let i = 0; i < bits.length; i++) {
            if (bits[i] !== '1') {
                continue;
            }
            const last = out.at(-1);
            if (last && last.x + last.w === i) {
                last.w++;
            } else {
                out.push({ x: i, w: 1 });
            }
        }

        return { out, width: Math.max(bits.length, 1) };
    }, [value]);

    return (
        <svg viewBox={`0 0 ${bars.width} 24`} preserveAspectRatio="none" aria-hidden="true">
            <rect width="100%" height="24" fill="var(--mc-white)" />
            <g fill="var(--mc-black)">
                {bars.out.map((b) => (
                    <rect key={b.x} x={b.x} y="0" width={b.w} height="24" />
                ))}
            </g>
        </svg>
    );
}

function Qr({ value }: { value: string }) {
    const qr = useMemo(() => {
        const { modules } = QRCode.create(value, { errorCorrectionLevel: 'M' });
        let d = '';
        for (let r = 0; r < modules.size; r++) {
            for (let c = 0; c < modules.size; c++) {
                if (modules.get(r, c)) {
                    d += `M${c} ${r}h1v1h-1z`;
                }
            }
        }

        return { d, size: modules.size };
    }, [value]);

    return (
        <svg viewBox={`0 0 ${qr.size} ${qr.size}`} aria-hidden="true">
            <path d={qr.d} fill="var(--mc-black)" />
        </svg>
    );
}

/**
 * The membership card (CR80, ISO/IEC 7810 ID-1), the baseline mockup's teal card one-to-one plus the
 * tenant logo badge. The artwork lives in resources/css/membership-card.css, shared with the print
 * views (resources/views/cards/_front|_back.blade.php), so the preview and the printed card match.
 * Size it with `width` (any CSS length; 85.6mm = 1:1, the mockup's 340px by default).
 *
 * On screen the number is masked (`•••• 0017`) and shows in full while the card is hovered, while the
 * focusable wrapper around it (a button) has keyboard focus, or when `revealed` is set; `forPrint`
 * always shows it in full.
 */
export function MembershipCard({
    member,
    brand,
    side = 'front',
    forPrint = false,
    width = '340px',
    revealed = false,
    className,
}: {
    member: CardMember;
    brand: CardBrand;
    side?: CardSide;
    forPrint?: boolean;
    width?: string;
    revealed?: boolean;
    className?: string;
}) {
    const { t } = useT();
    const digits = useDigits();
    const code = cardCode(member);
    const tier = tierOf(member.group);

    return (
        <div
            className={cn('mc', forPrint && 'mc-print', className)}
            data-side={side}
            data-revealed={forPrint || revealed ? 'true' : 'false'}
            data-testid="membership-card"
            style={{ '--mc-width': width } as CSSProperties}
        >
            {side === 'front' ? (
                <div className="mc-face mc-front">
                    <div className="mc-sheen" />
                    <div className="mc-top">
                        <div className="mc-brand">
                            {brand.logo_url ? (
                                <div className="mc-logo">
                                    <img src={brand.logo_url} alt="" />
                                </div>
                            ) : null}
                            <div>
                                <div className="mc-tenant">{brand.name}</div>
                                <div className="mc-tagline">{t('cards.face.tagline')}</div>
                            </div>
                        </div>
                        <div className="mc-marks">
                            <div className="mc-chip" aria-hidden="true" />
                            <div className="mc-nfc" aria-hidden="true">
                                <span /><span /><span />
                            </div>
                        </div>
                    </div>
                    <div className="mc-bottom">
                        <div className="mc-number" data-testid="card-number">
                            <span className="mc-num-full">{digits.n(formatNumber(member.customer_no))}</span>
                            <span className="mc-num-masked">{digits.n(maskNumber(member.customer_no))}</span>
                        </div>
                        <div className="mc-holder">
                            <div>
                                <div className="mc-label">{t('cards.face.cardholder')}</div>
                                <div className="mc-name">{member.name}</div>
                                <div className="mc-name-bn">{member.name_bn ?? ''}</div>
                            </div>
                            <div className="mc-right">
                                <div className="mc-label">{t('cards.face.club_tier')}</div>
                                <div className="mc-tier">{t(`cards.face.tier.${tier}`)}</div>
                                <div className="mc-valid">
                                    {t('cards.face.valid', { date: digits.n(sinceShort(member.member_since)) })}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            ) : (
                <div className="mc-face mc-back">
                    <div className="mc-stripe" />
                    <div className="mc-backbody">
                        <div className="mc-sigrow">
                            <div className="mc-sig">
                                <small>{t('cards.face.signature')}</small>
                                <span>{member.name}</span>
                            </div>
                            <div className="mc-cid">{digits.n(cid(member.customer_no))}</div>
                        </div>
                        <div className="mc-codes">
                            <div className="mc-barcode">
                                <Barcode value={code} />
                                <div>{code}</div>
                            </div>
                            <div className="mc-qr" data-qr={qrPayload(member)}>
                                <Qr value={qrPayload(member)} />
                                <span>SCAN</span>
                            </div>
                        </div>
                        <div className="mc-terms">
                            {brand.phone
                                ? t('cards.face.terms', {
                                      name: brand.name,
                                      branch: brand.branch ?? brand.name,
                                      phone: digits.n(brand.phone),
                                  })
                                : t('cards.face.terms_no_phone', {
                                      name: brand.name,
                                      branch: brand.branch ?? brand.name,
                                  })}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
