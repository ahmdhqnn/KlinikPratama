import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';

export function StatCard({
    label,
    value,
    description,
    icon,
    iconClassName,
}: {
    label: string;
    value: number | string;
    description: string;
    icon: ReactNode;
    iconClassName: string;
}) {
    return (
        <Card>
            <CardContent className="flex min-h-32 items-center justify-between gap-4 p-5 sm:p-6">
                <div className="min-w-0">
                    <p className="text-sm font-medium text-neutral-500">{label}</p>
                    <p className="mt-3 truncate text-2xl font-semibold tracking-tight text-neutral-950 sm:text-3xl">
                        {typeof value === 'number' ? new Intl.NumberFormat('id-ID').format(value) : value}
                    </p>
                    <p className="mt-2 text-xs text-neutral-500">{description}</p>
                </div>
                <span className={`flex size-12 shrink-0 items-center justify-center rounded-xl ${iconClassName}`}>
                    {icon}
                </span>
            </CardContent>
        </Card>
    );
}
