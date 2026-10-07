import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';

export function Field({
    children,
    error,
    htmlFor,
    label,
    required = false,
}: {
    children: ReactNode;
    error?: string;
    htmlFor: string;
    label: string;
    required?: boolean;
}) {
    return (
        <div className="space-y-1.5">
            <Label className="block" htmlFor={htmlFor}>
                {label}{required && <span className="ml-1 text-red-600" aria-hidden="true">*</span>}
            </Label>
            {children}
            {error && <p className="text-xs font-medium text-red-600" id={`${htmlFor}-error`}>{error}</p>}
        </div>
    );
}
