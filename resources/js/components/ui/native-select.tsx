import type { SelectHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export function NativeSelect({ className, ...props }: SelectHTMLAttributes<HTMLSelectElement>) {
    return (
        <select
            className={cn(
                'flex h-11 w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm text-slate-950 shadow-sm outline-none transition focus-visible:border-blue-500 focus-visible:ring-4 focus-visible:ring-blue-500/10 disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}
