import { StatCard } from '@/components/dashboard/stat-card';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { ActionButton } from '@/components/ui/action-button';
import { FilterPanel } from '@/components/ui/filter-panel';
import { DatePicker } from '@/components/ui/date-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CalendarDays, ClipboardCheck, Clock3, FileText, Search } from 'lucide-react';
import { useEffect, useState, type FormEvent, type ReactNode } from 'react';

interface Visit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    doctor: string;
    date: string;
    status: string;
    examinationStarted: boolean;
    treatmentStarted: boolean;
    pendingCount: number;
    completedCount: number;
    url: string | null;
}

interface Props {
    stats: { todayTotal: number; todayPending: number; todayCompleted: number; allPending: number };
    filters: { search: string; tanggal: string; status: string };
    todayVisits: PaginationData & { data: Visit[] };
    visits: PaginationData & { data: Visit[] };
}

export default function NursingProcedureQueue({ stats, filters: initialFilters, todayVisits, visits }: Props) {
    const [filters, setFilters] = useState(initialFilters);

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

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/tindakan-perawat', filters, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <>
        <Head title="Tindakan Perawat" />
        <div className="space-y-6">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Tindakan perawat</h2>
                    <p className="mt-1 text-sm text-neutral-500">Lihat tindakan yang sudah disiapkan dokter untuk pasien di poli Anda.</p>
                </div>
                <Button asChild variant="secondary"><Link href="/perawat/laporan-tindakan"><FileText className="size-4" />Laporan tindakan</Link></Button>
            </div>
            <section aria-label="Ringkasan tindakan perawat" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Kunjungan poli hari ini" value={stats.todayTotal} description="Kunjungan pada poli Anda hari ini" icon={<Activity className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Menunggu tindakan" value={stats.todayPending} description="Kunjungan hari ini dengan tindakan yang belum dilakukan" icon={<Clock3 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Tindakan selesai hari ini" value={stats.todayCompleted} description="Kunjungan hari ini dengan seluruh tindakan perawat selesai" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Tindakan tertunda" value={stats.allPending} description="Termasuk kunjungan dari hari sebelumnya" icon={<Clock3 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
            </section>
            <VisitList title="Menunggu tindakan hari ini" description="Tindakan dari dokter yang siap dikerjakan perawat, diurutkan dari waktu tunggu terlama." icon={<Clock3 className="size-5" />} visits={todayVisits} />
            <FilterPanel><form className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 lg:items-end" onSubmit={applyFilters}>
                <Field htmlFor="nursing-action-search" label="Cari kunjungan atau pasien"><Input id="nursing-action-search" placeholder="Nama, No. RM, atau No. kunjungan" value={filters.search} onChange={(event) => setFilters({ ...filters, search: event.target.value })} /></Field>
                <Field htmlFor="nursing-action-date" label="Tanggal kunjungan"><DatePicker id="nursing-action-date" value={filters.tanggal} onChange={(event) => setFilters({ ...filters, tanggal: event.target.value })} /></Field>
                <Field htmlFor="nursing-action-status" label="Status tindakan"><Select id="nursing-action-status" value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}><option value="">Semua status</option><option value="pending">Menunggu tindakan</option><option value="completed">Tindakan selesai</option></Select></Field>
                <Button type="submit"><Search className="size-4" />Terapkan filter</Button>
            </form></FilterPanel>
            <VisitList title="Daftar kunjungan pasien" description={`${new Intl.NumberFormat('id-ID').format(visits.total)} kunjungan dengan tindakan perawat sesuai filter.`} icon={<CalendarDays className="size-5" />} visits={visits} />
        </div>
    </>;
}

function VisitList({ title, description, icon, visits }: { title: string; description: string; icon: ReactNode; visits: Props['visits'] }) {
    return <Card className="overflow-hidden">
        <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">{icon}</span><div><CardTitle>{title}</CardTitle><CardDescription className="mt-1">{description}</CardDescription></div></div></CardHeader>
        <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[1060px]"><TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Dokter / poli</TableHead><TableHead>Tindakan</TableHead><TableHead>Status kunjungan</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader><TableBody>
            {visits.data.length ? visits.data.map((visit) => <TableRow key={visit.id}>
                <TableCell className="font-mono text-xs text-neutral-600">{visit.number}</TableCell>
                <TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                <TableCell>{visit.doctor}<p className="mt-0.5 text-xs text-neutral-500">{visit.clinic}</p></TableCell>
                <TableCell>{visit.url ? <><span className="font-medium text-neutral-700">{visit.pendingCount > 0 ? 'Tertunda' : 'Selesai'}</span><p className="mt-0.5 text-xs text-neutral-500">{visit.completedCount} selesai</p></> : <span className="text-sm text-neutral-500">Menunggu instruksi dokter</span>}</TableCell>
                <TableCell><StatusBadge context="nurse" examinationStarted={visit.examinationStarted} status={visit.status} treatmentStarted={visit.treatmentStarted} /></TableCell>
                <TableCell className="text-center"><div className="flex justify-center gap-2">{visit.url ? <><ActionButton asChild action="detail" label="Lihat pelaksanaan"><Link href={visit.url} /></ActionButton>{visit.pendingCount > 0 && visit.status === 'tindakan' && <ActionButton asChild action="procedure" label={visit.treatmentStarted ? 'Lanjutkan tindakan' : 'Mulai tindakan'}><Link href={`${visit.url}#pelaksanaan-tindakan`} /></ActionButton>}</> : <ActionButton action="detail" disabled label="Tindakan belum tersedia" />}</div></TableCell>
            </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Belum ada tindakan pada daftar ini" description="Pasien akan muncul saat dokter menyiapkan tindakan perawat." /></TableCell></TableRow>}
        </TableBody></Table></div></CardContent><Pagination pagination={visits} />
    </Card>;
}
