import { Head, router } from '@inertiajs/react';
import { Activity, CalendarDays, Clock3, ClipboardCheck, Printer, Search, Stethoscope } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DatePicker } from '@/components/ui/date-picker';

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
    status: string;
    ticketUrl: string | null;
}

interface Props {
    filters: { poliklinikId: number | ''; tanggal: string; status: string };
    clinics: Clinic[];
    stats: { total: number; waiting: number; screening: number; examination: number };
    visits: Visit[];
}

const statusOptions = [
    ['menunggu', 'Menunggu'], ['screening', 'Skrining'], ['pemeriksaan', 'Pemeriksaan'],
    ['farmasi', 'Farmasi'], ['kasir', 'Kasir'], ['selesai', 'Selesai'], ['batal', 'Dibatalkan'],
];

export default function ClinicVisits({ filters: initialFilters, clinics, stats, visits }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/kunjungan-per-poli', filters, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Kunjungan Per Poliklinik" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pemantauan antrean klinik</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Kunjungan per poliklinik</h2>
                    <p className="mt-1 text-sm text-neutral-500">Pantau alur kunjungan pada poliklinik dan tanggal tertentu.</p>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 sm:grid-cols-2 sm:items-end xl:grid-cols-[1fr_1fr_1fr_auto]" onSubmit={applyFilters}>
                            <Field htmlFor="visits-by-clinic" label="Poliklinik" required>
                                <Select id="visits-by-clinic" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} required value={filters.poliklinikId}>
                                    <option value="">Pilih poliklinik</option>
                                    {clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                </Select>
                            </Field>
                            <Field htmlFor="visits-by-clinic-date" label="Tanggal kunjungan" required>
                                <DatePicker id="visits-by-clinic-date" onChange={(event) => setFilters({ ...filters, tanggal: event.target.value })} required value={filters.tanggal} />
                            </Field>
                            <Field htmlFor="visits-by-clinic-status" label="Status">
                                <Select id="visits-by-clinic-status" onChange={(event) => setFilters({ ...filters, status: event.target.value })} value={filters.status}>
                                    <option value="">Semua status</option>
                                    {statusOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </Select>
                            </Field>
                            <Button disabled={clinics.length === 0} type="submit"><Search className="size-4" />Terapkan filter</Button>
                        </form>
                    </CardContent>
                </Card>
                <section aria-label="Ringkasan antrean poliklinik" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard description="Kunjungan sesuai filter saat ini" icon={<Activity className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Total kunjungan" value={stats.total} />
                    <StatCard description="Belum memulai skrining" icon={<Clock3 className="size-5" />} iconClassName="bg-amber-50 text-amber-700" label="Menunggu" value={stats.waiting} />
                    <StatCard description="Sedang dalam skrining perawat" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Skrining" value={stats.screening} />
                    <StatCard description="Menunggu atau sedang diperiksa dokter" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Pemeriksaan" value={stats.examination} />
                </section>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><CalendarDays className="size-5" /></span>
                            <div><CardTitle>Daftar pasien</CardTitle><CardDescription className="mt-1">Urutan antrean mengikuti waktu pendaftaran.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[760px]">
                                <TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                                <TableBody>
                                    {visits.length > 0 ? visits.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-mono text-xs text-neutral-600">{visit.number}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.doctor}</TableCell>
                                            <TableCell><StatusBadge status={visit.status} /></TableCell>
                                            <TableCell className="text-right">{visit.ticketUrl ? <Button asChild size="sm" variant="secondary"><a href={visit.ticketUrl} rel="noreferrer" target="_blank"><Printer className="size-4" />Cetak antrean</a></Button> : <span className="text-xs text-neutral-400">—</span>}</TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty description={clinics.length ? 'Coba sesuaikan filter tanggal untuk melihat kunjungan.' : 'Aktifkan poliklinik agar daftar kunjungan dapat ditampilkan.'} size="compact" title={clinics.length ? 'Tidak ada kunjungan pada filter ini.' : 'Belum ada poliklinik aktif.'} /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
