import { cn } from '@/lib/utils';

export interface PriorityBadgeProps {
    triage?: string | null;
    priority?: string | null;
    className?: string;
}

const triageMap: Record<string, { label: string; subtext: string; pillClass: string; dotClass: string }> = {
    merah: {
        label: 'Merah',
        subtext: 'perlu respons segera',
        pillClass: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200 dark:bg-red-950/60 dark:text-red-300 dark:ring-red-800',
        dotClass: 'bg-red-600 dark:bg-red-400',
    },
    kuning: {
        label: 'Kuning',
        subtext: 'perlu penilaian lanjut',
        pillClass: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:ring-amber-800',
        dotClass: 'bg-amber-500 dark:bg-amber-400',
    },
    hijau: {
        label: 'Hijau',
        subtext: 'prioritas rutin',
        pillClass: 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:ring-emerald-800',
        dotClass: 'bg-emerald-600 dark:bg-emerald-400',
    },
};

export function PriorityBadge({ triage, priority, className }: PriorityBadgeProps) {
    if (!triage) {
        return (
            <div className={cn('flex flex-col items-center justify-center gap-0.5', className)}>
                <span className="text-xs text-neutral-400 dark:text-neutral-500">—</span>
            </div>
        );
    }

    const key = triage.toLowerCase();
    const config = triageMap[key] ?? {
        label: triage,
        subtext: priority ? `Prioritas: ${priority}` : 'Prioritas awal',
        pillClass: 'bg-neutral-100 text-neutral-700 ring-1 ring-inset ring-neutral-200 dark:bg-neutral-800 dark:text-neutral-300 dark:ring-neutral-700',
        dotClass: 'bg-neutral-500',
    };

    return (
        <div className={cn('flex flex-col items-center justify-center gap-1', className)}>
            <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-none', config.pillClass)}>
                <span className={cn('size-1.5 rounded-full', config.dotClass)} />
                {config.label}
            </span>
            <span className="text-[11px] leading-tight font-medium text-neutral-500 dark:text-neutral-400">
                {config.subtext}
            </span>
        </div>
    );
}
