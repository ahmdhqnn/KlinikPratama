import type { ReactNode } from 'react';

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
            <label className="block text-sm font-medium text-slate-700" htmlFor={htmlFor}>
                {label}{required && <span className="ml-1 text-red-600" aria-hidden="true">*</span>}
            </label>
            {children}
            {error && <p className="text-xs font-medium text-red-600" id={`${htmlFor}-error`}>{error}</p>}
        </div>
    );
}
