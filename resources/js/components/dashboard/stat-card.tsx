import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';

export function StatCard({
    label,
    value,
    description,
    icon,
    iconClassName,
    href,
}: {
    label: string;
    value: number | string;
    description: string;
    icon: ReactNode;
    iconClassName: string;
    href?: string;
}) {
    const card = (
        <Card className={href ? 'h-full min-w-0 transition-colors group-hover:border-neutral-300' : 'h-full min-w-0'}>
            <CardContent className="flex min-h-32 h-full items-center justify-between gap-4 p-5 sm:p-6">
                <div className="min-w-0">
                    <p className="text-sm font-medium text-neutral-500 print:text-neutral-600">{label}</p>
                    <p className="mt-3 truncate text-2xl font-semibold tracking-tight text-neutral-950 print:text-black sm:text-3xl">
                        {typeof value === 'number' ? new Intl.NumberFormat('id-ID').format(value) : value}
                    </p>
                    <p className="mt-2 text-xs text-neutral-500 print:text-neutral-600">{description}</p>
                </div>
                <span className={`flex size-12 shrink-0 items-center justify-center rounded-xl border border-neutral-200/70 ${iconClassName}`}>
                    {icon}
                </span>
            </CardContent>
        </Card>
    );

    if (!href) {
        return card;
    }

    return <Link aria-label={`${label}: ${typeof value === 'number' ? new Intl.NumberFormat('id-ID').format(value) : value}. ${description}`} className="group block h-full rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 focus-visible:ring-offset-2" href={href}>{card}</Link>;
}
