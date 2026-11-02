import { Link, router } from '@inertiajs/react';
import { Keyboard, LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { Kbd } from '@/components/kbd';
import { useUiStore } from '@/command/ui-store';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { useT } from '@/i18n';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

export function UserMenuContent({ user }: { user: User }) {
    const { t } = useT();
    const cleanup = useMobileNavigation();

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild className="min-h-11 md:min-h-8">
                    <Link
                        className="w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings aria-hidden="true" />
                        {t('user.profile')}
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem
                    className="min-h-11 md:min-h-8"
                    onSelect={() => useUiStore.getState().open('shortcuts')}
                >
                    <Keyboard aria-hidden="true" />
                    {t('user.shortcuts')}
                    <Kbd keys="?" className="ml-auto" />
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild className="min-h-11 md:min-h-8">
                <Link
                    className="w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={() => {
                        cleanup();
                        router.flushAll();
                    }}
                    data-test="logout-button"
                >
                    <LogOut aria-hidden="true" />
                    {t('user.logout')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
