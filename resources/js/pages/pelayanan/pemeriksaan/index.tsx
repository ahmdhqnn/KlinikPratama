import { ActionButton } from '@/components/ui/action-button';
import { StatCard } from '@/components/dashboard/stat-card';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, ClipboardCheck, ClipboardPlus, Clock3, Search, Stethoscope } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DatePicker } from '@/components/ui/date-picker';

interface Visit {
    id: number;
    date: string;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    gender: string | null;
    clinic: string;
    doctor: string | null;
    status: string;
    examinationStarted?: boolean;
    diagnosisCount: number;
    recordUrl: string | null;
}

export default function ExaminationsIndex({ visits, date: initialDate, stats }: { visits: PaginationData & { data: Visit[] }; date: string; stats: { total: number; waiting: number; inProgress: number; completed: number } }) {
    const [date, setDate] = useState(initialDate);

    function filterVisits(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/pemeriksaan', { tanggal: date }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Pemeriksaan Dokter" />
            <div className="space-y-6">
                <div><p className="text-sm font-medium text-neutral-700">Layanan klinis</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pemeriksaan dokter</h2><p className="mt-1 text-sm text-neutral-500">Tinjau pasien, catat diagnosis, dan lanjutkan rencana pelayanan.</p></div>
                <section aria-label="Ringkasan pemeriksaan hari ini" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4 [&>section]:h-full [&>section>div]:h-full">
                    <StatCard label="Pemeriksaan hari ini" value={stats.total} description="Kunjungan pada tahap dokter dan sesudahnya" icon={<Activity className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" />
                    <StatCard label="Menunggu pemeriksaan" value={stats.waiting} description="Belum mulai diperiksa hari ini" icon={<Clock3 className="size-5" />} iconClassName="bg-amber-50 text-amber-700" />
                    <StatCard label="Sedang diperiksa" value={stats.inProgress} description="Pemeriksaan hari ini belum final" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" />
                    <StatCard label="Pemeriksaan selesai" value={stats.completed} description="Kunjungan hari ini sudah final" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-emerald-50 text-emerald-700" />
                </section>
                <FilterPanel><form className="flex flex-col gap-4 sm:flex-row sm:items-end" onSubmit={filterVisits}><Field className="w-full sm:max-w-xs" htmlFor="examination-visit-date" label="Tanggal kunjungan"><DatePicker id="examination-visit-date" onChange={(event) => setDate(event.target.value)} value={date} /></Field><Button type="submit"><Search className="size-4" />Tampilkan</Button></form></FilterPanel>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ClipboardPlus className="size-5" /></span><div><CardTitle>Daftar pemeriksaan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} kunjungan.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead>Diagnosis</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader><TableBody>
                        {visits.data.length ? visits.data.map((visit) => <TableRow key={visit.id}><TableCell className="font-mono text-xs font-semibold text-neutral-700">{visit.number}<p className="mt-1 text-xs text-neutral-500">{visit.date}</p></TableCell><TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="font-mono text-xs text-neutral-500">{visit.medicalRecordNumber} · {visit.gender ?? 'Belum diisi'}</p></TableCell><TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor ?? 'Belum ditentukan'}</TableCell><TableCell><StatusBadge examinationStarted={visit.examinationStarted} status={visit.status} /></TableCell><TableCell>{visit.diagnosisCount}</TableCell><TableCell className="text-center"><div className="flex items-center justify-center gap-2"><ActionButton asChild action="examination" label={visit.status === 'pemeriksaan' ? (visit.examinationStarted ? 'Lanjutkan pemeriksaan' : 'Mulai pemeriksaan') : 'Tinjau pemeriksaan'}><Link href={`/pelayanan/pemeriksaan/${visit.id}`} /></ActionButton>{visit.recordUrl ? <ActionButton asChild action="rme" label="Rekam medis"><Link href={visit.recordUrl} /></ActionButton> : <ActionButton action="rme" label="Mulai pemeriksaan terlebih dahulu untuk membuka rekam medis" disabled />}</div></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Belum ada kunjungan untuk ditampilkan." /></TableCell></TableRow>}
                    </TableBody></Table></div><Pagination pagination={visits} /></CardContent>
                </Card>
            </div>
        </>
    );
}
