import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { ActionButton } from '@/components/ui/action-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, FileText, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Prescription { id: number; date: string; number: string; visitNumber: string; patient: string; medicalRecordNumber: string; clinic: string; doctor: string; itemCount: number; printUrl: string }
type PrescriptionPage = PaginationData & { data: Prescription[] };

export default function ExternalPrescriptions({ stats, filters, todayRecords, records }: {
    stats: { today: number; all: number };
    filters: { search: string; tanggal: string };
    todayRecords: PrescriptionPage;
    records: PrescriptionPage;
}) {
    const [search, setSearch] = useState(filters.search);
    const [date, setDate] = useState(filters.tanggal);
    function apply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/farmasi/resep-luar', { search, tanggal: date }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <><Head title="Resep luar" /><div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Pelayanan farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Resep luar</h2><p className="mt-1 text-sm text-neutral-500">Lihat dan cetak resep dari dokter untuk obat yang ditebus di luar klinik. Obat ini tidak mengurangi persediaan klinik.</p></div><Button asChild variant="secondary"><Link href="/farmasi/laporan/resep-luar">Lihat laporan</Link></Button></div>
        <section aria-label="Ringkasan resep luar" className="grid gap-4 sm:grid-cols-2"><StatCard label="Resep luar hari ini" value={stats.today} description="Kunjungan hari ini dengan resep luar yang sudah ditandatangani" icon={<CalendarDays className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" /><StatCard label="Seluruh resep luar" value={stats.all} description="Riwayat resep luar yang siap dicetak" icon={<FileText className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" /></section>
        <PrescriptionList title="Resep luar hari ini" description="Resep dari kunjungan hari ini yang pemeriksaannya sudah ditandatangani." records={todayRecords} />
        <FilterPanel><form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={apply}><Field htmlFor="external-prescription-search" label="Cari pasien"><Input id="external-prescription-search" onChange={(event) => setSearch(event.target.value)} placeholder="Nama atau No. RM" value={search} /></Field><Field htmlFor="external-prescription-date" label="Tanggal kunjungan"><DatePicker id="external-prescription-date" onChange={(event) => setDate(event.target.value)} value={date} /></Field><Button type="submit"><Search className="size-4" />Terapkan filter</Button><Button asChild variant="secondary"><Link href="/farmasi/resep-luar">Reset</Link></Button></form></FilterPanel>
        <PrescriptionList title="Semua resep luar" description={`${records.total.toLocaleString('id-ID')} resep sesuai filter.`} records={records} />
    </div></>;
}

function PrescriptionList({ title, description, records }: { title: string; description: string; records: PrescriptionPage }) {
    return <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><FileText className="size-5" /></span><div><CardTitle>{title}</CardTitle><CardDescription className="mt-1">{description}</CardDescription></div></div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[900px]"><TableHeader><TableRow><TableHead className="text-center">Tanggal</TableHead><TableHead className="text-center">No. resep</TableHead><TableHead className="text-center">Pasien</TableHead><TableHead className="text-center">Poliklinik</TableHead><TableHead className="text-center">Dokter</TableHead><TableHead className="text-center">Item obat</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{records.data.length ? records.data.map((record) => <TableRow key={record.id}><TableCell className="text-center whitespace-nowrap">{record.date}</TableCell><TableCell className="text-center font-mono text-xs">{record.number}</TableCell><TableCell className="text-center"><p className="font-medium">{record.patient}</p><p className="text-xs text-neutral-500">{record.medicalRecordNumber}</p></TableCell><TableCell className="text-center">{record.clinic}</TableCell><TableCell className="text-center">{record.doctor}</TableCell><TableCell className="text-center">{record.itemCount}</TableCell><TableCell className="text-center"><ActionButton asChild action="print" label={`Cetak resep ${record.number}`}><Link href={record.printUrl} /></ActionButton></TableCell></TableRow>) : <TableRow><TableCell className="text-center" colSpan={7}><Empty size="compact" title="Belum ada resep luar pada daftar ini." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={records} /></CardContent></Card>;
}
