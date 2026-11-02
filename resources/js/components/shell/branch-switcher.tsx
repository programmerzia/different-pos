import { usePage, router } from '@inertiajs/react';
import { Check, ChevronsUpDown, Store } from 'lucide-react';
import { Kbd } from '@/components/kbd';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useUiStore } from '@/command/ui-store';
import { useT } from '@/i18n';

/** POST /app/branch/{branch}/switch (route #7, lands with D3.2). Shown only with more than one branch. */
const switchUrl = (id: number) => `/app/branch/${id}/switch`;

export function BranchSwitcher() {
    const { t } = useT();
    const { branches, branch_id } = usePage().props.auth;
    const open = useUiStore((s) => s.overlay === 'branch');
    const toggle = useUiStore((s) => s.toggle);
    const close = useUiStore((s) => s.close);

    if (branches.length < 2) {
        return null;
    }
    const current = branches.find((b) => b.id === branch_id);

    return (
        <DropdownMenu
            open={open}
            onOpenChange={(next) => (next ? toggle('branch') : close())}
        >
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11 max-w-48 md:min-h-9"
                    aria-label={t('branch.switch')}
                >
                    <Store aria-hidden="true" />
                    <span className="truncate">
                        {current?.name ?? t('branch.choose')}
                    </span>
                    <ChevronsUpDown className="opacity-60" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="min-w-56">
                {branches.map((branch) => (
                    <DropdownMenuItem
                        key={branch.id}
                        className="min-h-11 md:min-h-8"
                        onSelect={() => router.post(switchUrl(branch.id))}
                    >
                        <span className="flex-1 truncate">{branch.name}</span>
                        {branch.id === branch_id ? (
                            <Check aria-hidden="true" />
                        ) : null}
                    </DropdownMenuItem>
                ))}
                <div className="flex justify-end px-2 py-1">
                    <Kbd keys="alt+b" />
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
