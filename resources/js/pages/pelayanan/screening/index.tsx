import { Head, router } from '@inertiajs/react';
import { ClipboardCheck, HeartPulse, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
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
    clinic: string;
    hasScreening: boolean;
    screeningUrl: string;
}

interface Props {
    filters: { search: string; tanggal: string; poliklinikId: number | '' };
    clinics: Clinic[];
    visits: Visit[];
}

export default function ScreeningQueue({ filters: initialFilters, clinics, visits }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/screening', {
            search: filters.search,
            tanggal: filters.tanggal,
            poliklinik_id: filters.poliklinikId,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Antrean Skrining" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Antrean skrining dan tanda vital</h2>
                    <p className="mt-1 text-sm text-neutral-500">Pasien yang menunggu atau sedang menjalani pemeriksaan awal.</p>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 sm:grid-cols-2 sm:items-end xl:grid-cols-[minmax(0,1fr)_1fr_1fr_auto]" onSubmit={applyFilters}>
                            <Field htmlFor="screening-search" label="Cari pasien">
                                <Input id="screening-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama atau nomor RM" value={filters.search} />
                            </Field>
                            <Field htmlFor="screening-date" label="Tanggal" required>
                                <DatePicker id="screening-date" onChange={(event) => setFilters({ ...filters, tanggal: event.target.value })} required value={filters.tanggal} />
                            </Field>
                            <Field htmlFor="screening-clinic" label="Poliklinik">
                                <Select id="screening-clinic" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                                    <option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                                </Select>
                            </Field>
                            <Button type="submit"><Search className="size-4" />Cari antrean</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><HeartPulse className="size-5" /></span>
                            <div><CardTitle>Daftar pasien</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.length)} pasien pada antrean ini.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[780px]">
                                <TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Status skrining</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                                <TableBody>
                                    {visits.length > 0 ? visits.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-mono text-xs text-neutral-600">{visit.number}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                                            <TableCell><Badge variant={visit.hasScreening ? 'complete' : 'waiting'}>{visit.hasScreening ? 'Sudah skrining' : 'Belum skrining'}</Badge></TableCell>
                                            <TableCell className="text-right"><Button asChild size="sm"><a href={visit.screeningUrl}><ClipboardCheck className="size-4" />{visit.hasScreening ? 'Lihat / ubah' : 'Mulai skrining'}</a></Button></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Tidak ada pasien menunggu skrining pada filter ini." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
