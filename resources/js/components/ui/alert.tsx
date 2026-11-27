import { cva, type VariantProps } from 'class-variance-authority';
import { X } from 'lucide-react';
import { useEffect, useState, type HTMLAttributes, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

const alertVariants = cva('relative w-full rounded-xl border px-4 py-3.5 text-sm [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-4 [&>svg]:size-4 [&>svg~*]:pl-7', {
    variants: {
        variant: {
            default: 'border-neutral-200 bg-neutral-50 text-neutral-900 [&>svg]:text-neutral-600 [&_[data-alert-content]>svg]:text-neutral-600',
            info: 'border-blue-200 bg-blue-50 text-blue-950 [&>svg]:text-blue-700 [&_[data-alert-content]>svg]:text-blue-700 dark:[&>svg]:text-blue-400 dark:[&_[data-alert-content]>svg]:text-blue-400',
            destructive: 'border-red-200 bg-red-50 text-red-950 [&>svg]:text-red-700 [&_[data-alert-content]>svg]:text-red-700 dark:[&>svg]:text-red-400 dark:[&_[data-alert-content]>svg]:text-red-400',
            warning: 'border-amber-200 bg-amber-50 text-amber-950 [&>svg]:text-amber-700 [&_[data-alert-content]>svg]:text-amber-700 dark:[&>svg]:text-amber-400 dark:[&_[data-alert-content]>svg]:text-amber-400',
            success: 'border-emerald-200 bg-emerald-50 text-emerald-950 [&>svg]:text-emerald-700 [&_[data-alert-content]>svg]:text-emerald-700 dark:[&>svg]:text-emerald-400 dark:[&_[data-alert-content]>svg]:text-emerald-400',
        },
    },
    defaultVariants: { variant: 'default' },
});

type AlertProps = HTMLAttributes<HTMLDivElement> & VariantProps<typeof alertVariants> & {
    action?: ReactNode;
    dismissLabel?: string;
    dismissible?: boolean;
    persistent?: boolean;
    autoDismissMs?: number;
    onDismiss?: () => void;
};

export function Alert({
    className,
    variant = 'default',
    role,
    children,
    action,
    dismissLabel,
    dismissible = true,
    persistent = false,
    autoDismissMs,
    onDismiss,
    ...props
}: AlertProps) {
    const [dismissed, setDismissed] = useState(false);
    const [paused, setPaused] = useState(false);

    const defaultDuration = variant === 'destructive' ? 8000 : variant === 'warning' ? 7000 : 4500;
    const duration = persistent || !dismissible || autoDismissMs === 0 ? undefined : (autoDismissMs ?? defaultDuration);
    const showClose = dismissible;
    const closeLabel = dismissLabel ?? 'Tutup pesan';

    useEffect(() => {
        if (!duration || paused || dismissed) return;
        const timer = window.setTimeout(() => {
            setDismissed(true);
            onDismiss?.();
        }, duration);

        return () => window.clearTimeout(timer);
    }, [duration, paused, dismissed, onDismiss]);

    if (dismissed) return null;

    return (
        <div
            className={cn(
                alertVariants({ variant }),
                showClose && 'min-h-12 pr-12',
                action && 'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between',
                className,
            )}
            role={role ?? (variant === 'destructive' ? 'alert' : 'status')}
            {...props}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={(event) => setPaused(event.currentTarget.contains(document.activeElement))}
            onFocus={() => setPaused(true)}
            onBlur={(event) => setPaused(event.currentTarget.matches(':hover') || event.currentTarget.contains(event.relatedTarget))}
        >
            {action ? (
                <>
                    <div className="relative min-w-0 flex-1 [&>svg]:absolute [&>svg]:left-0 [&>svg]:top-0.5 [&>svg]:size-4 [&>svg~*]:pl-7" data-alert-content="">
                        {children}
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center gap-2 sm:pl-2">{action}</div>
                </>
            ) : children}
            {showClose && (
                <button
                    aria-label={closeLabel}
                    className="absolute right-2 top-2 inline-flex size-8 items-center justify-center rounded-md text-current opacity-70 transition hover:bg-black/5 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-neutral-500 dark:hover:bg-white/10 [&:is(button)]:pl-0"
                    onClick={() => {
                        setDismissed(true);
                        onDismiss?.();
                    }}
                    type="button"
                >
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
