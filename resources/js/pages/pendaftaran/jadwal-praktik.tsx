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
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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

export default function DoctorSchedule({ canManage, canManageHolidays = false, canSelectClinic, filters: initialFilters, doctors, clinics, days, schedules, today, holidays, matchingDoctors = 0, exceptions: exceptionPagination }: Props) {
    const exceptions = exceptionPagination?.data ?? [];
    const [filters, setFilters] = useState(initialFilters);
    const [asnExpanded, setAsnExpanded] = useState(false);
    const [addScheduleExpanded, setAddScheduleExpanded] = useState(true);
    const [editingSchedule, setEditingSchedule] = useState<Schedule | null>(null);
    const [detailSchedule, setDetailSchedule] = useState<Schedule | null>(null);
    const defaultClinicId = !canSelectClinic && clinics.length === 1 ? String(clinics[0].id) : '';
    const form = useForm<ScheduleForm>({ dokter_id: '', poliklinik_id: defaultClinicId, hari: '', jam_mulai: '08:00', jam_selesai: '16:00', berlaku_mulai: today, berlaku_sampai: '' });
    const asnForm = useForm({ dokter_id: '', poliklinik_id: defaultClinicId });
    const holidayForm = useForm({ tanggal: '', nama: '', jenis: 'nasional', sumber: '' });
    const exceptionForm = useForm({ dokter_id: '', poliklinik_id: defaultClinicId, tanggal: '', is_active: true, jam_mulai: '08:00', jam_selesai: '16:00', alasan: '' });

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/jadwal-praktik', {
            search: filters.search ?? '',
            dokter_id: filters.dokterId,
            poliklinik_id: filters.poliklinikId,
            hari: filters.hari,
            status: filters.status,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function saveSchedule(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { setEditingSchedule(null); form.reset(); form.setData('berlaku_mulai', today); }, onError: () => { if (!editingSchedule) setAddScheduleExpanded(true); } };
        if (editingSchedule) {
            form.put(editingSchedule.updateUrl, options);
            return;
        }
        form.post('/pendaftaran/jadwal-praktik', options);
    }

    function editSchedule(schedule: Schedule) {
        setEditingSchedule(schedule);
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
        form.clearErrors();
        form.reset();
        form.setData('berlaku_mulai', today);
    }

    function deleteSchedule(schedule: Schedule) {
        confirmAction(`Hapus jadwal praktik ${schedule.doctor} di ${schedule.clinic}?`, () => { router.delete(schedule.deleteUrl, { preserveScroll: true }); }, 'Hapus');
    }

    return (
        <>
            <Head title="Jadwal praktik dokter" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">{canManage ? 'Pengaturan layanan' : 'Informasi jadwal layanan'}</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Jadwal praktik dokter</h2>
                    {!canSelectClinic && <p className="mt-1 text-sm text-neutral-500">Jadwal untuk {clinics[0]?.name ?? 'poli penugasan Anda'}.</p>}
                    <p className="mt-1 text-sm text-neutral-500">Jadwal baku ASN Senin–Jumat 08.00–16.00. Sabtu, Minggu, libur nasional, dan cuti bersama tutup kecuali ada pengecualian.</p>
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

                {canManage && <DisclosureCard title="Tambah jadwal praktik" description="Atur masa berlaku jadwal; periode untuk dokter dan poli yang sama tidak boleh bertumpuk." icon={<Plus className="size-5" />} expanded={addScheduleExpanded} onToggle={() => setAddScheduleExpanded((current) => !current)}>
                    <CardContent className="pt-5 sm:pt-6">
                        <form className="space-y-4" onSubmit={saveSchedule}>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                <Field error={form.errors.dokter_id} htmlFor="schedule-doctor" label="Dokter" required>
                                    <Select id="schedule-doctor" onChange={(event) => form.setData('dokter_id', event.target.value)} required value={form.data.dokter_id}>
                                        <option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
                                    </Select>
                                </Field>
                                <Field error={form.errors.poliklinik_id} htmlFor="schedule-clinic" label="Poliklinik" required>
                                    <Select disabled={!canSelectClinic} id="schedule-clinic" onChange={(event) => form.setData('poliklinik_id', event.target.value)} required value={form.data.poliklinik_id}>
                                        <option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                    </Select>
                                </Field>
                                <Field error={form.errors.hari} htmlFor="schedule-day" label="Hari" required>
                                    <Select id="schedule-day" onChange={(event) => form.setData('hari', event.target.value)} required value={form.data.hari}>
                                        <option value="">Pilih hari</option>{days.map((day) => <option key={day} value={day}>{capitalize(day)}</option>)}
                                    </Select>
                                </Field>
                                <Field error={form.errors.jam_mulai} htmlFor="schedule-start" label="Jam mulai" required>
                                    <Input id="schedule-start" onChange={(event) => form.setData('jam_mulai', event.target.value)} required type="time" value={form.data.jam_mulai} />
                                </Field>
                                <Field error={form.errors.jam_selesai} htmlFor="schedule-end" label="Jam selesai" required>
                                    <Input id="schedule-end" min={form.data.jam_mulai} onChange={(event) => form.setData('jam_selesai', event.target.value)} required type="time" value={form.data.jam_selesai} />
                                </Field>
                                <Field error={form.errors.berlaku_mulai || form.errors.berlaku_sampai} htmlFor="schedule-validity" label="Masa berlaku"><DateRangePicker id="schedule-validity" from={form.data.berlaku_mulai} to={form.data.berlaku_sampai} min={today} onChange={(range) => form.setData((current) => ({ ...current, berlaku_mulai: range.from, berlaku_sampai: range.to }))} /></Field>
                            </div>
                            <Button disabled={form.processing || doctors.length === 0 || clinics.length === 0} type="submit"><Plus className="size-4" />{form.processing ? 'Menyimpan…' : 'Tambah jadwal'}</Button>
                        </form>
                    </CardContent>
                </DisclosureCard>}
                <FilterPanel><form className={canSelectClinic ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-5' : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'} onSubmit={applyFilters}>
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
                        <div className="overflow-x-auto">
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
                        </div>
                    </CardContent>
                    <Pagination pagination={schedules} />
                </Card>
                <div className={canManage ? "grid gap-4 xl:grid-cols-2" : undefined}>
                    <Card className="min-w-0 overflow-hidden">
                        <CardHeader className="border-b border-neutral-100">
                            <div className="flex items-start gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><CalendarDays className="size-5" /></span>
                                <div className="min-w-0">
                                    <CardTitle>Kalender libur praktik</CardTitle>
                                    <CardDescription className="mt-1">Libur nasional, cuti bersama, dan libur instansi mulai hari ini.</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-5 sm:pt-6">
                            {canManageHolidays && <form className="grid gap-4 sm:grid-cols-2" onSubmit={(event) => {
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
                                <Button className="w-full sm:col-span-full sm:w-auto sm:justify-self-end" disabled={holidayForm.processing} type="submit">Simpan hari libur</Button>
                            </form>}
                            {holidays.data.length > 0 ? <ul className="divide-y divide-neutral-100">
                                {holidays.data.map((holiday) => <li className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0" key={holiday.id}>
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold">{holiday.nama}</p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            <time dateTime={holiday.tanggal}>{holidayDate.format(new Date(holiday.tanggal))}</time>
                                            {holiday.sumber && <span className="wrap-anywhere"> · {holiday.sumber}</span>}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge>{holidayTypes[holiday.jenis] ?? holiday.jenis}</Badge>
                                        {canManageHolidays && <ActionButton type="button" onClick={() => confirmAction('Hapus libur '+holiday.tanggal+'?', () => router.delete('/pendaftaran/jadwal-praktik/libur/'+holiday.id, { preserveScroll: true }), 'Hapus')} action="delete" label={'Hapus libur '+holiday.tanggal} />}
                                    </div>
                                </li>)}
                            </ul> : <Empty size="compact" title="Belum ada hari libur mendatang." />}
                        </CardContent>
                        <Pagination pagination={holidays} />
                    </Card>
                    {canManage && exceptionPagination && <Card className="min-w-0 overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Clock3 className="size-5" /></span><div className="min-w-0"><CardTitle>Pengecualian per tanggal</CardTitle><CardDescription className="mt-1">Buka layanan pada hari libur atau tutup praktik dokter pada tanggal tertentu.</CardDescription></div></div></CardHeader><CardContent className="space-y-3 pt-5 sm:pt-6">{canManage && <form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={(event) => { event.preventDefault(); exceptionForm.post('/pendaftaran/jadwal-praktik/pengecualian', { preserveScroll: true, onSuccess: () => exceptionForm.reset() }); }}><Field label="Dokter" htmlFor="exception-doctor" error={exceptionForm.errors.dokter_id} required><Select id="exception-doctor" value={exceptionForm.data.dokter_id} onChange={(event) => exceptionForm.setData('dokter_id', event.target.value)} required><option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select></Field><Field label="Poliklinik" htmlFor="exception-clinic" error={exceptionForm.errors.poliklinik_id} required><Select disabled={!canSelectClinic} id="exception-clinic" value={exceptionForm.data.poliklinik_id} onChange={(event) => exceptionForm.setData('poliklinik_id', event.target.value)} required><option value="">Pilih poli</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field><Field label="Tanggal" htmlFor="exception-date" error={exceptionForm.errors.tanggal} required><DatePicker id="exception-date" value={exceptionForm.data.tanggal} onChange={(event) => exceptionForm.setData('tanggal', event.target.value)} required /></Field><Field label="Keputusan" htmlFor="exception-active" error={exceptionForm.errors.is_active}><Select id="exception-active" value={exceptionForm.data.is_active ? '1' : '0'} onChange={(event) => exceptionForm.setData('is_active', event.target.value === '1')}><option value="1">Buka praktik</option><option value="0">Tutup praktik</option></Select></Field>{exceptionForm.data.is_active && <><Field label="Mulai" htmlFor="exception-start" error={exceptionForm.errors.jam_mulai} required><Input id="exception-start" type="time" value={exceptionForm.data.jam_mulai} onChange={(event) => exceptionForm.setData('jam_mulai', event.target.value)} required /></Field><Field label="Selesai" htmlFor="exception-end" error={exceptionForm.errors.jam_selesai} required><Input id="exception-end" type="time" value={exceptionForm.data.jam_selesai} onChange={(event) => exceptionForm.setData('jam_selesai', event.target.value)} required /></Field></>}<Field label="Alasan" htmlFor="exception-reason" error={exceptionForm.errors.alasan} required><Input placeholder="Alasan pembatalan atau perubahan jam praktik" id="exception-reason" value={exceptionForm.data.alasan} onChange={(event) => exceptionForm.setData('alasan', event.target.value)} required /></Field><Button disabled={exceptionForm.processing} type="submit">Simpan pengecualian</Button></form>}<div className="max-h-64 space-y-2 overflow-y-auto">{exceptions.map((exception) => <div className="flex items-center justify-between gap-3 rounded-lg border border-neutral-100 bg-neutral-50/80 px-3 py-2 text-sm" key={exception.id}><span><strong>{exception.tanggal}</strong> · {doctors.find((doctor) => doctor.id === exception.dokter_id)?.name ?? 'Dokter'} · {clinics.find((clinic) => clinic.id === exception.poliklinik_id)?.name ?? 'Poli'}<span className="block text-xs text-neutral-500">{exception.is_active ? `Buka ${exception.jam_mulai?.slice(0, 5)}–${exception.jam_selesai?.slice(0, 5)}` : 'Tutup'} · {exception.alasan}</span></span>{canManage && <ActionButton type="button" onClick={() => confirmAction(`Hapus pengecualian ${exception.tanggal}?`, () => router.delete(`/pendaftaran/jadwal-praktik/pengecualian/${exception.id}`, { preserveScroll: true }), 'Hapus')} action="delete" label={`Hapus pengecualian ${exception.tanggal}`} />}</div>)}</div></CardContent><Pagination pagination={exceptionPagination} /></Card>}
                </div>
                <Dialog onOpenChange={(open) => { if (!open) closeScheduleEditor(); }} open={Boolean(editingSchedule)}>
                    <DialogContent className="max-w-3xl">
                        <DialogHeader><DialogTitle>Perbarui jadwal praktik</DialogTitle><DialogDescription>Perubahan periode akan diperiksa agar tidak bertumpuk dengan jadwal dokter yang sama.</DialogDescription></DialogHeader>
                        <form className="space-y-4" onSubmit={saveSchedule}>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field error={form.errors.dokter_id} htmlFor="edit-schedule-doctor" label="Dokter" required><Select id="edit-schedule-doctor" onChange={(event) => form.setData('dokter_id', event.target.value)} required value={form.data.dokter_id}><option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select></Field>
                                <Field error={form.errors.poliklinik_id} htmlFor="edit-schedule-clinic" label="Poliklinik" required><Select disabled={!canSelectClinic} id="edit-schedule-clinic" onChange={(event) => form.setData('poliklinik_id', event.target.value)} required value={form.data.poliklinik_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>
                                <Field error={form.errors.hari} htmlFor="edit-schedule-day" label="Hari" required><Select id="edit-schedule-day" onChange={(event) => form.setData('hari', event.target.value)} required value={form.data.hari}><option value="">Pilih hari</option>{days.map((day) => <option key={day} value={day}>{capitalize(day)}</option>)}</Select></Field>
                                <div className="grid grid-cols-2 gap-3"><Field error={form.errors.jam_mulai} htmlFor="edit-schedule-start" label="Jam mulai" required><Input id="edit-schedule-start" onChange={(event) => form.setData('jam_mulai', event.target.value)} required type="time" value={form.data.jam_mulai} /></Field><Field error={form.errors.jam_selesai} htmlFor="edit-schedule-end" label="Jam selesai" required><Input id="edit-schedule-end" min={form.data.jam_mulai} onChange={(event) => form.setData('jam_selesai', event.target.value)} required type="time" value={form.data.jam_selesai} /></Field></div>
                            </div>
                            <Field error={form.errors.berlaku_mulai || form.errors.berlaku_sampai} htmlFor="edit-schedule-validity" label="Masa berlaku" required><DateRangePicker id="edit-schedule-validity" from={form.data.berlaku_mulai} to={form.data.berlaku_sampai} required={false} onChange={(range) => form.setData((current) => ({ ...current, berlaku_mulai: range.from, berlaku_sampai: range.to }))} /></Field>
                            <p className="text-xs text-neutral-500">Tanggal akhir boleh dikosongkan untuk jadwal tanpa batas akhir.</p>
                            <div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={closeScheduleEditor} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan perubahan'}</Button></div>
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
