import type { ReactNode } from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

export type PageSkeletonVariant =
    | 'auth-login'
    | 'auth-public'
    | 'dashboard'
    | 'dashboard-doctor'
    | 'report-hub'
    | 'patient-profile'
    | 'clinical-workspace'
    | 'settings-profile'
    | 'form'
    | 'form-tabs'
    | 'stock-detail'
    | 'detail'
    | 'print'
    | 'table';

export function resolvePageSkeletonVariant(pathname: string): PageSkeletonVariant {
    const cleanPath = pathname.split(/[?#]/, 1)[0].replace(/\/+$/, '') || '/';

    if (cleanPath === '/login') {
        return 'auth-login';
    }

    if (cleanPath === '/bantuan-akses' || cleanPath.startsWith('/verification-document/') || cleanPath.startsWith('/verifikasi/')) {
        return 'auth-public';
    }

    if (cleanPath === '/dokter') {
        return 'dashboard-doctor';
    }

    if (/^\/(?:dashboard|pendaftaran|perawat\/dashboard|farmasi\/dashboard)?$/.test(cleanPath)) {
        return 'dashboard';
    }

    if (cleanPath === '/admin/laporan') {
        return 'report-hub';
    }

    if (/\/(?:cetak|cetak-antrian)$/.test(cleanPath)) {
        return 'print';
    }

    if (/^\/pelayanan\/pasien\/\d+$/.test(cleanPath)) {
        return 'patient-profile';
    }

    if (
        /^\/pelayanan\/(?:pemeriksaan|screening|tindakan-perawat|farmasi)\/\d+(?:\/review)?$/.test(cleanPath) ||
        /^\/pelayanan\/pasien\/\d+\/rekam-medis$/.test(cleanPath)
    ) {
        return 'clinical-workspace';
    }

    if (cleanPath === '/setting' || cleanPath === '/profile') {
        return 'settings-profile';
    }

    if (/^\/pendaftaran\/pendaftaran-(?:baru|lama)$/.test(cleanPath)) {
        return 'form-tabs';
    }

    if (
        /\/(?:baru|create|edit|persetujuan-umum|persetujuan|tanda-tangan|kualifikasi-farmasi)$/.test(cleanPath) ||
        /\/diagnosa\/koreksi$/.test(cleanPath) ||
        /^\/persuratan\/(?:terbitkan|pelepasan-informasi)\/\d+$/.test(cleanPath)
    ) {
        return 'form';
    }

    if (/^\/stok\/persediaan\/\d+$/.test(cleanPath) || /^\/master\/obat\/\d+\/stok$/.test(cleanPath)) {
        return 'stock-detail';
    }

    if (/^\/(?:kunjungan|pelayanan\/kunjungan|pendaftaran\/kunjungan|kepesertaan|master\/poliklinik|master\/terminologi-klinis|farmasi\/pasien)\/\d+$/.test(cleanPath)) {
        return 'detail';
    }

    return 'table';
}

export function SearchResultsSkeleton({ label, rows = 3 }: { label: string; rows?: number }) {
    return (
        <div className="divide-y divide-neutral-100 rounded-xl border border-neutral-200 bg-surface" role="status">
            <span className="sr-only">{label}</span>
            {Array.from({ length: rows }, (_, row) => (
                <div className="flex items-center justify-between gap-3 px-4 py-3" key={row}>
                    <div className="min-w-0 flex-1 space-y-2">
                        <Skeleton className={cn('h-4', row === 0 ? 'w-3/4' : row === 1 ? 'w-2/3' : 'w-4/5')} />
                        <Skeleton className="h-3 w-1/3" />
                    </div>
                    <Skeleton className="h-5 w-14 shrink-0 rounded-full" />
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

export function ConsentDialogSkeleton({ label = 'Memuat persetujuan…' }: { label?: string }) {
    return (
        <div className="space-y-5" role="status">
            <span className="sr-only">{label}</span>
            <div className="flex items-start gap-3 rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3.5">
                <Skeleton className="mt-0.5 size-4 shrink-0 rounded" />
                <div className="min-w-0 flex-1 space-y-2">
                    <Skeleton className="h-4 w-44" />
                    <Skeleton className="h-3.5 w-full max-w-lg" />
                </div>
            </div>
            <Card className="border border-neutral-100">
                <CardHeader className="border-b border-neutral-100">
                    <Skeleton className="h-5 w-48" />
                    <Skeleton className="h-4 w-72 max-w-full" />
                </CardHeader>
                <CardContent className="grid gap-4 pt-5 sm:grid-cols-2 lg:grid-cols-4 sm:pt-6">
                    {[0, 1, 2, 3].map((item) => (
                        <div className="space-y-2" key={item}>
                            <Skeleton className="h-3 w-20" />
                            <Skeleton className="h-4 w-28" />
                        </div>
                    ))}
                </CardContent>
            </Card>
            <Card className="border border-neutral-100">
                <CardHeader className="border-b border-neutral-100">
                    <Skeleton className="h-5 w-52" />
                    <Skeleton className="h-4 w-80 max-w-full" />
                </CardHeader>
                <CardContent className="space-y-5 pt-5 sm:pt-6">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Skeleton className="h-4 w-36" />
                            <Skeleton className="h-10 w-full rounded-lg" />
                        </div>
                        <div className="space-y-2">
                            <Skeleton className="h-4 w-40" />
                            <Skeleton className="h-20 w-full rounded-lg" />
                        </div>
                    </div>
                    <div className="flex justify-end border-t border-neutral-100 pt-4">
                        <Skeleton className="h-10 w-36 rounded-lg" />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function PageHeaderSkeleton({ actionCount = 0 }: { actionCount?: 0 | 1 | 2 }) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="min-w-0 flex-1 space-y-2">
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-8 w-56 max-w-full sm:w-72" />
                <Skeleton className="h-4 w-72 max-w-full sm:w-96" />
            </div>
            {actionCount > 0 && (
                <div className="flex flex-wrap items-center gap-2">
                    {actionCount === 2 && <Skeleton className="h-9 w-28 rounded-lg" />}
                    <Skeleton className="h-9 w-36 rounded-lg" />
                </div>
            )}
        </div>
    );
}

function StatCardSkeleton({ count = 4 }: { count?: 2 | 3 | 4 }) {
    const gridClass =
        count === 4
            ? 'grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4'
            : count === 3
                ? 'grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-3'
                : 'grid items-stretch gap-4 sm:grid-cols-2';

    return (
        <div className={gridClass} data-skeleton-stats="">
            {Array.from({ length: count }, (_, card) => (
                <Card className="h-full min-w-0" key={card}>
                    <CardContent className="flex h-full min-h-32 items-center justify-between gap-4 p-5 sm:p-6">
                        <div className="flex min-w-0 flex-1 flex-col">
                            <Skeleton className={cn('h-4', card % 2 === 0 ? 'w-28' : 'w-24')} />
                            <Skeleton className="mt-2 h-7 w-16" />
                            <Skeleton className={cn('mt-2.5 h-3.5', card % 2 === 0 ? 'w-4/5' : 'w-3/4')} />
                        </div>
                        <Skeleton className="size-10 shrink-0 rounded-xl" />
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function CardHeaderSkeleton({ withBadge = false }: { withBadge?: boolean }) {
    return (
        <CardHeader className="border-b border-neutral-100">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 flex-1 items-start gap-3">
                    <Skeleton className="size-10 shrink-0 rounded-xl" />
                    <div className="min-w-0 flex-1 space-y-2">
                        <Skeleton className="h-5 w-44 max-w-full" />
                        <Skeleton className="h-4 w-64 max-w-full" />
                    </div>
                </div>
                {withBadge && <Skeleton className="h-6 w-28 shrink-0 rounded-full" />}
            </div>
        </CardHeader>
    );
}

function FilterToolbarSkeleton({ withSecondary = true }: { withSecondary?: boolean }) {
    return (
        <div className="flex flex-wrap items-center justify-end gap-2">
            {withSecondary && <Skeleton className="h-9 w-32 rounded-lg" />}
            <Skeleton className="h-9 w-24 rounded-lg" />
        </div>
    );
}

function TableCardSkeleton({ rows = 5, withFooterLink = false }: { rows?: number; withFooterLink?: boolean }) {
    return (
        <Card className="overflow-hidden" data-skeleton-table="">
            <CardHeaderSkeleton />
            <CardContent className="p-0">
                <div className="grid grid-cols-[2fr_1fr] items-center gap-4 border-b border-neutral-100 bg-neutral-50/70 px-5 py-3 sm:grid-cols-[2fr_1fr_1fr_auto] sm:px-6">
                    <Skeleton className="h-3.5 w-24" />
                    <Skeleton className="h-3.5 w-20" />
                    <Skeleton className="hidden h-3.5 w-16 sm:block" />
                    <Skeleton className="hidden h-3.5 w-12 sm:block" />
                </div>
                <div className="divide-y divide-neutral-100">
                    {Array.from({ length: rows }, (_, row) => (
                        <div className="grid grid-cols-[2fr_1fr] items-center gap-4 px-5 py-4 sm:grid-cols-[2fr_1fr_1fr_auto] sm:px-6" key={row}>
                            <div className="min-w-0 space-y-2">
                                <Skeleton className={cn('h-4', row % 2 === 0 ? 'w-3/4' : 'w-2/3')} />
                                <Skeleton className="h-3 w-1/2" />
                            </div>
                            <div className="space-y-2">
                                <Skeleton className="h-4 w-24" />
                                <Skeleton className="h-3 w-16" />
                            </div>
                            <Skeleton className="hidden h-6 w-20 rounded-full sm:block" />
                            <div className="hidden items-center justify-end gap-1.5 sm:flex">
                                <Skeleton className="size-8 rounded-lg" />
                                <Skeleton className="size-8 rounded-lg" />
                            </div>
                        </div>
                    ))}
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 px-5 py-3.5 sm:px-6">
                    <Skeleton className="h-4 w-40" />
                    {withFooterLink ? (
                        <Skeleton className="h-9 w-36 rounded-lg" />
                    ) : (
                        <div className="flex items-center gap-2">
                            <Skeleton className="h-8 w-20 rounded-lg" />
                            <Skeleton className="h-8 w-20 rounded-lg" />
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function FormCardSkeleton({ fields = 6, columns = 2 }: { fields?: number; columns?: 2 | 3 }) {
    return (
        <Card className="overflow-hidden" data-skeleton-form="">
            <CardHeaderSkeleton />
            <CardContent className="space-y-6 p-5 sm:p-6">
                <div className={cn('grid gap-4', columns === 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2')}>
                    {Array.from({ length: fields }, (_, field) => (
                        <div className="space-y-2" key={field}>
                            <Skeleton className={cn('h-4', field % 2 === 0 ? 'w-28' : 'w-36')} />
                            <Skeleton className="h-10 w-full rounded-lg" />
                        </div>
                    ))}
                </div>
                <div className="flex justify-end gap-2 border-t border-neutral-100 pt-5">
                    <Skeleton className="h-10 w-36 rounded-lg" />
                </div>
            </CardContent>
        </Card>
    );
}

function DetailCardSkeleton({ entries = 6, columns = 2, withBadge = false }: { entries?: number; columns?: 2 | 3; withBadge?: boolean }) {
    return (
        <Card className="overflow-hidden" data-skeleton-detail="">
            <CardHeaderSkeleton withBadge={withBadge} />
            <CardContent className="p-5 sm:p-6">
                <div className={cn('grid gap-x-6 gap-y-5', columns === 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : 'sm:grid-cols-2')}>
                    {Array.from({ length: entries }, (_, item) => (
                        <div className="space-y-2" key={item}>
                            <Skeleton className="h-3.5 w-24" />
                            <Skeleton className={cn('h-4', item % 2 === 0 ? 'w-4/5' : 'w-2/3')} />
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function HeroPatientCardSkeleton() {
    return (
        <Card className="overflow-hidden" data-skeleton-hero="">
            <CardContent className="p-0">
                <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                        <Skeleton className="size-12 shrink-0 rounded-xl" />
                        <div className="min-w-0 flex-1 space-y-2">
                            <Skeleton className="h-3.5 w-36" />
                            <Skeleton className="h-6 w-48 max-w-full" />
                            <Skeleton className="h-4 w-64 max-w-full" />
                        </div>
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center gap-2">
                        <Skeleton className="h-9 w-28 rounded-lg" />
                        <Skeleton className="h-9 w-32 rounded-lg" />
                    </div>
                </div>
                <div className="grid gap-4 border-t border-neutral-100 bg-neutral-50/60 px-5 py-4 sm:grid-cols-3 sm:px-6">
                    {[0, 1, 2].map((meta) => (
                        <div className="space-y-1.5" key={meta}>
                            <Skeleton className="h-3 w-20" />
                            <Skeleton className="h-4 w-32" />
                        </div>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}

function TabsBarSkeleton({ count = 3 }: { count?: number }) {
    return (
        <div className="flex w-full flex-wrap items-center gap-4 border-b border-neutral-200 pb-3">
            {Array.from({ length: count }, (_, idx) => (
                <Skeleton className={cn('h-5 rounded-md', idx === 0 ? 'w-28' : idx === 1 ? 'w-32' : 'w-24')} key={idx} />
            ))}
        </div>
    );
}

export function PageSkeleton({ pathname }: { pathname: string }) {
    const variant = resolvePageSkeletonVariant(pathname);

    if (variant === 'auth-login') {
        return (
            <div className="grid min-h-[85vh] gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(28rem,0.9fr)]" data-page-skeleton={variant}>
                <div className="hidden flex-col justify-between rounded-2xl bg-surface p-8 lg:flex xl:p-12">
                    <Skeleton className="h-10 w-44 rounded-lg" />
                    <div className="space-y-4">
                        <Skeleton className="h-7 w-48 rounded-full" />
                        <Skeleton className="h-10 w-4/5" />
                        <Skeleton className="h-10 w-3/5" />
                        <Skeleton className="h-4 w-full max-w-md" />
                        <Skeleton className="h-4 w-4/5 max-w-md" />
                    </div>
                    <Skeleton className="h-4 w-36" />
                </div>
                <div className="flex items-center justify-center">
                    <div className="w-full max-w-md space-y-6">
                        <div className="space-y-2">
                            <Skeleton className="h-4 w-24" />
                            <Skeleton className="h-9 w-64" />
                            <Skeleton className="h-4 w-72 max-w-full" />
                        </div>
                        <Card>
                            <CardContent className="space-y-5 p-6 sm:p-8">
                                <div className="space-y-2">
                                    <Skeleton className="h-4 w-16" />
                                    <Skeleton className="h-10 w-full rounded-lg" />
                                </div>
                                <div className="space-y-2">
                                    <Skeleton className="h-4 w-24" />
                                    <Skeleton className="h-10 w-full rounded-lg" />
                                </div>
                                <Skeleton className="h-10 w-full rounded-lg" />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        );
    }

    if (variant === 'auth-public') {
        return (
            <div className="mx-auto max-w-lg space-y-6 py-8" data-page-skeleton={variant}>
                <div className="space-y-2 text-center">
                    <Skeleton className="mx-auto h-4 w-32" />
                    <Skeleton className="mx-auto h-8 w-56" />
                    <Skeleton className="mx-auto h-4 w-72 max-w-full" />
                </div>
                <DetailCardSkeleton columns={2} entries={4} />
            </div>
        );
    }

    if (variant === 'dashboard' || variant === 'dashboard-doctor') {
        return (
            <div className="space-y-6" data-page-skeleton={variant}>
                <PageHeaderSkeleton />
                <StatCardSkeleton count={4} />
                <Card>
                    <CardHeader>
                        <Skeleton className="h-5 w-32" />
                        <Skeleton className="h-4 w-72 max-w-full" />
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        {[0, 1, 2, 3].map((link) => (
                            <Skeleton className="h-12 w-full rounded-xl" key={link} />
                        ))}
                    </CardContent>
                </Card>
                {variant === 'dashboard-doctor' ? (
                    <div className="grid items-stretch gap-6 2xl:grid-cols-[minmax(16rem,0.75fr)_minmax(0,1.5fr)]">
                        <Card className="overflow-hidden">
                            <CardHeaderSkeleton />
                            <CardContent className="space-y-3 p-5 sm:p-6">
                                {[0, 1, 2].map((slot) => (
                                    <div className="flex items-center justify-between gap-4 rounded-xl border border-neutral-100 p-3" key={slot}>
                                        <div className="space-y-1.5">
                                            <Skeleton className="h-4 w-28" />
                                            <Skeleton className="h-3 w-20" />
                                        </div>
                                        <Skeleton className="h-7 w-24 rounded-lg" />
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                        <TableCardSkeleton rows={4} withFooterLink />
                    </div>
                ) : (
                    <TableCardSkeleton rows={5} withFooterLink />
                )}
            </div>
        );
    }

    if (variant === 'report-hub') {
        return (
            <div className="space-y-6" data-page-skeleton={variant}>
                <PageHeaderSkeleton />
                <StatCardSkeleton count={4} />
                <div className="space-y-3">
                    <div className="space-y-1.5">
                        <Skeleton className="h-6 w-48" />
                        <Skeleton className="h-4 w-72 max-w-full" />
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {[0, 1, 2, 3, 4, 5].map((item) => (
                            <Card key={item}>
                                <CardContent className="flex h-full flex-col items-start gap-3 p-5 sm:p-6">
                                    <Skeleton className="size-10 rounded-xl" />
                                    <div className="w-full flex-1 space-y-2">
                                        <Skeleton className="h-5 w-44" />
                                        <Skeleton className="h-4 w-full" />
                                        <Skeleton className="h-4 w-4/5" />
                                    </div>
                                    <Skeleton className="h-9 w-32 rounded-lg" />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                </div>
            </div>
        );
    }

    if (variant === 'patient-profile') {
        return (
            <div className="space-y-6" data-page-skeleton={variant}>
                <PageHeaderSkeleton actionCount={2} />
                <div className="grid items-start gap-6 lg:grid-cols-[minmax(18rem,22rem)_minmax(0,1fr)]">
                    <Card className="overflow-hidden">
                        <CardContent className="space-y-6 p-5 sm:p-6">
                            <div className="flex items-center gap-3.5">
                                <Skeleton className="size-14 shrink-0 rounded-2xl" />
                                <div className="min-w-0 flex-1 space-y-2">
                                    <Skeleton className="h-5 w-40" />
                                    <Skeleton className="h-3.5 w-28" />
                                    <Skeleton className="h-5 w-24 rounded-full" />
                                </div>
                            </div>
                            <div className="space-y-3 border-t border-neutral-100 pt-5">
                                <Skeleton className="h-4 w-32" />
                                {[0, 1, 2, 3, 4].map((row) => (
                                    <div className="flex items-center justify-between gap-4" key={row}>
                                        <Skeleton className="h-3.5 w-24" />
                                        <Skeleton className="h-3.5 w-28" />
                                    </div>
                                ))}
                            </div>
                            <div className="space-y-3 border-t border-neutral-100 pt-5">
                                <Skeleton className="h-4 w-32" />
                                {[0, 1, 2].map((row) => (
                                    <div className="flex items-center justify-between gap-4" key={row}>
                                        <Skeleton className="h-3.5 w-20" />
                                        <Skeleton className="h-3.5 w-32" />
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                    <div className="min-w-0 space-y-6">
                        <TabsBarSkeleton count={3} />
                        <TableCardSkeleton rows={4} />
                        <div className="flex justify-end">
                            <Skeleton className="h-9 w-36 rounded-lg" />
                        </div>
                        <TableCardSkeleton rows={3} />
                    </div>
                </div>
            </div>
        );
    }

    if (variant === 'clinical-workspace') {
        return (
            <div className="space-y-6" data-page-skeleton={variant}>
                <PageHeaderSkeleton actionCount={1} />
                <HeroPatientCardSkeleton />
                <TabsBarSkeleton count={5} />
                <FormCardSkeleton columns={2} fields={6} />
            </div>
        );
    }

    if (variant === 'settings-profile') {
        return (
            <div className="space-y-6" data-page-skeleton={variant}>
                <PageHeaderSkeleton />
                <FormCardSkeleton columns={3} fields={6} />
                <FormCardSkeleton columns={2} fields={4} />
            </div>
        );
    }

    if (variant === 'form-tabs') {
        return (
            <div className="space-y-6" data-page-skeleton={variant}>
                <PageHeaderSkeleton />
                <TabsBarSkeleton count={3} />
                <FormCardSkeleton columns={2} fields={6} />
                <FormCardSkeleton columns={2} fields={4} />
            </div>
        );
    }

    if (variant === 'form') {
        return (
            <div className="space-y-6" data-page-skeleton={variant}>
                <PageHeaderSkeleton actionCount={1} />
                <FormCardSkeleton columns={2} fields={6} />
            </div>
        );
    }

    if (variant === 'stock-detail') {
        return (
            <div className="space-y-6" data-page-skeleton={variant}>
                <PageHeaderSkeleton actionCount={2} />
                <StatCardSkeleton count={4} />
                <FilterToolbarSkeleton withSecondary={false} />
                <TableCardSkeleton rows={5} />
            </div>
        );
    }

    if (variant === 'print') {
        return (
            <div className="space-y-6" data-page-skeleton={variant}>
                <PageHeaderSkeleton actionCount={2} />
                <Card className="mx-auto max-w-4xl overflow-hidden">
                    <CardContent className="space-y-6 p-6 sm:p-8">
                        <div className="flex items-center justify-between gap-4 border-b border-neutral-100 pb-5">
                            <Skeleton className="size-14 shrink-0 rounded-xl" />
                            <div className="flex-1 space-y-2">
                                <Skeleton className="mx-auto h-5 w-56" />
                                <Skeleton className="mx-auto h-3.5 w-72 max-w-full" />
                            </div>
                            <Skeleton className="size-14 shrink-0 rounded-xl" />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {[0, 1, 2, 3, 4, 5].map((cell) => (
                                <div className="space-y-1.5" key={cell}>
                                    <Skeleton className="h-3 w-20" />
                                    <Skeleton className="h-4 w-32" />
                                </div>
                            ))}
                        </div>
                        <div className="space-y-3 border-t border-neutral-100 pt-5">
                            {[0, 1, 2, 3].map((line) => (
                                <Skeleton className={cn('h-4', line === 3 ? 'w-2/3' : 'w-full')} key={line} />
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>
        );
    }

    if (variant === 'detail') {
        return (
            <div className="space-y-6" data-page-skeleton={variant}>
                <PageHeaderSkeleton actionCount={2} />
                <div className="grid gap-4 lg:grid-cols-2">
                    <DetailCardSkeleton columns={2} entries={6} withBadge />
                    <DetailCardSkeleton columns={2} entries={4} />
                </div>
                <DetailCardSkeleton columns={3} entries={6} />
            </div>
        );
    }

    return (
        <div className="space-y-6" data-page-skeleton={variant}>
            <PageHeaderSkeleton actionCount={1} />
            <StatCardSkeleton count={4} />
            <FilterToolbarSkeleton />
            <TableCardSkeleton rows={5} />
        </div>
    );
}
