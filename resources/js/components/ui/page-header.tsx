import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { handleBackNavigation } from '@/lib/navigation';

export function PageHeaderWithActions({
    eyebrow,
    title,
    description,
    subtitle,
    backHref,
    backLabel = 'Kembali',
    actions,
    badges,
}: {
    eyebrow?: string;
    title: string;
    description?: string;
    subtitle?: string;
    backHref?: string;
    backLabel?: string;
    actions?: ReactNode;
    badges?: ReactNode;
}) {
    const subtext = description ?? subtitle;
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="min-w-0 flex-1">
                {eyebrow && <p className="text-sm font-medium text-neutral-700">{eyebrow}</p>}
                <div className="mt-1 flex flex-wrap items-center gap-2.5">
                    <h2 className="text-2xl font-semibold tracking-tight text-neutral-950">{title}</h2>
                    {badges}
                </div>
                {subtext && <p className="mt-1 max-w-3xl text-sm text-neutral-500">{subtext}</p>}
            </div>
            {(backHref || actions) && (
                <div className="flex flex-wrap items-center gap-2">
                    {backHref && (
                        <Button asChild size="sm" variant="secondary">
                            <Link href={backHref} onClick={(event) => handleBackNavigation(event, backHref)}>
                                <ArrowLeft aria-hidden="true" className="size-4" />
                                {backLabel}
                            </Link>
                        </Button>
                    )}
                    {actions}
                </div>
            )}
        </div>
    );
}
