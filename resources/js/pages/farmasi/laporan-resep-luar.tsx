import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { ActionButton } from '@/components/ui/action-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Download, FileText, Search, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Row { id: number; date: string; prescription: string; patient: string; medicalRecordNumber: string; clinic: string; medicine: string; quantity: number; unit: string | null; source: string; printUrl: string }
interface Filters { dari: string; sampai: string; search: string }

export default function ExternalPrescriptionReport({ filters, stats, rows, exportUrl }: {
    filters: Filters;
    stats: { items: number; prescriptions: number; redirected: number };
    rows: PaginationData & { data: Row[] };
    exportUrl: string;
}) {
    const [values, setValues] = useState(filters);
    function apply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/farmasi/laporan/resep-luar', { ...values }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <><Head title="Laporan resep luar" /><div className="space-y-6">
        <div><p className="text-sm font-medium text-neutral-700">Laporan pelayanan farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laporan resep luar</h2><p className="mt-1 text-sm text-neutral-500">Obat yang diresepkan untuk ditebus di luar klinik, termasuk yang dialihkan karena stok kosong.</p></div>
        <section aria-label="Ringkasan laporan resep luar" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><StatCard label="Item resep luar" value={stats.items} description="Obat sesuai periode laporan" icon={<FileText className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" /><StatCard label="Resep tercatat" value={stats.prescriptions} description="Resep dengan obat luar" icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" /><StatCard label="Dialihkan farmasi" value={stats.redirected} description="Obat dengan stok klinik kosong" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" /></section>
        <FilterPanel actions={<Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}><form className="flex flex-col gap-4 xl:flex-row xl:items-end" onSubmit={apply}><Field className="w-full xl:max-w-sm" htmlFor="external-report-search" label="Cari pasien, resep, atau obat"><Input id="external-report-search" onChange={(event) => setValues({ ...values, search: event.target.value })} placeholder="Nama, No. RM, No. resep, atau obat" value={values.search} /></Field><Field className="w-full xl:max-w-md" htmlFor="external-report-period" label="Periode kunjungan"><DateRangePicker from={values.dari} id="external-report-period" onChange={(range) => setValues({ ...values, dari: range.from, sampai: range.to })} to={values.sampai} /></Field><Button type="submit"><Search className="size-4" />Terapkan filter</Button><Button asChild variant="secondary"><Link href="/farmasi/laporan/resep-luar">Reset</Link></Button></form></FilterPanel>
        <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><FileText className="size-5" /></span><div><CardTitle>Daftar resep luar</CardTitle><CardDescription className="mt-1">{rows.total.toLocaleString('id-ID')} item sesuai filter.</CardDescription></div></div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[1000px]"><TableHeader><TableRow><TableHead className="text-center">Tanggal</TableHead><TableHead className="text-center">No. resep</TableHead><TableHead className="text-center">Pasien</TableHead><TableHead className="text-center">Poliklinik</TableHead><TableHead className="text-center">Obat</TableHead><TableHead className="text-center">Jumlah</TableHead><TableHead className="text-center">Asal</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{rows.data.length ? rows.data.map((row) => <TableRow key={row.id}><TableCell className="text-center whitespace-nowrap">{row.date}</TableCell><TableCell className="text-center font-mono text-xs">{row.prescription}</TableCell><TableCell className="text-center"><p className="font-medium">{row.patient}</p><p className="text-xs text-neutral-500">{row.medicalRecordNumber}</p></TableCell><TableCell className="text-center">{row.clinic}</TableCell><TableCell className="text-center">{row.medicine}</TableCell><TableCell className="text-center whitespace-nowrap">{row.quantity} {row.unit ?? ''}</TableCell><TableCell className="text-center whitespace-nowrap">{row.source}</TableCell><TableCell className="text-center"><ActionButton asChild action="print" label="Cetak resep luar"><Link href={row.printUrl} /></ActionButton></TableCell></TableRow>) : <TableRow><TableCell className="text-center" colSpan={8}><Empty size="compact" title="Belum ada resep luar pada periode ini." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={rows} /></CardContent></Card>
    </div></>;
}
