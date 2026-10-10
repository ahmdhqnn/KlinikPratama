import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, Clock3, Eye, Pencil, Plus, Trash2 } from 'lucide-react';
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
    filters: { dokterId: number | ''; poliklinikId: number | ''; hari: string };
    doctors: Option[];
    clinics: Option[];
    days: string[];
    schedules: Schedule[];
    today: string;
}

interface ScheduleForm {
    dokter_id: string;
    poliklinik_id: string;
    hari: string;
    jam_mulai: string;
    jam_selesai: string;
    berlaku_mulai: string;
    berlaku_sampai: string;
}

export default function DoctorSchedule({ canManage, filters: initialFilters, doctors, clinics, days, schedules, today }: Props) {
    const [filters, setFilters] = useState(initialFilters);
    const [editingSchedule, setEditingSchedule] = useState<Schedule | null>(null);
    const [detailSchedule, setDetailSchedule] = useState<Schedule | null>(null);
    const form = useForm<ScheduleForm>({ dokter_id: '', poliklinik_id: '', hari: '', jam_mulai: '', jam_selesai: '', berlaku_mulai: today, berlaku_sampai: '' });

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/jadwal-praktik', {
            dokter_id: filters.dokterId,
            poliklinik_id: filters.poliklinikId,
            hari: filters.hari,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function saveSchedule(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { setEditingSchedule(null); form.reset(); form.setData('berlaku_mulai', today); } };
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
            <Head title="Jadwal Praktik Dokter" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pengaturan layanan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Jadwal praktik dokter</h2>
                    <p className="mt-1 text-sm text-neutral-500">{canManage ? 'Atur jam layanan dokter yang menjadi acuan pendaftaran pasien.' : 'Lihat jam layanan dokter yang menjadi acuan pelayanan pasien.'}</p>
                </div>
                {canManage && <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Plus className="size-5" /></span>
                            <div><CardTitle>Tambah jadwal praktik</CardTitle><CardDescription className="mt-1">Atur masa berlaku jadwal; periode untuk dokter dan poli yang sama tidak boleh bertumpuk.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={saveSchedule}>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_1fr_1fr_2fr]">
                                <Field error={form.errors.dokter_id} htmlFor="schedule-doctor" label="Dokter" required>
                                    <Select id="schedule-doctor" onChange={(event) => form.setData('dokter_id', event.target.value)} required value={form.data.dokter_id}>
                                        <option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
                                    </Select>
                                </Field>
                                <Field error={form.errors.poliklinik_id} htmlFor="schedule-clinic" label="Poliklinik" required>
                                    <Select id="schedule-clinic" onChange={(event) => form.setData('poliklinik_id', event.target.value)} required value={form.data.poliklinik_id}>
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
                                <Field error={form.errors.berlaku_mulai || form.errors.berlaku_sampai} htmlFor="schedule-validity" label="Masa berlaku" required><DateRangePicker id="schedule-validity" from={form.data.berlaku_mulai} to={form.data.berlaku_sampai} min={today} required onChange={(range) => { form.setData('berlaku_mulai', range.from); form.setData('berlaku_sampai', range.to); }} /></Field>
                            </div>
                            <Button disabled={form.processing || doctors.length === 0 || clinics.length === 0} type="submit"><Plus className="size-4" />{form.processing ? 'Menyimpan…' : 'Tambah jadwal'}</Button>
                        </form>
                    </CardContent>
                </Card>}
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 sm:grid-cols-2 sm:items-start xl:grid-cols-[1fr_1fr_1fr_auto]" onSubmit={applyFilters}>
                            <Field htmlFor="schedule-doctor-filter" label="Dokter">
                                <Select id="schedule-doctor-filter" onChange={(event) => setFilters({ ...filters, dokterId: event.target.value ? Number(event.target.value) : '' })} value={filters.dokterId}>
                                    <option value="">Semua dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
                                </Select>
                            </Field>
                            <Field htmlFor="schedule-clinic-filter" label="Poliklinik">
                                <Select id="schedule-clinic-filter" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                                    <option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                </Select>
                            </Field>
                            <Field htmlFor="schedule-day-filter" label="Hari">
                                <Select id="schedule-day-filter" onChange={(event) => setFilters({ ...filters, hari: event.target.value })} value={filters.hari}>
                                    <option value="">Semua hari</option>{days.map((day) => <option key={day} value={day}>{capitalize(day)}</option>)}
                                </Select>
                            </Field>
                            <div className="flex gap-3 sm:mt-7"><Button type="submit"><CalendarDays className="size-4" />Filter jadwal</Button><Button asChild variant="secondary"><Link href="/pendaftaran/jadwal-praktik">Reset</Link></Button></div>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <CardTitle>Jadwal terdaftar</CardTitle><CardDescription>{schedules.length} jadwal sesuai filter.</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[820px]">
                                <TableHeader><tr><TableHead>Dokter</TableHead><TableHead>Poliklinik</TableHead><TableHead>Hari</TableHead><TableHead>Jam praktik</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                                <TableBody>
                                    {schedules.length > 0 ? schedules.map((schedule) => (
                                        <TableRow key={schedule.id}>
                                            <TableCell className="font-medium text-neutral-900">{schedule.doctor}</TableCell>
                                            <TableCell className="text-neutral-600">{schedule.clinic}</TableCell>
                                            <TableCell><span className="rounded-full bg-neutral-50 px-2.5 py-1 text-xs font-semibold capitalize text-neutral-700">{schedule.day}</span></TableCell>
                                            <TableCell><span className="inline-flex items-center gap-2 font-mono text-xs text-neutral-600"><Clock3 className="size-3.5" />{schedule.start} – {schedule.end}</span><span className="mt-1 block text-xs text-neutral-500">{schedule.validFrom ?? '1970-01-01'} s.d. {schedule.validUntil ?? 'seterusnya'}</span></TableCell>
                                            <TableCell><Badge variant={schedule.active ? 'complete' : 'cancelled'}>{schedule.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell>
                                            <TableCell><div className="flex justify-end gap-1"><Button aria-label={`Detail jadwal ${schedule.doctor} ${schedule.day}`} onClick={() => setDetailSchedule(schedule)} size="icon" type="button" variant="ghost"><Eye className="size-4" /></Button>{canManage && <><Button aria-label={`Perbarui jadwal ${schedule.doctor} ${schedule.day}`} onClick={() => editSchedule(schedule)} size="icon" type="button" variant="ghost"><Pencil className="size-4" /></Button><Button aria-label={`Hapus jadwal ${schedule.doctor} ${schedule.day}`} onClick={() => deleteSchedule(schedule)} size="icon" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" /></Button></>}</div></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={6}><Empty size="compact" title="Tidak ada jadwal praktik sesuai filter." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
                <Dialog onOpenChange={(open) => { if (!open) closeScheduleEditor(); }} open={Boolean(editingSchedule)}>
                    <DialogContent className="max-w-3xl">
                        <DialogHeader><DialogTitle>Perbarui jadwal praktik</DialogTitle><DialogDescription>Perubahan periode akan diperiksa agar tidak bertumpuk dengan jadwal dokter yang sama.</DialogDescription></DialogHeader>
                        <form className="space-y-4" onSubmit={saveSchedule}>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field error={form.errors.dokter_id} htmlFor="edit-schedule-doctor" label="Dokter" required><Select id="edit-schedule-doctor" onChange={(event) => form.setData('dokter_id', event.target.value)} required value={form.data.dokter_id}><option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select></Field>
                                <Field error={form.errors.poliklinik_id} htmlFor="edit-schedule-clinic" label="Poliklinik" required><Select id="edit-schedule-clinic" onChange={(event) => form.setData('poliklinik_id', event.target.value)} required value={form.data.poliklinik_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>
                                <Field error={form.errors.hari} htmlFor="edit-schedule-day" label="Hari" required><Select id="edit-schedule-day" onChange={(event) => form.setData('hari', event.target.value)} required value={form.data.hari}><option value="">Pilih hari</option>{days.map((day) => <option key={day} value={day}>{capitalize(day)}</option>)}</Select></Field>
                                <div className="grid grid-cols-2 gap-3"><Field error={form.errors.jam_mulai} htmlFor="edit-schedule-start" label="Jam mulai" required><Input id="edit-schedule-start" onChange={(event) => form.setData('jam_mulai', event.target.value)} required type="time" value={form.data.jam_mulai} /></Field><Field error={form.errors.jam_selesai} htmlFor="edit-schedule-end" label="Jam selesai" required><Input id="edit-schedule-end" min={form.data.jam_mulai} onChange={(event) => form.setData('jam_selesai', event.target.value)} required type="time" value={form.data.jam_selesai} /></Field></div>
                            </div>
                            <Field error={form.errors.berlaku_mulai || form.errors.berlaku_sampai} htmlFor="edit-schedule-validity" label="Masa berlaku" required><DateRangePicker id="edit-schedule-validity" from={form.data.berlaku_mulai} to={form.data.berlaku_sampai} required={false} onChange={(range) => { form.setData('berlaku_mulai', range.from); form.setData('berlaku_sampai', range.to); }} /></Field>
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
