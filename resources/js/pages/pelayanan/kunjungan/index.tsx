import { ActionButton } from '@/components/ui/action-button';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CalendarDays, Clock3, ClipboardCheck, Plus, Stethoscope } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { StatusBadge, visitStatusFilterLabels } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { DatePicker } from '@/components/ui/date-picker';

interface Visit {
    id: number;
    number: string;
    date: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    doctor: string | null;
    status: string;
    examinationStarted?: boolean;
    isReservation?: boolean;
}

interface Option {
    id: number;
    name: string;
}

type VisitPagination = PaginationData & { data: Visit[] };

interface Props {
    visits: VisitPagination;
    todayVisits: VisitPagination;
    stats: { total: number; waiting: number; examination: number; completedToday: number };
    filters: { search: string; date: string; status: string; clinicId: string };
    clinics: Option[];
    canSelectClinic: boolean;
    canManageVisits: boolean;
}

const statuses = ['menunggu', 'screening', 'pemeriksaan', 'tindakan', 'farmasi', 'selesai', 'batal'];

export default function VisitsIndex({ visits, todayVisits, stats, filters: initialFilters, clinics, canSelectClinic, canManageVisits }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/kunjungan', { search: filters.search, tanggal: filters.date, status: filters.status, poliklinik_id: filters.clinicId }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function cancelVisit(id: number) {
        confirmAction('Batalkan kunjungan ini?', () => { router.post(`/pelayanan/kunjungan/${id}/batal`, {}, { preserveScroll: true }); }, 'Batalkan');
    }

    return (
        <>
            <Head title="Daftar kunjungan" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-neutral-700">Layanan klinik</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Daftar kunjungan</h2><p className="mt-1 text-sm text-neutral-500">Pantau alur layanan pasien berdasarkan tanggal, poli, dan status.</p></div>{canManageVisits && <Button asChild><Link href="/pelayanan/kunjungan/create"><Plus className="size-4" />Buka kunjungan baru</Link></Button>}</div>
                <section aria-label="Ringkasan kunjungan" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Total kunjungan" value={stats.total} description="Seluruh kunjungan sesuai akses poliklinik" icon={<Activity className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Menunggu pengkajian awal" value={stats.waiting} description="Kunjungan sampai hari ini yang belum dikaji" icon={<Clock3 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Pemeriksaan dokter" value={stats.examination} description="Menunggu atau sedang diperiksa dokter" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Kunjungan selesai hari ini" value={stats.completedToday} description="Pelayanan selesai pada tanggal hari ini" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </section>
                <VisitList icon={<Clock3 className="size-5" />} title="Kunjungan hari ini" description="Seluruh kunjungan hari ini sesuai akses poliklinik." visits={todayVisits} canManageVisits={canManageVisits} onCancel={cancelVisit} />
                <FilterPanel><form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={applyFilters}>
                    <Field htmlFor="visit-search" label="Cari pasien"><Input id="visit-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Cari nama atau No. RM" value={filters.search} /></Field>
                    <Field htmlFor="visit-date-filter" label="Tanggal kunjungan"><DatePicker id="visit-date-filter" onChange={(event) => setFilters({ ...filters, date: event.target.value })} value={filters.date} /></Field>
                    <Field htmlFor="visit-status-filter" label="Status"><Select id="visit-status-filter" onChange={(event) => setFilters({ ...filters, status: event.target.value })} value={filters.status}><option value="">Semua status</option>{statuses.map((status) => <option key={status} value={status}>{visitStatusFilterLabels[status]}</option>)}</Select></Field>
                    {canSelectClinic && <Field htmlFor="visit-clinic-filter" label="Poliklinik"><Select id="visit-clinic-filter" onChange={(event) => setFilters({ ...filters, clinicId: event.target.value })} value={filters.clinicId}><option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>}
                    <div className="flex flex-wrap gap-2 sm:col-span-2"><FilterSubmit /></div>
                </form></FilterPanel>
                <VisitList icon={<CalendarDays className="size-5" />} title="Daftar kunjungan pasien" description={`${new Intl.NumberFormat('id-ID').format(visits.total)} kunjungan sesuai filter poli, tanggal, dan status.`} visits={visits} canManageVisits={canManageVisits} onCancel={cancelVisit} />
            </div>
        </>
    );
}

function VisitList({ title, description, icon, visits, canManageVisits, onCancel }: { title: string; description: string; icon: ReactNode; visits: VisitPagination; canManageVisits: boolean; onCancel: (id: number) => void }) {
    return <Card className="overflow-hidden">
        <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">{icon}</span><div><CardTitle>{title}</CardTitle><CardDescription className="mt-1">{description}</CardDescription></div></div></CardHeader>
        <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[1100px]"><TableHeader><TableRow><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>
            {visits.data.length ? visits.data.map((visit) => <TableRow key={visit.id}><TableCell className="font-sans tabular-nums text-xs text-neutral-600"><Link className="font-semibold hover:underline" href={`/pelayanan/kunjungan/${visit.id}`}>{visit.number}</Link></TableCell><TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell><TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell><TableCell className="text-neutral-600">{visit.clinic}</TableCell><TableCell className="text-neutral-600">{visit.doctor ?? 'Belum ditentukan'}</TableCell><TableCell><StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} status={visit.status} /></TableCell><TableCell><div className="flex justify-center gap-2"><ActionButton asChild action="detail" label="Detail"><Link href={`/pelayanan/kunjungan/${visit.id}`} /></ActionButton><ActionButton disabledReason={!canManageVisits ? 'Akun Anda tidak memiliki akses pengelolaan kunjungan.' : 'Hanya kunjungan yang belum dilayani yang dapat dibatalkan.'} disabled={!(canManageVisits && visit.status === 'menunggu')} onClick={() => onCancel(visit.id)} action="cancel" label="Batalkan" /></div></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Tidak ada kunjungan pada filter yang dipilih." /></TableCell></TableRow>}
        </TableBody></Table></div><Pagination pagination={visits} /></CardContent>
    </Card>;
}
