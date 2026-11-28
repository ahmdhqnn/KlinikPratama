import { DisclosureCard } from '@/components/ui/disclosure-card';
import { StatCard } from '@/components/dashboard/stat-card';
import { ActionButton } from '@/components/ui/action-button';
import { DatePicker } from '@/components/ui/date-picker';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, Clock3, Pencil, Plus, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableScroll } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';

interface Option {
    id: number;
    name: string;
}

interface Schedule {
    id: number;
    doctorId: number;
    clinicId: number;
    doctor: string;
    clinic: string;
    day: string;
    start: string;
    end: string;
    active: boolean;
    validFrom: string | null;
    validUntil: string | null;
    updateUrl: string;
    deleteUrl: string;
}

interface Props {
    canManage: boolean;
    canManageHolidays: boolean;
    canSelectClinic: boolean;
    filters: { search?: string; dokterId: number | ''; poliklinikId: number | ''; hari: string; status?: string };
    holidayFilters?: { tahun: string; jenis: string; search: string };
    holidayYears?: string[];
    exceptionFilters?: { dokterId: number | ''; poliklinikId: number | ''; tanggal: string; status: string };
    doctors: Option[];
    clinics: Option[];
    days: string[];
    schedules: PaginationData & { data: Schedule[] };
    today: string;
    holidays: PaginationData & { data: { id: number; tanggal: string; nama: string; jenis: string; sumber: string | null }[] };
    matchingDoctors?: number;
    exceptions: (PaginationData & { data: { id: number; dokter_id: number; poliklinik_id: number; tanggal: string; jam_mulai: string | null; jam_selesai: string | null; is_active: boolean; alasan: string }[] }) | null;
}

const holidayTypes: Record<string, string> = {
    nasional: 'Libur nasional',
    cuti_bersama: 'Cuti bersama',
    instansi: 'Libur instansi',
};

const holidayDate = new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeZone: 'UTC' });

interface ScheduleForm {
    dokter_id: string;
    poliklinik_id: string;
    hari: string;
    jam_mulai: string;
    jam_selesai: string;
    berlaku_mulai: string;
    berlaku_sampai: string;
}

export default function DoctorSchedule({
    canManage,
    canManageHolidays = false,
    canSelectClinic,
    filters: initialFilters,
    holidayFilters: initialHolidayFilters = { tahun: '', jenis: '', search: '' },
    holidayYears = [],
    exceptionFilters: initialExceptionFilters = { dokterId: '', poliklinikId: '', tanggal: '', status: '' },
    doctors,
    clinics,
    days,
    schedules,
    today,
    holidays,
    matchingDoctors = 0,
    exceptions: exceptionPagination,
}: Props) {
    const exceptions = exceptionPagination?.data ?? [];
    const [filters, setFilters] = useState(initialFilters);
    const [holidayFilters, setHolidayFilters] = useState(initialHolidayFilters);
    const [exceptionFilters, setExceptionFilters] = useState(initialExceptionFilters);
    const [asnExpanded, setAsnExpanded] = useState(false);
    const [holidayExpanded, setHolidayExpanded] = useState(false);
    const [exceptionExpanded, setExceptionExpanded] = useState(
        Boolean(initialExceptionFilters.dokterId || initialExceptionFilters.poliklinikId || initialExceptionFilters.tanggal || initialExceptionFilters.status),
    );
    const [scheduleDialogOpen, setScheduleDialogOpen] = useState(false);
    const [editingSchedule, setEditingSchedule] = useState<Schedule | null>(null);
    const [detailSchedule, setDetailSchedule] = useState<Schedule | null>(null);
    const defaultClinicId = !canSelectClinic && clinics.length === 1 ? String(clinics[0].id) : '';
    const form = useForm<ScheduleForm>({ dokter_id: '', poliklinik_id: defaultClinicId, hari: '', jam_mulai: '08:00', jam_selesai: '16:00', berlaku_mulai: today, berlaku_sampai: '' });
    const asnForm = useForm({ dokter_id: '', poliklinik_id: defaultClinicId });
    const holidayForm = useForm({ tanggal: '', nama: '', jenis: 'nasional', sumber: '' });
    const exceptionForm = useForm({ dokter_id: '', poliklinik_id: defaultClinicId, tanggal: '', is_active: true, jam_mulai: '08:00', jam_selesai: '16:00', alasan: '' });

    function visitWithAllFilters(overrides: Record<string, string | number> = {}) {
        router.get('/pendaftaran/jadwal-praktik', {
            search: filters.search ?? '',
            dokter_id: filters.dokterId,
            poliklinik_id: filters.poliklinikId,
            hari: filters.hari,
            status: filters.status ?? '',
            libur_tahun: holidayFilters.tahun,
            libur_jenis: holidayFilters.jenis,
            libur_search: holidayFilters.search,
            exc_dokter: exceptionFilters.dokterId,
            exc_poli: exceptionFilters.poliklinikId,
            exc_tanggal: exceptionFilters.tanggal,
            exc_status: exceptionFilters.status,
            ...overrides,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visitWithAllFilters();
    }

    function applyHolidayFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visitWithAllFilters();
    }

    function applyExceptionFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visitWithAllFilters();
    }

    function saveSchedule(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { setEditingSchedule(null); setScheduleDialogOpen(false); form.reset(); form.setData('berlaku_mulai', today); } };
        if (editingSchedule) {
            form.put(editingSchedule.updateUrl, options);
            return;
        }
        form.post('/pendaftaran/jadwal-praktik', options);
    }

    function editSchedule(schedule: Schedule) {
        setEditingSchedule(schedule);
        setScheduleDialogOpen(true);
        form.clearErrors();
        form.setData({
            dokter_id: String(schedule.doctorId),
            poliklinik_id: String(schedule.clinicId),
            hari: schedule.day,
            jam_mulai: schedule.start,
            jam_selesai: schedule.end,
            berlaku_mulai: schedule.validFrom ?? today,
            berlaku_sampai: schedule.validUntil ?? '',
        });
    }

    function closeScheduleEditor(): void {
        setEditingSchedule(null);
        setScheduleDialogOpen(false);
        form.clearErrors();
        form.reset();
        form.setData('berlaku_mulai', today);
    }

    function openScheduleCreator(): void {
        setEditingSchedule(null);
        form.clearErrors();
        form.reset();
        form.setData('berlaku_mulai', today);
        setScheduleDialogOpen(true);
    }

    function deleteSchedule(schedule: Schedule) {
        confirmAction(`Hapus jadwal praktik ${schedule.doctor} di ${schedule.clinic}?`, () => { router.delete(schedule.deleteUrl, { preserveScroll: true }); }, 'Hapus');
    }

    return (
        <>
            <Head title="Jadwal praktik dokter" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-neutral-700">{canManage ? 'Pengaturan layanan' : 'Informasi jadwal layanan'}</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Jadwal praktik dokter</h2>
                        {!canSelectClinic && <p className="mt-1 text-sm text-neutral-500">Jadwal untuk {clinics[0]?.name ?? 'poli penugasan Anda'}.</p>}
                        <p className="mt-1 text-sm text-neutral-500">Jadwal baku ASN Senin–Jumat 08.00–16.00. Sabtu, Minggu, libur nasional, dan cuti bersama tutup kecuali ada pengecualian.</p>
                    </div>
                    {canManage && <Button onClick={openScheduleCreator}><Plus className="size-4" />Tambah jadwal praktik</Button>}
                </div>
                <section aria-label="Ringkasan jadwal" className="balanced-stats">
                    <StatCard label="Jadwal praktik" value={schedules.total} description="Jadwal sesuai filter" icon={<CalendarDays className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Hari libur" value={holidays.total} description="Libur mulai hari ini" icon={<CalendarDays className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Dokter terjadwal" value={matchingDoctors} description="Dokter unik pada hasil filter" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </section>
                {canManage && (
                    <DisclosureCard title="Terapkan jadwal ASN" description="Isi hari kerja yang belum memiliki jadwal untuk dokter dan poli terpilih. Jadwal khusus yang sudah ada tetap berlaku." icon={<CalendarDays className="size-5" />} expanded={asnExpanded} onToggle={() => setAsnExpanded((current) => !current)}>
                        <CardContent className="pt-5 sm:pt-6">
                            <form
                                className="grid gap-x-4 gap-y-2 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] lg:grid-rows-[auto_auto_auto] lg:items-start"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    asnForm.post('/pendaftaran/jadwal-praktik/asn', { preserveScroll: true, onError: () => setAsnExpanded(true) });
                                }}
                            >
                                <Field className="lg:row-span-3 lg:grid-rows-subgrid" label="Dokter" htmlFor="asn-doctor" error={asnForm.errors.dokter_id} required>
                                    <Select id="asn-doctor" value={asnForm.data.dokter_id} onChange={(event) => asnForm.setData('dokter_id', event.target.value)} required>
                                        <option value="">Pilih dokter</option>
                                        {doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
                                    </Select>
                                </Field>
                                <Field className="lg:row-span-3 lg:grid-rows-subgrid" label="Poliklinik" htmlFor="asn-clinic" error={asnForm.errors.poliklinik_id} required>
                                    <Select disabled={!canSelectClinic} id="asn-clinic" value={asnForm.data.poliklinik_id} onChange={(event) => asnForm.setData('poliklinik_id', event.target.value)} required>
                                        <option value="">Pilih poli</option>
                                        {clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                    </Select>
                                </Field>
                                <Button
                                    className="sm:col-span-2 lg:col-span-1 lg:col-start-3 lg:row-start-2"
                                    disabled={asnForm.processing || !asnForm.data.dokter_id || !asnForm.data.poliklinik_id}
                                    type="submit"
                                >
                                    Terapkan Senin–Jumat
                                </Button>
                            </form>
                        </CardContent>
                    </DisclosureCard>
                )}

                <FilterPanel filterKeys={['search', 'dokter_id', 'poliklinik_id', 'hari', 'status']}><form className={canSelectClinic ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-5' : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'} onSubmit={applyFilters}>
                            <Field htmlFor="schedule-search" label="Cari jadwal"><Input id="schedule-search" maxLength={100} value={filters.search ?? ''} onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama dokter atau poliklinik" /></Field>
                            <Field htmlFor="schedule-doctor-filter" label="Dokter">
                                <Select id="schedule-doctor-filter" onChange={(event) => setFilters({ ...filters, dokterId: event.target.value ? Number(event.target.value) : '' })} value={filters.dokterId}>
                                    <option value="">Semua dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
                                </Select>
                            </Field>
                            {canSelectClinic && <Field htmlFor="schedule-clinic-filter" label="Poliklinik">
                                <Select id="schedule-clinic-filter" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                                    <option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                </Select>
                            </Field>}
                            <Field htmlFor="schedule-day-filter" label="Hari">
                                <Select id="schedule-day-filter" onChange={(event) => setFilters({ ...filters, hari: event.target.value })} value={filters.hari}>
                                    <option value="">Semua hari</option>{days.map((day) => <option key={day} value={day}>{capitalize(day)}</option>)}
                                </Select>
                            </Field>
                            <Field htmlFor="schedule-status-filter" label="Status jadwal">
                                <Select id="schedule-status-filter" onChange={(event) => setFilters({ ...filters, status: event.target.value })} value={filters.status ?? ''}>
                                    <option value="">Semua status</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option>
                                </Select>
                            </Field>
                            <FilterSubmit />
                        </form></FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><CalendarDays className="size-5" /></span><div><CardTitle>Jadwal terdaftar</CardTitle><CardDescription className="mt-1">{schedules.total} jadwal praktik.</CardDescription></div></div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <TableScroll className="overflow-x-auto">
                            <Table className="min-w-[720px]">
                                <TableHeader><TableRow><TableHead>Dokter</TableHead><TableHead>Poliklinik</TableHead><TableHead>Hari</TableHead><TableHead>Jam praktik</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {schedules.data.length > 0 ? schedules.data.map((schedule) => (
                                        <TableRow key={schedule.id}>
                                            <TableCell className="font-medium text-neutral-900">{schedule.doctor}</TableCell>
                                            <TableCell className="text-neutral-600">{schedule.clinic}</TableCell>
                                            <TableCell><span className="rounded-full bg-neutral-50 px-2.5 py-1 text-xs font-semibold capitalize text-neutral-700">{schedule.day}</span></TableCell>
                                            <TableCell><span className="inline-flex items-center gap-2 font-sans tabular-nums text-xs text-neutral-600"><Clock3 className="size-3.5" />{schedule.start} – {schedule.end}</span><span className="mt-1 block text-xs text-neutral-500">{schedule.validFrom ?? 'Awal tidak tercatat'} s.d. {schedule.validUntil ?? 'seterusnya'}</span></TableCell>
                                            <TableCell><Badge variant="default">{schedule.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell>
                                            <TableCell><div className="flex justify-center gap-2"><ActionButton onClick={() => setDetailSchedule(schedule)} type="button" action="detail" label={`Detail jadwal ${schedule.doctor} ${schedule.day}`} />{canManage && <><ActionButton onClick={() => editSchedule(schedule)} type="button" action="edit" label={`Perbarui jadwal ${schedule.doctor} ${schedule.day}`} /><ActionButton onClick={() => deleteSchedule(schedule)} type="button" action="delete" label={`Hapus jadwal ${schedule.doctor} ${schedule.day}`} /></>}</div></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={6}><Empty size="compact" title="Tidak ada jadwal praktik sesuai filter." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </TableScroll>
                    </CardContent>
                    <Pagination pagination={schedules} />
                </Card>
                <div className="space-y-4">
                    {canManageHolidays && <DisclosureCard title="Kalender libur praktik" description="Kelola libur nasional, cuti bersama, dan libur instansi." icon={<CalendarDays className="size-5" />} expanded={holidayExpanded} onToggle={() => setHolidayExpanded((current) => !current)}>
                        <CardContent className="pt-5 sm:pt-6">
                            <form className="grid gap-4 sm:grid-cols-2" onSubmit={(event) => {
                                event.preventDefault();
                                holidayForm.post('/pendaftaran/jadwal-praktik/libur', { preserveScroll: true, onSuccess: () => holidayForm.reset() });
                            }}>
                                <Field label="Tanggal" htmlFor="holiday-date" error={holidayForm.errors.tanggal} required>
                                    <DatePicker id="holiday-date" value={holidayForm.data.tanggal} onChange={(event) => holidayForm.setData('tanggal', event.target.value)} required />
                                </Field>
                                <Field label="Jenis libur" htmlFor="holiday-type" error={holidayForm.errors.jenis} required>
                                    <Select id="holiday-type" value={holidayForm.data.jenis} onChange={(event) => holidayForm.setData('jenis', event.target.value)}>
                                        {Object.entries(holidayTypes).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                                    </Select>
                                </Field>
                                <Field label="Nama libur" htmlFor="holiday-name" error={holidayForm.errors.nama} required>
                                    <Input placeholder="Contoh: Cuti bersama" id="holiday-name" value={holidayForm.data.nama} onChange={(event) => holidayForm.setData('nama', event.target.value)} required />
                                </Field>
                                <Field label="Dasar / surat" htmlFor="holiday-source" error={holidayForm.errors.sumber}>
                                    <Input placeholder="Nomor surat atau dasar penetapan" id="holiday-source" value={holidayForm.data.sumber} onChange={(event) => holidayForm.setData('sumber', event.target.value)} />
                                </Field>
                                <Button className="sm:col-span-2 sm:justify-self-end" disabled={holidayForm.processing} type="submit">Simpan hari libur</Button>
                            </form>
                        </CardContent>
                    </DisclosureCard>}
                    <div className="space-y-3">
                        <FilterPanel ariaLabel="Filter hari libur praktik" filterKeys={['libur_search', 'libur_tahun', 'libur_jenis']}>
                            <form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" onSubmit={applyHolidayFilters}>
                                <Field htmlFor="holiday-filter-search" label="Cari nama / dasar libur">
                                    <Input id="holiday-filter-search" onChange={(event) => setHolidayFilters({ ...holidayFilters, search: event.target.value })} placeholder="Nama libur atau nomor surat" value={holidayFilters.search} />
                                </Field>
                                <Field htmlFor="holiday-filter-year" label="Tahun">
                                    <Select id="holiday-filter-year" onChange={(event) => setHolidayFilters({ ...holidayFilters, tahun: event.target.value })} value={holidayFilters.tahun}>
                                        <option value="">Mendatang</option>
                                        {holidayYears.map((year) => <option key={year} value={year}>{year}</option>)}
                                    </Select>
                                </Field>
                                <Field htmlFor="holiday-filter-type" label="Jenis libur">
                                    <Select id="holiday-filter-type" onChange={(event) => setHolidayFilters({ ...holidayFilters, jenis: event.target.value })} value={holidayFilters.jenis}>
                                        <option value="">Semua jenis</option>
                                        {Object.entries(holidayTypes).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                                    </Select>
                                </Field>
                                <FilterSubmit />
                            </form>
                        </FilterPanel>
                        <Card className="min-w-0 overflow-hidden">
                            <CardHeader className="border-b border-neutral-100">
                                <div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><CalendarDays className="size-5" /></span><div className="min-w-0"><CardTitle>{canManageHolidays ? 'Libur praktik terdaftar' : 'Kalender libur praktik'}</CardTitle><CardDescription className="mt-1">{holidays.total} libur nasional, cuti bersama, dan libur instansi sesuai filter.</CardDescription></div></div>
                            </CardHeader>
                            <CardContent className="p-0">
                                <TableScroll className="overflow-x-auto">
                                    <Table className="min-w-[760px]">
                                        <TableHeader><TableRow><TableHead>Tanggal</TableHead><TableHead>Nama libur</TableHead><TableHead>Jenis</TableHead><TableHead>Dasar / surat</TableHead>{canManageHolidays && <TableHead className="text-center">Aksi</TableHead>}</TableRow></TableHeader>
                                        <TableBody>{holidays.data.length ? holidays.data.map((holiday) => <TableRow key={holiday.id}>
                                            <TableCell className="whitespace-nowrap"><time dateTime={holiday.tanggal}>{holidayDate.format(new Date(holiday.tanggal))}</time></TableCell>
                                            <TableCell className="font-medium">{holiday.nama}</TableCell>
                                            <TableCell><Badge>{holidayTypes[holiday.jenis] ?? holiday.jenis}</Badge></TableCell>
                                            <TableCell className="max-w-xs whitespace-normal">{holiday.sumber ?? '—'}</TableCell>
                                            {canManageHolidays && <TableCell><div className="flex justify-center"><ActionButton type="button" onClick={() => confirmAction('Hapus libur '+holiday.tanggal+'?', () => router.delete('/pendaftaran/jadwal-praktik/libur/'+holiday.id, { preserveScroll: true }), 'Hapus')} action="delete" label={'Hapus libur '+holiday.tanggal} /></div></TableCell>}
                                        </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={canManageHolidays ? 5 : 4}><Empty size="compact" title="Tidak ada hari libur sesuai filter." /></TableCell></TableRow>}</TableBody>
                                    </Table>
                                </TableScroll>
                            </CardContent>
                            <Pagination pagination={holidays} />
                        </Card>
                    </div>
                    {canManage && exceptionPagination && <DisclosureCard title="Pengecualian per tanggal" description="Buka layanan pada hari libur atau tutup praktik dokter pada tanggal tertentu." icon={<Clock3 className="size-5" />} expanded={exceptionExpanded} onToggle={() => setExceptionExpanded((current) => !current)}>
                        <CardContent className="space-y-5 pt-5 sm:pt-6">
                            <form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={(event) => {
                                event.preventDefault();
                                exceptionForm.post('/pendaftaran/jadwal-praktik/pengecualian', { preserveScroll: true, onSuccess: () => exceptionForm.reset() });
                            }}>
                                <Field label="Dokter" htmlFor="exception-doctor" error={exceptionForm.errors.dokter_id} required>
                                    <Select id="exception-doctor" value={exceptionForm.data.dokter_id} onChange={(event) => exceptionForm.setData('dokter_id', event.target.value)} required>
                                        <option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
                                    </Select>
                                </Field>
                                <Field label="Poliklinik" htmlFor="exception-clinic" error={exceptionForm.errors.poliklinik_id} required>
                                    <Select disabled={!canSelectClinic} id="exception-clinic" value={exceptionForm.data.poliklinik_id} onChange={(event) => exceptionForm.setData('poliklinik_id', event.target.value)} required>
                                        <option value="">Pilih poli</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                    </Select>
                                </Field>
                                <Field label="Tanggal" htmlFor="exception-date" error={exceptionForm.errors.tanggal} required>
                                    <DatePicker id="exception-date" value={exceptionForm.data.tanggal} onChange={(event) => exceptionForm.setData('tanggal', event.target.value)} required />
                                </Field>
                                <Field label="Keputusan" htmlFor="exception-active" error={exceptionForm.errors.is_active}>
                                    <Select id="exception-active" value={exceptionForm.data.is_active ? '1' : '0'} onChange={(event) => exceptionForm.setData('is_active', event.target.value === '1')}>
                                        <option value="1">Buka praktik</option><option value="0">Tutup praktik</option>
                                    </Select>
                                </Field>
                                {exceptionForm.data.is_active && <>
                                    <Field label="Mulai" htmlFor="exception-start" error={exceptionForm.errors.jam_mulai} required>
                                        <Input id="exception-start" type="time" value={exceptionForm.data.jam_mulai} onChange={(event) => exceptionForm.setData('jam_mulai', event.target.value)} required />
                                    </Field>
                                    <Field label="Selesai" htmlFor="exception-end" error={exceptionForm.errors.jam_selesai} required>
                                        <Input id="exception-end" type="time" value={exceptionForm.data.jam_selesai} onChange={(event) => exceptionForm.setData('jam_selesai', event.target.value)} required />
                                    </Field>
                                </>}
                                <Field label="Alasan" htmlFor="exception-reason" error={exceptionForm.errors.alasan} required>
                                    <Input placeholder="Alasan pembatalan atau perubahan jam praktik" id="exception-reason" value={exceptionForm.data.alasan} onChange={(event) => exceptionForm.setData('alasan', event.target.value)} required />
                                </Field>
                                <Button className="sm:col-span-2 sm:justify-self-end" disabled={exceptionForm.processing} type="submit">Simpan pengecualian</Button>
                            </form>
                            <div className="space-y-3 border-t border-neutral-200 pt-4">
                                <FilterPanel ariaLabel="Filter pengecualian jadwal" filterKeys={['exc_dokter', 'exc_poli', 'exc_tanggal', 'exc_status']}>
                                    <form className={canSelectClinic ? 'grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-4' : 'grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3'} onSubmit={applyExceptionFilters}>
                                        <Field htmlFor="exc-filter-doctor" label="Dokter">
                                            <Select id="exc-filter-doctor" onChange={(event) => setExceptionFilters({ ...exceptionFilters, dokterId: event.target.value ? Number(event.target.value) : '' })} value={exceptionFilters.dokterId}>
                                                <option value="">Semua dokter</option>
                                                {doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
                                            </Select>
                                        </Field>
                                        {canSelectClinic && (
                                            <Field htmlFor="exc-filter-poli" label="Poliklinik">
                                                <Select id="exc-filter-poli" onChange={(event) => setExceptionFilters({ ...exceptionFilters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={exceptionFilters.poliklinikId}>
                                                    <option value="">Semua poliklinik</option>
                                                    {clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                                </Select>
                                            </Field>
                                        )}
                                        <Field htmlFor="exc-filter-date" label="Tanggal">
                                            <DatePicker id="exc-filter-date" onChange={(event) => setExceptionFilters({ ...exceptionFilters, tanggal: event.target.value })} value={exceptionFilters.tanggal} />
                                        </Field>
                                        <Field htmlFor="exc-filter-status" label="Keputusan">
                                            <Select id="exc-filter-status" onChange={(event) => setExceptionFilters({ ...exceptionFilters, status: event.target.value })} value={exceptionFilters.status}>
                                                <option value="">Semua keputusan</option>
                                                <option value="buka">Buka praktik</option>
                                                <option value="tutup">Tutup praktik</option>
                                            </Select>
                                        </Field>
                                        <FilterSubmit />
                                    </form>
                                </FilterPanel>
                                <TableScroll className="overflow-x-auto">
                                    <Table className="min-w-[700px]">
                                        <TableHeader><TableRow><TableHead>Tanggal</TableHead><TableHead>Dokter</TableHead><TableHead>Poliklinik</TableHead><TableHead>Keputusan / alasan</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                                        <TableBody>{exceptions.length ? exceptions.map((exception) => <TableRow key={exception.id}>
                                            <TableCell className="whitespace-nowrap">{exception.tanggal}</TableCell>
                                            <TableCell>{doctors.find((doctor) => doctor.id === exception.dokter_id)?.name ?? 'Dokter'}</TableCell>
                                            <TableCell>{clinics.find((clinic) => clinic.id === exception.poliklinik_id)?.name ?? 'Poli'}</TableCell>
                                            <TableCell>{exception.is_active ? `Buka ${exception.jam_mulai?.slice(0, 5)}–${exception.jam_selesai?.slice(0, 5)}` : 'Tutup'}<p className="text-xs text-neutral-500">{exception.alasan}</p></TableCell>
                                            <TableCell><div className="flex justify-center"><ActionButton type="button" onClick={() => confirmAction(`Hapus pengecualian ${exception.tanggal}?`, () => router.delete(`/pendaftaran/jadwal-praktik/pengecualian/${exception.id}`, { preserveScroll: true }), 'Hapus')} action="delete" label={`Hapus pengecualian ${exception.tanggal}`} /></div></TableCell>
                                        </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Tidak ada pengecualian jadwal sesuai filter." /></TableCell></TableRow>}</TableBody>
                                    </Table>
                                </TableScroll>
                            </div>
                        </CardContent>
                        <Pagination pagination={exceptionPagination} />
                    </DisclosureCard>}
                </div>
                <Dialog onOpenChange={(open) => { if (!open) closeScheduleEditor(); }} open={scheduleDialogOpen}>
                    <DialogContent className="max-w-3xl">
                        <DialogHeader><DialogTitle>{editingSchedule ? 'Perbarui jadwal praktik' : 'Tambah jadwal praktik'}</DialogTitle><DialogDescription>Atur masa berlaku jadwal; periode untuk dokter dan poli yang sama tidak boleh bertumpuk.</DialogDescription></DialogHeader>
                        <form className="space-y-4" onSubmit={saveSchedule}>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field error={form.errors.dokter_id} htmlFor="schedule-doctor" label="Dokter" required><Select id="schedule-doctor" onChange={(event) => form.setData('dokter_id', event.target.value)} required value={form.data.dokter_id}><option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select></Field>
                                <Field error={form.errors.poliklinik_id} htmlFor="schedule-clinic" label="Poliklinik" required><Select disabled={!canSelectClinic} id="schedule-clinic" onChange={(event) => form.setData('poliklinik_id', event.target.value)} required value={form.data.poliklinik_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>
                                <Field error={form.errors.hari} htmlFor="schedule-day" label="Hari" required><Select id="schedule-day" onChange={(event) => form.setData('hari', event.target.value)} required value={form.data.hari}><option value="">Pilih hari</option>{days.map((day) => <option key={day} value={day}>{capitalize(day)}</option>)}</Select></Field>
                                <div className="grid grid-cols-2 gap-3"><Field error={form.errors.jam_mulai} htmlFor="schedule-start" label="Jam mulai" required><Input id="schedule-start" onChange={(event) => form.setData('jam_mulai', event.target.value)} required type="time" value={form.data.jam_mulai} /></Field><Field error={form.errors.jam_selesai} htmlFor="schedule-end" label="Jam selesai" required><Input id="schedule-end" min={form.data.jam_mulai} onChange={(event) => form.setData('jam_selesai', event.target.value)} required type="time" value={form.data.jam_selesai} /></Field></div>
                            </div>
                            <Field error={form.errors.berlaku_mulai || form.errors.berlaku_sampai} htmlFor="schedule-validity" label="Masa berlaku">
                                <DateRangePicker id="schedule-validity" from={form.data.berlaku_mulai} to={form.data.berlaku_sampai} min={today} onChange={(range) => form.setData((current) => ({ ...current, berlaku_mulai: range.from, berlaku_sampai: range.to }))} />
                                <p className="text-xs leading-5 text-neutral-500">Tanggal akhir boleh dikosongkan untuk jadwal tanpa batas akhir.</p>
                            </Field>
                            <div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={closeScheduleEditor} type="button" variant="secondary">Batal</Button><Button disabled={form.processing || doctors.length === 0 || clinics.length === 0} type="submit">{form.processing ? 'Menyimpan…' : editingSchedule ? 'Simpan perubahan' : 'Tambah jadwal'}</Button></div>
                        </form>
                    </DialogContent>
                </Dialog>
                <Dialog onOpenChange={(open) => { if (!open) setDetailSchedule(null); }} open={Boolean(detailSchedule)}>
                    <DialogContent>
                        <DialogHeader><DialogTitle>Detail jadwal praktik</DialogTitle><DialogDescription>Informasi jadwal dan periode berlakunya.</DialogDescription></DialogHeader>
                        {detailSchedule && <div className="grid gap-3 sm:grid-cols-2"><ScheduleDetail label="Dokter" value={detailSchedule.doctor} /><ScheduleDetail label="Poliklinik" value={detailSchedule.clinic} /><ScheduleDetail label="Hari" value={capitalize(detailSchedule.day)} /><ScheduleDetail label="Jam praktik" value={`${detailSchedule.start} – ${detailSchedule.end}`} /><ScheduleDetail label="Berlaku mulai" value={detailSchedule.validFrom ?? 'Tidak ditentukan'} /><ScheduleDetail label="Berlaku sampai" value={detailSchedule.validUntil ?? 'Tanpa batas akhir'} /><ScheduleDetail label="Status" value={detailSchedule.active ? 'Aktif' : 'Nonaktif'} />{canManage && <div className="flex items-end justify-end"><Button onClick={() => { setDetailSchedule(null); editSchedule(detailSchedule); }} type="button"><Pencil className="size-4" />Perbarui jadwal</Button></div>}</div>}
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

function capitalize(value: string): string {
    return `${value.charAt(0).toLocaleUpperCase('id-ID')}${value.slice(1)}`;
}

function ScheduleDetail({ label, value }: { label: string; value: string }) {
    return <div className="rounded-lg bg-neutral-50 px-3 py-2.5"><p className="text-xs font-medium text-neutral-500">{label}</p><p className="mt-1 text-sm font-semibold text-neutral-900">{value}</p></div>;
}
