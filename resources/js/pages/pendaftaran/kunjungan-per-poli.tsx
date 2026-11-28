import { ActionButton } from '@/components/ui/action-button';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { Activity, CalendarDays, Clock3, ClipboardCheck, Stethoscope } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { StatusBadge, visitStatusFilterLabels } from '@/components/dashboard/status-badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableScroll } from '@/components/ui/table';
import { DatePicker } from '@/components/ui/date-picker';
import { confirmReason } from '@/components/ui/confirm-dialog';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';

interface Option {
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
    examinationStarted?: boolean;
    pendingReservation?: boolean;
    isReservation?: boolean;
    actionUrl: string;
    actionLabel: string;
    consentUrl?: string | null;
    ticketUrl: string | null;
    editUrl: string | null;
    cancelUrl: string | null;
}

interface Props {
    filters: { poliklinikId: number | ''; tanggal: string; status: string; search?: string; dokterId?: number | '' };
    reservationFilters?: { search: string; poliklinikId: number | ''; tanggal: string };
    clinics: Option[];
    doctors?: Option[];
    stats: { total: number; waiting: number; screening: number; examination: number; completedToday: number };
    access: { manageVisits: boolean; monitorClinicalFlow: boolean; selectClinic: boolean };
    visits: PaginationData & { data: Visit[] };
    recentVisits: PaginationData & { data: Visit[] };
    reservations?: PaginationData & { data: Visit[] };
}

const statusOptions = Object.entries(visitStatusFilterLabels);

export default function ClinicVisits({
    filters: initialFilters,
    reservationFilters: initialResFilters = { search: '', poliklinikId: '', tanggal: '' },
    clinics,
    doctors = [],
    stats,
    access,
    visits,
    recentVisits,
    reservations,
}: Props) {
    const [filters, setFilters] = useState({
        poliklinikId: initialFilters.poliklinikId,
        tanggal: initialFilters.tanggal,
        status: initialFilters.status,
        search: initialFilters.search ?? '',
        dokterId: initialFilters.dokterId ?? '',
    });
    const [resFilters, setResFilters] = useState(initialResFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/kunjungan-per-poli', {
            poliklinik_id: filters.poliklinikId,
            tanggal: filters.tanggal,
            status: filters.status,
            search: filters.search,
            dokter_id: filters.dokterId,
            res_search: resFilters.search,
            res_poli: resFilters.poliklinikId,
            res_tanggal: resFilters.tanggal,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function applyReservationFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/kunjungan-per-poli', {
            poliklinik_id: filters.poliklinikId,
            tanggal: filters.tanggal,
            status: filters.status,
            search: filters.search,
            dokter_id: filters.dokterId,
            res_search: resFilters.search,
            res_poli: resFilters.poliklinikId,
            res_tanggal: resFilters.tanggal,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Kunjungan poli" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pemantauan antrean klinik</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Kunjungan poli</h2>
                    <p className="mt-1 text-sm text-neutral-500">Daftar mencakup seluruh dokter. Pilih tanggal atau pencarian untuk melihat periode tertentu.</p>
                </div>

                <section aria-label="Ringkasan antrean poliklinik" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard description="Seluruh kunjungan tercatat" icon={<Activity className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Total kunjungan" value={stats.total} />
                    <StatCard description="Belum dikaji sampai hari ini" icon={<Clock3 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Menunggu pengkajian" value={stats.waiting} />
                    <StatCard description="Menunggu atau diperiksa dokter" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Pemeriksaan dokter" value={stats.examination} />
                    <StatCard description="Pelayanan selesai hari ini" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Selesai hari ini" value={stats.completedToday} />
                </section>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Clock3 className="size-5" /></span>
                            <div><CardTitle>Kunjungan hari ini</CardTitle><CardDescription className="mt-1">Seluruh kunjungan hari ini sesuai akses poliklinik Anda.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <TableScroll className="overflow-x-auto">
                            <Table className="min-w-[1120px]">
                                <TableHeader><TableRow><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {recentVisits.data.length > 0 ? recentVisits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-sans tabular-nums text-xs text-neutral-600">{visit.number}</TableCell>
                                            <TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.doctor}</TableCell>
                                            <TableCell><StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} pendingReservation={visit.pendingReservation} status={visit.status} /></TableCell>
                                            <TableCell><VisitActions manageVisits={access.manageVisits} visit={visit} /></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={6}><Empty description="Belum ada kunjungan terbaru untuk poliklinik ini." size="compact" title="Belum ada data kunjungan." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </TableScroll>
                        <Pagination pagination={recentVisits} />
                    </CardContent>
                </Card>
                {reservations && (
                    <div className="space-y-3">
                        <FilterPanel ariaLabel="Filter reservasi 7 hari ke depan" filterKeys={['res_search', 'res_poli', 'res_tanggal']}>
                            <form className={access.selectClinic ? 'grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3' : 'grid min-w-0 gap-4 sm:grid-cols-2'} onSubmit={applyReservationFilters}>
                                <Field htmlFor="res-filter-search" label="Cari pasien / No. RM">
                                    <Input id="res-filter-search" onChange={(event) => setResFilters({ ...resFilters, search: event.target.value })} placeholder="Nama pasien atau No. RM" value={resFilters.search} />
                                </Field>
                                {access.selectClinic && (
                                    <Field htmlFor="res-filter-poli" label="Poliklinik">
                                        <Select id="res-filter-poli" onChange={(event) => setResFilters({ ...resFilters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={resFilters.poliklinikId}>
                                            <option value="">Semua poliklinik</option>
                                            {clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                        </Select>
                                    </Field>
                                )}
                                <Field htmlFor="res-filter-date" label="Tanggal reservasi">
                                    <DatePicker id="res-filter-date" onChange={(event) => setResFilters({ ...resFilters, tanggal: event.target.value })} value={resFilters.tanggal} />
                                </Field>
                                <FilterSubmit />
                            </form>
                        </FilterPanel>
                        <Card className="overflow-hidden">
                            <CardHeader className="border-b border-neutral-100">
                                <div className="flex items-start gap-3">
                                    <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><CalendarDays className="size-5" /></span>
                                    <div>
                                        <CardTitle>Reservasi 7 hari ke depan</CardTitle>
                                        <CardDescription className="mt-1">Periksa jadwal praktik sebelum mengonfirmasi reservasi kontrol.</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                <TableScroll className="overflow-x-auto">
                                    <Table className="min-w-[720px]">
                                        <TableHeader><TableRow><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik dan dokter</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                                        <TableBody>
                                            {reservations.data.map(visit => <TableRow key={visit.id}>
                                                <TableCell className="whitespace-nowrap">{visit.date}</TableCell>
                                                <TableCell><p className="font-medium">{visit.patient}</p><p className="text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                                <TableCell><p>{visit.clinic}</p><p className="text-xs text-neutral-500">{visit.doctor}</p></TableCell>
                                                <TableCell><StatusBadge status={visit.status} reservation pendingReservation={visit.pendingReservation} /></TableCell>
                                                <TableCell><VisitActions visit={visit} manageVisits={access.manageVisits} /></TableCell>
                                            </TableRow>)}
                                            {!reservations.data.length && <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Tidak ada reservasi yang sesuai dalam tujuh hari ke depan." /></TableCell></TableRow>}
                                        </TableBody>
                                    </Table>
                                </TableScroll>
                                <Pagination pagination={reservations} />
                            </CardContent>
                        </Card>
                    </div>
                )}
                <FilterPanel filterKeys={['poliklinik_id', 'tanggal', 'status', 'search', 'dokter_id']}><form className={access.selectClinic ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-5' : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'} onSubmit={applyFilters}>
                    <Field htmlFor="visits-by-clinic-search" label="Cari pasien / kunjungan">
                        <Input id="visits-by-clinic-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama, No. RM, atau No. kunjungan" value={filters.search} />
                    </Field>
                    {access.selectClinic && <Field htmlFor="visits-by-clinic" label="Poliklinik">
                        <Select id="visits-by-clinic" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                            <option value="">Semua poliklinik</option>
                            {clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                        </Select>
                    </Field>}
                    {doctors.length > 0 && <Field htmlFor="visits-by-clinic-doctor" label="Dokter">
                        <Select id="visits-by-clinic-doctor" onChange={(event) => setFilters({ ...filters, dokterId: event.target.value ? Number(event.target.value) : '' })} value={filters.dokterId}>
                            <option value="">Semua dokter</option>
                            {doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
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
                    <FilterSubmit disabled={clinics.length === 0} />
                </form></FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><CalendarDays className="size-5" /></span>
                            <div><CardTitle>Daftar kunjungan pasien</CardTitle><CardDescription className="mt-1">Seluruh kunjungan sesuai filter poliklinik, dokter, tanggal, dan status.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <TableScroll className="overflow-x-auto">
                            <Table className="min-w-[1120px]">
                                <TableHeader><TableRow><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {visits.data.length > 0 ? visits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-sans tabular-nums text-xs text-neutral-600">{visit.number}</TableCell>
                                            <TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.doctor}</TableCell>
                                            <TableCell><StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} pendingReservation={visit.pendingReservation} status={visit.status} /></TableCell>
                                            <TableCell><VisitActions manageVisits={access.manageVisits} visit={visit} /></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={6}><Empty description={clinics.length ? 'Coba sesuaikan filter tanggal untuk melihat kunjungan.' : 'Aktifkan poliklinik agar daftar kunjungan dapat ditampilkan.'} size="compact" title={clinics.length ? 'Tidak ada kunjungan pada filter ini.' : 'Belum ada poliklinik aktif.'} /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </TableScroll>
                        <Pagination pagination={visits} />
                    </CardContent>
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
        {manageVisits && visit.consentUrl && <ActionButton asChild action="verify" label={`Persetujuan umum ${visit.number}`}><Link href={visit.consentUrl} /></ActionButton>}
        <ActionButton disabledReason={!manageVisits ? 'Akun Anda tidak memiliki akses mengubah kunjungan.' : 'Kunjungan yang sudah dilayani tidak dapat diubah.'} disabled={!(manageVisits && visit.editUrl)} asChild action="edit" label={visit.pendingReservation ? 'Konfirmasi reservasi' : `Perbarui kunjungan ${visit.number}`}><Link href={visit.editUrl ?? ''} /></ActionButton>
        <ActionButton disabledReason={!manageVisits ? 'Akun Anda tidak memiliki akses membatalkan kunjungan.' : 'Kunjungan yang sudah dilayani tidak dapat dibatalkan.'} disabled={!(manageVisits && visit.cancelUrl)} onClick={() => confirmReason(`Batalkan kunjungan ${visit.number} untuk ${visit.patient}?`, (reason) => router.post(visit.cancelUrl as string, { reason }, { preserveScroll: true }), 'Batalkan')} type="button" action="cancel" label={`Batalkan kunjungan ${visit.number}`} />
        <ActionButton disabledReason="Dokumen belum tersedia untuk dicetak." disabled={!visit.ticketUrl} asChild action="print" label={`Cetak antrean ${visit.number}`}><a href={visit.ticketUrl ?? ''} /></ActionButton>
    </div>;
}
