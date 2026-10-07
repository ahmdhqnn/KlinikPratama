import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import type { ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

const buttonVariants = cva(
    'inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                default: 'bg-blue-600 text-white shadow-sm hover:bg-blue-700',
                secondary: 'border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50',
                ghost: 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                destructive: 'bg-red-600 text-white shadow-sm hover:bg-red-700',
                link: 'text-blue-700 underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-10 px-4 py-2',
                sm: 'h-9 rounded-md px-3',
                lg: 'h-11 rounded-lg px-6',
                icon: 'size-10',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> &
    VariantProps<typeof buttonVariants> & {
        asChild?: boolean;
    };

export function Button({
    asChild = false,
    className,
    size,
    variant,
    ...props
}: ButtonProps) {
    const Component = asChild ? Slot : 'button';

    return (
        <Component
            className={cn(buttonVariants({ className, size, variant }))}
            {...props}
        />
    );
}
