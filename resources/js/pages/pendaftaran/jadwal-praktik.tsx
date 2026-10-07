import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, Clock3, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface Option {
    id: number;
    name: string;
}

interface Schedule {
    id: number;
    doctor: string;
    clinic: string;
    day: string;
    start: string;
    end: string;
    active: boolean;
    deleteUrl: string;
}

interface Props {
    filters: { dokterId: number | ''; poliklinikId: number | ''; hari: string };
    doctors: Option[];
    clinics: Option[];
    days: string[];
    schedules: Schedule[];
}

interface ScheduleForm {
    dokter_id: string;
    poliklinik_id: string;
    hari: string;
    jam_mulai: string;
    jam_selesai: string;
}

export default function DoctorSchedule({ filters: initialFilters, doctors, clinics, days, schedules }: Props) {
    const [filters, setFilters] = useState(initialFilters);
    const form = useForm<ScheduleForm>({ dokter_id: '', poliklinik_id: '', hari: '', jam_mulai: '', jam_selesai: '' });

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/jadwal-praktik', {
            dokter_id: filters.dokterId,
            poliklinik_id: filters.poliklinikId,
            hari: filters.hari,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function createSchedule(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/pendaftaran/jadwal-praktik', { preserveScroll: true, onSuccess: () => form.reset() });
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
                    <p className="mt-1 text-sm text-neutral-500">Atur jam layanan dokter yang menjadi acuan pendaftaran pasien.</p>
                </div>
                <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Plus className="size-5" /></span>
                            <div><CardTitle>Tambah jadwal praktik</CardTitle><CardDescription className="mt-1">Kombinasi dokter, poliklinik, dan hari hanya dapat dibuat satu kali.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={createSchedule}>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
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
                            </div>
                            <Button disabled={form.processing || doctors.length === 0 || clinics.length === 0} type="submit"><Plus className="size-4" />{form.processing ? 'Menyimpan…' : 'Tambah jadwal'}</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_auto_auto] xl:items-end" onSubmit={applyFilters}>
                            <Label className="space-y-1.5 text-sm font-medium text-neutral-700"><span>Dokter</span>
                                <Select onChange={(event) => setFilters({ ...filters, dokterId: event.target.value ? Number(event.target.value) : '' })} value={filters.dokterId}>
                                    <option value="">Semua dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
                                </Select>
                            </Label>
                            <Label className="space-y-1.5 text-sm font-medium text-neutral-700"><span>Poliklinik</span>
                                <Select onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                                    <option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                </Select>
                            </Label>
                            <Label className="space-y-1.5 text-sm font-medium text-neutral-700"><span>Hari</span>
                                <Select onChange={(event) => setFilters({ ...filters, hari: event.target.value })} value={filters.hari}>
                                    <option value="">Semua hari</option>{days.map((day) => <option key={day} value={day}>{capitalize(day)}</option>)}
                                </Select>
                            </Label>
                            <Button type="submit"><CalendarDays className="size-4" />Filter jadwal</Button>
                            <Button asChild variant="secondary"><Link href="/pendaftaran/jadwal-praktik">Reset</Link></Button>
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
                                            <TableCell><span className="inline-flex items-center gap-2 font-mono text-xs text-neutral-600"><Clock3 className="size-3.5" />{schedule.start} – {schedule.end}</span></TableCell>
                                            <TableCell><Badge variant={schedule.active ? 'complete' : 'cancelled'}>{schedule.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell>
                                            <TableCell className="text-right"><Button aria-label={`Hapus jadwal ${schedule.doctor} ${schedule.day}`} onClick={() => deleteSchedule(schedule)} size="icon" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" /></Button></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-neutral-500" colSpan={6}>Tidak ada jadwal praktik sesuai filter.</TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function capitalize(value: string): string {
    return `${value.charAt(0).toLocaleUpperCase('id-ID')}${value.slice(1)}`;
}
