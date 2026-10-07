import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export function PageTitle({ className, ...props }: ComponentProps<'h1'>) {
    return <h1 className={cn('text-xl font-semibold tracking-tight text-neutral-950 sm:text-2xl', className)} {...props} />;
}

export function SectionTitle({ className, ...props }: ComponentProps<'h2'>) {
    return <h2 className={cn('text-base font-semibold tracking-tight text-neutral-950', className)} {...props} />;
}

export function Muted({ className, ...props }: ComponentProps<'p'>) {
    return <p className={cn('text-sm leading-6 text-neutral-500', className)} {...props} />;
}
