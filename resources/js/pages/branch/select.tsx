import { PageHeader } from '@/components/page-header';
import { Head, router } from '@inertiajs/react';
import { Store } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { useDigits } from '@/hooks/use-digits';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

type Branch = {
    id: number;
    name: string;
    code: string;
    address: string | null;
};

const switchUrl = (id: number) => `/app/branch/${id}/switch`;

/** Pick where to work today: the last used branch is first and focused; arrows move, Enter chooses, 1-9 jump. */
export default function BranchSelect({
    branches,
    lastBranchId,
}: {
    branches: Branch[];
    lastBranchId: number | null;
}) {
    const { t } = useT();
    const digits = useDigits();
    const [busy, setBusy] = useState(false);
    const refs = useRef<(HTMLButtonElement | null)[]>([]);

    useEffect(() => {
        refs.current[0]?.focus();
    }, []);

    const choose = (branch: Branch) => {
        if (busy) {
            return;
        }
        setBusy(true);
        router.post(
            switchUrl(branch.id),
            {},
            { onFinish: () => setBusy(false) },
        );
    };

    const onKeyDown = (event: React.KeyboardEvent, index: number) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const next = index + (event.key === 'ArrowDown' ? 1 : -1);
            refs.current[(next + branches.length) % branches.length]?.focus();
        } else if (/^[1-9]$/.test(event.key)) {
            const target = branches[Number(event.key) - 1];
            if (target) {
                event.preventDefault();
                choose(target);
            }
        }
    };

    return (
        <>
            <Head title={t('branch.select_title')} />
            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4 md:py-12">
                <PageHeader
                    title={t('branch.select_title')}
                    subtitle={t('branch.select_hint')}
                />
                {branches.length === 0 ? (
                    <EmptyState
                        icon={Store}
                        title={t('branch.none_assigned')}
                        description={t('branch.none_hint')}
                    />
                ) : (
                    <ul className="flex flex-col gap-2">
                        {branches.map((branch, index) => (
                            <li key={branch.id}>
                                <button
                                    ref={(el) => {
                                        refs.current[index] = el;
                                    }}
                                    type="button"
                                    disabled={busy}
                                    onClick={() => choose(branch)}
                                    onKeyDown={(event) =>
                                        onKeyDown(event, index)
                                    }
                                    className={cn(
                                        'flex min-h-14 w-full items-center gap-3 rounded-xl border border-border bg-card px-4 text-left outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60',
                                    )}
                                >
                                    <span className="num flex size-7 items-center justify-center rounded-md bg-muted text-xs text-muted-foreground">
                                        {index < 9 ? digits.n(index + 1) : ''}
                                    </span>
                                    <span className="flex min-w-0 flex-1 flex-col">
                                        <span className="truncate font-medium">
                                            {branch.name}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {branch.code}
                                            {branch.address
                                                ? ` · ${branch.address}`
                                                : ''}
                                        </span>
                                    </span>
                                    {branch.id === lastBranchId ? (
                                        <span className="text-xs text-muted-foreground">
                                            {t('branch.last_used')}
                                        </span>
                                    ) : null}
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
