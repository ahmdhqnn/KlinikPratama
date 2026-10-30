import { cva, type VariantProps } from 'class-variance-authority';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const badgeVariants = cva(
    'inline-flex shrink-0 items-center whitespace-nowrap rounded-full border border-neutral-300 bg-transparent px-2.5 py-1 text-xs font-semibold leading-none',
    {
        variants: {
            variant: {
                default: 'text-neutral-700',
                waiting: 'text-neutral-700',
                screening: 'text-neutral-700',
                examination: 'text-neutral-700',
                pharmacy: 'text-neutral-700',
                complete: 'text-neutral-700',
                cancelled: 'border-red-300 text-red-700 dark:border-red-800 dark:text-red-300',
                warning: 'border-amber-300 text-amber-800 dark:border-amber-800 dark:text-amber-300',
                notification: 'h-5 min-w-5 justify-center border-transparent bg-neutral-900 px-1.5 py-0 text-[10px] text-neutral-50 tabular-nums',
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
