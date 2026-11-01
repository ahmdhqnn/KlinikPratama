import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { ActionButton } from '@/components/ui/action-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, FilePlus2, Pill, Search, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Record { number: string; date: string; patient: string; medicalRecordNumber: string; medicine: string; quantity: number; unit: string; pharmacist: string; printUrl: string }
type RecordPage = PaginationData & { data: Record[] };

export default function DirectMedicineIndex({ stats, search: initialSearch, todayRecords, records }: {
    stats: { today: number; patients: number; all: number };
    search: string;
    todayRecords: RecordPage;
    records: RecordPage;
}) {
    const [search, setSearch] = useState(initialSearch);
    function apply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/farmasi/pemberian-obat', { search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <><Head title="Obat langsung" /><div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Pelayanan farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Obat langsung</h2><p className="mt-1 text-sm text-neutral-500">Catat obat yang diberikan tanpa pemeriksaan dokter dan lihat riwayat penyerahannya.</p></div><Button asChild><Link href="/farmasi/pemberian-obat/baru"><FilePlus2 className="size-4" />Catat pemberian obat</Link></Button></div>
        <section aria-label="Ringkasan obat langsung" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <StatCard label="Pemberian hari ini" value={stats.today} description="Obat langsung yang diserahkan hari ini" icon={<Pill className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
            <StatCard label="Pasien hari ini" value={stats.patients} description="Pasien yang menerima obat langsung" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
            <StatCard label="Seluruh pemberian" value={stats.all} description="Riwayat pemberian obat langsung" icon={<CalendarDays className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
        </section>
        <RecordList title="Pemberian obat hari ini" description="Obat langsung yang telah diserahkan hari ini." records={todayRecords} />
        <FilterPanel><form className="flex flex-col gap-4 sm:flex-row sm:items-end" onSubmit={apply}><Field className="w-full sm:max-w-md" htmlFor="direct-index-search" label="Cari pasien, transaksi, atau obat"><Input id="direct-index-search" onChange={(event) => setSearch(event.target.value)} placeholder="Nama, No. RM, nomor transaksi, atau obat" value={search} /></Field><Button type="submit"><Search className="size-4" />Terapkan filter</Button><Button asChild variant="secondary"><Link href="/farmasi/pemberian-obat">Reset</Link></Button></form></FilterPanel>
        <RecordList title="Semua pemberian obat" description={`${records.total.toLocaleString('id-ID')} catatan sesuai filter.`} records={records} />
    </div></>;
}

function RecordList({ title, description, records }: { title: string; description: string; records: RecordPage }) {
    return <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Pill className="size-5" /></span><div><CardTitle>{title}</CardTitle><CardDescription className="mt-1">{description}</CardDescription></div></div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[900px]"><TableHeader><TableRow><TableHead className="text-center">Tanggal</TableHead><TableHead className="text-center">No. transaksi</TableHead><TableHead className="text-center">Pasien</TableHead><TableHead className="text-center">Obat</TableHead><TableHead className="text-center">Jumlah</TableHead><TableHead className="text-center">Petugas</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{records.data.length ? records.data.map((record) => <TableRow key={record.number}><TableCell className="text-center whitespace-nowrap">{record.date}</TableCell><TableCell className="text-center font-mono text-xs">{record.number}</TableCell><TableCell className="text-center"><p className="font-medium">{record.patient}</p><p className="text-xs text-neutral-500">{record.medicalRecordNumber}</p></TableCell><TableCell className="text-center">{record.medicine}</TableCell><TableCell className="text-center whitespace-nowrap">{record.quantity} {record.unit}</TableCell><TableCell className="text-center">{record.pharmacist}</TableCell><TableCell className="text-center"><ActionButton asChild action="print" label="Cetak bukti pemberian"><Link href={record.printUrl} /></ActionButton></TableCell></TableRow>) : <TableRow><TableCell className="text-center" colSpan={7}><Empty size="compact" title="Belum ada pemberian obat pada daftar ini." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={records} /></CardContent></Card>;
}
