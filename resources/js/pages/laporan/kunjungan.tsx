import { Head, router } from '@inertiajs/react';
import { Activity, Download, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DateRangePicker } from '@/components/ui/date-range-picker';

interface Visit {
    id: number;
    number: string;
    date: string | null;
    medicalRecordNumber: string;
    patient: string;
    clinic: string;
    doctor: string | null;
    payer: string;
    status: string;
}

interface Props {
    filters: { from: string; to: string; clinicId: string | number; status: string };
    clinics: { id: number; name: string }[];
    stats: { total: number; bpjs: number; general: number };
    visits: PaginationData & { data: Visit[] };
    exportUrl: string;
}

export default function VisitReport({ filters, clinics, stats, visits, exportUrl }: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [clinicId, setClinicId] = useState(String(filters.clinicId));
    const [status, setStatus] = useState(filters.status);

    function filterReport(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/laporan/kunjungan', { dari: from, sampai: to, poliklinik_id: clinicId, status }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Laporan Kunjungan" />
            <div className="space-y-6">
                <div><p className="text-sm font-medium text-neutral-700">Analisis pelayanan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laporan kunjungan pasien</h2><p className="mt-1 text-sm text-neutral-500">Pantau volume kunjungan berdasarkan periode, poliklinik, dan status pelayanan.</p></div>
                <Card><CardContent className="p-5 sm:p-6"><form className="grid gap-4 sm:grid-cols-2 sm:items-end xl:grid-cols-[minmax(18rem,1.5fr)_minmax(12rem,1fr)_minmax(12rem,1fr)_auto_auto]" onSubmit={filterReport}>
                    <Field htmlFor="visit-report-period" label="Periode kunjungan" required><DateRangePicker id="visit-report-period" from={from} onChange={(range) => { setFrom(range.from); setTo(range.to); }} required to={to} /></Field>
                    <Field htmlFor="visit-report-clinic" label="Poliklinik"><Select id="visit-report-clinic" onChange={(event) => setClinicId(event.target.value)} value={clinicId}><option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>
                    <Field htmlFor="visit-report-status" label="Status kunjungan"><Select id="visit-report-status" onChange={(event) => setStatus(event.target.value)} value={status}><option value="">Semua status</option>{['menunggu', 'screening', 'pemeriksaan', 'farmasi', 'kasir', 'selesai', 'batal'].map((value) => <option key={value} value={value}>{value.charAt(0).toUpperCase() + value.slice(1)}</option>)}</Select></Field>
                    <Button type="submit">Terapkan filter</Button><Button asChild variant="secondary"><a href={exportUrl}><Download className="size-4" />Export Excel</a></Button>
                </form></CardContent></Card>
                <div className="grid gap-4 md:grid-cols-3"><StatCard description="Dalam rentang tanggal terpilih" icon={<Activity className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Total kunjungan" value={stats.total} /><StatCard description="Pembayaran melalui BPJS" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Kunjungan BPJS" value={stats.bpjs} /><StatCard description="Pembayaran umum" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Kunjungan umum" value={stats.general} /></div>
                <Card className="overflow-hidden"><div className="overflow-x-auto"><Table className="min-w-[1050px]"><TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>No. RM</TableHead><TableHead>Nama pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Penjamin</TableHead><TableHead>Status</TableHead></tr></TableHeader><TableBody>{visits.data.length ? visits.data.map((visit) => <TableRow key={visit.id}><TableCell className="font-mono text-xs font-semibold">{visit.number}</TableCell><TableCell>{visit.date ?? '—'}</TableCell><TableCell className="font-mono">{visit.medicalRecordNumber}</TableCell><TableCell className="font-medium text-neutral-900">{visit.patient}</TableCell><TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor ?? '—'}</TableCell><TableCell className="uppercase">{visit.payer}</TableCell><TableCell><StatusBadge status={visit.status} /></TableCell></TableRow>) : <TableRow><TableCell colSpan={8}><Empty size="compact" title="Tidak ada data kunjungan pada filter ini." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={visits} /></Card>
            </div>
        </>
    );
}
