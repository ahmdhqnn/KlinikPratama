import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { ClipboardCheck, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface ScreeningRecord {
    id: number;
    date: string;
    visitNumber: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    staff: string;
    triage: string | null;
    priority: string | null;
    visitUrl: string | null;
    printUrl: string | null;
    isDraft: boolean;
}

interface Props {
    filters: { search: string; tanggal: string; poliklinikId: number | '' };
    canSelectClinic: boolean;
    clinics: { id: number; name: string }[];
    screenings: PaginationData & { data: ScreeningRecord[] };
}

export default function ScreeningHistory({ filters: initialFilters, canSelectClinic, clinics, screenings }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/screening/riwayat', {
            search: filters.search,
            tanggal: filters.tanggal,
            ...(canSelectClinic ? { poliklinik_id: filters.poliklinikId } : {}),
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <>
        <Head title="Riwayat Skrining Saya" />
        <div className="space-y-6">
            <div>
                <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p>
                <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Riwayat skrining saya</h2>
                <p className="mt-1 text-sm text-neutral-500">Daftar skrining yang dicatat oleh akun Anda, tersimpan per halaman.</p>
            </div>
            <FilterPanel><form className={`grid gap-4 sm:items-end ${canSelectClinic ? 'sm:grid-cols-2 xl:grid-cols-2' : 'sm:grid-cols-2'}`} onSubmit={applyFilters}>
                        <Field htmlFor="screening-history-search" label="Cari pasien">
                            <Input id="screening-history-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama atau nomor RM" value={filters.search} />
                        </Field>
                        <Field htmlFor="screening-history-date" label="Tanggal skrining">
                            <Input id="screening-history-date" onChange={(event) => setFilters({ ...filters, tanggal: event.target.value })} type="date" value={filters.tanggal} />
                        </Field>
                        {canSelectClinic && <Field htmlFor="screening-history-clinic" label="Poliklinik">
                            <Select id="screening-history-clinic" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                                <option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                            </Select>
                        </Field>}
                        <Button type="submit"><Search className="size-4" />Terapkan filter</Button>
                    </form></FilterPanel>
            <Card className="overflow-hidden">
                <CardHeader className="border-b border-neutral-100">
                    <div className="flex items-start gap-3">
                        <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ClipboardCheck className="size-5" /></span>
                        <div><CardTitle>Catatan skrining</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(screenings.total)} catatan sesuai cakupan akun dan filter.</CardDescription></div>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
                        <Table className="min-w-[950px]">
                            <TableHeader><tr><TableHead>Tanggal</TableHead><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Petugas</TableHead><TableHead>Triase</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader>
                            <TableBody>
                                {screenings.data.length ? screenings.data.map((record) => <TableRow key={record.id}>
                                    <TableCell>{record.date}</TableCell>
                                    <TableCell className="font-mono text-xs">{record.visitNumber}</TableCell>
                                    <TableCell><p className="font-medium text-neutral-900">{record.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{record.medicalRecordNumber}</p></TableCell>
                                    <TableCell>{record.clinic}</TableCell>
                                    <TableCell>{record.staff}<p className="mt-1 text-xs text-neutral-500">{record.isDraft ? 'Draf asesmen' : 'Asesmen terkirim'}</p></TableCell>
                                    <TableCell><span className="font-medium capitalize">{record.triage ?? 'Belum ditentukan'}</span>{record.priority && <span className="mt-0.5 block text-xs text-neutral-500 capitalize">{record.priority}</span>}</TableCell>
                                    <TableCell className="text-center"><div className="flex flex-wrap justify-center gap-2">{record.printUrl && <Button asChild size="sm" variant="secondary"><a href={record.printUrl} target="_blank" rel="noopener noreferrer">Cetak asesmen</a></Button>}{record.visitUrl && <Button asChild size="sm" variant="secondary"><Link href={record.visitUrl}>Lihat kunjungan</Link></Button>}</div></TableCell>
                                </TableRow>) : <TableRow><TableCell colSpan={7}><Empty size="compact" title="Belum ada catatan skrining pada filter ini." /></TableCell></TableRow>}
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
                <Pagination pagination={screenings} />
            </Card>
        </div>
    </>;
}
