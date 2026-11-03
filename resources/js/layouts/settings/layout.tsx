import { Link, usePage } from '@inertiajs/react';
import {
    Bell,
    Building2,
    Check,
    CreditCard,
    Download,
    Globe,
    Hash,
    KeyRound,
    Keyboard,
    Lock,
    type LucideIcon,
    MessageSquare,
    Palette,
    Percent,
    Printer,
    RefreshCw,
    Ruler,
    ScrollText,
    Shield,
    Smartphone,
    Store,
    Tag,
    UserRound,
} from 'lucide-react';
import {
    createContext,
    type MouseEvent,
    type PropsWithChildren,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { toast } from 'sonner';
import { Kbd } from '@/components/kbd';
import { Button } from '@/components/ui/button';
import { useUiStore } from '@/command/ui-store';
import { useCan } from '@/hooks/use-can';
import { useHotkeys } from '@/hotkeys/use-hotkeys';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

/**
 * The Settings hub (baseline mockup screen 21, render_admin_and_states.py): header with "Save Changes
 * (Ctrl+S)", the unsaved-changes bar, the left "Configuration menu" and the content pane. Every business
 * settings page and the personal account pages render inside it; a page with a form hands its save,
 * dirty state and discard to the header through `useSettingsSave`.
 */

export type SettingsSave = {
    save: () => void;
    dirty: boolean;
    processing?: boolean;
    discard?: (() => void) | undefined;
};

type HubApi = { register: (spec: SettingsSave | null) => void };

const HubContext = createContext<HubApi | null>(null);

/** True inside the hub (AdminPage then renders the hub's section header instead of a page header). */
export function useInSettingsHub(): boolean {
    return useContext(HubContext) !== null;
}

/** Wires a page form to the hub's Save button, Ctrl+S and the unsaved-changes bar. */
export function useSettingsSave(spec: SettingsSave): void {
    const hub = useContext(HubContext);
    const { save, dirty, processing = false, discard } = spec;
    useEffect(() => {
        hub?.register({ save, dirty, processing, discard });
    }, [hub, save, dirty, processing, discard]);
    useEffect(() => () => hub?.register(null), [hub]);
}

type Section = {
    id: string;
    icon: LucideIcon;
    /** Path (and optional #anchor) inside the app; absent = a later phase. */
    href?: string;
    permission?: string;
    /** Opens a shell overlay instead of navigating. */
    overlay?: 'shortcuts';
};

/** The mockup's 16 sections in its order, plus the existing Units, Users & Roles, Audit and account pages. */
const SECTIONS: Section[] = [
    {
        id: 'business_profile',
        icon: Store,
        href: '/app/settings/business',
        permission: 'settings.manage',
    },
    {
        id: 'branches',
        icon: Building2,
        href: '/app/settings/branches',
        permission: 'settings.manage',
    },
    {
        id: 'registers_devices',
        icon: Smartphone,
        href: '/app/settings/registers',
        permission: 'settings.manage',
    },
    {
        id: 'tax_vat',
        icon: Percent,
        href: '/app/settings/taxes',
        permission: 'settings.manage',
    },
    {
        id: 'units',
        icon: Ruler,
        href: '/app/settings/units',
        permission: 'settings.manage',
    },
    {
        id: 'payment_methods',
        icon: CreditCard,
        href: '/app/settings/payment-methods',
        permission: 'settings.manage',
    },
    { id: 'receipt_printers', icon: Printer, permission: 'settings.manage' },
    { id: 'invoice_numbering', icon: Hash, permission: 'settings.manage' },
    {
        id: 'customer_pin_policy',
        icon: Lock,
        href: '/app/settings/business#pin-policy',
        permission: 'settings.manage',
    },
    {
        id: 'membership_cards',
        icon: Tag,
        href: '/app/membership-cards',
        permission: 'cards.view',
    },
    {
        id: 'messaging_providers',
        icon: MessageSquare,
        href: '/app/settings/sms',
        permission: 'settings.manage',
    },
    {
        id: 'returns_policy',
        icon: RefreshCw,
        href: '/app/settings/business#sales-rules',
        permission: 'settings.manage',
    },
    { id: 'notifications', icon: Bell, permission: 'settings.manage' },
    { id: 'appearance', icon: Palette, href: '/settings/appearance' },
    {
        id: 'language',
        icon: Globe,
        href: '/app/settings/business#regional',
        permission: 'settings.manage',
    },
    { id: 'keyboard_shortcuts', icon: Keyboard, overlay: 'shortcuts' },
    { id: 'data_import_export', icon: Download, permission: 'settings.manage' },
    {
        id: 'users_roles',
        icon: Shield,
        href: '/app/settings/roles',
        permission: 'users.manage',
    },
    {
        id: 'audit',
        icon: ScrollText,
        href: '/app/settings/audit',
        permission: 'audit.view',
    },
];

const ACCOUNT: Section[] = [
    { id: 'profile', icon: UserRound, href: '/settings/profile' },
    { id: 'security', icon: KeyRound, href: '/settings/security' },
];

const ANCHORS = ['#pin-policy', '#sales-rules', '#regional'];

/**
 * A menu #anchor targets the section with id `section-<anchor>`: the ids differ from the hash on purpose,
 * so the browser's own anchor jump (which would also scroll the app frame) never fires; the hub scrolls
 * only its content pane.
 */
export const sectionId = (anchor: string) => `section-${anchor}`;

function scrollPaneTo(
    pane: HTMLElement | null,
    anchor: string,
    behavior: ScrollBehavior,
): void {
    const el = document.getElementById(sectionId(anchor));
    if (!el) {
        return;
    }
    if (pane && pane.scrollHeight > pane.clientHeight) {
        const top =
            el.getBoundingClientRect().top -
            pane.getBoundingClientRect().top +
            pane.scrollTop -
            16;
        pane.scrollTo({ top, behavior });
    } else {
        el.scrollIntoView({ block: 'start', behavior });
    }
}

/** Active when the path matches; a `#anchor` item also needs that hash (Business Profile owns the rest). */
export function isActiveSection(
    href: string,
    path: string,
    hash: string,
): boolean {
    const [target, anchor] = href.split('#');
    if (
        target !== path &&
        !(target === '/app/settings/roles' && path === '/app/settings/users')
    ) {
        return false;
    }
    if (anchor) {
        return hash === `#${anchor}`;
    }

    return target !== '/app/settings/business' || !ANCHORS.includes(hash);
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { t } = useT();
    const can = useCan();
    const page = usePage();
    const openOverlay = useUiStore((s) => s.open);
    const path = new URL(page.url, 'http://localhost').pathname;
    const [hash, setHash] = useState('');
    const pane = useRef<HTMLDivElement>(null);
    // Follow the hash on every visit (Inertia links do not fire hashchange) and on manual edits.
    useEffect(() => {
        const sync = (behavior: ScrollBehavior) => {
            const current = window.location.hash;
            setHash(current);
            if (current.length > 1) {
                requestAnimationFrame(() =>
                    scrollPaneTo(pane.current, current.slice(1), behavior),
                );
            }
        };
        sync('auto');
        const onHash = () => sync('smooth');
        window.addEventListener('hashchange', onHash);

        return () => window.removeEventListener('hashchange', onHash);
    }, [page.url]);
    const [spec, setSpec] = useState<SettingsSave | null>(null);
    const register = useCallback(
        (next: SettingsSave | null) => setSpec(next),
        [],
    );
    const api = useMemo(() => ({ register }), [register]);

    useHotkeys('form', [
        {
            id: 'settings.save',
            keys: 'mod+s',
            labelKey: 'settings_hub.save',
            allowInInput: true,
            handler: (event) => {
                event.preventDefault();
                spec?.save();
            },
        },
    ]);

    const visible = (s: Section) => can(s.permission);
    const sections = SECTIONS.filter(visible);
    const showSoon = can('settings.manage');

    const goAnchor = (event: MouseEvent, href: string) => {
        const [target, anchor] = href.split('#');
        if (!anchor || target !== path) {
            return;
        }
        event.preventDefault();
        window.history.replaceState(window.history.state, '', `#${anchor}`);
        setHash(`#${anchor}`);
        scrollPaneTo(pane.current, anchor, 'smooth');
    };

    const item = (s: Section) => {
        const active = s.href ? isActiveSection(s.href, path, hash) : false;
        const soon = !s.href && !s.overlay;
        const body = (
            <>
                <s.icon
                    className={cn(
                        'size-3.75 shrink-0',
                        active
                            ? 'text-primary-foreground'
                            : 'text-muted-foreground',
                    )}
                    aria-hidden="true"
                />
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-xs">
                        {t(`settings_hub.section.${s.id}`)}
                    </span>
                    <span
                        className={cn(
                            'block truncate text-[10px]',
                            active
                                ? 'text-primary-foreground opacity-80'
                                : 'text-muted-foreground',
                        )}
                    >
                        {soon
                            ? t('settings_hub.next_phase')
                            : t(`settings_hub.desc.${s.id}`)}
                    </span>
                </span>
            </>
        );
        const cls = cn(
            'flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left transition-all [@media(pointer:coarse)]:min-h-11',
            active
                ? 'bg-primary font-bold text-primary-foreground shadow-xs'
                : 'text-muted-foreground hover:bg-surface hover:text-foreground',
            soon && 'cursor-not-allowed opacity-60 hover:bg-transparent',
        );

        if (s.overlay) {
            return (
                <button
                    key={s.id}
                    type="button"
                    className={cls}
                    onClick={() => openOverlay(s.overlay!)}
                >
                    {body}
                </button>
            );
        }
        if (soon) {
            return (
                <button
                    key={s.id}
                    type="button"
                    aria-disabled="true"
                    className={cls}
                    title={t('settings_hub.next_phase')}
                    onClick={() =>
                        toast.info(t('settings_hub.next_phase_toast'))
                    }
                >
                    {body}
                </button>
            );
        }

        return (
            <Link
                key={s.id}
                href={s.href!}
                className={cls}
                aria-current={active ? 'page' : undefined}
                preserveScroll
                onClick={(e) => goAnchor(e, s.href!)}
            >
                {body}
            </Link>
        );
    };

    return (
        <HubContext.Provider value={api}>
            <div className="flex min-h-0 w-full flex-1 flex-col bg-background md:overflow-clip">
                <header className="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b bg-surface p-3.5 select-none">
                    <div className="min-w-0">
                        <h1 className="text-base font-bold text-foreground">
                            {t('settings_hub.title')}
                        </h1>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {t('settings_hub.subtitle')}
                        </p>
                    </div>
                    <Button
                        type="button"
                        className="font-bold"
                        disabled={!spec || spec.processing}
                        title={
                            spec ? undefined : t('settings_hub.nothing_to_save')
                        }
                        onClick={() => spec?.save()}
                    >
                        <Check aria-hidden="true" />
                        {t('settings_hub.save')}
                        <Kbd keys="mod+s" onButton />
                    </Button>
                </header>

                {spec?.dirty ? (
                    <div
                        aria-live="polite"
                        className="flex shrink-0 items-center justify-between gap-2 border-b border-warning bg-warning-soft px-4 py-2 text-xs"
                    >
                        <div className="flex items-center gap-2 font-semibold text-foreground">
                            <span
                                className="size-2 animate-pulse rounded-full bg-warning"
                                aria-hidden="true"
                            />
                            <span>{t('settings_hub.unsaved')}</span>
                        </div>
                        <div className="flex items-center gap-2">
                            {spec.discard ? (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    className="h-6"
                                    onClick={spec.discard}
                                >
                                    {t('settings_hub.discard')}
                                </Button>
                            ) : null}
                            <Button
                                type="button"
                                size="sm"
                                className="h-6 font-bold"
                                disabled={spec.processing}
                                onClick={spec.save}
                            >
                                {t('settings_hub.save_short')}
                            </Button>
                        </div>
                    </div>
                ) : null}

                <div className="flex min-h-0 w-full flex-1 flex-col md:flex-row md:overflow-clip">
                    <nav
                        aria-label={t('settings_hub.menu')}
                        className="shrink-0 space-y-0.5 overflow-y-auto border-b bg-surface-sunken p-2 select-none max-md:max-h-56 md:w-64 md:border-r md:border-b-0"
                    >
                        <div className="px-2.5 py-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                            {t('settings_hub.menu')}
                        </div>
                        {sections
                            .filter((s) => s.href || s.overlay || showSoon)
                            .map(item)}
                        <div className="px-2.5 pt-3 pb-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                            {t('settings_hub.account')}
                        </div>
                        {ACCOUNT.map(item)}
                    </nav>
                    <div
                        ref={pane}
                        className="min-w-0 flex-1 bg-surface p-4 md:overflow-y-auto md:p-6"
                    >
                        {children}
                    </div>
                </div>
            </div>
        </HubContext.Provider>
    );
}
