import type { InputHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export function Input({ className, type = 'text', ...props }: InputHTMLAttributes<HTMLInputElement>) {
    return (
        <input
            type={type}
            className={cn(
                'flex h-10 w-full rounded-lg border border-neutral-200 bg-surface px-3.5 py-2 text-sm text-neutral-950 shadow-sm outline-none transition placeholder:text-neutral-400 focus-visible:border-neutral-500 focus-visible:ring-4 focus-visible:ring-neutral-500/10 disabled:cursor-not-allowed disabled:opacity-50',
                className,
            )}
            {...props}
        />
    );
}
