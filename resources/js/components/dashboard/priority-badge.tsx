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
        pillClass: 'border border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-950/60 dark:text-red-300',
        dotClass: 'bg-red-600 dark:bg-red-400',
    },
    kuning: {
        label: 'Kuning',
        subtext: 'perlu penilaian lanjut',
        pillClass: 'border border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
        dotClass: 'bg-amber-500 dark:bg-amber-400',
    },
    hijau: {
        label: 'Hijau',
        subtext: 'prioritas rutin',
        pillClass: 'border border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
        dotClass: 'bg-emerald-600 dark:bg-emerald-400',
    },
};

export function PriorityBadge({ triage, priority, className }: PriorityBadgeProps) {
    if (!triage) {
        return (
            <div className={cn('flex flex-col items-center justify-center gap-0.5', className)}>
                <span className="whitespace-nowrap text-xs text-neutral-500">Belum ditentukan</span>
            </div>
        );
    }

    const key = triage.toLowerCase();
    const config = triageMap[key] ?? {
        label: triage,
        subtext: priority ? `Prioritas: ${priority}` : 'Prioritas awal',
        pillClass: 'border border-neutral-300 bg-neutral-100 text-neutral-700',
        dotClass: 'bg-neutral-500',
    };

    return (
        <div className={cn('flex flex-col items-center justify-center gap-1', className)}>
            <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold leading-none', config.pillClass)}>
                <span className={cn('size-1.5 rounded-full', config.dotClass)} />
                {config.label}
            </span>
            <span className="text-[11px] leading-tight font-medium text-neutral-500">
                {config.subtext}
            </span>
        </div>
    );
}
