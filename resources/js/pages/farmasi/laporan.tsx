import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { ActionButton } from '@/components/ui/action-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, Download, FileText, Pill, Search, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Row { date: string; number: string; patient: string; medicalRecordNumber: string; medicine: string; quantity: number; unit: string; instruction: string; pharmacist: string; printUrl: string }
interface Filters { dari: string; sampai: string; search: string }

export default function PharmacyReport({ filters, stats, rows, exportUrl }: { filters: Filters; stats: { total: number; patients: number; items: number }; rows: PaginationData & { data: Row[] }; exportUrl: string }) {
    const [values, setValues] = useState(filters);
    function apply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/farmasi/laporan/pemberian-langsung', { ...values }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <><Head title="Laporan obat langsung" /><div className="space-y-6">
        <div><p className="text-sm font-medium text-neutral-700">Laporan pelayanan farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laporan obat langsung</h2><p className="mt-1 text-sm text-neutral-500">Obat yang diberikan langsung tanpa pemeriksaan dokter, sesuai periode yang dipilih.</p></div>
        <section aria-label="Ringkasan laporan" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-3"><StatCard description="Transaksi pemberian langsung" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Total penyerahan" value={stats.total} /><StatCard description="Pasien unik sesuai filter" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Pasien dilayani" value={stats.patients} /><StatCard description="Jenis obat yang diberikan sesuai filter" icon={<Pill className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Jenis obat" value={stats.items} /></section>
        <FilterPanel actions={<Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}><form className="flex flex-col gap-4 xl:flex-row xl:items-end" onSubmit={apply}><Field className="w-full xl:max-w-sm" htmlFor="pharmacy-report-search" label="Cari pasien, transaksi, atau obat"><Input id="pharmacy-report-search" onChange={(event) => setValues({ ...values, search: event.target.value })} placeholder="Nama, No. RM, nomor transaksi, atau obat" value={values.search} /></Field><Field className="w-full xl:max-w-md" htmlFor="pharmacy-report-period" label="Periode penyerahan"><DateRangePicker from={values.dari} id="pharmacy-report-period" onChange={(range) => setValues({ ...values, dari: range.from, sampai: range.to })} to={values.sampai} /></Field><Button type="submit"><Search className="size-4" />Terapkan filter</Button><Button asChild variant="secondary"><Link href="/farmasi/laporan/pemberian-langsung">Reset</Link></Button></form></FilterPanel>
        <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><FileText className="size-5" /></span><div><CardTitle>Riwayat pemberian obat langsung</CardTitle><CardDescription className="mt-1">{rows.total.toLocaleString('id-ID')} transaksi sesuai filter.</CardDescription></div></div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[1050px]"><TableHeader><TableRow><TableHead className="text-center">Tanggal</TableHead><TableHead className="text-center">No. transaksi</TableHead><TableHead className="text-center">Pasien</TableHead><TableHead className="text-center">Obat</TableHead><TableHead className="text-center">Jumlah</TableHead><TableHead className="text-center">Aturan pakai</TableHead><TableHead className="text-center">Petugas</TableHead><TableHead className="text-center">Jenis layanan</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{rows.data.length ? rows.data.map((row) => <TableRow key={row.number}><TableCell className="text-center whitespace-nowrap">{row.date}</TableCell><TableCell className="text-center font-mono text-xs">{row.number}</TableCell><TableCell className="text-center"><p className="font-medium">{row.patient}</p><p className="text-xs text-neutral-500">{row.medicalRecordNumber}</p></TableCell><TableCell className="text-center">{row.medicine}</TableCell><TableCell className="text-center whitespace-nowrap tabular-nums">{row.quantity} {row.unit}</TableCell><TableCell className="max-w-56 text-center"><span className="line-clamp-2">{row.instruction}</span></TableCell><TableCell className="text-center">{row.pharmacist}</TableCell><TableCell className="text-center"><Badge>Tanpa pemeriksaan</Badge></TableCell><TableCell className="text-center"><ActionButton asChild action="print" label="Cetak bukti"><Link href={row.printUrl} /></ActionButton></TableCell></TableRow>) : <TableRow><TableCell className="text-center" colSpan={9}><Empty size="compact" title="Belum ada penyerahan obat pada periode ini." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={rows} /></CardContent></Card>
    </div></>;
}



