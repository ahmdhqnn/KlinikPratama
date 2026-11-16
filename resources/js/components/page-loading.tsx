import { router } from '@inertiajs/react';
import { useEffect, useState, type ReactNode } from 'react';
import { PageSkeleton } from '@/components/loading-skeletons';

export function PageLoading({ children }: { children: ReactNode }) {
    const [pendingVisit, setPendingVisit] = useState<{ pathname: string; showSkeleton: boolean } | null>(null);

    useEffect(() => {
        let activeVisit: string | undefined;
        let timer: number | undefined;

        function reset() {
            window.clearTimeout(timer);
            activeVisit = undefined;
            setPendingVisit(null);
        }

        const removeStart = router.on('start', ({ detail: { visit } }) => {
            if (visit.method !== 'get' || visit.prefetch || visit.async || visit.only.length || visit.except.length) return;

            reset();
            const target = visit.url;
            const current = window.location;
            const changesPath = target.pathname !== current.pathname;
            const changesQuery = target.search !== current.search;
            if (!changesPath && !changesQuery) return;

            activeVisit = visit.id;
            timer = window.setTimeout(() => {
                setPendingVisit({
                    pathname: target.pathname,
                    showSkeleton: changesPath || !visit.preserveState,
                });
            }, 250);
        });
        const removeFinish = router.on('finish', ({ detail: { visit } }) => {
            if (activeVisit !== undefined && visit.id === activeVisit) reset();
        });
        window.addEventListener('popstate', reset);

        return () => {
            window.clearTimeout(timer);
            removeStart();
            removeFinish();
            window.removeEventListener('popstate', reset);
        };
    }, []);

    return (
        <div className="relative min-w-0">
            <div aria-busy={pendingVisit !== null} data-page-content={pendingVisit?.showSkeleton ? 'loading' : undefined} inert={pendingVisit?.showSkeleton ?? false}>
                {children}
            </div>
            <span className="sr-only print:hidden" role="status">
                {pendingVisit && (pendingVisit.showSkeleton ? 'Memuat halaman…' : 'Memperbarui hasil…')}
            </span>
            {pendingVisit?.showSkeleton && (
                <div aria-hidden="true" className="absolute inset-0 bg-canvas print:hidden [&:not(.clinic-app_*)]:p-4 sm:[&:not(.clinic-app_*)]:p-8">
                    <div className="sticky top-4 [&:not(.clinic-app_*)]:mx-auto [&:not(.clinic-app_*)]:max-w-4xl sm:top-8 [.clinic-app_&]:top-32 sm:[.clinic-app_&]:top-24 lg:[.clinic-app_&]:top-28">
                        <PageSkeleton pathname={pendingVisit.pathname} />
                    </div>
                </div>
            )}
        </div>
    );
}
