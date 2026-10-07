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
    return (
        <div className="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <p aria-live="polite" className="text-sm text-slate-500">
                {pagination.total > 0
                    ? `Menampilkan ${pagination.from}–${pagination.to} dari ${new Intl.NumberFormat('id-ID').format(pagination.total)} data`
                    : 'Tidak ada data untuk ditampilkan'}
            </p>
            <div className="flex items-center justify-between gap-3 sm:justify-end">
                <Button asChild disabled={!pagination.previousUrl} size="sm" variant="secondary">
                    {pagination.previousUrl
                        ? <Link href={pagination.previousUrl} preserveScroll><ArrowLeft className="size-4" />Sebelumnya</Link>
                        : <span><ArrowLeft className="size-4" />Sebelumnya</span>}
                </Button>
                <span className="whitespace-nowrap text-xs text-slate-500">Halaman {pagination.currentPage} dari {pagination.lastPage}</span>
                <Button asChild disabled={!pagination.nextUrl} size="sm" variant="secondary">
                    {pagination.nextUrl
                        ? <Link href={pagination.nextUrl} preserveScroll>Berikutnya<ArrowRight className="size-4" /></Link>
                        : <span>Berikutnya<ArrowRight className="size-4" /></span>}
                </Button>
            </div>
        </div>
    );
}
