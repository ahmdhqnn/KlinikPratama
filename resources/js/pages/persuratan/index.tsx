import { letterTypes } from '@/components/medical-letter-fields';
import { ActionButton } from '@/components/ui/action-button';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Save, FileText, ArrowRightLeft, Files } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';

interface Template {
    id: number;
    code: string;
    name: string;
    type: string;
    prefix: string;
    numberFormat: string;
    nextNumber: number;
    content: string;
    active: boolean;
    inUse: boolean;
}

interface DocumentRow {
    id: number;
    type?: string;
    number: string | null;
    date: string | null;
    patient: string;
    visitNumber: string;
    clinic?: string;
    fromClinic?: string;
    toClinic?: string;
    doctor: string;
    status?: string;
    printUrl: string;
}

interface Props {
    filters: { search: string; visitId?: number | null };
    isAdmin: boolean;
    templates: PaginationData & { data: Template[] };
    letters: PaginationData & { data: DocumentRow[] };
    referrals: PaginationData & { data: DocumentRow[] };
    eligibleVisits: (PaginationData & { data: { id: number; date: string; number: string; patient: string; medicalRecordNumber: string; clinic: string; letterUrl: string | null }[] }) | null;
}

const documentTypes = [...letterTypes, ['rujukan_internal', 'Rujukan internal']];

const emptyTemplate = {
    kode: '', nama: '', jenis: 'sakit', prefix: 'SK', format_nomor: '[prefix]/[urut]/[bulan]/[tahun]',
    nomor_berikutnya: '1', isi: '', is_active: true,
};

export default function Persuratan({ filters, isAdmin, templates: templatePagination, letters: letterPagination, referrals: referralPagination, eligibleVisits }: Props) {
    const [search, setSearch] = useState(filters.search);
    const templates = templatePagination.data;
    const letters = letterPagination.data;
    const referrals = referralPagination.data;
    const [editingId, setEditingId] = useState<number | null>(null);
    const [activeTab, setActiveTab] = useState('dokumen');
    const form = useForm(emptyTemplate);

    function startEdit(template: Template) {
        setEditingId(template.id);
        form.setData({
            kode: template.code,
            nama: template.name,
            jenis: template.type,
            prefix: template.prefix,
            format_nomor: template.numberFormat,
            nomor_berikutnya: template.nextNumber.toString(),
            isi: template.content,
            is_active: template.active,
        });
    }

    function resetForm() {
        setEditingId(null);
        form.setData(emptyTemplate);
        form.clearErrors();
    }

    function saveTemplate(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: resetForm };
        if (editingId) {
            form.put(`/persuratan/template/${editingId}`, options);
            return;
        }
        form.post('/persuratan/template', options);
    }

    function removeTemplate(template: Template) {
        confirmAction(`Hapus template “${template.name}”?`, () => {
            router.delete(`/persuratan/template/${template.id}`, { preserveScroll: true });
        }, 'Lanjutkan');
    }

    return (
        <>
            <Head title="Persuratan" />
            <div className="space-y-6">
                {isAdmin && <p className="rounded-xl border border-neutral-200 bg-surface p-4 text-sm text-neutral-600">Arsip dokumen dapat ditinjau dan dicetak lintas poli. Template dikelola pada tab tersendiri. Surat baru diterbitkan oleh dokter penanggung jawab setelah pemeriksaan final.</p>}
                <div>
                    <p className="text-sm font-medium text-neutral-600">Dokumen klinis dan administrasi</p>
                    <h1 className="mt-1 text-2xl font-semibold text-neutral-950">Persuratan</h1>
                    <p className="mt-1 text-sm text-neutral-500">Terbitkan surat dari pemeriksaan pasien dan tinjau dokumen yang sudah dibuat.</p>
                </div>

                <section aria-label="Ringkasan persuratan" className="grid auto-rows-fr items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Total dokumen terbit" value={letterPagination.total + referralPagination.total} description="Surat medis dan rujukan internal" icon={<Files className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Surat medis" value={letterPagination.total} description="Surat yang sudah diterbitkan" icon={<FileText className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Rujukan internal" value={referralPagination.total} description="Rujukan antarpoliklinik terbit" icon={<ArrowRightLeft className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    {eligibleVisits && <StatCard label="Penerbitan dokumen" value={eligibleVisits.total} description={isAdmin ? 'Kunjungan memenuhi syarat penerbitan oleh dokter' : 'Pemeriksaan final yang dapat dibuatkan surat'} icon={<Plus className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />}
                </section>
                {filters.visitId && <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm"><p>Dokumen untuk kunjungan yang dipilih dari antrean.</p><Button asChild size="sm" variant="secondary"><Link href="/persuratan">Tampilkan semua dokumen</Link></Button></div>}
            <Tabs className="space-y-5" onValueChange={setActiveTab} value={activeTab}>
                    <TabsList aria-label="Bagian persuratan">
                        <TabsTrigger value="dokumen">Dokumen terbit</TabsTrigger>
                        {eligibleVisits && <TabsTrigger value="terbitkan">Penerbitan dokumen</TabsTrigger>}
                        {isAdmin && <TabsTrigger value="template">Template & penomoran</TabsTrigger>}
                    </TabsList>
                    <TabsContent className="space-y-5" value="terbitkan">
                        {eligibleVisits && <SearchFilter visitId={filters.visitId} search={search} onSearchChange={setSearch} />}
                        {eligibleVisits && <Card className="overflow-hidden">
                            <CardHeader className="min-h-24 border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><FileText className="size-5" /></span><div className="min-w-0"><CardTitle>{isAdmin ? 'Kunjungan siap diterbitkan' : 'Siap diterbitkan surat'}</CardTitle><CardDescription className="mt-1">{isAdmin ? 'Dokter penanggung jawab menerbitkan surat setelah pemeriksaan final.' : 'Pemeriksaan final pasien Anda. Pilih kunjungan untuk membuat surat medis.'}</CardDescription></div></div></CardHeader>
                            <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[700px]"><TableHeader><TableRow><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{eligibleVisits.data.length ? eligibleVisits.data.map((visit) => <TableRow key={visit.id}><TableCell className="font-sans tabular-nums text-xs">{visit.number}</TableCell><TableCell className="whitespace-nowrap">{visit.date}</TableCell><TableCell><p className="font-medium">{visit.patient}</p><p className="text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell><TableCell>{visit.clinic}</TableCell><TableCell className="text-center"><ActionButton asChild action="issue" disabled={!visit.letterUrl} disabledReason="Surat diterbitkan oleh dokter penanggung jawab setelah pemeriksaan final." label={`Terbitkan surat untuk ${visit.patient}`}><Link href={visit.letterUrl ?? ''} /></ActionButton></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada pemeriksaan final untuk dibuatkan surat." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={eligibleVisits} /></CardContent>
                        </Card>}
                    </TabsContent>
                    <TabsContent className="space-y-5" value="dokumen">
                        <SearchFilter visitId={filters.visitId} search={search} onSearchChange={setSearch} />
                        <Card>
                            <CardHeader className="min-h-24"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><FileText className="size-5" /></span><div className="min-w-0"><CardTitle>Surat medis</CardTitle><CardDescription className="mt-1">Surat yang terkait dengan kunjungan pasien.</CardDescription></div></div></CardHeader>
                            <CardContent className="p-0"><div className="overflow-x-auto">
                                <Table className="min-w-[760px]"><TableHeader><TableRow><TableHead className="text-center">Nomor / tanggal</TableHead><TableHead className="text-center">Pasien</TableHead><TableHead className="text-center">Jenis</TableHead><TableHead className="text-center">Poli / dokter</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                                    <TableBody>{letters.length ? letters.map((letter) => <TableRow key={letter.id}><TableCell className="text-center"><span className="font-sans tabular-nums text-sm">{letter.number ?? 'Belum diisi'}</span><p className="mt-1 text-xs text-neutral-500">{letter.date ?? 'Belum diisi'}</p></TableCell><TableCell className="text-center"><span className="font-medium">{letter.patient}</span><p className="text-xs text-neutral-500">{letter.visitNumber}</p></TableCell><TableCell className="text-center"><Badge variant="examination">{documentTypes.find(([value]) => value === letter.type)?.[1] ?? letter.type}</Badge></TableCell><TableCell className="text-center">{letter.clinic}<p className="text-xs text-neutral-500">{letter.doctor}</p></TableCell><TableCell className="text-center"><ActionButton asChild action="print" label="Cetak"><a href={letter.printUrl} /></ActionButton></TableCell></TableRow>) : <TableRow><TableCell className="text-center" colSpan={5}><Empty size="compact" title="Belum ada surat medis." /></TableCell></TableRow>}</TableBody>
                                </Table></div></CardContent>
                            <Pagination pagination={letterPagination} />
                        </Card>

                        <Card>
                            <CardHeader className="min-h-24"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ArrowRightLeft className="size-5" /></span><div className="min-w-0"><CardTitle>Rujukan internal</CardTitle><CardDescription className="mt-1">Rujukan antarpoliklinik yang telah diterbitkan.</CardDescription></div></div></CardHeader>
                            <CardContent className="p-0"><div className="overflow-x-auto">
                                <Table className="min-w-[760px]"><TableHeader><TableRow><TableHead className="text-center">Nomor / tanggal</TableHead><TableHead className="text-center">Pasien</TableHead><TableHead className="text-center">Tujuan</TableHead><TableHead className="text-center">Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                                    <TableBody>{referrals.length ? referrals.map((referral) => <TableRow key={referral.id}><TableCell className="text-center"><span className="font-sans tabular-nums text-sm">{referral.number ?? 'Belum diisi'}</span><p className="mt-1 text-xs text-neutral-500">{referral.date ?? 'Belum diisi'}</p></TableCell><TableCell className="text-center"><span className="font-medium">{referral.patient}</span><p className="text-xs text-neutral-500">{referral.visitNumber}</p></TableCell><TableCell className="text-center">{referral.fromClinic} → {referral.toClinic}<p className="text-xs text-neutral-500">{referral.doctor}</p></TableCell><TableCell className="text-center"><Badge variant={referral.status === 'selesai' ? 'complete' : 'waiting'}>{referral.status}</Badge></TableCell><TableCell className="text-center"><ActionButton asChild action="print" label="Cetak"><a href={referral.printUrl} /></ActionButton></TableCell></TableRow>) : <TableRow><TableCell className="text-center" colSpan={5}><Empty size="compact" title="Belum ada rujukan internal." /></TableCell></TableRow>}</TableBody>
                                </Table></div></CardContent>
                            <Pagination pagination={referralPagination} />
                        </Card>
                    </TabsContent>

                    {isAdmin && <TabsContent className="space-y-5" value="template">
                        <Card>
                            <CardHeader><CardTitle>{editingId ? 'Ubah template' : 'Tambah template'}</CardTitle><CardDescription>Atur format dokumen dan penomoran. Nomor surat dibuat otomatis saat dokter menerbitkan dokumen.</CardDescription></CardHeader>
                            <CardContent>
                                <form className="space-y-5" onSubmit={saveTemplate}>
                                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                        <Field error={form.errors.kode} htmlFor="template-kode" label="Kode" required><Input placeholder="Contoh: SURAT-SEHAT" id="template-kode" maxLength={60} onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field>
                                        <Field error={form.errors.nama} htmlFor="template-nama" label="Nama template" required><Input placeholder="Contoh: Surat Keterangan Sehat" id="template-nama" onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field>
                                        <Field error={form.errors.jenis} htmlFor="template-jenis" label="Jenis dokumen" required><Select id="template-jenis" onChange={(event) => form.setData('jenis', event.target.value)} value={form.data.jenis}>{documentTypes.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>
                                        <Field error={form.errors.prefix} htmlFor="template-prefix" label="Prefix nomor" required><Input placeholder="Contoh: SKS" id="template-prefix" onChange={(event) => form.setData('prefix', event.target.value)} required value={form.data.prefix} /></Field>
                                        <Field error={form.errors.format_nomor} htmlFor="template-format" label="Format nomor" required><Input placeholder="[prefix]/[urut]/[bulan]/[tahun]" id="template-format" onChange={(event) => form.setData('format_nomor', event.target.value)} required value={form.data.format_nomor} /></Field>
                                        <Field error={form.errors.nomor_berikutnya} htmlFor="template-next-number" label="Nomor berikutnya" required><Input id="template-next-number" min="1" onChange={(event) => form.setData('nomor_berikutnya', event.target.value)} required type="number" value={form.data.nomor_berikutnya} /></Field>
                                        <Field error={form.errors.is_active} htmlFor="template-active" label="Status"><Select id="template-active" onChange={(event) => form.setData('is_active', event.target.value === 'true')} value={form.data.is_active ? 'true' : 'false'}><option value="true">Aktif</option><option value="false">Nonaktif</option></Select></Field>
                                    </div>
                                    <p className="text-xs text-neutral-500">Token nomor: [prefix], [urut], [bulan], [tahun]. Token isi surat mengikuti konteks dokumen seperti [pasien], [no_rm], [anamnesis], [diagnosis], [terapi], [dokter], [nomor], [tanggal], [poli], dan [poli_tujuan].</p>
                                    <Field error={form.errors.isi} htmlFor="template-isi" label="Keterangan tambahan template"><Textarea placeholder="Tulis isi surat menggunakan token seperti [pasien], [dokter], dan [tanggal]" id="template-isi" onChange={(event) => form.setData('isi', event.target.value)} rows={8} value={form.data.isi} /></Field>
                                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">{editingId && <Button onClick={resetForm} type="button" variant="secondary">Batal ubah</Button>}<Button disabled={form.processing} type="submit">{editingId ? <Save className="size-4" /> : <Plus className="size-4" />}{editingId ? 'Simpan perubahan' : 'Simpan template'}</Button></div>
                                </form>
                            </CardContent>
                        </Card>

                        <SearchFilter visitId={filters.visitId} search={search} onSearchChange={setSearch} />
                        <Card>
                            <CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Files className="size-5" /></span><div><CardTitle>Template tersedia</CardTitle><CardDescription className="mt-1">{templatePagination.total} template surat dan penomoran.</CardDescription></div></div></CardHeader>
                            <CardContent className="p-0"><div className="overflow-x-auto">
                                <Table className="min-w-[760px]"><TableHeader><TableRow><TableHead className="text-center">Kode / nama</TableHead><TableHead className="text-center">Jenis</TableHead><TableHead className="text-center">Format</TableHead><TableHead className="text-center">Nomor berikutnya</TableHead><TableHead className="text-center">Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                                    <TableBody>{templates.length ? templates.map((template) => <TableRow key={template.id}><TableCell className="text-center"><span className="font-sans tabular-nums text-xs">{template.code}</span><p className="font-medium">{template.name}</p></TableCell><TableCell className="text-center">{documentTypes.find(([value]) => value === template.type)?.[1] ?? template.type}</TableCell><TableCell className="font-sans tabular-nums text-xs">{template.numberFormat}</TableCell><TableCell className="text-center">{template.nextNumber}</TableCell><TableCell className="text-center"><Badge variant="default">{template.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell><TableCell className="text-center"><div className="inline-flex flex-wrap items-center justify-center gap-2"><ActionButton onClick={() => startEdit(template)} type="button" action="edit" label={`Ubah ${template.name}`} /><ActionButton disabled={template.inUse} onClick={() => removeTemplate(template)} title={template.inUse ? 'Template sudah digunakan; nonaktifkan jika tidak dipakai lagi.' : undefined} type="button" action="delete" label={`Hapus ${template.name}`} /></div></TableCell></TableRow>) : <TableRow><TableCell className="text-center" colSpan={6}><Empty size="compact" title="Belum ada template persuratan." /></TableCell></TableRow>}</TableBody>
                                </Table></div></CardContent>
                            <Pagination pagination={templatePagination} />
                        </Card>
                    </TabsContent>}
                </Tabs>
            </div>
        </>
    );
}

function SearchFilter({ search, onSearchChange, visitId }: { visitId?: number | null; search: string; onSearchChange: (value: string) => void }) {
    return <FilterPanel><form className="flex flex-col gap-3 sm:flex-row" onSubmit={(event) => { event.preventDefault(); router.get('/persuratan', { search, kunjungan_id: visitId ?? '' }, { preserveState: true, preserveScroll: true, replace: true }); }}><Input aria-label="Cari dokumen atau pasien" placeholder="Cari nama, No. RM, nomor surat, atau template" value={search} onChange={(event) => onSearchChange(event.target.value)} /><Button type="submit" variant="secondary">Terapkan filter</Button></form></FilterPanel>;
}
