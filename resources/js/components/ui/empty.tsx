import type { ReactNode } from 'react';
import { FileText } from 'lucide-react';
import { cn } from '@/lib/utils';

const sizeClasses = {
    default: 'flex min-h-44 flex-col items-center justify-center gap-2.5 rounded-2xl border border-neutral-200/80 bg-gradient-to-b from-surface to-neutral-50 px-5 py-8 text-center',
    compact: 'flex min-h-0 flex-col items-center justify-center gap-2.5 px-5 py-6 text-center',
};

export function Empty({
    className,
    icon,
    title,
    description,
    action,
    eyebrow = 'Data kosong',
    size = 'default',
}: {
    className?: string;
    icon?: ReactNode | null;
    title: string;
    description?: string;
    action?: ReactNode;
    eyebrow?: string | null;
    size?: 'default' | 'compact';
}) {
    return (
        <div className={cn(sizeClasses[size], className)} role="status">
            {icon !== null && (
                <span className="flex size-10 items-center justify-center rounded-full bg-neutral-900 text-neutral-50 shadow-sm shadow-inverse/15">
                    {icon === undefined ? <FileText aria-hidden="true" className="size-[18px]" /> : icon}
                </span>
            )}
            {eyebrow && (
                <span className="rounded-full border border-neutral-200 bg-surface px-2.5 py-1 text-[10px] font-medium uppercase tracking-[0.1em] text-neutral-500">
                    {eyebrow}
                </span>
            )}
            <div className="max-w-sm space-y-1">
                <h3 className="text-base font-semibold tracking-tight text-neutral-950">{title}</h3>
                <p className="text-sm leading-5 text-neutral-500">{description ?? 'Konten yang tersedia akan ditampilkan di sini.'}</p>
            </div>
            {action}
        </div>
    );
}
