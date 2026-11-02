import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { RowContextMenu } from '@/components/row-context-menu';
import { Kbd } from '@/components/kbd';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { NAV, type NavGroup } from '@/command/nav';
import { useCan } from '@/hooks/use-can';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useT } from '@/i18n';

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

function GroupItem({ group }: { group: NavGroup }) {
    const { t } = useT();
    const can = useCan();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { state, setOpenMobile } = useSidebar();
    const label = t(group.labelKey);
    const children = (group.children ?? []).filter((c) => can(c.permission));
    const active =
        isCurrentOrParentUrl(group.href) ||
        children.some((c) => isCurrentOrParentUrl(c.href));
    const hint = group.chord ? `g ${group.chord}` : group.keys;

    const link = (
        <SidebarMenuButton
            asChild
            isActive={active}
            tooltip={{ children: label }}
            className="min-h-11 md:min-h-8"
        >
            {group.external ? (
                <a href={group.href}>
                    <group.icon aria-hidden="true" />
                    <span>{label}</span>
                    {hint ? (
                        <Kbd
                            keys={hint}
                            className="ml-auto group-data-[collapsible=icon]:hidden"
                        />
                    ) : null}
                </a>
            ) : (
                <Link
                    href={group.href}
                    prefetch
                    onClick={() => setOpenMobile(false)}
                >
                    <group.icon aria-hidden="true" />
                    <span>{label}</span>
                    {hint ? (
                        <Kbd
                            keys={hint}
                            className="ml-auto group-data-[collapsible=icon]:hidden"
                        />
                    ) : null}
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
        <Collapsible asChild defaultOpen={active} className="group/collapsible">
            <SidebarMenuItem>
                <div className="flex items-center">
                    <div className="min-w-0 flex-1">
                        <NavContext id={group.id} href={group.href}>
                            {link}
                        </NavContext>
                    </div>
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            aria-label={t('nav.toggle_group', { name: label })}
                            className="flex size-11 items-center justify-center rounded-md text-sidebar-foreground hover:bg-sidebar-accent md:size-8"
                        >
                            <ChevronRight
                                className="size-4 transition-transform group-data-[state=open]/collapsible:rotate-90"
                                aria-hidden="true"
                            />
                        </button>
                    </CollapsibleTrigger>
                </div>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {children.map((leaf) => (
                            <SidebarMenuSubItem key={leaf.id}>
                                <NavContext id={leaf.id} href={leaf.href}>
                                    <SidebarMenuSubButton
                                        asChild
                                        isActive={isCurrentOrParentUrl(
                                            leaf.href,
                                        )}
                                        className="min-h-11 md:min-h-7"
                                    >
                                        <Link
                                            href={leaf.href}
                                            prefetch
                                            onClick={() => setOpenMobile(false)}
                                        >
                                            <span>{t(leaf.labelKey)}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </NavContext>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

/** Permission-filtered navigation (shared `auth.permissions`); collapses to icons (Ctrl+B). */
export function AppSidebar() {
    const { t } = useT();
    const can = useCan();
    const groups = NAV.filter((g) => can(g.permission));

    return (
        <Sidebar collapsible="icon" aria-label={t('nav.label')}>
            <SidebarContent>
                <SidebarGroup className="px-2 py-2">
                    <SidebarMenu>
                        {groups.map((group) => (
                            <GroupItem key={group.id} group={group} />
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>
        </Sidebar>
    );
}
