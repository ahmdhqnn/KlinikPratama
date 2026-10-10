import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Printer, Save, Trash2 } from 'lucide-react';
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
    isAdmin: boolean;
    templates: Template[];
    letters: DocumentRow[];
    referrals: DocumentRow[];
}

const documentTypes = [
    ['sakit', 'Surat keterangan sakit'],
    ['sehat', 'Surat keterangan sehat'],
    ['rujukan', 'Surat rujukan eksternal'],
    ['lainnya', 'Surat lainnya'],
    ['rujukan_internal', 'Rujukan internal'],
];

const emptyTemplate = {
    kode: '', nama: '', jenis: 'sakit', prefix: 'SK', format_nomor: '[prefix]/[urut]/[bulan]/[tahun]',
    nomor_berikutnya: '1', isi: '', is_active: true,
};

export default function Persuratan({ isAdmin, templates, letters, referrals }: Props) {
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
                <div>
                    <p className="text-sm font-medium text-neutral-600">Dokumen klinis dan administrasi</p>
                    <h1 className="mt-1 text-2xl font-semibold text-neutral-950">Persuratan</h1>
                    <p className="mt-1 text-sm text-neutral-500">Kelola template, penomoran surat, dan cetak dokumen dari kunjungan.</p>
                </div>

                <Tabs className="space-y-5" onValueChange={setActiveTab} value={activeTab}>
                    <TabsList aria-label="Bagian persuratan">
                        <TabsTrigger value="dokumen">Dokumen diterbitkan</TabsTrigger>
                        {isAdmin && <TabsTrigger value="template">Template & penomoran</TabsTrigger>}
                    </TabsList>
                    <TabsContent className="space-y-5" value="dokumen">
                        <Card>
                            <CardHeader><CardTitle>Surat medis</CardTitle><CardDescription>Surat yang terkait dengan kunjungan pasien.</CardDescription></CardHeader>
                            <CardContent className="overflow-x-auto">
                                <Table><TableHeader><tr><TableHead>Nomor / tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Jenis</TableHead><TableHead>Poli / dokter</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                                    <TableBody>{letters.length ? letters.map((letter) => <TableRow key={letter.id}><TableCell><span className="font-mono text-sm">{letter.number ?? '—'}</span><p className="mt-1 text-xs text-neutral-500">{letter.date ?? '—'}</p></TableCell><TableCell><span className="font-medium">{letter.patient}</span><p className="text-xs text-neutral-500">{letter.visitNumber}</p></TableCell><TableCell><Badge variant="examination">{documentTypes.find(([value]) => value === letter.type)?.[1] ?? letter.type}</Badge></TableCell><TableCell>{letter.clinic}<p className="text-xs text-neutral-500">{letter.doctor}</p></TableCell><TableCell className="text-right"><Button asChild size="sm" variant="secondary"><a href={letter.printUrl} target="_blank"><Printer className="size-4" />Cetak</a></Button></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada surat medis." /></TableCell></TableRow>}</TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle>Rujukan internal</CardTitle><CardDescription>Rujukan antarpoliklinik yang telah diterbitkan.</CardDescription></CardHeader>
                            <CardContent className="overflow-x-auto">
                                <Table><TableHeader><tr><TableHead>Nomor / tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Tujuan</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                                    <TableBody>{referrals.length ? referrals.map((referral) => <TableRow key={referral.id}><TableCell><span className="font-mono text-sm">{referral.number ?? '—'}</span><p className="mt-1 text-xs text-neutral-500">{referral.date ?? '—'}</p></TableCell><TableCell><span className="font-medium">{referral.patient}</span><p className="text-xs text-neutral-500">{referral.visitNumber}</p></TableCell><TableCell>{referral.fromClinic} → {referral.toClinic}<p className="text-xs text-neutral-500">{referral.doctor}</p></TableCell><TableCell><Badge variant={referral.status === 'selesai' ? 'complete' : 'waiting'}>{referral.status}</Badge></TableCell><TableCell className="text-right"><Button asChild size="sm" variant="secondary"><a href={referral.printUrl} target="_blank"><Printer className="size-4" />Cetak</a></Button></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada rujukan internal." /></TableCell></TableRow>}</TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {isAdmin && <TabsContent className="space-y-5" value="template">
                        <Card>
                            <CardHeader><CardTitle>{editingId ? 'Ubah template' : 'Tambah template'}</CardTitle><CardDescription>Kelola bentuk dokumen dan nomor urut. Nomor diberikan atomik saat dokter menerbitkan surat.</CardDescription></CardHeader>
                            <CardContent>
                                <form className="space-y-5" onSubmit={saveTemplate}>
                                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                        <Field error={form.errors.kode} htmlFor="template-kode" label="Kode" required><Input id="template-kode" maxLength={60} onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field>
                                        <Field error={form.errors.nama} htmlFor="template-nama" label="Nama template" required><Input id="template-nama" onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field>
                                        <Field error={form.errors.jenis} htmlFor="template-jenis" label="Jenis dokumen" required><Select id="template-jenis" onChange={(event) => form.setData('jenis', event.target.value)} value={form.data.jenis}>{documentTypes.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>
                                        <Field error={form.errors.prefix} htmlFor="template-prefix" label="Prefix nomor" required><Input id="template-prefix" onChange={(event) => form.setData('prefix', event.target.value)} required value={form.data.prefix} /></Field>
                                        <Field error={form.errors.format_nomor} htmlFor="template-format" label="Format nomor" required><Input id="template-format" onChange={(event) => form.setData('format_nomor', event.target.value)} required value={form.data.format_nomor} /></Field>
                                        <Field error={form.errors.nomor_berikutnya} htmlFor="template-next-number" label="Nomor berikutnya" required><Input id="template-next-number" min="1" onChange={(event) => form.setData('nomor_berikutnya', event.target.value)} required type="number" value={form.data.nomor_berikutnya} /></Field>
                                        <Field error={form.errors.is_active} htmlFor="template-active" label="Status"><Select id="template-active" onChange={(event) => form.setData('is_active', event.target.value === 'true')} value={form.data.is_active ? 'true' : 'false'}><option value="true">Aktif</option><option value="false">Nonaktif</option></Select></Field>
                                    </div>
                                    <p className="text-xs text-neutral-500">Token nomor: [prefix], [urut], [bulan], [tahun]. Token isi surat mengikuti konteks dokumen seperti [pasien], [no_rm], [dokter], [nomor], [tanggal], [poli], dan [poli_tujuan].</p>
                                    <Field error={form.errors.isi} htmlFor="template-isi" label="Isi template" required><Textarea id="template-isi" onChange={(event) => form.setData('isi', event.target.value)} required rows={8} value={form.data.isi} /></Field>
                                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">{editingId && <Button onClick={resetForm} type="button" variant="secondary">Batal ubah</Button>}<Button disabled={form.processing} type="submit">{editingId ? <Save className="size-4" /> : <Plus className="size-4" />}{editingId ? 'Simpan perubahan' : 'Simpan template'}</Button></div>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle>Template tersedia</CardTitle><CardDescription>{templates.length} template surat dan penomoran.</CardDescription></CardHeader>
                            <CardContent className="overflow-x-auto">
                                <Table><TableHeader><tr><TableHead>Kode / nama</TableHead><TableHead>Jenis</TableHead><TableHead>Format</TableHead><TableHead>Nomor berikutnya</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                                    <TableBody>{templates.length ? templates.map((template) => <TableRow key={template.id}><TableCell><span className="font-mono text-xs">{template.code}</span><p className="font-medium">{template.name}</p></TableCell><TableCell>{documentTypes.find(([value]) => value === template.type)?.[1] ?? template.type}</TableCell><TableCell className="font-mono text-xs">{template.numberFormat}</TableCell><TableCell>{template.nextNumber}</TableCell><TableCell><Badge variant={template.active ? 'complete' : 'cancelled'}>{template.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell><TableCell className="text-right"><div className="inline-flex gap-1"><Button aria-label={`Ubah ${template.name}`} onClick={() => startEdit(template)} size="sm" type="button" variant="ghost"><Pencil className="size-4" /></Button><Button aria-label={`Hapus ${template.name}`} disabled={template.inUse} onClick={() => removeTemplate(template)} size="sm" title={template.inUse ? 'Template sudah digunakan; nonaktifkan jika tidak dipakai lagi.' : undefined} type="button" variant="ghost"><Trash2 className="size-4 text-red-600" /></Button></div></TableCell></TableRow>) : <TableRow><TableCell colSpan={6}><Empty size="compact" title="Belum ada template persuratan." /></TableCell></TableRow>}</TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>}
                </Tabs>
            </div>
        </>
    );
}
