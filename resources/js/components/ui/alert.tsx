import { cva, type VariantProps } from 'class-variance-authority';
import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const alertVariants = cva('relative w-full rounded-xl border px-4 py-3 text-sm [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-3.5 [&>svg]:size-4 [&>svg~*]:pl-7', {
    variants: {
        variant: {
            default: 'border-blue-200 bg-blue-50 text-blue-950 [&>svg]:text-blue-700',
            destructive: 'border-red-200 bg-red-50 text-red-950 [&>svg]:text-red-700',
            warning: 'border-amber-200 bg-amber-50 text-amber-950 [&>svg]:text-amber-700',
            success: 'border-emerald-200 bg-emerald-50 text-emerald-950 [&>svg]:text-emerald-700',
        },
    },
    defaultVariants: { variant: 'default' },
});

type AlertProps = HTMLAttributes<HTMLDivElement> & VariantProps<typeof alertVariants>;

export function Alert({ className, variant, role, ...props }: AlertProps) {
    return <div className={cn(alertVariants({ variant }), className)} role={role ?? (variant === 'destructive' ? 'alert' : 'status')} {...props} />;
}

export function AlertTitle({ className, ...props }: HTMLAttributes<HTMLHeadingElement>) {
    return <h3 className={cn('mb-1 font-semibold leading-none tracking-tight', className)} {...props} />;
}

export function AlertDescription({ className, ...props }: HTMLAttributes<HTMLParagraphElement>) {
    return <p className={cn('text-sm leading-5 opacity-90', className)} {...props} />;
}
