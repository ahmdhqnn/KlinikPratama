import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';

export interface PaginationData {
    currentPage: number;
    lastPage: number;
    from: number | null;
    to: number | null;
    total: number;
    previousUrl: string | null;
    nextUrl: string | null;
}

export function Pagination({ pagination }: { pagination: PaginationData }) {
    if (pagination.total === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-center gap-x-6 gap-y-3 border-t border-neutral-100 px-5 py-4 sm:justify-between">
            <p aria-live="polite" className="text-center text-sm tabular-nums text-neutral-500">
                {`${pagination.from ?? 0}–${pagination.to ?? 0} dari ${new Intl.NumberFormat('id-ID').format(pagination.total)} data`}
            </p>
            {pagination.lastPage > 1 && <nav aria-label="Navigasi halaman" className="flex shrink-0 items-center justify-center gap-2">
                {pagination.previousUrl
                    ? <Button asChild size="sm" variant="secondary"><Link aria-label="Halaman sebelumnya" href={pagination.previousUrl} preserveScroll><ArrowLeft className="size-4" /><span className="hidden sm:inline">Sebelumnya</span></Link></Button>
                    : <Button aria-label="Halaman sebelumnya" disabled size="sm" variant="secondary"><ArrowLeft className="size-4" /><span className="hidden sm:inline">Sebelumnya</span></Button>}
                <span aria-current="page" className="min-w-20 whitespace-nowrap px-2 text-center text-sm tabular-nums text-neutral-600">{pagination.currentPage} / {pagination.lastPage}</span>
                {pagination.nextUrl
                    ? <Button asChild size="sm" variant="secondary"><Link aria-label="Halaman berikutnya" href={pagination.nextUrl} preserveScroll><span className="hidden sm:inline">Berikutnya</span><ArrowRight className="size-4" /></Link></Button>
                    : <Button aria-label="Halaman berikutnya" disabled size="sm" variant="secondary"><span className="hidden sm:inline">Berikutnya</span><ArrowRight className="size-4" /></Button>}
            </nav>}
        </div>
    );
}
