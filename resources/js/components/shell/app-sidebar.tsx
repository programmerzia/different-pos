import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, CircleHelp } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Kbd } from '@/components/kbd';
import { RowContextMenu } from '@/components/row-context-menu';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    activeNav,
    SIDEBAR_SECTIONS,
    type ActiveNav,
    type NavGroup,
} from '@/command/nav';
import { useUiStore } from '@/command/ui-store';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

/** Sidebar item context menu: Open / Open in new tab (generic row actions, `href` surface). */
function NavContext({
    id,
    href,
    children,
}: {
    id: string;
    href: string;
    children: React.ReactElement;
}) {
    return (
        <RowContextMenu entity="nav" row={{ id, __nav: true }} href={href}>
            {children}
        </RowContextMenu>
    );
}

/**
 * Mockup nav item (render_shell.py): h-8, px-2.5, rounded-lg, 12 px, 15 px icon; active = solid primary,
 * primary-foreground, semibold, shadow-xs. Touch screens keep a 44 px target.
 */
const ITEM =
    'h-8 gap-2 rounded-lg px-2.5 text-xs text-sidebar-foreground [&>svg]:mr-0.5 [&>svg]:size-3.75 [&>svg]:text-muted-foreground hover:bg-surface-sunken hover:text-foreground hover:[&>svg]:text-foreground ' +
    'data-[active=true]:bg-primary data-[active=true]:font-semibold data-[active=true]:text-primary-foreground data-[active=true]:shadow-xs data-[active=true]:[&>svg]:text-primary-foreground ' +
    'data-[active=true]:hover:bg-primary data-[active=true]:hover:text-primary-foreground active:bg-surface-sunken [@media(pointer:coarse)]:min-h-11';

function GroupItem({ group, active }: { group: NavGroup; active: ActiveNav }) {
    const { t } = useT();
    const can = useCan();
    const { state, setOpenMobile } = useSidebar();
    const label = t(group.labelKey);
    const children = (group.children ?? []).filter((c) => can(c.permission));
    const isActive = active?.groupId === group.id;
    const hint = group.chord ? `g ${group.chord}` : group.keys;
    const keycap = hint ? (
        <Kbd
            keys={hint}
            tone={isActive ? 'on-primary' : 'default'}
            className={cn(
                'ml-auto px-1 py-0 text-[10px] group-data-[collapsible=icon]:hidden',
                !isActive && 'bg-surface',
            )}
        />
    ) : null;
    const body = (
        <>
            <group.icon aria-hidden="true" />
            <span className="truncate">{label}</span>
            {keycap}
        </>
    );

    const link = (
        <SidebarMenuButton
            asChild
            isActive={isActive}
            tooltip={{ children: label }}
            className={ITEM}
        >
            {group.external ? (
                <a
                    href={group.href}
                    aria-current={isActive ? 'page' : undefined}
                >
                    {body}
                </a>
            ) : (
                <Link
                    href={group.href}
                    prefetch
                    aria-current={isActive ? 'page' : undefined}
                    onClick={() => setOpenMobile(false)}
                >
                    {body}
                </Link>
            )}
        </SidebarMenuButton>
    );

    if (children.length === 0 || state === 'collapsed') {
        return (
            <SidebarMenuItem>
                <NavContext id={group.id} href={group.href}>
                    {link}
                </NavContext>
            </SidebarMenuItem>
        );
    }

    return (
        <Collapsible
            asChild
            defaultOpen={isActive}
            className="group/collapsible"
        >
            <SidebarMenuItem>
                <div className="flex items-center gap-0.5">
                    <div className="min-w-0 flex-1">
                        <NavContext id={group.id} href={group.href}>
                            {link}
                        </NavContext>
                    </div>
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            aria-label={t('nav.toggle_group', { name: label })}
                            className="flex size-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-surface-sunken hover:text-foreground [@media(pointer:coarse)]:size-11"
                        >
                            <ChevronRight
                                className="size-3.5 transition-transform group-data-[state=open]/collapsible:rotate-90"
                                aria-hidden="true"
                            />
                        </button>
                    </CollapsibleTrigger>
                </div>
                <CollapsibleContent>
                    <SidebarMenuSub className="mx-4 gap-0.5 px-2 py-0.5">
                        {children.map((leaf) => {
                            const leafActive =
                                isActive && active?.leafId === leaf.id;

                            return (
                                <SidebarMenuSubItem key={leaf.id}>
                                    <NavContext id={leaf.id} href={leaf.href}>
                                        <SidebarMenuSubButton
                                            asChild
                                            size="sm"
                                            isActive={leafActive}
                                            className="h-7 rounded-lg px-2 text-xs text-muted-foreground hover:bg-surface-sunken hover:text-foreground data-[active=true]:bg-primary-soft data-[active=true]:font-semibold data-[active=true]:text-primary [@media(pointer:coarse)]:min-h-11"
                                        >
                                            <Link
                                                href={leaf.href}
                                                prefetch
                                                aria-current={
                                                    leafActive
                                                        ? 'page'
                                                        : undefined
                                                }
                                                onClick={() =>
                                                    setOpenMobile(false)
                                                }
                                            >
                                                <span>{t(leaf.labelKey)}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </NavContext>
                                </SidebarMenuSubItem>
                            );
                        })}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

/**
 * The mockup's "Active role" card (sidebar top). The demo's role-switching select is omitted: it shows
 * the signed-in user's role(s) and branch instead.
 */
function ActiveRoleCard() {
    const { t } = useT();
    const { auth } = usePage().props;
    const branch = auth.branches.find((b) => b.id === auth.branch_id);
    const role = auth.roles?.[0] ?? null;

    if (!auth.user) {
        return null;
    }

    return (
        <div className="px-2 group-data-[collapsible=icon]:hidden">
            <section
                aria-label={t('nav.active_role')}
                className="space-y-1 rounded-xl border bg-surface-sunken p-2 text-xs"
            >
                <div className="flex items-center justify-between gap-2 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                    <span>{t('nav.active_role')}</span>
                    {role ? (
                        <span className="truncate rounded-sm bg-primary-soft px-1.5 text-[9px] font-bold text-primary normal-case">
                            {role}
                        </span>
                    ) : null}
                </div>
                <div className="truncate rounded-lg border bg-surface p-1 font-semibold text-foreground">
                    {auth.user.name}
                    {branch ? (
                        <span className="font-normal text-muted-foreground">
                            {' · '}
                            {branch.name}
                        </span>
                    ) : null}
                </div>
            </section>
        </div>
    );
}

/** Permission-filtered navigation (shared `auth.permissions`); collapses to icons (Ctrl+B). */
export function AppSidebar() {
    const { t } = useT();
    const can = useCan();
    const { currentUrl } = useCurrentUrl();
    const openOverlay = useUiStore((s) => s.open);
    const sections = SIDEBAR_SECTIONS.map((section) => ({
        ...section,
        items: section.items.filter((g) => can(g.permission)),
    })).filter((section) => section.items.length > 0);
    const active = activeNav(currentUrl, sections);
    const navRef = useRef<HTMLDivElement>(null);

    // Only the nav area scrolls (the footer stays); keep the active item in view after each navigation.
    useEffect(() => {
        const item = navRef.current?.querySelector<HTMLElement>(
            '[aria-current="page"]',
        );
        item?.scrollIntoView?.({ block: 'nearest' });
    }, [currentUrl]);

    return (
        <Sidebar collapsible="icon" aria-label={t('nav.label')}>
            <SidebarContent ref={navRef} className="gap-3 py-2">
                <ActiveRoleCard />
                {sections.map((section) => (
                    <SidebarGroup key={section.id} className="px-2 py-0">
                        <SidebarGroupLabel className="mb-1 h-auto px-2.5 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                            {t(section.labelKey)}
                        </SidebarGroupLabel>
                        <SidebarMenu className="gap-0.5">
                            {section.items.map((group) => (
                                <GroupItem
                                    key={group.id}
                                    group={group}
                                    active={active}
                                />
                            ))}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>
            <SidebarFooter className="shrink-0 border-t p-2">
                <button
                    type="button"
                    onClick={() => openOverlay('shortcuts')}
                    title={`${t('nav.shortcuts_guide')} (?)`}
                    className="flex h-7 w-full items-center justify-between gap-2 rounded-md px-2 text-xs text-muted-foreground hover:bg-surface-sunken hover:text-foreground [@media(pointer:coarse)]:min-h-11"
                >
                    <span className="flex items-center gap-2">
                        <CircleHelp className="size-3.5" aria-hidden="true" />
                        <span className="group-data-[collapsible=icon]:hidden">
                            {t('nav.shortcuts_guide')}
                        </span>
                    </span>
                    <Kbd
                        keys="?"
                        className="text-[10px] group-data-[collapsible=icon]:hidden"
                    />
                </button>
            </SidebarFooter>
        </Sidebar>
    );
}
