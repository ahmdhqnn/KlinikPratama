import { fieldControlInvalidClassName, useFieldControl } from '@/components/ui/field';
import type { TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export function Textarea({ className, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    const accessibility = useFieldControl(props);
    return (
        <textarea
            className={cn(
                'clinic-canvas-control flex min-h-24 min-w-0 w-full resize-y rounded-lg border border-neutral-200 bg-surface px-3.5 py-2.5 text-sm leading-relaxed text-neutral-950 outline-none transition placeholder:text-neutral-400 focus-visible:border-neutral-500 focus-visible:ring-4 focus-visible:ring-neutral-500/10 disabled:cursor-not-allowed disabled:opacity-50',
                fieldControlInvalidClassName,
                className,
            )}
            {...props}
            placeholder={props.placeholder ?? 'Catat keterangan yang relevan'}
            {...accessibility}
        />
    );
}
