import type { FormEvent, ReactNode } from 'react';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { cn } from '@/lib/utils';

export function TableFilterBar({
    onSubmit,
    actions,
    contextKeys,
    columns = 3,
    children,
}: {
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    actions?: ReactNode;
    contextKeys?: string[];
    columns?: 2 | 3 | 4;
    children: ReactNode;
}) {
    const gridClass =
        columns === 4
            ? 'sm:grid-cols-2 xl:grid-cols-4'
            : columns === 2
                ? 'sm:grid-cols-2'
                : 'sm:grid-cols-2 xl:grid-cols-3';

    return (
        <FilterPanel actions={actions} contextKeys={contextKeys}>
            <form className={cn('grid min-w-0 gap-4', gridClass)} onSubmit={onSubmit}>
                {children}
                <FilterSubmit />
            </form>
        </FilterPanel>
    );
}
