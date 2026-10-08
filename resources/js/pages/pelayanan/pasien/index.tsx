import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet, HeartPulse, Plus, Search, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Attachment } from '@/components/ui/attachment';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface Patient {
    id: number;
    medicalRecordNumber: string;
    name: string;
    nik: string | null;
    gender: string | null;
    age: number;
    phone: string | null;
    address: string | null;
    insurance: string;
    insuranceType: string | null;
}

interface InsuranceProvider {
    id: number;
    name: string;
    type: string;
}

interface Props {
    filters: { search: string; insuranceId: string };
    patients: PaginationData & { data: Patient[] };
    insuranceProviders: InsuranceProvider[];
}

export default function PatientsIndex({ filters: initialFilters, patients, insuranceProviders }: Props) {
    const [filters, setFilters] = useState(initialFilters);
    const [importOpen, setImportOpen] = useState(false);
    const [mergeOpen, setMergeOpen] = useState(false);
    const importForm = useForm({ file: null as File | null });
    const mergeForm = useForm({ pasien_utama_id: '', pasien_hapus_id: '' });

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/pasien', filters, { preserveState: true, preserveScroll: true, replace: true });
    }

    function submitImport(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        importForm.post('/pelayanan/pasien-import', { forceFormData: true, onSuccess: () => setImportOpen(false) });
    }

    function submitMerge(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        confirmAction('Gabungkan rekam medis dan pindahkan seluruh kunjungan ke pasien utama?', () => { mergeForm.post('/pelayanan/pasien/gabung', { onSuccess: () => setMergeOpen(false) }); }, 'Lanjutkan');
    }

    return (
        <>
            <Head title="Database Pasien" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div><p className="text-sm font-medium text-neutral-700">Data induk pasien</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Database pasien</h2><p className="mt-1 text-sm text-neutral-500">Kelola identitas, penjamin, dan rekam medis pasien klinik.</p></div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild size="sm" variant="secondary"><a href="/pelayanan/pasien-template"><Download className="size-4" />Template</a></Button>
                        <Button onClick={() => setImportOpen(true)} size="sm" variant="secondary"><FileSpreadsheet className="size-4" />Import</Button>
                        <Button asChild size="sm" variant="secondary"><a href="/pelayanan/pasien-export"><Download className="size-4" />Export</a></Button>
                        <Button onClick={() => setMergeOpen(true)} size="sm" variant="secondary">Gabung RM</Button>
                        <Button asChild size="sm"><Link href="/pelayanan/pasien/create"><Plus className="size-4" />Pasien baru</Link></Button>
                    </div>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 md:grid-cols-[minmax(0,1fr)_16rem_auto] md:items-end" onSubmit={applyFilters}>
                            <Field htmlFor="patient-search" label="Cari pasien"><Input id="patient-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama, No. RM, NIK, atau telepon" value={filters.search} /></Field>
                            <Field htmlFor="patient-insurance-filter" label="Penjamin"><Select id="patient-insurance-filter" onChange={(event) => setFilters({ ...filters, insuranceId: event.target.value })} value={filters.insuranceId}><option value="">Semua penjamin</option>{insuranceProviders.map((provider) => <option key={provider.id} value={provider.id}>{provider.name}</option>)}</Select></Field>
                            <Button type="submit"><Search className="size-4" />Cari pasien</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UsersRound className="size-5" /></span><div><CardTitle>Pasien terdaftar</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(patients.total)} pasien ditemukan.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto"><Table className="min-w-[980px]"><TableHeader><tr><TableHead>No. RM</TableHead><TableHead>Pasien</TableHead><TableHead>JK / Umur</TableHead><TableHead>Kontak</TableHead><TableHead>Alamat</TableHead><TableHead>Penjamin</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                            <TableBody>{patients.data.length ? patients.data.map((patient) => <TableRow key={patient.id}>
                                <TableCell><Link className="font-mono text-xs font-semibold text-neutral-700 hover:underline" href={`/pelayanan/pasien/${patient.id}`}>{patient.medicalRecordNumber}</Link></TableCell>
                                <TableCell><div className="font-medium text-neutral-900">{patient.name}</div>{patient.nik && <div className="font-mono text-xs text-neutral-400">NIK: {patient.nik}</div>}</TableCell>
                                <TableCell className="whitespace-nowrap text-neutral-600">{patient.gender ?? '—'} / {patient.age} th</TableCell>
                                <TableCell className="text-neutral-600">{patient.phone ?? '—'}</TableCell>
                                <TableCell className="max-w-56 truncate text-neutral-500">{patient.address ?? '—'}</TableCell>
                                <TableCell><span className="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-700">{patient.insurance}</span></TableCell>
                                <TableCell><div className="flex justify-end gap-2"><Button asChild size="sm" variant="secondary"><Link href={`/pelayanan/pasien/${patient.id}`}>Detail</Link></Button><Button asChild size="sm" variant="ghost"><Link href={`/pelayanan/pasien/${patient.id}/rekam-medis`}><HeartPulse className="size-4" />RME</Link></Button></div></TableCell>
                            </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty description="Ubah kata kunci atau penjamin, lalu coba lagi." size="compact" title="Tidak ada pasien yang cocok" /></TableCell></TableRow>}</TableBody>
                        </Table></div>
                        <Pagination pagination={patients} />
                    </CardContent>
                </Card>
            </div>
            <Dialog onOpenChange={setImportOpen} open={importOpen}><DialogContent><DialogHeader><DialogTitle>Import data pasien</DialogTitle><DialogDescription>Unggah berkas Excel berdasarkan format template.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={submitImport}><Attachment accept=".xlsx,.xls,.csv" error={importForm.errors.file} fileName={importForm.data.file?.name} id="patient-import" label="File Excel / CSV" onFileChange={(file) => importForm.setData('file', file)} required /><div className="flex justify-end gap-2"><Button onClick={() => setImportOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={!importForm.data.file || importForm.processing} type="submit">Import data</Button></div></form></DialogContent></Dialog>
            <Dialog onOpenChange={setMergeOpen} open={mergeOpen}><DialogContent><DialogHeader><DialogTitle>Gabungkan rekam medis</DialogTitle><DialogDescription>Riwayat kunjungan pasien duplikat akan dipindahkan ke pasien utama.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={submitMerge}><Field error={mergeForm.errors.pasien_utama_id} htmlFor="merge-primary-patient" label="ID pasien utama" required><Input id="merge-primary-patient" min={1} onChange={(event) => mergeForm.setData('pasien_utama_id', event.target.value)} required type="number" value={mergeForm.data.pasien_utama_id} /></Field><Field error={mergeForm.errors.pasien_hapus_id} htmlFor="merge-duplicate-patient" label="ID pasien duplikat" required><Input id="merge-duplicate-patient" min={1} onChange={(event) => mergeForm.setData('pasien_hapus_id', event.target.value)} required type="number" value={mergeForm.data.pasien_hapus_id} /></Field><div className="flex justify-end gap-2"><Button onClick={() => setMergeOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={mergeForm.processing} type="submit">Gabungkan</Button></div></form></DialogContent></Dialog>
        </>
    );
}
