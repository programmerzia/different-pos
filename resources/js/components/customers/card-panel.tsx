import { Link, router } from '@inertiajs/react';
import { CreditCard, ExternalLink, Printer, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { StatusBadge, type Tone } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import type { CustomerCard } from '@/types/customers';

const CARD_TONE: Record<string, Tone> = {
    active: 'success',
    lost: 'danger',
    replaced: 'neutral',
    revoked: 'neutral',
};

/**
 * The membership card on the customer profile: a live preview (the same server view the printing
 * press layout uses, so front and back always match), status and the lifecycle buttons.
 */
export function CardPanel({
    customerUuid,
    card,
    canIssue,
    canViewCards,
}: {
    customerUuid: string;
    card: CustomerCard | null;
    canIssue: boolean;
    canViewCards: boolean;
}) {
    const { t } = useT();
    const digits = useDigits();
    const [confirmLost, setConfirmLost] = useState(false);
    const active = card?.status === 'active';

    const printCard = () => {
        if (!card) {
            return;
        }
        window.open(
            `/app/membership-cards/print?ids=${card.id}&layout=cr80`,
            '_blank',
            'noopener',
        );
        router.post(
            '/app/membership-cards/printed',
            { ids: [card.id], layout: 'cr80' },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <section
            aria-labelledby="card-heading"
            className="flex flex-col gap-3 rounded-lg border bg-card p-4"
        >
            <div className="flex items-center gap-2">
                <CreditCard className="size-5 text-primary" aria-hidden="true" />
                <h2 id="card-heading" className="mr-auto text-lg font-semibold">
                    {t('cards.panel.title')}
                </h2>
                {card ? (
                    <StatusBadge
                        status={card.status}
                        tone={CARD_TONE[card.status] ?? 'neutral'}
                        label={t(`cards.status.${card.status}`)}
                    />
                ) : null}
            </div>

            {card && active ? (
                <iframe
                    title={t('cards.panel.preview')}
                    src={`/app/membership-cards/print?ids=${card.id}&layout=cr80`}
                    scrolling="no"
                    className="h-102 w-82 max-w-full self-center rounded-md border bg-white"
                />
            ) : (
                <p className="py-6 text-center text-sm text-muted-foreground">
                    {card
                        ? t('cards.panel.blocked')
                        : t('cards.panel.none')}
                </p>
            )}

            {card ? (
                <dl className="grid grid-cols-2 gap-x-3 gap-y-1 text-sm">
                    <dt className="text-muted-foreground">
                        {t('cards.panel.code')}
                    </dt>
                    <dd className="num font-mono">{digits.n(card.card_code)}</dd>
                    <dt className="text-muted-foreground">
                        {t('cards.panel.member_since')}
                    </dt>
                    <dd className="num">
                        {card.member_since ? digits.n(card.member_since) : '-'}
                    </dd>
                    <dt className="text-muted-foreground">
                        {t('cards.panel.printed')}
                    </dt>
                    <dd className="num">{digits.n(card.print_count)}</dd>
                </dl>
            ) : null}

            <div className="flex flex-wrap gap-2">
                {canIssue && active ? (
                    <Button
                        type="button"
                        className="min-h-11 md:min-h-9"
                        onClick={printCard}
                    >
                        <Printer aria-hidden="true" />
                        {t('cards.panel.print')}
                    </Button>
                ) : null}
                {canIssue && !card ? (
                    <Button
                        type="button"
                        className="min-h-11 md:min-h-9"
                        onClick={() =>
                            router.post(
                                '/app/membership-cards/issue',
                                { customers: [customerUuid] },
                                { preserveScroll: true },
                            )
                        }
                    >
                        <CreditCard aria-hidden="true" />
                        {t('customers.actions.issue_card')}
                    </Button>
                ) : null}
                {canIssue && card && active ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11 md:min-h-9"
                        onClick={() => setConfirmLost(true)}
                    >
                        {t('cards.actions.lost')}
                    </Button>
                ) : null}
                {canIssue && card?.status === 'lost' ? (
                    <Button
                        type="button"
                        className="min-h-11 md:min-h-9"
                        onClick={() =>
                            router.post(
                                `/app/membership-cards/${card.id}/reissue`,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <RefreshCw aria-hidden="true" />
                        {t('cards.actions.reissue')}
                    </Button>
                ) : null}
                {canViewCards ? (
                    <Button
                        asChild
                        variant="ghost"
                        className="min-h-11 md:min-h-9"
                    >
                        <Link href="/app/membership-cards">
                            <ExternalLink aria-hidden="true" />
                            {t('cards.panel.all_cards')}
                        </Link>
                    </Button>
                ) : null}
            </div>

            <ConfirmDialog
                open={confirmLost}
                onOpenChange={setConfirmLost}
                title={t('cards.lost.title')}
                description={t('cards.lost.body')}
                confirmLabel={t('cards.actions.lost')}
                destructive
                onConfirm={() => {
                    if (card) {
                        router.post(
                            `/app/membership-cards/${card.id}/lost`,
                            {},
                            { preserveScroll: true },
                        );
                    }
                }}
            />
        </section>
    );
}
