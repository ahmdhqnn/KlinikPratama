import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export function Card({ className, ...props }: HTMLAttributes<HTMLElement>) {
    return (
        <section
            className={cn(
                'rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-950/[0.025]',
                className,
            )}
            {...props}
        />
    );
}

export function CardHeader({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('space-y-1.5 p-5 sm:p-6', className)} {...props} />;
}

export function CardTitle({
    className,
    ...props
}: HTMLAttributes<HTMLHeadingElement>) {
    return (
        <h2
            className={cn('text-base font-semibold tracking-tight text-slate-950', className)}
            {...props}
        />
    );
}

export function CardDescription({
    className,
    ...props
}: HTMLAttributes<HTMLParagraphElement>) {
    return <p className={cn('text-sm text-slate-500', className)} {...props} />;
}

export function CardContent({
    className,
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('p-5 pt-0 sm:p-6 sm:pt-0', className)} {...props} />;
}
