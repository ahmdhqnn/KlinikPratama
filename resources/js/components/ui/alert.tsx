import { cva, type VariantProps } from 'class-variance-authority';
import { X } from 'lucide-react';
import { useState, type HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const alertVariants = cva('relative w-full rounded-xl border px-4 py-3 text-sm [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-3.5 [&>svg]:size-4 [&>svg~*]:pl-7', {
    variants: {
        variant: {
            default: 'border-neutral-200 bg-neutral-50 text-neutral-900 [&>svg]:text-neutral-600',
            destructive: 'border-red-200 bg-red-50 text-red-950 [&>svg]:text-red-700 dark:border-red-800 dark:bg-red-950/30 dark:text-red-200 dark:[&>svg]:text-red-400',
            warning: 'border-amber-200 bg-amber-50 text-amber-950 [&>svg]:text-amber-700 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200 dark:[&>svg]:text-amber-400',
            success: 'border-neutral-200 bg-neutral-50 text-neutral-900 [&>svg]:text-neutral-600',
        },
    },
    defaultVariants: { variant: 'default' },
});

type AlertProps = HTMLAttributes<HTMLDivElement> & VariantProps<typeof alertVariants> & { dismissLabel?: string };

export function Alert({ className, variant, role, children, dismissLabel, ...props }: AlertProps) {
    const [dismissed, setDismissed] = useState(false);

    if (dismissed) return null;

    return (
        <div className={cn(alertVariants({ variant }), dismissLabel && 'min-h-12 pr-14', className)} role={role ?? (variant === 'destructive' ? 'alert' : 'status')} {...props}>
            {children}
            {dismissLabel && (
                <button aria-label={dismissLabel} className="absolute right-2 top-2 inline-flex size-8 items-center justify-center rounded-md text-neutral-500 hover:bg-neutral-200 hover:text-neutral-900 focus-visible:outline-2 focus-visible:outline-neutral-500 [&:is(button)]:pl-0" onClick={() => setDismissed(true)} type="button">
                    <X aria-hidden="true" className="size-4" />
                </button>
            )}
        </div>
    );
}

export function AlertTitle({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) {
    return <h3 className={cn('mb-1 font-semibold leading-none tracking-tight', className)} {...props} />;
}

export function AlertDescription({ className, ...props }: HTMLAttributes<HTMLParagraphElement>) {
    return <p className={cn('text-sm leading-5 opacity-90', className)} {...props} />;
}
