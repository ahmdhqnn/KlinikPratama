import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function SummaryStatGrid({
    ariaLabel,
    children,
    columns,
    className,
}: {
    ariaLabel: string;
    children: ReactNode;
    columns?: 2 | 3 | 4;
    className?: string;
}) {
    const gridClass =
        columns === 4
            ? 'grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4'
            : columns === 3
                ? 'grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-3'
                : columns === 2
                    ? 'grid items-stretch gap-4 sm:grid-cols-2'
                    : 'balanced-stats';

    return (
        <section aria-label={ariaLabel} className={cn(gridClass, className)}>
            {children}
        </section>
    );
}
