import { VisitActionButtons } from '@/components/dashboard/visit-action-buttons';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, router } from '@inertiajs/react';
import { Activity, UserRound, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Field } from '@/components/ui/field';

interface Patient {
    id: number;
    medicalRecordNumber: string;
    name: string;
    gender: string | null;
    visitCount: number;
    recordUrl: string;
    profileUrl: string;
    letterUrl: string | null;
}

interface Props {
    search: string;
    gender?: string;
    stats?: { totalPatients: number; totalVisits: number; male: number; female: number };
    patients: PaginationData & { data: Patient[] };
}

export default function DoctorPatients({ search, gender = '', stats, patients }: Props) {
    const [query, setQuery] = useState(search);
    const [selectedGender, setSelectedGender] = useState(gender);

    function applySearch(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/dokter/pasien', { search: query, jenis_kelamin: selectedGender }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Pasien dokter" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Data pasien</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pasien yang pernah ditangani</h2>
                    <p className="mt-1 text-sm text-neutral-500">Daftar ini hanya mencakup pasien yang memiliki kunjungan dengan Anda.</p>
                </div>
                <SummaryStatGrid ariaLabel="Ringkasan pasien dokter" columns={4}>
                    <StatCard label="Total pasien" value={stats?.totalPatients ?? patients.total} description="Pasien pernah ditangani" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Total kunjungan" value={stats?.totalVisits ?? patients.data.reduce((s, p) => s + p.visitCount, 0)} description="Akumulasi layanan Anda" icon={<Activity className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Pasien laki-laki" value={stats?.male ?? patients.data.filter((p) => p.gender === 'L').length} description="Jenis kelamin L" icon={<UserRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Pasien perempuan" value={stats?.female ?? patients.data.filter((p) => p.gender === 'P').length} description="Jenis kelamin P" icon={<UserRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </SummaryStatGrid>
                <FilterPanel>
                    <form className="grid min-w-0 gap-4 sm:grid-cols-2" onSubmit={applySearch}>
                        <Field htmlFor="patient-search" label="Cari pasien">
                            <Input id="patient-search" onChange={(event) => setQuery(event.target.value)} placeholder="Cari nama, No. RM, atau NIK" value={query} />
                        </Field>
                        <Field htmlFor="patient-gender" label="Jenis kelamin">
                            <Select id="patient-gender" onChange={(event) => setSelectedGender(event.target.value)} value={selectedGender}>
                                <option value="">Semua</option>
                                <option value="L">Laki-laki</option>
                                <option value="P">Perempuan</option>
                            </Select>
                        </Field>
                        <FilterSubmit />
                    </form>
                </FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><UsersRound className="size-5" /></span>
                            <div><CardTitle>Data pasien</CardTitle><CardDescription className="mt-1">Informasi identitas dibatasi pada kebutuhan pelayanan.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[680px]">
                                <TableHeader><TableRow><TableHead>No. rekam medis</TableHead><TableHead>Nama</TableHead><TableHead>Jenis kelamin</TableHead><TableHead className="text-center">Kunjungan</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {patients.data.length > 0 ? patients.data.map((patient) => (
                                        <TableRow key={patient.id}>
                                            <TableCell className="font-sans tabular-nums text-xs font-medium text-neutral-700">{patient.medicalRecordNumber}</TableCell>
                                            <TableCell className="font-medium text-neutral-900">{patient.name}</TableCell>
                                            <TableCell className="text-neutral-600">{patient.gender ?? 'Belum diisi'}</TableCell>
                                            <TableCell className="text-center tabular-nums text-neutral-600">{new Intl.NumberFormat('id-ID').format(patient.visitCount)}</TableCell>
                                            <TableCell className="text-center"><VisitActionButtons letterUrl={patient.letterUrl} patient={patient.name} primary={{ href: patient.profileUrl, label: `Lihat profil ${patient.name}`, type: 'detail' }} recordUrl={patient.recordUrl} /></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Tidak ada pasien yang cocok dengan pencarian." /></TableCell></TableRow>}
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
