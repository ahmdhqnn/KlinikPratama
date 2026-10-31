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
import { ClipboardCheck, Download, PackageCheck, Search, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Filters { dari: string; sampai: string; search: string }
interface Row { date: string; number: string; patient: string; medicalRecordNumber: string; clinic: string; medicine: string; quantity: number; pharmacist: string; detailUrl: string }

export default function PharmacyHandoverReport({ filters, stats, rows, exportUrl }: { filters: Filters; stats: { total: number; patients: number; items: number }; rows: PaginationData & { data: Row[] }; exportUrl: string }) {
    const [values, setValues] = useState(filters);
    function apply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/farmasi/laporan/penyerahan-obat', { ...values }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <><Head title="Laporan obat" /><div className="space-y-6">
        <div><p className="text-sm font-medium text-neutral-700">Laporan pelayanan farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laporan obat</h2><p className="mt-1 text-sm text-neutral-500">Obat yang telah diserahkan berdasarkan resep dokter, sesuai periode yang dipilih.</p></div>
        <section aria-label="Ringkasan laporan" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-3"><StatCard description="Resep farmasi yang sudah diselesaikan" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Total penyerahan" value={stats.total} /><StatCard description="Kunjungan unik yang dilayani" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Kunjungan dilayani" value={stats.patients} /><StatCard description="Jumlah item pada resep yang diserahkan" icon={<PackageCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Item obat diserahkan" value={stats.items} /></section>
        <FilterPanel actions={<Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}><form className="flex flex-col gap-4 xl:flex-row xl:items-end" onSubmit={apply}><Field className="w-full xl:max-w-sm" htmlFor="pharmacy-handover-search" label="Cari pasien, kunjungan, atau obat"><Input id="pharmacy-handover-search" onChange={(event) => setValues({ ...values, search: event.target.value })} placeholder="Nama, No. RM, No. kunjungan, atau obat" value={values.search} /></Field><Field className="w-full xl:max-w-md" htmlFor="pharmacy-handover-period" label="Periode penyerahan"><DateRangePicker from={values.dari} id="pharmacy-handover-period" onChange={(range) => setValues({ ...values, dari: range.from, sampai: range.to })} to={values.sampai} /></Field><Button type="submit"><Search className="size-4" />Terapkan filter</Button><Button asChild variant="secondary"><Link href="/farmasi/laporan/penyerahan-obat">Reset</Link></Button></form></FilterPanel>
        <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardCheck className="size-5" /></span><div><CardTitle>Riwayat penyerahan resep</CardTitle><CardDescription className="mt-1">{rows.total.toLocaleString('id-ID')} penyerahan sesuai filter.</CardDescription></div></div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[1100px]"><TableHeader><TableRow><TableHead className="text-center">Tanggal penyerahan</TableHead><TableHead className="text-center">No. kunjungan</TableHead><TableHead className="text-center">Pasien</TableHead><TableHead className="text-center">Poliklinik</TableHead><TableHead className="text-center">Obat diserahkan</TableHead><TableHead className="text-center">Jumlah item obat</TableHead><TableHead className="text-center">Petugas farmasi</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{rows.data.length ? rows.data.map((row) => <TableRow key={row.number}><TableCell className="text-center whitespace-nowrap">{row.date}</TableCell><TableCell className="text-center font-mono text-xs">{row.number}</TableCell><TableCell className="text-center"><p className="font-medium">{row.patient}</p><p className="text-xs text-neutral-500">{row.medicalRecordNumber}</p></TableCell><TableCell className="text-center">{row.clinic}</TableCell><TableCell className="max-w-64 text-center"><span className="line-clamp-2">{row.medicine}</span></TableCell><TableCell className="text-center tabular-nums">{row.quantity}</TableCell><TableCell className="text-center">{row.pharmacist}</TableCell><TableCell className="text-center"><ActionButton asChild action="detail" label="Lihat detail penyerahan"><Link href={row.detailUrl} /></ActionButton></TableCell></TableRow>) : <TableRow><TableCell className="text-center" colSpan={8}><Empty size="compact" title="Belum ada penyerahan obat pada periode ini." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={rows} /></CardContent></Card>
    </div></>;
}

