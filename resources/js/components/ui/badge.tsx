import { cva, type VariantProps } from 'class-variance-authority';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold leading-none',
    {
        variants: {
            variant: {
                default: 'bg-neutral-100 text-neutral-700',
                waiting: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200',
                screening: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-200',
                examination: 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
                pharmacy: 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-200',
                complete: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200',
                cancelled: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200',
                notification: 'h-5 min-w-5 justify-center bg-neutral-900 px-1.5 py-0 text-[10px] text-neutral-50 tabular-nums',
            },
        },
        defaultVariants: {
            variant: 'default',
        },
    },
);

type BadgeProps = HTMLAttributes<HTMLSpanElement> & VariantProps<typeof badgeVariants>;

export function Badge({ className, variant, ...props }: BadgeProps) {
    return <span className={cn(badgeVariants({ variant }), className)} {...props} />;
}
