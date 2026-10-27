import { ActionButton } from '@/components/ui/action-button';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { Search, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Patient {
    id: number;
    medicalRecordNumber: string;
    name: string;
    nip: string | null;
    gender: string | null;
    birthDate: string;
    age: number | null;
    phone: string | null;
    membership: string;
    registeredAt: string;
    viewUrl: string;
    editUrl: string | null;
}

interface Props {
    filters: { search: string; jenisKelamin: string };
    patients: PaginationData & { data: Patient[] };
}

export default function PatientDatabase({ filters: initialFilters, patients }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/database-pasien', { search: filters.search, jenis_kelamin: filters.jenisKelamin }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Database Pasien" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Data induk pasien</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Database pasien</h2>
                    <p className="mt-1 text-sm text-neutral-500">Temukan data pasien dengan nomor rekam medis, nama, NIK, atau nomor telepon.</p>
                </div>
                <FilterPanel><form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={applyFilters}>
                            <Field htmlFor="patient-database-search" label="Cari pasien">
                                <Input id="patient-database-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Cari nama, No. RM, NIK, NIP, atau telepon" value={filters.search} />
                            </Field>
                            <Field htmlFor="patient-database-gender" label="Jenis kelamin">
                                <Select id="patient-database-gender" onChange={(event) => setFilters({ ...filters, jenisKelamin: event.target.value })} value={filters.jenisKelamin}>
                                    <option value="">Semua</option><option value="L">Laki-laki</option><option value="P">Perempuan</option>
                                </Select>
                            </Field>
                            <Button type="submit"><Search className="size-4" />Cari pasien</Button>
                        </form></FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UsersRound className="size-5" /></span>
                            <div><CardTitle>Data pasien terdaftar</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(patients.total)} pasien ditemukan.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[980px]">
                                <TableHeader><tr><TableHead>No. RM</TableHead><TableHead>Nama</TableHead><TableHead>JK</TableHead><TableHead>Tanggal lahir</TableHead><TableHead>Telepon</TableHead><TableHead>Kepesertaan</TableHead><TableHead>Terdaftar</TableHead><TableHead>Aksi</TableHead></tr></TableHeader>
                                <TableBody>
                                    {patients.data.length > 0 ? patients.data.map((patient) => (
                                        <TableRow key={patient.id}>
                                            <TableCell className="font-mono text-xs font-semibold text-neutral-700">{patient.medicalRecordNumber}</TableCell>
                                            <TableCell className="font-medium text-neutral-900">{patient.name}{patient.nip && <p className="mt-1 font-mono text-xs font-normal text-neutral-500">NIP: {patient.nip}</p>}</TableCell>
                                            <TableCell className="text-neutral-600">{patient.gender === 'L' ? 'Laki-laki' : patient.gender === 'P' ? 'Perempuan' : 'Belum diisi'}</TableCell>
                                            <TableCell className="whitespace-nowrap text-neutral-600">{patient.birthDate}{patient.age !== null && <span className="ml-1 text-xs text-neutral-400">({patient.age} th)</span>}</TableCell>
                                            <TableCell className="text-neutral-600">{patient.phone || 'Belum diisi'}</TableCell>
                                            <TableCell className="text-neutral-600">{patient.membership}</TableCell>
                                            <TableCell className="whitespace-nowrap text-neutral-500">{patient.registeredAt}</TableCell>
                                            <TableCell><div className="flex justify-center gap-2"><ActionButton asChild action="detail" label={`Lihat ${patient.name}`}><Link href={patient.viewUrl} /></ActionButton>{patient.editUrl && <ActionButton asChild action="edit" label={`Edit ${patient.name}`}><Link href={patient.editUrl} /></ActionButton>}</div></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={8}><Empty size="compact" title="Tidak ada data pasien yang cocok." /></TableCell></TableRow>}
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
