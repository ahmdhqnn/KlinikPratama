import { createContext, useContext, type AriaAttributes, type ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

const FieldContext = createContext<{ errorId?: string; required?: boolean }>({});

export const fieldControlInvalidClassName = 'aria-invalid:border-red-500 aria-invalid:focus-visible:ring-red-500/10';

export function useFieldControl(props: AriaAttributes): AriaAttributes {
    const field = useContext(FieldContext);
    const descriptionIds = [...new Set([props['aria-describedby'], field.errorId].filter(Boolean).join(' ').split(' ').filter(Boolean))].join(' ');

    return {
        'aria-describedby': descriptionIds || undefined,
        'aria-invalid': field.errorId ? true : props['aria-invalid'],
        'aria-required': field.required || props['aria-required'] || undefined,
    };
}

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
        <FieldContext.Provider value={{ errorId: error ? `${htmlFor}-error` : undefined, required }}>
            <div className={cn('grid min-w-0 content-start gap-2', className)}>
                <Label htmlFor={htmlFor}>
                    {label}{required && <span className="ml-1 text-red-600" aria-hidden="true">*</span>}
                </Label>
                <div className="min-w-0">{children}</div>
                {error && <p className="text-xs font-medium text-red-600" id={`${htmlFor}-error`} role="alert">{error}</p>}
            </div>
        </FieldContext.Provider>
    );
}
