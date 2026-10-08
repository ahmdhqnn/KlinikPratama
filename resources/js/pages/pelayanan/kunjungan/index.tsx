import { Head, Link, router } from '@inertiajs/react';
import { ClipboardList, Plus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { DatePicker } from '@/components/ui/date-picker';

interface Visit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    gender: string | null;
    clinic: string;
    doctor: string | null;
    paymentType: string;
    status: string;
    billId: number | null;
}

interface Option {
    id: number;
    name: string;
}

interface Props {
    visits: PaginationData & { data: Visit[] };
    filters: { search: string; date: string; status: string; clinicId: string };
    clinics: Option[];
}

const statuses = ['menunggu', 'screening', 'pemeriksaan', 'farmasi', 'kasir', 'selesai', 'batal'];

export default function VisitsIndex({ visits, filters: initialFilters, clinics }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/kunjungan', filters, { preserveState: true, preserveScroll: true, replace: true });
    }

    function cancelVisit(id: number) {
        confirmAction('Batalkan kunjungan ini?', () => { router.post(`/pelayanan/kunjungan/${id}/batal`, {}, { preserveScroll: true }); }, 'Batalkan');
    }

    return (
        <>
            <Head title="Daftar Kunjungan" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-neutral-700">Layanan klinik</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Daftar kunjungan</h2><p className="mt-1 text-sm text-neutral-500">Pantau alur layanan pasien berdasarkan tanggal, poli, dan status.</p></div><Button asChild><Link href="/pelayanan/kunjungan/create"><Plus className="size-4" />Buka kunjungan baru</Link></Button></div>
                <Card><CardContent className="p-5 sm:p-6"><form className="grid gap-4 sm:grid-cols-2 sm:items-end xl:grid-cols-[minmax(0,1fr)_12rem_12rem_14rem_auto]" onSubmit={applyFilters}>
                    <Field htmlFor="visit-search" label="Cari pasien"><Input id="visit-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama pasien atau No. RM" value={filters.search} /></Field>
                    <Field htmlFor="visit-date-filter" label="Tanggal"><DatePicker id="visit-date-filter" onChange={(event) => setFilters({ ...filters, date: event.target.value })} value={filters.date} /></Field>
                    <Field htmlFor="visit-status-filter" label="Status"><Select id="visit-status-filter" onChange={(event) => setFilters({ ...filters, status: event.target.value })} value={filters.status}><option value="">Semua status</option>{statuses.map((status) => <option key={status} value={status}>{status[0].toUpperCase() + status.slice(1)}</option>)}</Select></Field>
                    <Field htmlFor="visit-clinic-filter" label="Poliklinik"><Select id="visit-clinic-filter" onChange={(event) => setFilters({ ...filters, clinicId: event.target.value })} value={filters.clinicId}><option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>
                    <Button type="submit"><Search className="size-4" />Filter</Button>
                </form></CardContent></Card>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ClipboardList className="size-5" /></span><div><CardTitle>Jadwal kunjungan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} data ditemukan.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[1050px]"><TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Pembayaran</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>
                        {visits.data.length ? visits.data.map((visit) => <TableRow key={visit.id}><TableCell><Link className="font-mono text-xs font-semibold text-neutral-700 hover:underline" href={`/pelayanan/kunjungan/${visit.id}`}>{visit.number}</Link></TableCell><TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="font-mono text-xs text-neutral-500">{visit.medicalRecordNumber} · {visit.gender ?? '—'}</p></TableCell><TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor ?? 'Belum ditentukan'}</TableCell><TableCell className="uppercase text-neutral-600">{visit.paymentType}</TableCell><TableCell><StatusBadge status={visit.status} /></TableCell><TableCell><div className="flex justify-end gap-2"><Button asChild size="sm" variant="secondary"><Link href={`/pelayanan/kunjungan/${visit.id}`}>Detail</Link></Button>{visit.status === 'menunggu' && <Button asChild size="sm" variant="ghost"><Link href={`/pelayanan/screening/${visit.id}`}>Skrining</Link></Button>}{!['selesai', 'batal'].includes(visit.status) && <Button onClick={() => cancelVisit(visit.id)} size="sm" variant="ghost">Batalkan</Button>}</div></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Tidak ada kunjungan pada filter yang dipilih." /></TableCell></TableRow>}
                    </TableBody></Table></div><Pagination pagination={visits} /></CardContent>
                </Card>
            </div>
        </>
    );
}
