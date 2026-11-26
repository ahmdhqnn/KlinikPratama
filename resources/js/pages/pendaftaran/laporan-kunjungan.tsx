import { ActionButton } from '@/components/ui/action-button';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, router } from '@inertiajs/react';
import { CalendarDays, CheckCircle2, Clock3, Download } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { StatusBadge, visitStatusFilterLabels } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';
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
    clinic: string;
    doctor: string;
    date: string;
    status: string;
    examinationStarted?: boolean;
    pendingReservation?: boolean;
    isReservation?: boolean;
    ticketUrl: string | null;
    editUrl: string | null;
    cancelUrl: string | null;
}

interface Filters {
    search: string;
    tanggal: string;
    status: string;
    isReservation?: boolean;
    poliklinikId: number | '';
    dokterId?: number | '';
}

interface Props {
    exportUrl: string | null;
    filters: Filters;
    clinics: Clinic[];
    doctors?: Clinic[];
    personal?: boolean;
    visits: PaginationData & { data: Visit[] };
}

const statusOptions = Object.entries(visitStatusFilterLabels);

export default function RegistrationVisitReport({ filters: initialFilters, clinics, doctors = [], visits, exportUrl, personal = false }: Props) {
    const [filters, setFilters] = useState({ ...initialFilters, dokterId: initialFilters.dokterId ?? '' });

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/laporan-kunjungan', {
            search: filters.search,
            tanggal: filters.tanggal,
            status: filters.status,
            poliklinik_id: filters.poliklinikId,
            dokter_id: filters.dokterId,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Laporan kunjungan" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Analisis pelayanan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laporan kunjungan</h2>
                    <p className="mt-1 text-sm text-neutral-500">{personal ? 'Kunjungan yang didaftarkan atau dikonfirmasi melalui akun Anda.' : 'Lihat rincian kunjungan berdasarkan tanggal, poliklinik, dan status pelayanan.'}</p>
                </div>

                <SummaryStatGrid ariaLabel="Ringkasan laporan kunjungan" columns={3}>
                    <StatCard description="Seluruh kunjungan sesuai filter aktif" icon={<CalendarDays className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Total kunjungan" value={visits.total} />
                    <StatCard description="Kunjungan berstatus selesai di halaman ini" icon={<CheckCircle2 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Selesai (halaman ini)" value={visits.data.filter((v) => v.status === 'selesai').length} />
                    <StatCard description="Kunjungan masih aktif/antre di halaman ini" icon={<Clock3 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Dalam pelayanan (halaman ini)" value={visits.data.filter((v) => !['selesai', 'batal'].includes(v.status)).length} />
                </SummaryStatGrid>

                <FilterPanel actions={exportUrl && <Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}><form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-5" onSubmit={applyFilters}>
                                <Field htmlFor="registration-visits-search" label="Cari pasien atau kunjungan">
                                    <Input id="registration-visits-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama, No. RM, atau No. kunjungan" value={filters.search} />
                                </Field>
                                <Field htmlFor="registration-visits-date" label="Tanggal kunjungan">
                                    <DatePicker id="registration-visits-date" onChange={(event) => setFilters({ ...filters, tanggal: event.target.value })} value={filters.tanggal} />
                                </Field>
                                <Field htmlFor="registration-visits-clinic" label="Poliklinik">
                                    <Select id="registration-visits-clinic" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                                        <option value="">Semua poliklinik</option>
                                        {clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                    </Select>
                                </Field>
                                {doctors.length > 0 && (
                                    <Field htmlFor="registration-visits-doctor" label="Dokter">
                                        <Select id="registration-visits-doctor" onChange={(event) => setFilters({ ...filters, dokterId: event.target.value ? Number(event.target.value) : '' })} value={filters.dokterId}>
                                            <option value="">Semua dokter</option>
                                            {doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}
                                        </Select>
                                    </Field>
                                )}
                                <Field htmlFor="registration-visits-status" label="Status pelayanan">
                                    <Select id="registration-visits-status" onChange={(event) => setFilters({ ...filters, status: event.target.value })} value={filters.status}>
                                        <option value="">Semua status</option>
                                        {statusOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                    </Select>
                                </Field>
                                <FilterSubmit />
                        </form></FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><CalendarDays className="size-5" /></span>
                            <div><CardTitle>Daftar kunjungan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} kunjungan sesuai filter.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[1100px]">
                                <TableHeader><TableRow><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {visits.data.length > 0 ? visits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-sans tabular-nums text-xs text-neutral-600">{visit.number}</TableCell>
                                            <TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                                            <TableCell className="text-neutral-600">{visit.doctor}</TableCell>
                                            <TableCell><StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} pendingReservation={visit.pendingReservation} status={visit.status} /></TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-center gap-2">
                                                    <ActionButton disabledReason="Tiket antrean belum tersedia untuk kunjungan ini." disabled={!visit.ticketUrl} asChild action="print" label="Cetak tiket antrean"><a href={visit.ticketUrl ?? ''} /></ActionButton>
                                                    <ActionButton disabledReason="Kunjungan pada tahap ini belum dapat diubah." disabled={!visit.editUrl} asChild action="edit" label={visit.pendingReservation ? 'Konfirmasi reservasi' : 'Ubah kunjungan'}><a href={visit.editUrl ?? ''} /></ActionButton>
                                                    <ActionButton disabledReason="Hanya kunjungan yang belum dilayani yang dapat dibatalkan." disabled={!visit.cancelUrl} onClick={() => {
                                                        confirmAction('Batalkan kunjungan ini?', () => { router.post(visit.cancelUrl as string); }, 'Batalkan');
                                                    }} action="cancel" label="Batalkan kunjungan" />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Tidak ada kunjungan yang sesuai filter." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination pagination={visits} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
