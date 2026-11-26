import { Input } from '@/components/ui/input';
import { Field } from '@/components/ui/field';
import { type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ReportTableCard } from '@/components/dashboard/report-table-card';
import { Empty } from '@/components/ui/empty';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatNumber } from '@/lib/format';
import { Head, router } from '@inertiajs/react';
import { Download, AlertTriangle, Boxes, Clock3, PackageX } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Medicine {
    id: number;
    code: string;
    name: string;
    type: string;
    stock: number;
    usable: number;
    unit: string;
    minimumStock: number;
    nextExpiry: string | null;
}

interface Props {
    exportUrl: string;
    filters: { search?: string; type: string; lowStockOnly: boolean; alertsOnly: boolean };
    stats: { totalItems: number; lowStockItems: number; outOfStockItems?: number; nearExpiryItems?: number };
    medicines: PaginationData & { data: Medicine[] };
}

export default function StockReport({ filters, stats, medicines, exportUrl }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.type);
    const [condition, setCondition] = useState(filters.lowStockOnly ? 'kritis' : filters.alertsOnly ? 'perhatian' : '');

    function filterReport(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/laporan/stok', { search, jenis: type, perhatian: condition === 'perhatian' ? 1 : undefined, stok_rendah: condition === 'kritis' ? 1 : undefined }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Laporan stok" />
            <div className="space-y-6">
                <header><p className="text-sm font-medium text-neutral-700">Persediaan klinik</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laporan stok</h2><p className="mt-1 text-sm text-neutral-500">Pantau stok fisik, stok layak pakai, kedaluwarsa, dan batas minimum obat serta BHP.</p></header>
                <SummaryStatGrid ariaLabel="Ringkasan persediaan klinik" columns={4}>
                    <StatCard description="Obat dan BHP sesuai filter" icon={<Boxes className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Total item" value={`${formatNumber(stats.totalItems)} item`} />
                    <StatCard description="Stok layak pakai pada atau di bawah batas minimum" icon={<AlertTriangle className="size-5" />} iconClassName="bg-amber-50 text-amber-700" label="Stok kritis" value={`${formatNumber(stats.lowStockItems)} item`} />
                    <StatCard description="Stok layak pakai kosong (0)" icon={<PackageX className="size-5" />} iconClassName="bg-red-50 text-red-700" label="Stok habis" value={`${formatNumber(stats.outOfStockItems ?? 0)} item`} />
                    <StatCard description="Memiliki batch kedaluwarsa dalam 90 hari" icon={<Clock3 className="size-5" />} iconClassName="bg-amber-50 text-amber-700" label="Mendekati ED ≤ 90 hari" value={`${formatNumber(stats.nearExpiryItems ?? 0)} item`} />
                </SummaryStatGrid>
                <FilterPanel actions={<Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}><form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" onSubmit={filterReport}><Field htmlFor="stock-report-search" label="Cari item"><Input id="stock-report-search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Nama atau kode obat / BHP" /></Field><Field htmlFor="medicine-type" label="Jenis item"><Select id="medicine-type" onChange={(event) => setType(event.target.value)} value={type}><option value="">Semua jenis</option><option value="obat">Obat</option><option value="bhp">BHP</option></Select></Field><Field htmlFor="stock-report-condition" label="Kondisi persediaan"><Select id="stock-report-condition" value={condition} onChange={(event) => setCondition(event.target.value)}>
                    <option value="">Semua kondisi</option><option value="kritis">Stok kritis</option><option value="perhatian">Stok kritis atau kedaluwarsa dalam 90 hari</option>
                </Select></Field><FilterSubmit /></form></FilterPanel>
                <ReportTableCard title="Daftar stok obat dan BHP" description={`${new Intl.NumberFormat('id-ID').format(medicines.total)} item tercatat.`} icon={<Boxes className="size-5" />} pagination={medicines}>
                    <Table className="min-w-[800px]">
                        <TableHeader><TableRow><TableHead>Kode</TableHead><TableHead>Nama item</TableHead><TableHead>Jenis</TableHead><TableHead className="text-center">Stok fisik / layak pakai</TableHead><TableHead className="text-center">Batas minimum</TableHead><TableHead>Status</TableHead><TableHead>Kedaluwarsa terdekat</TableHead></TableRow></TableHeader>
                        <TableBody>{medicines.data.length ? medicines.data.map((medicine) => { const critical = medicine.usable <= medicine.minimumStock; const empty = medicine.usable <= 0; return <TableRow key={medicine.id}><TableCell className="font-sans tabular-nums text-xs font-semibold">{medicine.code}</TableCell><TableCell className="font-medium text-neutral-900">{medicine.name}</TableCell><TableCell><Badge>{medicine.type.toUpperCase()}</Badge></TableCell><TableCell className="text-center font-sans tabular-nums font-semibold">{formatNumber(medicine.stock)} {medicine.unit}<p className="text-xs font-normal text-muted-foreground">Layak pakai {formatNumber(medicine.usable)}</p></TableCell><TableCell className="text-center font-sans tabular-nums">{formatNumber(medicine.minimumStock)}</TableCell><TableCell><Badge variant={empty ? 'cancelled' : critical ? 'warning' : 'default'}>{empty ? 'Habis' : critical ? 'Kritis' : 'Aman'}</Badge></TableCell><TableCell>{medicine.nextExpiry?.slice(0, 10) ?? '—'}</TableCell></TableRow>; }) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Tidak ada item yang cocok dengan filter stok." /></TableCell></TableRow>}</TableBody>
                    </Table>
                </ReportTableCard>
            </div>
        </>
    );
}
