import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export function Field({
    children,
    error,
    htmlFor,
    label,
    required = false,
    className,
}: {
    children: ReactNode;
    className?: string;
    error?: string;
    htmlFor: string;
    label: string;
    required?: boolean;
}) {
    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={htmlFor}>
                {label}{required && <span className="ml-1 text-red-600" aria-hidden="true">*</span>}
            </Label>
            {children}
            {error && <p className="text-xs font-medium text-red-600" id={`${htmlFor}-error`}>{error}</p>}
        </div>
    );
}
