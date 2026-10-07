import { Head, router } from '@inertiajs/react';
import { Search, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Patient {
    id: number;
    medicalRecordNumber: string;
    name: string;
    gender: string | null;
    birthDate: string;
    age: number | null;
    phone: string | null;
    insurance: string;
    registeredAt: string;
}

interface Props {
    filters: { search: string; jenisKelamin: string };
    patients: PaginationData & { data: Patient[] };
}

export default function PatientDatabase({ filters: initialFilters, patients }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/database-pasien', filters, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Database Pasien" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-blue-700">Data induk pasien</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Database pasien</h2>
                    <p className="mt-1 text-sm text-slate-500">Temukan data pasien dengan nomor rekam medis, nama, NIK, atau nomor telepon.</p>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem_auto] sm:items-end" onSubmit={applyFilters}>
                            <label className="space-y-1.5 text-sm font-medium text-slate-700">
                                <span>Cari pasien</span>
                                <Input onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama, No. RM, NIK, atau telepon" value={filters.search} />
                            </label>
                            <label className="space-y-1.5 text-sm font-medium text-slate-700">
                                <span>Jenis kelamin</span>
                                <NativeSelect onChange={(event) => setFilters({ ...filters, jenisKelamin: event.target.value })} value={filters.jenisKelamin}>
                                    <option value="">Semua</option><option value="L">Laki-laki</option><option value="P">Perempuan</option>
                                </NativeSelect>
                            </label>
                            <Button type="submit"><Search className="size-4" />Cari pasien</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-slate-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><UsersRound className="size-5" /></span>
                            <div><CardTitle>Data pasien terdaftar</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(patients.total)} pasien ditemukan.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[980px]">
                                <TableHeader><tr><TableHead>No. RM</TableHead><TableHead>Nama</TableHead><TableHead>JK</TableHead><TableHead>Tanggal lahir</TableHead><TableHead>Telepon</TableHead><TableHead>Asuransi</TableHead><TableHead>Terdaftar</TableHead></tr></TableHeader>
                                <TableBody>
                                    {patients.data.length > 0 ? patients.data.map((patient) => (
                                        <TableRow key={patient.id}>
                                            <TableCell className="font-mono text-xs font-semibold text-blue-700">{patient.medicalRecordNumber}</TableCell>
                                            <TableCell className="font-medium text-slate-900">{patient.name}</TableCell>
                                            <TableCell className="text-slate-600">{patient.gender === 'L' ? 'Laki-laki' : patient.gender === 'P' ? 'Perempuan' : '—'}</TableCell>
                                            <TableCell className="whitespace-nowrap text-slate-600">{patient.birthDate}{patient.age !== null && <span className="ml-1 text-xs text-slate-400">({patient.age} th)</span>}</TableCell>
                                            <TableCell className="text-slate-600">{patient.phone || '—'}</TableCell>
                                            <TableCell className="text-slate-600">{patient.insurance}</TableCell>
                                            <TableCell className="whitespace-nowrap text-slate-500">{patient.registeredAt}</TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-slate-500" colSpan={7}>Tidak ada data pasien yang cocok.</TableCell></TableRow>}
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
