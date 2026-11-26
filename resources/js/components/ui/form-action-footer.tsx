import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function FormActionFooter({
    left,
    children,
    className,
}: {
    left?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-center gap-2 border-t border-neutral-100 pt-4',
                left ? 'justify-between' : 'justify-end',
                className,
            )}
        >
            {left && <div className="flex flex-wrap items-center gap-2">{left}</div>}
            <div className="ml-auto flex flex-wrap items-center justify-end gap-2">{children}</div>
        </div>
    );
}
