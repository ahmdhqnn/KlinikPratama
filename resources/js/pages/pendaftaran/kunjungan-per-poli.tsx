import { ActionButton } from '@/components/ui/action-button';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CalendarDays, Clock3, ClipboardCheck, Search, Stethoscope } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { StatusBadge, visitStatusLabels } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DatePicker } from '@/components/ui/date-picker';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';

interface Clinic {
    id: number;
    name: string;
}

interface Visit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    doctor: string;
    date: string;
    clinic: string;
    status: string;
    isReservation?: boolean;
    actionUrl: string;
    actionLabel: string;
    ticketUrl: string | null;
    editUrl: string | null;
    cancelUrl: string | null;
}

interface Props {
    filters: { poliklinikId: number | ''; tanggal: string; status: string };
    clinics: Clinic[];
    stats: { total: number; waiting: number; screening: number; examination: number };
    access: { manageVisits: boolean; monitorClinicalFlow: boolean; selectClinic: boolean };
    visits: PaginationData & { data: Visit[] };
    recentVisits: PaginationData & { data: Visit[] };
}

const statusOptions = ['menunggu', 'screening', 'pemeriksaan', 'farmasi', 'selesai', 'batal'].map((status) => [status, visitStatusLabels[status]]);

export default function ClinicVisits({ filters: initialFilters, clinics, stats, access, visits, recentVisits }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/kunjungan-per-poli', { poliklinik_id: filters.poliklinikId, tanggal: filters.tanggal, status: filters.status }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Kunjungan Poli" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pemantauan antrean klinik</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Kunjungan poli</h2>
                    <p className="mt-1 text-sm text-neutral-500">Daftar mencakup seluruh dokter. Pilih tanggal untuk melihat periode tertentu.</p>
                </div>
                <FilterPanel><form className={`grid gap-4 sm:items-end ${access.selectClinic ? 'sm:grid-cols-2 xl:grid-cols-2' : 'sm:grid-cols-2'}`} onSubmit={applyFilters}>
                            {access.selectClinic && <Field htmlFor="visits-by-clinic" label="Poliklinik">
                                <Select id="visits-by-clinic" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                                    <option value="">Semua poliklinik</option>
                                    {clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                </Select>
                            </Field>}
                            <Field htmlFor="visits-by-clinic-date" label="Tanggal kunjungan">
                                <DatePicker id="visits-by-clinic-date" onChange={(event) => setFilters({ ...filters, tanggal: event.target.value })} value={filters.tanggal} />
                            </Field>
                            <Field htmlFor="visits-by-clinic-status" label="Status">
                                <Select id="visits-by-clinic-status" onChange={(event) => setFilters({ ...filters, status: event.target.value })} value={filters.status}>
                                    <option value="">Semua status</option>
                                    {statusOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </Select>
                            </Field>
                            <Button disabled={clinics.length === 0} type="submit"><Search className="size-4" />Terapkan filter</Button>
                        </form></FilterPanel>
                <section aria-label="Ringkasan antrean poliklinik" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard description="Kunjungan pada periode dan pilihan layanan Anda" icon={<Activity className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Total kunjungan" value={stats.total} />
                    <StatCard description="Belum memulai pengkajian awal perawat" icon={<Clock3 className="size-5" />} iconClassName="bg-amber-50 text-amber-700" label="Menunggu pengkajian awal" value={stats.waiting} />
                    <StatCard description="Sedang dalam pengkajian awal perawat" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Pengkajian awal berlangsung" value={stats.screening} />
                    <StatCard description="Menunggu atau sedang diperiksa dokter" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Pemeriksaan dokter" value={stats.examination} />
                </section>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><CalendarDays className="size-5" /></span>
                            <div><CardTitle>Daftar pasien</CardTitle><CardDescription className="mt-1">Daftar kunjungan di setiap poliklinik.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[1120px]">
                                <TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader>
                                <TableBody>
                                    {visits.data.length > 0 ? visits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-mono text-xs text-neutral-600">{visit.number}<p className="mt-1 text-xs text-neutral-500">{visit.date} · {visit.clinic}</p></TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.doctor}</TableCell>
                                            <TableCell><StatusBadge reservation={visit.isReservation} status={visit.status} /></TableCell>
                                            <TableCell><VisitActions manageVisits={access.manageVisits} visit={visit} /></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty description={clinics.length ? 'Coba sesuaikan filter tanggal untuk melihat kunjungan.' : 'Aktifkan poliklinik agar daftar kunjungan dapat ditampilkan.'} size="compact" title={clinics.length ? 'Tidak ada kunjungan pada filter ini.' : 'Belum ada poliklinik aktif.'} /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                    <Pagination pagination={visits} />
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Clock3 className="size-5" /></span>
                            <div><CardTitle>Kunjungan hari ini</CardTitle><CardDescription className="mt-1">Kunjungan terbaru di poliklinik.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[1120px]">
                                <TableHeader><tr><TableHead>Tanggal</TableHead><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader>
                                <TableBody>
                                    {recentVisits.data.length > 0 ? recentVisits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="text-neutral-600">{visit.date}</TableCell>
                                            <TableCell className="font-mono text-xs text-neutral-600">{visit.number}<p className="mt-1 text-xs text-neutral-500">{visit.date} · {visit.clinic}</p></TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.doctor}</TableCell>
                                            <TableCell><StatusBadge reservation={visit.isReservation} status={visit.status} /></TableCell>
                                            <TableCell><VisitActions manageVisits={access.manageVisits} visit={visit} /></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={6}><Empty description="Belum ada kunjungan terbaru untuk poliklinik ini." size="compact" title="Belum ada data kunjungan." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                    <Pagination pagination={recentVisits} />
                </Card>
            </div>
        </>
    );
}

function VisitActions({ visit, manageVisits }: { visit: Visit; manageVisits: boolean }) {
    const isScreeningAction = visit.actionLabel === 'Mulai pengkajian' || visit.actionLabel === 'Lanjutkan pengkajian';
    const isMedicalRecordAction = visit.actionLabel === 'Lihat RME';

    return <div className="flex justify-center gap-2">
        <ActionButton asChild action={isScreeningAction ? 'screening' : isMedicalRecordAction ? 'rme' : 'detail'} label={visit.actionLabel}><Link href={visit.actionUrl} /></ActionButton>
        {manageVisits && visit.editUrl && <ActionButton asChild action="edit" label={`Perbarui kunjungan ${visit.number}`}><Link href={visit.editUrl} /></ActionButton>}
        {manageVisits && visit.cancelUrl && <ActionButton onClick={() => confirmAction(`Batalkan kunjungan ${visit.number} untuk ${visit.patient}?`, () => { router.post(visit.cancelUrl as string, {}, { preserveScroll: true }); }, 'Batalkan')} type="button" action="cancel" label={`Batalkan kunjungan ${visit.number}`} />}
        {visit.ticketUrl && <ActionButton asChild action="print" label={`Cetak antrean ${visit.number}`}><a href={visit.ticketUrl} /></ActionButton>}
    </div>;
}
