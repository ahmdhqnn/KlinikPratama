import type { ReactNode } from 'react';
import { Skeleton } from '@/components/ui/skeleton';

export function SearchResultsSkeleton({ label }: { label: string }) {
    return (
        <div className="divide-y divide-neutral-200 rounded-xl border border-neutral-200 bg-surface" role="status">
            <span className="sr-only">{label}</span>
            {[0, 1, 2].map((row) => (
                <div className="grid gap-2 px-4 py-3" key={row}>
                    <Skeleton className="h-4 w-2/3" />
                    <Skeleton className="h-3 w-1/3" />
                </div>
            ))}
        </div>
    );
}

export function LoadingField({ children, loading, label }: { children: ReactNode; loading: boolean; label: string }) {
    return (
        <div className="relative">
            <div className={loading ? 'invisible' : undefined}>{children}</div>
            {loading && (
                <div className="absolute inset-0" role="status">
                    <span className="sr-only">{label}</span>
                    <Skeleton className="h-10 w-full rounded-lg" />
                </div>
            )}
        </div>
    );
}

export function PageSkeleton({ pathname }: { pathname: string }) {
    const dashboard = /^\/(?:dashboard|dokter|farmasi|perawat|pendaftaran)?\/?$/.test(pathname);
    const form = /\/(?:login|bantuan-akses|baru|create|edit|pendaftaran-(?:baru|lama)|profile|setting)\/?$/.test(pathname);
    const detail = /\/\d+(?:\/[^/]+)*\/?$/.test(pathname);

    return (
        <div className="space-y-5" data-page-skeleton>
            <div className="space-y-3">
                <Skeleton className="h-7 w-48 max-w-full sm:w-64" />
                <Skeleton className="h-4 w-64 max-w-full sm:w-80" />
            </div>
            {dashboard && (
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {[0, 1, 2, 3].map((card) => (
                        <div className="grid gap-3 rounded-xl bg-surface p-4 sm:p-5" key={card}>
                            <Skeleton className="size-8" />
                            <Skeleton className="h-3 w-3/4" />
                            <Skeleton className="h-6 w-12" />
                        </div>
                    ))}
                </div>
            )}
            {form || detail ? (
                <div className="grid gap-5 rounded-xl bg-surface p-4 sm:p-6">
                    <Skeleton className="h-5 w-40" />
                    <div className="grid gap-4 sm:grid-cols-2">
                        {[0, 1, 2, 3].map((field) => (
                            <div className="grid gap-2" key={field}>
                                <Skeleton className="h-3 w-24" />
                                <Skeleton className="h-10 w-full" />
                            </div>
                        ))}
                    </div>
                    <Skeleton className="h-10 w-28" />
                </div>
            ) : (
                <div className="divide-y divide-neutral-200 overflow-hidden rounded-xl bg-surface">
                    {[0, 1, 2, 3, 4].map((row) => (
                        <div className="grid grid-cols-[2fr_1fr] items-center gap-4 px-4 py-4 sm:grid-cols-[2fr_1fr_1fr_auto] sm:px-6" key={row}>
                            <Skeleton className="h-4 w-4/5" />
                            <Skeleton className="h-4 w-3/4" />
                            <Skeleton className="hidden h-4 w-2/3 sm:block" />
                            <Skeleton className="hidden size-7 sm:block" />
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
