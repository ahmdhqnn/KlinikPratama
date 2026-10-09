import { Head, useForm } from '@inertiajs/react';
import { FileSpreadsheet, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface TerminologySystem {
    system: string;
    label: string;
    release: string;
    count: number;
    importedAt: string;
}

interface Props {
    systems: TerminologySystem[];
    codeSystems: { value: string; label: string }[];
}

export default function ClinicalTerminologyIndex({ systems, codeSystems }: Props) {
    const form = useForm<{ file: File | null; code_system: string; custom_system: string; release: string }>({
        file: null,
        code_system: 'icd10_who',
        custom_system: '',
        release: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/master/terminologi-klinis/import', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset('file', 'release') });
    }

    return (
        <>
            <Head title="Terminologi klinis" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Data klinis terstandar</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Terminologi klinis</h2>
                    <p className="mt-1 text-sm text-neutral-500">Impor katalog resmi untuk pencarian kode dan uraian saat pencatatan klinis.</p>
                </div>
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Upload className="size-5" /></span><div><CardTitle>Impor berkas terminologi</CardTitle><CardDescription className="mt-1">Unggah CSV atau Excel dari sumber resmi. Kode, uraian, dan tipe kolom dideteksi dari header; data divalidasi sebelum disimpan.</CardDescription></div></div>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" encType="multipart/form-data" onSubmit={submit}>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field error={form.errors.code_system} htmlFor="terminology-system" label="Sistem kode" required><Select id="terminology-system" onChange={(event) => form.setData('code_system', event.target.value)} value={form.data.code_system}>{codeSystems.map((system) => <option key={system.value} value={system.value}>{system.label}</option>)}<option value="other">Terminologi lain</option></Select></Field>
                                {form.data.code_system === 'other' && <Field error={form.errors.custom_system} htmlFor="terminology-custom-system" label="Nama sistem kode" required><Input id="terminology-custom-system" maxLength={100} onChange={(event) => form.setData('custom_system', event.target.value)} placeholder="Contoh: SNOMED CT" value={form.data.custom_system} /></Field>}
                                <Field error={form.errors.release} htmlFor="terminology-release" label="Versi / rilis"><Input id="terminology-release" maxLength={100} onChange={(event) => form.setData('release', event.target.value)} placeholder="Contoh: WHO 2016 atau FY 2015" value={form.data.release} /></Field>
                                <Field error={form.errors.file} htmlFor="terminology-file" label="Berkas CSV / Excel / ZIP ICD-9-CM" required><Input accept=".csv,.xls,.xlsx,.zip" id="terminology-file" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} required type="file" /></Field>
                            </div>
                            <p className="text-xs text-neutral-500">Berkas CSV/Excel dideteksi dari header kode dan uraian. Arsip ZIP ICD-9-CM CMS dibaca langsung dari file uraian panjang DX/SG. Katalog versi berbeda disimpan terpisah. Maksimum 20 MB.</p>
                            <div className="flex justify-end"><Button disabled={form.processing || !form.data.file} type="submit"><FileSpreadsheet className="size-4" />{form.processing ? 'Memproses…' : 'Validasi dan impor'}</Button></div>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100"><CardTitle>Katalog terpasang</CardTitle><CardDescription>Setiap sistem dan rilis tetap terpisah untuk mempertahankan identitas kode aslinya.</CardDescription></CardHeader>
                    <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[720px]"><TableHeader><tr><TableHead>Sistem kode</TableHead><TableHead>Rilis</TableHead><TableHead>Jumlah kode</TableHead><TableHead>Status</TableHead></tr></TableHeader><TableBody>{systems.length ? systems.map((system) => <TableRow key={`${system.system}-${system.release}`}><TableCell className="font-medium text-neutral-900">{system.label}</TableCell><TableCell>{system.release}</TableCell><TableCell>{new Intl.NumberFormat('id-ID').format(system.count)}</TableCell><TableCell><Badge variant="complete">Tersedia untuk pencarian</Badge></TableCell></TableRow>) : <TableRow><TableCell colSpan={4}><Empty description="Impor file terminologi yang telah dimiliki dan diizinkan untuk penggunaan instansi." size="compact" title="Belum ada katalog terminologi" /></TableCell></TableRow>}</TableBody></Table></div></CardContent>
                </Card>
            </div>
        </>
    );
}
