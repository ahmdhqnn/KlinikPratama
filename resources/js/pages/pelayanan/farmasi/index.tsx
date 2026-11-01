import { ActionButton } from '@/components/ui/action-button';
import { StatCard } from '@/components/dashboard/stat-card';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, ClipboardList, Search, Timer } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DatePicker } from '@/components/ui/date-picker';

interface Visit {
    id: number;
    date: string;
    prescriptionNumber: string | null;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    doctor: string | null;
    itemCount: number;
    pharmacyStatus: string;
}

interface Props {
    visits: PaginationData & { data: Visit[] };
    todayVisits: PaginationData & { data: Visit[] };
    stats: { today: number; waiting: number; processing: number; completed: number };
    date: string;
    search: string;
}

export default function PharmacyQueue({ visits, todayVisits, stats, date: initialDate, search: initialSearch }: Props) {
    const [date, setDate] = useState(initialDate);
    const [search, setSearch] = useState(initialSearch);

    useEffect(() => {
        const refresh = () => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['stats', 'todayVisits', 'visits'], async: true });
            }
        };
        const interval = window.setInterval(refresh, 15_000);
        window.addEventListener('focus', refresh);

        return () => { window.clearInterval(interval); window.removeEventListener('focus', refresh); };
    }, []);

    function filterQueue(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/farmasi', { tanggal: date, search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Penyerahan obat" />
            <div className="space-y-6">
                <div><p className="text-sm font-medium text-neutral-700">Pelayanan obat</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Penyerahan obat</h2><p className="mt-1 text-sm text-neutral-500">Siapkan resep, periksa jumlah dan aturan pakai, lalu catat penyerahan obat kepada pasien.</p></div>
                <section aria-label="Ringkasan penyerahan obat" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Resep hari ini" value={stats.today} description="Kunjungan poli yang memerlukan layanan obat" icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Menunggu penyerahan" value={stats.waiting} description="Resep hari ini yang belum diproses" icon={<Timer className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Sedang diproses" value={stats.processing} description="Penyiapan obat yang sedang berlangsung" icon={<CalendarDays className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Selesai hari ini" value={stats.completed} description="Obat yang diserahkan hari ini" icon={<CheckCircle2 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </section>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><CardTitle>Menunggu penyerahan hari ini</CardTitle><CardDescription>Resep aktif hari ini, diurutkan dari waktu tunggu terlama.</CardDescription></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><TableRow><TableHead className="text-center">No. resep</TableHead><TableHead className="text-center">Pasien</TableHead><TableHead className="text-center">Poliklinik</TableHead><TableHead className="text-center">Jumlah item</TableHead><TableHead className="text-center">Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{todayVisits.data.length ? todayVisits.data.map((visit) => <TableRow key={visit.id}><TableCell className="text-center font-mono text-xs">{visit.prescriptionNumber ?? 'Belum diisi'}</TableCell><TableCell className="text-center"><p className="font-medium">{visit.patient}</p><p className="text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell><TableCell className="text-center">{visit.clinic}</TableCell><TableCell className="text-center">{visit.itemCount} item</TableCell><TableCell className="text-center"><StatusBadge context="pharmacy" status={visit.pharmacyStatus} /></TableCell><TableCell className="text-center"><ActionButton asChild action="pharmacy" label="Proses penyerahan"><Link href={`/pelayanan/farmasi/${visit.id}`} /></ActionButton></TableCell></TableRow>) : <TableRow><TableCell className="text-center" colSpan={6}><Empty size="compact" title="Tidak ada resep yang menunggu hari ini." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={todayVisits} /></CardContent></Card>
                <FilterPanel><form className="grid gap-4 md:grid-cols-2 md:items-end" onSubmit={filterQueue}><Field htmlFor="pharmacy-search" label="Cari pasien atau No. RM"><Input id="pharmacy-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau No. RM" value={search} /></Field><Field htmlFor="pharmacy-visit-date" label="Tanggal kunjungan"><DatePicker id="pharmacy-visit-date" onChange={(event) => setDate(event.target.value)} value={date} /></Field><Button type="submit"><Search className="size-4" />Filter antrean</Button></form></FilterPanel>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardList className="size-5" /></span><div><CardTitle>Semua penyerahan obat</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} resep sesuai filter, termasuk yang sudah selesai.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[980px]"><TableHeader><tr><TableHead className="text-center">No. resep</TableHead><TableHead className="text-center">Tanggal kunjungan</TableHead><TableHead className="text-center">Pasien</TableHead><TableHead className="text-center">Poliklinik</TableHead><TableHead className="text-center">Dokter</TableHead><TableHead className="text-center">Jumlah item</TableHead><TableHead className="text-center">Status</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader><TableBody>{visits.data.length ? visits.data.map((visit) => {
                        const isComplete = visit.pharmacyStatus === 'selesai';

                        return <TableRow key={visit.id}><TableCell className="text-center font-mono text-xs font-semibold text-neutral-700">{visit.prescriptionNumber ?? 'Belum diisi'}</TableCell><TableCell className="text-center whitespace-nowrap text-neutral-600">{visit.date}</TableCell><TableCell className="text-center"><p className="font-medium text-neutral-900">{visit.patient}</p><p className="font-mono text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell><TableCell className="text-center">{visit.clinic}</TableCell><TableCell className="text-center">{visit.doctor ?? 'Belum diisi'}</TableCell><TableCell className="text-center">{visit.itemCount} item</TableCell><TableCell className="text-center"><StatusBadge context="pharmacy" status={visit.pharmacyStatus} /></TableCell><TableCell className="text-center"><ActionButton asChild action={isComplete ? 'detail' : 'pharmacy'} label={isComplete ? 'Lihat detail resep' : 'Proses resep'}><Link href={isComplete ? `/farmasi/pasien/kunjungan/${visit.id}?asal=penyerahan` : `/pelayanan/farmasi/${visit.id}`} /></ActionButton></TableCell></TableRow>;
                    }) : <TableRow className="hover:bg-transparent"><TableCell className="text-center" colSpan={8}><Empty size="compact" title="Tidak ada resep pada tanggal dan filter ini." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={visits} /></CardContent>
                </Card>
            </div>
        </>
    );
}
