import { Head, Link } from '@inertiajs/react';
import { Banknote, Download, TrendingUp } from 'lucide-react';
import { DateRangeFilter } from '@/components/reports/date-range-filter';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency } from '@/lib/format';

interface Invoice {
    id: number;
    number: string;
    date: string | null;
    patient: string;
    clinic: string;
    paymentMethod: string;
    subtotal: number;
    discount: number;
    total: number;
    receiptUrl: string;
}

interface Props {
    filters: { from: string; to: string };
    stats: { revenue: number; discount: number };
    invoices: PaginationData & { data: Invoice[] };
    exportUrl: string;
}

export default function RevenueReport({ filters, stats, invoices, exportUrl }: Props) {
    return (
        <>
            <Head title="Laporan Pendapatan" />
            <div className="space-y-6"><div><p className="text-sm font-medium text-emerald-700">Ringkasan keuangan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Laporan pendapatan</h2><p className="mt-1 text-sm text-slate-500">Rekap tagihan lunas dan potongan dalam periode terpilih.</p></div>
                <DateRangeFilter from={filters.from} to={filters.to} route="/laporan/pendapatan" exportUrl={exportUrl} />
                <div className="grid gap-4 md:grid-cols-2"><StatCard description="Akumulasi bersih seluruh transaksi lunas" icon={<TrendingUp className="size-5" />} iconClassName="bg-emerald-50 text-emerald-700" label="Total pendapatan bersih" value={formatCurrency(stats.revenue)} /><StatCard description="Akumulasi potongan dari transaksi lunas" icon={<Banknote className="size-5" />} iconClassName="bg-red-50 text-red-700" label="Total potongan / diskon" value={formatCurrency(stats.discount)} /></div>
                <Card className="overflow-hidden"><div className="overflow-x-auto"><Table className="min-w-[1050px]"><TableHeader><tr><TableHead>No. tagihan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Metode bayar</TableHead><TableHead className="text-right">Subtotal</TableHead><TableHead className="text-right">Diskon</TableHead><TableHead className="text-right">Total bersih</TableHead><TableHead className="text-right">Kuitansi</TableHead></tr></TableHeader><TableBody>{invoices.data.length ? invoices.data.map((invoice) => <TableRow key={invoice.id}><TableCell className="font-mono text-xs font-semibold">{invoice.number}</TableCell><TableCell>{invoice.date ?? '—'}</TableCell><TableCell className="font-medium text-slate-900">{invoice.patient}</TableCell><TableCell>{invoice.clinic}</TableCell><TableCell className="uppercase">{invoice.paymentMethod}</TableCell><TableCell className="text-right">{formatCurrency(invoice.subtotal)}</TableCell><TableCell className="text-right text-red-700">{formatCurrency(invoice.discount)}</TableCell><TableCell className="text-right font-semibold">{formatCurrency(invoice.total)}</TableCell><TableCell className="text-right"><Button asChild size="sm" variant="secondary"><Link href={invoice.receiptUrl}>Buka</Link></Button></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-slate-500" colSpan={9}>Tidak ada transaksi lunas dalam rentang ini.</TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={invoices} /></Card>
            </div>
        </>
    );
}
