import { Head, router } from '@inertiajs/react';
import { CalendarDays, Pencil, Search, Ticket, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Label } from '@/components/ui/label';
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
    ticketUrl: string | null;
    editUrl: string | null;
    cancelUrl: string | null;
}

interface Filters {
    search: string;
    tanggal: string;
    status: string;
    poliklinikId: number | '';
}

interface Props {
    filters: Filters;
    clinics: Clinic[];
    visits: PaginationData & { data: Visit[] };
}

const statusOptions = [
    ['menunggu', 'Menunggu'], ['screening', 'Skrining'], ['pemeriksaan', 'Pemeriksaan'],
    ['farmasi', 'Farmasi'], ['kasir', 'Kasir'], ['selesai', 'Selesai'], ['batal', 'Dibatalkan'],
];

export default function RegistrationVisitReport({ filters: initialFilters, clinics, visits }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/laporan-kunjungan', {
            search: filters.search,
            tanggal: filters.tanggal,
            status: filters.status,
            poliklinik_id: filters.poliklinikId,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Laporan Kunjungan" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pendaftaran dan pelayanan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laporan kunjungan pasien</h2>
                    <p className="mt-1 text-sm text-neutral-500">Cari kunjungan, cetak nomor antrean, atau kelola kunjungan aktif.</p>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="space-y-4" onSubmit={applyFilters}>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <Label className="space-y-1.5 text-sm font-medium text-neutral-700">
                                    <span>Cari pasien</span>
                                    <Input onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama atau nomor RM" value={filters.search} />
                                </Label>
                                <Label className="space-y-1.5 text-sm font-medium text-neutral-700">
                                    <span>Tanggal</span>
                                    <DatePicker onChange={(event) => setFilters({ ...filters, tanggal: event.target.value })}  value={filters.tanggal} />
                                </Label>
                                <Label className="space-y-1.5 text-sm font-medium text-neutral-700">
                                    <span>Poliklinik</span>
                                    <Select onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                                        <option value="">Semua poliklinik</option>
                                        {clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                    </Select>
                                </Label>
                                <Label className="space-y-1.5 text-sm font-medium text-neutral-700">
                                    <span>Status</span>
                                    <Select onChange={(event) => setFilters({ ...filters, status: event.target.value })} value={filters.status}>
                                        <option value="">Semua status</option>
                                        {statusOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                    </Select>
                                </Label>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button type="submit"><Search className="size-4" />Terapkan filter</Button>
                                <Button asChild variant="secondary"><a href="/pendaftaran/laporan-kunjungan">Reset</a></Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><CalendarDays className="size-5" /></span>
                            <div><CardTitle>Daftar kunjungan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} hasil sesuai filter.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[1100px]">
                                <TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Tanggal</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                                <TableBody>
                                    {visits.data.length > 0 ? visits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-mono text-xs text-neutral-600">{visit.number}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                                            <TableCell className="text-neutral-600">{visit.doctor}</TableCell>
                                            <TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                                            <TableCell><StatusBadge status={visit.status} /></TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-end gap-1.5">
                                                    {visit.ticketUrl && <Button asChild aria-label="Cetak nomor antrean" size="icon" variant="ghost"><a href={visit.ticketUrl} rel="noreferrer" target="_blank"><Ticket className="size-4" /></a></Button>}
                                                    {visit.editUrl && <Button asChild aria-label="Edit kunjungan" size="icon" variant="ghost"><a href={visit.editUrl}><Pencil className="size-4" /></a></Button>}
                                                    {visit.cancelUrl && <Button aria-label="Batalkan kunjungan" onClick={() => {
                                                        confirmAction('Batalkan kunjungan ini?', () => { router.post(visit.cancelUrl as string); }, 'Batalkan');
                                                    }} size="icon" variant="ghost"><X className="size-4 text-red-600" /></Button>}
                                                    {!visit.ticketUrl && !visit.editUrl && !visit.cancelUrl && <span className="pr-2 text-xs text-neutral-400">Tidak ada tindakan</span>}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-neutral-500" colSpan={7}>Tidak ada kunjungan yang sesuai filter.</TableCell></TableRow>}
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
