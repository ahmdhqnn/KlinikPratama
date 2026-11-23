import { Input } from '@/components/ui/input';
import { type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { StatusBadge, visitStatusFilterLabels } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { ReportTableCard } from '@/components/dashboard/report-table-card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/react';
import { Activity, Download, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Visit {
    id: number;
    number: string;
    date: string | null;
    medicalRecordNumber: string;
    patient: string;
    clinic: string;
    doctor: string | null;
    unit: string;
    status: string;
    examinationStarted?: boolean;
}

interface Props {
    filters: { search?: string; from: string; to: string; clinicId: string | number; status: string };
    clinics: { id: number; name: string }[];
    stats: { total: number; completed: number; verified: number };
    visits: PaginationData & { data: Visit[] };
    exportUrl: string;
}

export default function VisitReport({ filters, clinics, stats, visits, exportUrl }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [clinicId, setClinicId] = useState(String(filters.clinicId));
    const [status, setStatus] = useState(filters.status);

    function filterReport(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/laporan/kunjungan', { search, dari: from, sampai: to, poliklinik_id: clinicId, status }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Laporan kunjungan" />
            <div className="space-y-6">
                <div><p className="text-sm font-medium text-neutral-700">Analisis pelayanan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laporan kunjungan pasien</h2><p className="mt-1 text-sm text-neutral-500">Pantau volume kunjungan berdasarkan periode, poliklinik, dan status pelayanan.</p></div>
                <div className="grid gap-4 md:grid-cols-3"><StatCard description="Dalam rentang tanggal terpilih" icon={<Activity className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Total kunjungan" value={stats.total} /><StatCard description="Kunjungan telah diselesaikan" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Kunjungan selesai" value={stats.completed} /><StatCard description="Hak layanan tercatat saat pendaftaran" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Hak terverifikasi" value={stats.verified} /></div>
                <FilterPanel actions={<Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}><form className="grid gap-4 sm:grid-cols-2 sm:items-end xl:grid-cols-2" onSubmit={filterReport}>
                    <Field htmlFor="visit-report-search" label="Cari pasien"><Input id="visit-report-search" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Nama atau No. RM" /></Field>
                    <Field htmlFor="visit-report-period" label="Periode kunjungan"><DateRangePicker id="visit-report-period" from={from} onChange={(range) => { setFrom(range.from); setTo(range.to); }} to={to} /></Field>
                    <Field htmlFor="visit-report-clinic" label="Poliklinik"><Select id="visit-report-clinic" onChange={(event) => setClinicId(event.target.value)} value={clinicId}><option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>
                    <Field htmlFor="visit-report-status" label="Status kunjungan"><Select id="visit-report-status" onChange={(event) => setStatus(event.target.value)} value={status}><option value="">Semua status</option>{Object.entries(visitStatusFilterLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>
                    <FilterSubmit />
                </form></FilterPanel>
                <ReportTableCard title="Daftar kunjungan pasien" description={`${new Intl.NumberFormat('id-ID').format(visits.total)} kunjungan tercatat.`} icon={<Activity className="size-5" />} pagination={visits}>
                    <Table className="min-w-[850px]">
                        <TableHeader><TableRow><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Unit kerja</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
                        <TableBody>{visits.data.length ? visits.data.map((visit) => <TableRow key={visit.id}><TableCell className="font-sans tabular-nums text-xs font-semibold">{visit.number}</TableCell><TableCell>{visit.date ?? 'Belum diisi'}</TableCell><TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell><TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor ?? 'Belum diisi'}</TableCell><TableCell>{visit.unit}</TableCell><TableCell><StatusBadge examinationStarted={visit.examinationStarted} status={visit.status} /></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Tidak ada data kunjungan pada filter ini." /></TableCell></TableRow>}</TableBody>
                    </Table>
                </ReportTableCard>
            </div>
        </>
    );
}
