import type { ComponentProps } from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cn } from '@/lib/utils';

export function Sidebar({ className, ...props }: ComponentProps<'aside'>) {
    return <aside className={cn('flex min-h-0 flex-col bg-surface text-neutral-800', className)} {...props} />;
}

export function SidebarHeader({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('flex h-[4.5rem] shrink-0 items-center border-b border-neutral-100 px-5', className)} {...props} />;
}

export function SidebarContent({ className, ...props }: ComponentProps<'nav'>) {
    return <nav className={cn('min-h-0 flex-1 space-y-7 overflow-y-auto px-3 py-5', className)} {...props} />;
}

export function SidebarGroup({ className, ...props }: ComponentProps<'section'>) {
    return <section className={cn('space-y-2', className)} {...props} />;
}

export function SidebarGroupLabel({ className, ...props }: ComponentProps<'h2'>) {
    return <h2 className={cn('px-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-neutral-400', className)} {...props} />;
}

export function SidebarMenu({ className, ...props }: ComponentProps<'ul'>) {
    return <ul className={cn('space-y-1', className)} {...props} />;
}

export function SidebarMenuItem({ className, ...props }: ComponentProps<'li'>) {
    return <li className={className} {...props} />;
}

export function SidebarMenuButton({ className, isActive = false, asChild = false, ...props }: ComponentProps<'button'> & { isActive?: boolean; asChild?: boolean }) {
    const Component = asChild ? Slot : 'button';

    return <Component className={cn('flex min-h-10 w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500', isActive ? 'bg-neutral-100 text-neutral-900 ' : 'text-neutral-600 hover:bg-neutral-100 hover:text-neutral-950', className)} {...props} />;
}

export function SidebarFooter({ className, ...props }: ComponentProps<'div'>) {
    return <div className={cn('shrink-0 border-t border-neutral-100 p-3', className)} {...props} />;
}
