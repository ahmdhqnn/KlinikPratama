import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet, HeartPulse, Plus, Search, UsersRound } from 'lucide-react';
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
        if (window.confirm('Gabungkan rekam medis dan pindahkan seluruh kunjungan ke pasien utama?')) {
            mergeForm.post('/pelayanan/pasien/gabung', { onSuccess: () => setMergeOpen(false) });
        }
    }

    return (
        <>
            <Head title="Database Pasien" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div><p className="text-sm font-medium text-blue-700">Data induk pasien</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Database pasien</h2><p className="mt-1 text-sm text-slate-500">Kelola identitas, penjamin, dan rekam medis pasien klinik.</p></div>
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
                            <label className="space-y-1.5 text-sm font-medium text-slate-700"><span>Cari pasien</span><Input onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama, No. RM, NIK, atau telepon" value={filters.search} /></label>
                            <label className="space-y-1.5 text-sm font-medium text-slate-700"><span>Penjamin</span><NativeSelect onChange={(event) => setFilters({ ...filters, insuranceId: event.target.value })} value={filters.insuranceId}><option value="">Semua penjamin</option>{insuranceProviders.map((provider) => <option key={provider.id} value={provider.id}>{provider.name}</option>)}</NativeSelect></label>
                            <Button type="submit"><Search className="size-4" />Cari pasien</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-slate-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><UsersRound className="size-5" /></span><div><CardTitle>Pasien terdaftar</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(patients.total)} pasien ditemukan.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto"><Table className="min-w-[980px]"><TableHeader><tr><TableHead>No. RM</TableHead><TableHead>Pasien</TableHead><TableHead>JK / Umur</TableHead><TableHead>Kontak</TableHead><TableHead>Alamat</TableHead><TableHead>Penjamin</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                            <TableBody>{patients.data.length ? patients.data.map((patient) => <TableRow key={patient.id}>
                                <TableCell><Link className="font-mono text-xs font-semibold text-blue-700 hover:underline" href={`/pelayanan/pasien/${patient.id}`}>{patient.medicalRecordNumber}</Link></TableCell>
                                <TableCell><div className="font-medium text-slate-900">{patient.name}</div>{patient.nik && <div className="font-mono text-xs text-slate-400">NIK: {patient.nik}</div>}</TableCell>
                                <TableCell className="whitespace-nowrap text-slate-600">{patient.gender ?? '—'} / {patient.age} th</TableCell>
                                <TableCell className="text-slate-600">{patient.phone ?? '—'}</TableCell>
                                <TableCell className="max-w-56 truncate text-slate-500">{patient.address ?? '—'}</TableCell>
                                <TableCell><span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{patient.insurance}</span></TableCell>
                                <TableCell><div className="flex justify-end gap-2"><Button asChild size="sm" variant="secondary"><Link href={`/pelayanan/pasien/${patient.id}`}>Detail</Link></Button><Button asChild size="sm" variant="ghost"><Link href={`/pelayanan/pasien/${patient.id}/rekam-medis`}><HeartPulse className="size-4" />RME</Link></Button></div></TableCell>
                            </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-slate-500" colSpan={7}>Tidak ada pasien yang cocok dengan pencarian.</TableCell></TableRow>}</TableBody>
                        </Table></div>
                        <Pagination pagination={patients} />
                    </CardContent>
                </Card>
            </div>
            {importOpen && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" onClick={() => setImportOpen(false)}><section aria-labelledby="import-title" aria-modal="true" className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" onClick={(event) => event.stopPropagation()} role="dialog"><h2 className="text-lg font-semibold text-slate-950" id="import-title">Import data pasien</h2><p className="mt-1 text-sm text-slate-500">Unggah berkas Excel berdasarkan format template.</p><form className="mt-5 space-y-4" onSubmit={submitImport}><Input accept=".xlsx,.xls,.csv" onChange={(event) => importForm.setData('file', event.target.files?.[0] ?? null)} type="file" /><p className="text-xs text-red-600">{importForm.errors.file}</p><div className="flex justify-end gap-2"><Button onClick={() => setImportOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={!importForm.data.file || importForm.processing} type="submit">Import data</Button></div></form></section></div>}
            {mergeOpen && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4" onClick={() => setMergeOpen(false)}><section aria-labelledby="merge-title" aria-modal="true" className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl" onClick={(event) => event.stopPropagation()} role="dialog"><h2 className="text-lg font-semibold text-slate-950" id="merge-title">Gabungkan rekam medis</h2><p className="mt-1 text-sm text-slate-500">Riwayat kunjungan pasien duplikat akan dipindahkan ke pasien utama.</p><form className="mt-5 space-y-4" onSubmit={submitMerge}><label className="block space-y-1.5 text-sm font-medium text-slate-700"><span>ID pasien utama</span><Input min={1} onChange={(event) => mergeForm.setData('pasien_utama_id', event.target.value)} required type="number" value={mergeForm.data.pasien_utama_id} /></label><p className="text-xs text-red-600">{mergeForm.errors.pasien_utama_id}</p><label className="block space-y-1.5 text-sm font-medium text-slate-700"><span>ID pasien duplikat</span><Input min={1} onChange={(event) => mergeForm.setData('pasien_hapus_id', event.target.value)} required type="number" value={mergeForm.data.pasien_hapus_id} /></label><p className="text-xs text-red-600">{mergeForm.errors.pasien_hapus_id}</p><div className="flex justify-end gap-2"><Button onClick={() => setMergeOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={mergeForm.processing} type="submit">Gabungkan</Button></div></form></section></div>}
        </>
    );
}
