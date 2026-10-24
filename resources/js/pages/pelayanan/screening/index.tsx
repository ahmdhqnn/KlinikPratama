import { DatePicker } from '@/components/ui/date-picker';
import { ActionButton } from '@/components/ui/action-button';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, HeartPulse, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';

interface Clinic {
    id: number;
    name: string;
}

interface Visit {
    id: number;
    number: string;
    date: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    hasScreening: boolean;
    status: string;
    screeningUrl: string;
}

interface Props {
    filters: { search: string; tanggal: string; poliklinikId: number | '' };
    clinics: Clinic[];
    canSelectClinic: boolean;
    visits: PaginationData & { data: Visit[] };
}

export default function ScreeningQueue({ filters: initialFilters, clinics, canSelectClinic, visits }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/screening', {
            search: filters.search,
            tanggal: filters.tanggal,
            ...(canSelectClinic ? { poliklinik_id: filters.poliklinikId } : {}),
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Antrean Pengkajian Awal" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Antrean pengkajian awal perawat</h2>
                    <p className="mt-1 text-sm text-neutral-500">Pasien yang menunggu atau sedang menjalani pengkajian awal di poliklinik.</p>
                </div>
                <FilterPanel actions={<Button asChild size="sm" variant="secondary"><Link href="/pelayanan/screening/riwayat"><ClipboardCheck className="size-4" />Pengkajian saya</Link></Button>}>
                    <form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={applyFilters}>
                        <Field htmlFor="screening-queue-search" label="Cari pasien">
                            <Input id="screening-queue-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama atau nomor RM" value={filters.search} />
                        </Field>
                        <Field htmlFor="screening-queue-date" label="Tanggal kunjungan">
                            <DatePicker id="screening-queue-date" onChange={(event) => setFilters({ ...filters, tanggal: event.target.value })} value={filters.tanggal} />
                        </Field>
                        {canSelectClinic && <Field htmlFor="screening-queue-clinic" label="Poliklinik">
                            <Select id="screening-queue-clinic" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                                <option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                            </Select>
                        </Field>}
                        <Button type="submit"><Search className="size-4" />Terapkan filter</Button>
                    </form>
                </FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><HeartPulse className="size-5" /></span>
                            <div><CardTitle>Daftar pasien</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} pasien menunggu atau belum menyelesaikan pengkajian awal.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[880px]">
                                <TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Status pengkajian</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader>
                                <TableBody>
                                    {visits.data.length > 0 ? visits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-mono text-xs text-neutral-600">{visit.number}</TableCell>
                                            <TableCell className="whitespace-nowrap">{visit.date}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                                            <TableCell><StatusBadge status={visit.status} /></TableCell>
                                            <TableCell className="text-center"><ActionButton asChild action="screening" label={visit.hasScreening ? 'Lanjutkan pengkajian' : 'Mulai pengkajian'}><Link href={visit.screeningUrl} /></ActionButton></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={6}><Empty size="compact" title="Tidak ada pasien yang menunggu atau memiliki pengkajian yang belum selesai." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                    <Pagination pagination={visits} />
                </Card>
            </div>
        </>
    );
}
