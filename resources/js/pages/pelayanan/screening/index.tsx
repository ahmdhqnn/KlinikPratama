import { StatCard } from '@/components/dashboard/stat-card';
import { DatePicker } from '@/components/ui/date-picker';
import { ActionButton } from '@/components/ui/action-button';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CalendarDays, Clock3, ClipboardCheck, HeartPulse, Search } from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';

interface Clinic {
    id: number;
    name: string;
}

interface Visit {
    id: number;
    number: string;
    date: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    hasScreening: boolean;
    status: string;
    screeningState: string;
    screeningUrl: string;
    actionUrl: string;
    actionLabel: string;
    printUrl: string | null;
}

interface Props {
    serviceName: string;
    stats: { total: number; waiting: number; inProgress: number; submitted: number };
    filters: { search: string; tanggal: string; poliklinikId: number | '' };
    clinics: Clinic[];
    canSelectClinic: boolean;
    visits: PaginationData & { data: Visit[] };
    todayVisits: PaginationData & { data: Visit[] };
}

export default function ScreeningQueue({ filters: initialFilters, clinics, canSelectClinic, visits, todayVisits, stats, serviceName }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/screening', {
            search: filters.search,
            tanggal: filters.tanggal,
            ...(canSelectClinic ? { poliklinik_id: filters.poliklinikId } : {}),
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Pengkajian Awal" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pengkajian awal</h2>
                    <p className="mt-1 text-sm text-neutral-500">Pantau kunjungan hari ini dan seluruh riwayat pengkajian di {serviceName}.</p>
                </div>
                <section aria-label="Ringkasan pengkajian hari ini" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Kunjungan hari ini" value={stats.total} description={serviceName} icon={<Activity className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" />
                    <StatCard label="Menunggu pengkajian" value={stats.waiting} description="Pasien hari ini belum mulai dikaji" icon={<Clock3 className="size-5" />} iconClassName="bg-amber-50 text-amber-700" />
                    <StatCard label="Sedang dikaji" value={stats.inProgress} description="Pengkajian hari ini belum terkirim" icon={<HeartPulse className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" />
                    <StatCard label="Pengkajian terkirim" value={stats.submitted} description="Kunjungan hari ini sudah dikaji" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-emerald-50 text-emerald-700" />
                </section>
                <VisitList title="Kunjungan hari ini" description="Seluruh kunjungan hari ini sesuai akses poliklinik Anda." icon={<Clock3 className="size-5" />} visits={todayVisits} />
                <FilterPanel>
                    <form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={applyFilters}>
                        <Field htmlFor="screening-queue-search" label="Cari pasien">
                            <Input id="screening-queue-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Cari nama atau No. RM" value={filters.search} />
                        </Field>
                        <Field htmlFor="screening-queue-date" label="Tanggal kunjungan">
                            <DatePicker id="screening-queue-date" onChange={(event) => setFilters({ ...filters, tanggal: event.target.value })} value={filters.tanggal} />
                        </Field>
                        {canSelectClinic && <Field htmlFor="screening-queue-clinic" label="Poliklinik">
                            <Select id="screening-queue-clinic" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                                <option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                            </Select>
                        </Field>}
                        <Button type="submit"><Search className="size-4" />Terapkan filter</Button>
                    </form>
                </FilterPanel>
                <VisitList title="Daftar kunjungan pasien" description={`${new Intl.NumberFormat('id-ID').format(visits.total)} kunjungan sesuai filter, termasuk pengkajian yang sudah terkirim.`} icon={<CalendarDays className="size-5" />} visits={visits} />
            </div>
        </>
    );
}

function VisitList({ title, description, icon, visits }: { title: string; description: string; icon: ReactNode; visits: Props['visits'] }) {
    return <Card className="overflow-hidden">
        <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700">{icon}</span><div><CardTitle>{title}</CardTitle><CardDescription className="mt-1">{description}</CardDescription></div></div></CardHeader>
        <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[1040px]"><TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Status pengkajian</TableHead><TableHead>Status kunjungan</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader><TableBody>
            {visits.data.length ? visits.data.map((visit) => <TableRow key={visit.id}>
                <TableCell className="font-mono text-xs text-neutral-600">{visit.number}</TableCell>
                <TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                <TableCell><Badge variant={visit.screeningState === 'Terkirim' ? 'complete' : 'default'}>{visit.screeningState}</Badge></TableCell>
                <TableCell><StatusBadge status={visit.status} /></TableCell>
                <TableCell><div className="flex justify-center gap-2"><ActionButton asChild action={visit.actionLabel.includes('pengkajian') ? 'screening' : visit.actionLabel === 'Lihat RME' ? 'rme' : 'detail'} label={visit.actionLabel}><Link href={visit.actionUrl} /></ActionButton>{visit.printUrl && <ActionButton asChild action="print" label={`Cetak pengkajian ${visit.number}`}><a href={visit.printUrl} /></ActionButton>}</div></TableCell>
            </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Belum ada kunjungan" description="Kunjungan pada poli dan filter ini akan tampil di sini." /></TableCell></TableRow>}
        </TableBody></Table></div></CardContent><Pagination pagination={visits} />
    </Card>;
}
