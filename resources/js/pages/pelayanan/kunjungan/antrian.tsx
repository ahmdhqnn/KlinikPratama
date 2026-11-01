import { Head, Link, router } from '@inertiajs/react';
import { Activity, CalendarDays, Clock3, RefreshCw, Search } from 'lucide-react';
import { useState, type ComponentProps, type FormEvent, type ReactNode } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge, visitStatusFilterLabels } from '@/components/dashboard/status-badge';
import { ActionButton } from '@/components/ui/action-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { DatePicker } from '@/components/ui/date-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface QueueAction {
    action: ComponentProps<typeof ActionButton>['action'];
    label: string;
    href: string | null;
    disabled: boolean;
    disabledReason: string | null;
    method?: 'post';
}

interface Visit {
    id: number;
    number: string;
    date: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    doctor: string | null;
    status: string;
    examinationStarted: boolean;
    isReservation: boolean;
    actions: QueueAction[];
}

type VisitPagination = PaginationData & { data: Visit[] };
interface Props {
    todayVisits: VisitPagination;
    visits: VisitPagination;
    today: string;
    queueUrl: string;
    serviceQueues: Record<string, { total: number; data: Visit[] }>;
    filters: { search: string; date: string; status: string; clinicId: string };
    clinics: { id: number; name: string }[];
}

const stages = [
    { key: 'assessment', label: 'Antrean pengkajian awal perawat' },
    { key: 'examination', label: 'Pemeriksaan dokter' },
    { key: 'procedures', label: 'Tindakan perawat' },
    { key: 'pharmacy', label: 'Penyerahan obat' },
];

function QueueTable({ title, description, icon, visits }: { title: string; description: string; icon: ReactNode; visits: VisitPagination }) {
    return (
        <Card className="overflow-hidden">
            <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">{icon}</span><div><CardTitle>{title}</CardTitle><CardDescription className="mt-1">{description} · {visits.total} kunjungan</CardDescription></div></div></CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto">
                    <Table className="min-w-[1100px]">
                        <TableHeader><TableRow><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi operasional</TableHead></TableRow></TableHeader>
                        <TableBody>
                            {visits.data.length ? visits.data.map((visit) => (
                                <TableRow key={visit.id}>
                                    <TableCell className="font-mono text-xs text-neutral-600">{visit.number}</TableCell><TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                                    <TableCell><p className="font-medium">{visit.patient}</p><p className="font-mono text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                    <TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor ?? 'Belum ditentukan'}</TableCell>
                                    <TableCell><StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} status={visit.status} /></TableCell>
                                    <TableCell><div className="mx-auto grid w-fit grid-cols-5 gap-2">
                                        {visit.actions.map((action) => action.method === 'post'
                                            ? <ActionButton key={action.action} disabled={action.disabled} disabledReason={action.disabledReason} action={action.action} label={action.label} onClick={() => confirmAction(`Batalkan kunjungan ${visit.number} atas nama ${visit.patient}?`, () => router.post(action.href!, {}, { preserveScroll: true }), 'Batalkan')} />
                                            : <ActionButton key={action.action} asChild disabled={action.disabled} disabledReason={action.disabledReason} action={action.action} label={action.label}><Link href={action.href ?? ''} /></ActionButton>)}
                                    </div></TableCell>
                                </TableRow>
                            )) : <TableRow><TableCell colSpan={7}><Empty size="compact" title="Tidak ada kunjungan pada filter yang dipilih." /></TableCell></TableRow>}
                        </TableBody>
                    </Table>
                </div>
                <Pagination pagination={visits} />
            </CardContent>
        </Card>
    );
}

export default function VisitQueue({ visits, todayVisits, today, queueUrl, serviceQueues, filters: initialFilters, clinics }: Props) {
    const [filters, setFilters] = useState(initialFilters);
    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(queueUrl, { search: filters.search, tanggal: filters.date, status: filters.status, poliklinik_id: filters.clinicId }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Antrean Pasien" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div><p className="text-sm font-medium text-neutral-700">Operasional pelayanan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Antrean pasien</h2><p className="mt-1 text-sm text-neutral-500">Pusat pelayanan setelah pendaftaran: pengkajian, pemeriksaan, tindakan, persuratan, dan penyerahan obat.</p></div>
                    <Button onClick={() => router.reload()} variant="secondary"><RefreshCw className="size-4" />Perbarui antrean</Button>
                </div>
                <section aria-label="Antrean aktif hari ini menurut tahap pelayanan" className="grid min-w-0 items-stretch gap-4 md:grid-cols-2 2xl:grid-cols-4">
                    {stages.map((stage) => {
                        const queue = serviceQueues[stage.key];
                        return <Card className="flex min-w-0 flex-col overflow-hidden" key={stage.key}>
                            <CardHeader className="border-b border-neutral-200 bg-neutral-50"><div className="flex items-center justify-between gap-3"><CardTitle className="text-base leading-6">{stage.label}</CardTitle><span className="shrink-0 rounded-full bg-surface px-2.5 py-1 text-sm font-bold tabular-nums text-neutral-800">{queue.total}</span></div></CardHeader>
                            <CardContent className="max-h-96 min-h-56 flex-1 space-y-3 overflow-y-auto p-5 sm:p-6">
                                {queue.data.length ? queue.data.map((visit) => <article className="rounded-xl border border-neutral-200 bg-neutral-50 p-4" key={visit.id}>
                                    <div className="flex flex-wrap items-start justify-between gap-2"><span className="break-all font-mono text-xs font-semibold text-neutral-700">{visit.number}</span><StatusBadge examinationStarted={visit.examinationStarted} status={visit.status} /></div>
                                    <p className="mt-3 break-words font-semibold leading-5 text-neutral-900">{visit.patient}</p><p className="mt-1 break-words text-xs leading-5 text-neutral-600">{visit.clinic}{visit.doctor ? ` · ${visit.doctor}` : ''}</p>
                                    <div className="mt-4 flex justify-end border-t border-neutral-200 pt-3"><ActionButton asChild action="detail" label={`Detail kunjungan ${visit.number}`}><Link href={visit.actions[0].href ?? ''} /></ActionButton></div>
                                </article>) : <div className="flex min-h-48 items-center justify-center"><Empty description="Pasien pada tahap ini akan muncul di antrean." icon={<Activity aria-hidden="true" className="size-5" />} size="compact" title="Tidak ada antrean" /></div>}
                            </CardContent>
                            {queue.total > queue.data.length && <div className="border-t border-neutral-100 px-5 py-3 text-xs text-neutral-500">Menampilkan {queue.data.length} dari {queue.total} pasien. Lihat seluruh antrean pada tabel hari ini.</div>}
                        </Card>;
                    })}
                </section>
                <QueueTable icon={<Clock3 className="size-5" />} title="Kunjungan hari ini" description={`${today} · Seluruh kunjungan hari ini`} visits={todayVisits} />
                <FilterPanel><form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={applyFilters}>
                    <p className="text-sm text-neutral-600 sm:col-span-2">Filter ini mengatur daftar kunjungan di bawah; pemantauan kunjungan hari ini tetap menampilkan seluruh pasien hari ini.</p>
                    <Field htmlFor="queue-search" label="Cari pasien"><Input id="queue-search" placeholder="Nama atau No. RM" value={filters.search} onChange={(event) => setFilters({ ...filters, search: event.target.value })} /></Field>
                    <Field htmlFor="queue-clinic" label="Poliklinik"><Select id="queue-clinic" value={filters.clinicId} onChange={(event) => setFilters({ ...filters, clinicId: event.target.value })}><option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>
                    <Field htmlFor="queue-status" label="Status"><Select id="queue-status" value={filters.status} onChange={(event) => setFilters({ ...filters, status: event.target.value })}><option value="">Semua status</option>{Object.entries(visitStatusFilterLabels).map(([status, label]) => <option key={status} value={status}>{label}</option>)}</Select></Field>
                    <Field htmlFor="queue-date" label="Tanggal kunjungan"><DatePicker id="queue-date" value={filters.date} onChange={(event) => setFilters({ ...filters, date: event.target.value })} /></Field>
                    <div className="flex flex-wrap gap-2 sm:col-span-2"><Button type="submit"><Search className="size-4" />Terapkan filter</Button><Button type="button" variant="secondary" onClick={() => { setFilters({ search: '', date: '', status: '', clinicId: '' }); router.get(queueUrl); }}>Reset</Button></div>
                </form></FilterPanel>
                <QueueTable icon={<CalendarDays className="size-5" />} title="Daftar kunjungan pasien" description="Riwayat, kunjungan aktif, dan reservasi sesuai filter" visits={visits} />
            </div>
        </>
    );
}
