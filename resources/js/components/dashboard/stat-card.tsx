import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function StatCard({
    label,
    value,
    description,
    icon,
    iconClassName,
    href,
    className,
}: {
    label: string;
    value: number | string;
    description: string;
    icon: ReactNode;
    iconClassName: string;
    href?: string;
    className?: string;
}) {
    const displayValue = typeof value === 'number' ? new Intl.NumberFormat('id-ID').format(value) : value;
    const card = (
        <Card className={cn(href ? 'h-full min-w-0 transition-colors group-hover:bg-neutral-50' : 'h-full min-w-0', className)}>
            <CardContent className="flex h-full min-h-32 items-center justify-between gap-4 p-5 sm:p-6">
                <div className="flex min-w-0 flex-1 flex-col">
                    <p className="line-clamp-1 text-sm font-medium leading-5 text-neutral-500 print:text-neutral-600" title={label}>{label}</p>
                    <p className="mt-2 truncate text-2xl font-semibold leading-tight tracking-tight tabular-nums text-neutral-950 print:text-black sm:text-[1.75rem]" title={String(displayValue)}>
                        {displayValue}
                    </p>
                    <p className="mt-2 line-clamp-2 text-xs leading-4 text-neutral-500 print:text-neutral-600" title={description}>{description}</p>
                </div>
                <span aria-hidden="true" className={`flex size-10 shrink-0 items-center justify-center rounded-xl ${iconClassName}`}>
                    {icon}
                </span>
            </CardContent>
        </Card>
    );

    if (!href) {
        return card;
    }

    return <Link aria-label={`${label}: ${displayValue}. ${description}`} className="group block h-full min-w-0 rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 focus-visible:ring-offset-2" href={href}>{card}</Link>;
}
