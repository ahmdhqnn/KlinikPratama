import { MasterExportButton } from '@/components/admin/master-export-button';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ActionButton } from '@/components/ui/action-button';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Database, FileSpreadsheet, Layers, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface TerminologySystem {
    system: string;
    label: string;
    release: string;
    releaseValue: string;
    count: number;
    importedAt: string;
}

interface Props {
    systems: TerminologySystem[];
    codeSystems: { value: string; label: string }[];
}

export default function ClinicalTerminologyIndex({ systems, codeSystems }: Props) {
    const [importOpen, setImportOpen] = useState(false);
    const { url } = usePage();
    const query = new URLSearchParams(url.split('?')[1] ?? '');
    const appliedSearch = query.get('search') ?? '';
    const appliedSystem = query.get('system') ?? '';
    const [search, setSearch] = useState(appliedSearch);
    const [systemFilter, setSystemFilter] = useState(appliedSystem);
    const visibleSystems = systems.filter((system) => (!appliedSystem || system.system === appliedSystem) && `${system.label} ${system.system} ${system.release}`.toLocaleLowerCase('id-ID').includes(appliedSearch.trim().toLocaleLowerCase('id-ID')));
    const form = useForm<{ file: File | null; code_system: string; custom_system: string; release: string }>({
        file: null,
        code_system: 'icd10_who',
        custom_system: '',
        release: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/master/terminologi-klinis/import', { forceFormData: true, preserveScroll: true, onSuccess: () => { form.reset('file', 'release'); setImportOpen(false); } });
    }

    function remove(system: TerminologySystem) {
        confirmAction(`Hapus seluruh ${new Intl.NumberFormat('id-ID').format(system.count)} kode dari ${system.label}, rilis ${system.release}? Penghapusan ini tidak dapat dibatalkan.`, () => {
            router.delete('/master/terminologi-klinis', { data: { system: system.system, release: system.releaseValue }, preserveScroll: true });
        }, 'Hapus katalog');
    }

    return (
        <>
            <Head title="Terminologi klinis" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Data klinis terstandar</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Terminologi klinis</h2>
                    <p className="mt-1 text-sm text-neutral-500">Kelola katalog referensi klinis terstandar (ICD-10, ICD-9-CM, dan katalog terkait) untuk pencatatan diagnosis, tindakan, dan kesiapan interoperabilitas SATUSEHAT.</p>
                </div>
                <SummaryStatGrid ariaLabel="Ringkasan katalog terminologi klinis" columns={3}>
                    <StatCard label="Katalog terpasang" value={systems.length} description="Total rilis terminologi aktif" icon={<FileSpreadsheet className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Sistem kode unik" value={new Set(systems.map((s) => s.system)).size} description="ICD-10, ICD-9-CM, dll." icon={<Layers className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Total kode aktif" value={systems.reduce((sum, s) => sum + s.count, 0)} description="Tersedia untuk pencarian klinis" icon={<Database className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </SummaryStatGrid>
                <FilterPanel actions={<>
                    <MasterExportButton catalog="referensi-medis" label="Ekspor katalog" />
                    <MasterExportButton href="/master/terminologi-klinis/template" label="Unduh template" />
                    <Button onClick={() => setImportOpen(true)} size="sm" variant="secondary"><Upload className="size-4" />Impor terminologi</Button>
                </>}>
                    <form className="grid min-w-0 gap-4 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); router.get('/master/terminologi-klinis', { search, system: systemFilter }, { preserveScroll: true, replace: true }); }}>
                        <Field htmlFor="terminology-search" label="Cari katalog atau rilis"><Input id="terminology-search" maxLength={100} value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Nama sistem kode atau versi rilis" /></Field>
                        <Field htmlFor="terminology-filter-system" label="Sistem kode"><Select id="terminology-filter-system" value={systemFilter} onChange={(event) => setSystemFilter(event.target.value)}><option value="">Semua sistem kode</option>{[...new Map(systems.map((system) => [system.system, system])).values()].map((system) => <option key={system.system} value={system.system}>{system.label}</option>)}</Select></Field>
                        <FilterSubmit />
                    </form>
                </FilterPanel>
                <Dialog open={importOpen} onOpenChange={setImportOpen}><DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"><DialogHeader><DialogTitle>Impor berkas terminologi</DialogTitle><DialogDescription>Gunakan data dari sumber resmi. Unduh template untuk melihat format kolom dan pertahankan kode sebagai teks.</DialogDescription></DialogHeader>
                        <form className="space-y-4" encType="multipart/form-data" onSubmit={submit}>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field error={form.errors.code_system} htmlFor="terminology-system" label="Sistem kode" required><Select id="terminology-system" onChange={(event) => form.setData('code_system', event.target.value)} value={form.data.code_system}>{codeSystems.map((system) => <option key={system.value} value={system.value}>{system.label}</option>)}<option value="other">Terminologi lain</option></Select></Field>
                                {form.data.code_system === 'other' && <Field error={form.errors.custom_system} htmlFor="terminology-custom-system" label="Nama sistem kode" required><Input id="terminology-custom-system" maxLength={100} onChange={(event) => form.setData('custom_system', event.target.value)} placeholder="Contoh: SNOMED CT" value={form.data.custom_system} /></Field>}
                                <Field error={form.errors.release} htmlFor="terminology-release" label="Versi / rilis"><Input id="terminology-release" maxLength={100} onChange={(event) => form.setData('release', event.target.value)} placeholder="Contoh: WHO 2016 atau FY 2015" value={form.data.release} /></Field>
                                <Field error={form.errors.file} htmlFor="terminology-file" label="Berkas CSV / Excel / ZIP ICD-9-CM" required><Input accept=".csv,.xls,.xlsx,.zip" id="terminology-file" onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)} required type="file" /></Field>
                            </div>
                            <p className="text-xs text-neutral-500">Berkas CSV/Excel dideteksi dari header kode dan uraian. Arsip ZIP ICD-9-CM CMS dibaca langsung dari file uraian panjang DX/SG. Katalog versi berbeda disimpan terpisah. Maksimum 20 MB.</p>
                            <div className="flex flex-wrap items-center justify-between gap-3"><MasterExportButton href="/master/terminologi-klinis/template" label="Unduh template" /><div className="ml-auto flex flex-wrap justify-end gap-2"><Button disabled={form.processing} onClick={() => setImportOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing || !form.data.file} type="submit"><FileSpreadsheet className="size-4" />{form.processing ? 'Memproses…' : 'Validasi dan impor'}</Button></div></div>
                        </form>
                </DialogContent></Dialog>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><FileSpreadsheet className="size-5" /></span><div><CardTitle>Katalog terpasang</CardTitle><CardDescription className="mt-1">Setiap sistem dan rilis tetap terpisah untuk mempertahankan identitas kode aslinya.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[900px]"><TableHeader><TableRow><TableHead>Sistem kode</TableHead><TableHead>Rilis</TableHead><TableHead>Jumlah kode</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{visibleSystems.length ? visibleSystems.map((system) => <TableRow key={`${system.system}-${system.releaseValue}`}><TableCell className="font-medium text-neutral-900">{system.label}</TableCell><TableCell>{system.release}</TableCell><TableCell>{new Intl.NumberFormat('id-ID').format(system.count)}</TableCell><TableCell><Badge variant="complete">Tersedia untuk pencarian</Badge></TableCell><TableCell><div className="flex flex-wrap items-center justify-center gap-2"><ActionButton asChild action="detail" label="Detail"><Link href={`/master/terminologi-klinis/detail?system=${encodeURIComponent(system.system)}&release=${encodeURIComponent(system.releaseValue)}`} /></ActionButton><ActionButton onClick={() => remove(system)} action="delete" label="Hapus" /></div></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty description={systems.length ? "Ubah kata pencarian atau sistem kode untuk melihat katalog lainnya." : "Impor berkas terminologi resmi yang diizinkan untuk penggunaan instansi."} size="compact" title={systems.length ? "Tidak ada katalog sesuai filter" : "Belum ada katalog terminologi"} /></TableCell></TableRow>}</TableBody></Table></div></CardContent>
                </Card>
            </div>
        </>
    );
}
