import { createContext, useContext, type AriaAttributes, type ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

const FieldContext = createContext<{ errorId?: string; required?: boolean }>({});

export const fieldControlInvalidClassName = 'aria-invalid:border-red-500 aria-invalid:focus-visible:ring-red-500/10';

export function useFieldControl(props: AriaAttributes, options: { omitRequired?: boolean } = {}): AriaAttributes {
    const field = useContext(FieldContext);
    const descriptionIds = [...new Set([props['aria-describedby'], field.errorId].filter(Boolean).join(' ').split(' ').filter(Boolean))].join(' ');

    return {
        'aria-describedby': descriptionIds || undefined,
        'aria-invalid': field.errorId ? true : props['aria-invalid'],
        // Roles such as combobox reject aria-required; those controls move the state
        // to a hidden native input instead (see Select).
        'aria-required': options.omitRequired ? undefined : (field.required || props['aria-required'] || undefined),
    };
}

export function useFieldRequired(props?: { required?: boolean }): boolean {
    const field = useContext(FieldContext);

    return Boolean(field.required || props?.required);
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
            <div className={cn('grid min-w-0 content-start gap-2', className)} data-field-invalid={error ? true : undefined} tabIndex={error ? -1 : undefined}>
                <Label htmlFor={htmlFor}>
                    {label}{required && <span className="ml-1 text-red-600" aria-hidden="true">*</span>}
                </Label>
                <div className="flex min-w-0 flex-col gap-2">{children}</div>
                {error && <p className="text-xs font-medium text-red-600" id={`${htmlFor}-error`} role="alert">{error}</p>}
            </div>
        </FieldContext.Provider>
    );
}
