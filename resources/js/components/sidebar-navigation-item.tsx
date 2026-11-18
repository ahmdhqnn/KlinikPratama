import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import { Link } from '@inertiajs/react';
import { ChevronRight, type LucideIcon } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton } from '@/components/ui/sidebar';
import type { NavigationTarget } from '@/lib/navigation';

export interface NavigationLink extends NavigationTarget {
    label: string;
    icon: LucideIcon;
    badge?: number;
}

export interface NavigationBranch {
    label: string;
    icon: LucideIcon;
    children: NavigationLink[];
}

export type NavigationItem = NavigationLink | NavigationBranch;

export function navigationLinks(items: NavigationItem[]): NavigationLink[] {
    return items.flatMap((item) => 'children' in item ? item.children : [item]);
}

interface ItemProps {
    item: NavigationItem;
    activeHref: string | null;
    pathname: string;
    isCollapsed: boolean;
    onNavigate: () => void;
}

export function SidebarNavigationItem(props: ItemProps) {
    if ('children' in props.item) return <BranchItem {...props} item={props.item} />;
    const { item, activeHref, isCollapsed, onNavigate } = props;
    const Icon = item.icon;
    return <SidebarMenuItem><SidebarMenuButton asChild isActive={item.href === activeHref} className={isCollapsed ? 'justify-center px-2' : undefined}>
        <Link aria-current={item.href === activeHref ? 'page' : undefined} aria-label={item.badge ? `${item.label}, ${item.badge} memerlukan layanan` : item.label} className="relative" href={item.href} onClick={onNavigate} title={isCollapsed ? item.label : undefined}>
            <Icon aria-hidden="true" className="size-[18px] shrink-0" />
            {isCollapsed ? <span className="sr-only">{item.label}</span> : <span className="flex min-w-0 items-center gap-1.5"><span className="truncate">{item.label}</span><NavigationBadge count={item.badge} /></span>}
            {isCollapsed && <NavigationBadge count={item.badge} collapsed />}
        </Link>
    </SidebarMenuButton></SidebarMenuItem>;
}

function NavigationBadge({ count, collapsed = false }: { count?: number; collapsed?: boolean }) {
    return count ? <Badge aria-hidden="true" className={collapsed ? 'absolute right-0.5 top-0.5' : 'shrink-0'} variant="notification">{count > 99 ? '99+' : count}</Badge> : null;
}

function BranchItem({ item, activeHref, pathname, isCollapsed, onNavigate }: Omit<ItemProps, 'item'> & { item: NavigationBranch }) {
    const active = item.children.some((child) => child.href === activeHref);
    const [open, setOpen] = useState(active);
    const id = useId();
    const Icon = item.icon;
    const count = item.children.reduce((total, child) => total + (child.badge ?? 0), 0);
    useEffect(() => { if (active) setOpen(true); }, [active, pathname]);

    if (isCollapsed) return <SidebarMenuItem><DropdownMenu.Root>
        <DropdownMenu.Trigger asChild><SidebarMenuButton aria-label={count ? `${item.label}, ${count} memerlukan layanan` : item.label} className="relative justify-center px-2" isActive={active} title={item.label}>
            <Icon aria-hidden="true" className="size-[18px] shrink-0" /><span className="sr-only">{item.label}</span><NavigationBadge count={count} collapsed />
        </SidebarMenuButton></DropdownMenu.Trigger>
        <DropdownMenu.Portal><DropdownMenu.Content aria-label={item.label} side="right" align="start" sideOffset={12} className="z-50 max-h-[min(32rem,80vh)] w-64 overflow-y-auto rounded-xl border border-neutral-200 bg-surface p-1.5 text-neutral-900 shadow-lg">
            <DropdownMenu.Label className="px-2.5 py-2 text-xs font-semibold text-neutral-500">{item.label}</DropdownMenu.Label>
            {item.children.map((child) => <DropdownMenu.Item asChild key={child.href}><Link aria-label={child.badge ? `${child.label}, ${child.badge} memerlukan layanan` : child.label} aria-current={child.href === activeHref ? 'page' : undefined} className={`flex min-h-9 items-center justify-between gap-2 rounded-md px-2.5 py-2 text-sm outline-none focus:bg-neutral-100 ${child.href === activeHref ? 'bg-neutral-100 font-semibold' : ''}`} href={child.href} onClick={onNavigate}>{child.label}<NavigationBadge count={child.badge} /></Link></DropdownMenu.Item>)}
        </DropdownMenu.Content></DropdownMenu.Portal>
    </DropdownMenu.Root></SidebarMenuItem>;

    return <SidebarMenuItem>
        <SidebarMenuButton aria-controls={id} aria-expanded={open} aria-label={count ? `${item.label}, ${count} memerlukan layanan` : item.label} isActive={active} onClick={() => setOpen((value) => !value)} type="button">
            <Icon aria-hidden="true" className="size-[18px] shrink-0" /><span className="min-w-0 flex-1 truncate">{item.label}</span><NavigationBadge count={count} /><ChevronRight aria-hidden="true" className={`size-4 shrink-0 transition-transform ${open ? 'rotate-90' : ''}`} />
        </SidebarMenuButton>
        <SidebarMenuSub aria-label={item.label} hidden={!open} id={id}>
            {item.children.map((child) => <SidebarMenuItem key={child.href}><SidebarMenuSubButton asChild isActive={child.href === activeHref}>
                <Link aria-label={child.badge ? `${child.label}, ${child.badge} memerlukan layanan` : child.label} aria-current={child.href === activeHref ? 'page' : undefined} href={child.href} onClick={onNavigate}><span className="min-w-0 flex-1 whitespace-normal leading-5">{child.label}</span><NavigationBadge count={child.badge} /></Link>
            </SidebarMenuSubButton></SidebarMenuItem>)}
        </SidebarMenuSub>
    </SidebarMenuItem>;
}
