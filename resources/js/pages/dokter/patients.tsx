import { Head, router } from '@inertiajs/react';
import { Search, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Label } from '@/components/ui/label';

interface Patient {
    id: number;
    medicalRecordNumber: string;
    name: string;
    gender: string | null;
    visitCount: number;
    recordUrl: string;
}

interface Props {
    search: string;
    patients: PaginationData & { data: Patient[] };
}

export default function DoctorPatients({ search, patients }: Props) {
    const [query, setQuery] = useState(search);

    function applySearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/dokter/pasien', { search: query }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Pasien Dokter" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Data pasien</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pasien yang pernah ditangani</h2>
                    <p className="mt-1 text-sm text-neutral-500">Daftar ini hanya mencakup pasien yang memiliki kunjungan dengan Anda.</p>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="flex flex-col gap-3 sm:flex-row" onSubmit={applySearch}>
                            <Label className="sr-only" htmlFor="patient-search">Cari pasien</Label>
                            <Input id="patient-search" onChange={(event) => setQuery(event.target.value)} placeholder="Cari nama, nomor rekam medis, atau NIK" value={query} />
                            <Button className="sm:min-w-32" type="submit"><Search className="size-4" />Cari pasien</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UsersRound className="size-5" /></span>
                            <div><CardTitle>Database pasien</CardTitle><CardDescription className="mt-1">Informasi identitas dibatasi pada kebutuhan pelayanan.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[680px]">
                                <TableHeader><tr><TableHead>No. rekam medis</TableHead><TableHead>Nama</TableHead><TableHead>Jenis kelamin</TableHead><TableHead className="text-right">Kunjungan</TableHead><TableHead className="text-right">Rekam medis</TableHead></tr></TableHeader>
                                <TableBody>
                                    {patients.data.length > 0 ? patients.data.map((patient) => (
                                        <TableRow key={patient.id}>
                                            <TableCell className="font-mono text-xs font-medium text-neutral-700">{patient.medicalRecordNumber}</TableCell>
                                            <TableCell className="font-medium text-neutral-900">{patient.name}</TableCell>
                                            <TableCell className="text-neutral-600">{patient.gender ?? '—'}</TableCell>
                                            <TableCell className="text-right tabular-nums text-neutral-600">{new Intl.NumberFormat('id-ID').format(patient.visitCount)}</TableCell>
                                            <TableCell className="text-right"><Button asChild size="sm" variant="secondary"><a href={patient.recordUrl}>Lihat RME</a></Button></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-neutral-500" colSpan={5}>Tidak ada pasien yang cocok dengan pencarian.</TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination pagination={patients} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
