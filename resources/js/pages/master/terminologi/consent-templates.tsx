import { StatCard } from '@/components/dashboard/stat-card';
import { Archive, ClipboardCheck, FileText } from 'lucide-react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useState, type FormEvent } from 'react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

const url = '/master/template-persetujuan';
const labels = {
    indication: 'Diagnosis dan indikasi',
    method: 'Cara dan tahapan',
    benefits: 'Tujuan dan manfaat',
    risks: 'Risiko dan komplikasi',
    alternatives: 'Alternatif dan risikonya',
    without_procedure: 'Konsekuensi bila tidak dilakukan',
    prognosis: 'Prognosis',
};
const explanationPrompts: Record<keyof typeof labels, string> = {
    indication: 'Isi indikasi sesuai SOP atau PPK yang berlaku.',
    method: 'Uraikan cara dan tahapan yang dijelaskan kepada pasien.',
    benefits: 'Cantumkan tujuan tindakan dan manfaat yang diharapkan.',
    risks: 'Cantumkan risiko dan komplikasi yang relevan.',
    alternatives: 'Cantumkan pilihan lain dan risiko masing-masing.',
    without_procedure: 'Jelaskan konsekuensi bila tindakan tidak dilakukan.',
    prognosis: 'Cantumkan prognosis sesuai rujukan klinis.',
};
type Explanation = Partial<Record<keyof typeof labels, string | null>>;
type TemplateData = {
    code: string; release: string; clinic_type: string; version: string; mode: string;
    trigger_condition: string | null; sop_reference: string | null; explanation: Explanation | null;
};
type Template = TemplateData & { id: number; published_at: string | null; deleted_at: string | null; active: boolean };
const modes: Record<string, string> = { oral: 'Lisan', written: 'Tertulis', conditional: 'Kondisional' };

export default function ConsentTemplates({ templates, releases }: { templates: Template[]; releases: string[] }) {
    const [editorOpen, setEditorOpen] = useState(false);
    const [selected, setSelected] = useState<Template | null>(null);
    const [archiveFilter, setArchiveFilter] = useState(false);
    const [editing, setEditing] = useState<number | null>(null);
    const [publishing, setPublishing] = useState<number | null>(null);
    const [approved, setApproved] = useState(false);
    const form = useForm<TemplateData & { approved: boolean }>({
        code: '', release: releases.length === 1 ? releases[0] : '', clinic_type: '', version: '', mode: '',
        trigger_condition: '', sop_reference: '', explanation: {}, approved: false,
    });
    const editingPublished = templates.some((item) => item.id === editing && item.published_at);
    const errors = usePage<{ errors: Record<string, string> }>().props.errors;

    function submit(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => { form.reset(); setEditing(null); setEditorOpen(false); } };
        if (editing) form.put(`${url}/${editing}`, options);
        else form.post(url, options);
    }

    return <>
        <Head title="SOP persetujuan tindakan" />
        <div className="space-y-5">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-xl font-semibold">SOP persetujuan tindakan</h2>
                <Button onClick={() => { setEditing(null); form.reset(); form.clearErrors(); setEditorOpen(true); }}>Tambah template</Button>
            </div>
            <section aria-label="Ringkasan template SOP" className="grid gap-4 sm:grid-cols-3">
                <StatCard label="Template aktif" value={templates.filter((item) => item.active && !item.deleted_at).length} description="Tersedia untuk pelayanan" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Draf" value={templates.filter((item) => !item.published_at && !item.deleted_at).length} description="Menunggu peninjauan dan penerbitan" icon={<FileText className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Arsip" value={templates.filter((item) => item.deleted_at).length} description="Template yang diarsipkan" icon={<Archive className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
            </section>
            <Card>
                <CardHeader>
                    <CardTitle>Versi template</CardTitle>
                    <CardDescription>Terbitkan setelah materi dan klasifikasi disahkan Penanggung Jawab Klinik.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                    <div className="flex gap-2">
                        <Button size="sm" variant={archiveFilter ? 'secondary' : 'default'} onClick={() => setArchiveFilter(false)}>Template</Button>
                        <Button size="sm" variant={archiveFilter ? 'default' : 'secondary'} onClick={() => setArchiveFilter(true)}>Arsip</Button>
                    </div>
                    <div className="overflow-x-auto rounded-lg border border-neutral-200">
                        <Table className="min-w-[48rem]">
                            <TableHeader><TableRow><TableHead>Kode dan rilis</TableHead><TableHead>Poli</TableHead><TableHead>Versi / SOP</TableHead><TableHead>Bentuk</TableHead><TableHead>Status</TableHead><TableHead>Aksi</TableHead></TableRow></TableHeader>
                            <TableBody>
                                {templates.filter((item) => Boolean(item.deleted_at) === archiveFilter).map((item) => <TableRow key={item.id}>
                                    <TableCell><strong>{item.code}</strong><p className="text-xs text-neutral-600">{item.release}</p></TableCell>
                                    <TableCell className="capitalize">{item.clinic_type}</TableCell>
                                    <TableCell>{item.version}<p className="text-xs text-neutral-600">{item.sop_reference}</p></TableCell>
                                    <TableCell>{modes[item.mode]}</TableCell>
                                    <TableCell><Badge variant={item.deleted_at || (item.published_at && !item.active) ? 'cancelled' : item.active ? 'complete' : 'warning'}>{item.deleted_at ? 'Arsip' : item.active ? 'Aktif' : item.published_at ? 'Versi sebelumnya' : 'Draf'}</Badge></TableCell>
                                    <TableCell><div className="flex flex-wrap gap-2">
                                        <Button size="sm" variant="secondary" onClick={() => setSelected(item)}>Detail</Button>
                                        {item.deleted_at ? <Button size="sm" variant="secondary" onClick={() => router.post(`${url}/${item.id}/pulihkan`, {}, { preserveScroll: true })}>Pulihkan</Button> : <>
                                            <Button size="sm" variant="secondary" onClick={() => {
                                                setEditing(item.id);
                                                const { id, published_at, deleted_at, active, ...data } = item;
                                                form.setData({ ...data, approved: false });
                                                form.clearErrors();
                                                setEditorOpen(true);
                                            }}>Edit</Button>
                                            {!item.published_at && <Button size="sm" variant="secondary" onClick={() => { setPublishing(item.id); setApproved(false); }}>Terbitkan</Button>}
                                            <Button size="sm" variant="secondary" onClick={() => confirmAction(
                                                'Arsipkan template ini dari pilihan dokter?', () => router.delete(`${url}/${item.id}`, { preserveScroll: true }), 'Arsipkan',
                                            )}>Arsipkan</Button>
                                        </>}
                                    </div></TableCell>
                                </TableRow>)}
                                {!templates.some((item) => Boolean(item.deleted_at) === archiveFilter) && <TableRow><TableCell colSpan={6} className="py-6 text-center text-neutral-600">Belum ada template pada daftar ini.</TableCell></TableRow>}
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
            <Dialog open={publishing !== null} onOpenChange={(open) => { if (!open) setPublishing(null); }}>
                <DialogContent>
                    <DialogHeader><DialogTitle>Terbitkan template</DialogTitle><DialogDescription>Konfirmasi bahwa Penanggung Jawab Klinik sudah mengesahkan isinya.</DialogDescription></DialogHeader>
                    <label className="flex items-start gap-2 text-sm"><Checkbox checked={approved} onCheckedChange={(value) => setApproved(value === true)} /><span>Saya telah memeriksa pengesahan SOP untuk versi ini.</span></label>
                    {errors.approved && <Alert variant="destructive"><AlertDescription>{errors.approved}</AlertDescription></Alert>}
                    <div className="flex justify-end gap-2">
                        <Button size="sm" variant="secondary" onClick={() => setPublishing(null)}>Batal</Button>
                        <Button size="sm" disabled={!approved} onClick={() => router.post(`${url}/${publishing}/terbitkan`, { approved }, { preserveScroll: true, onSuccess: () => setPublishing(null) })}>Terbitkan versi</Button>
                    </div>
                </DialogContent>
            </Dialog>
            <Dialog open={selected !== null} onOpenChange={(open) => { if (!open) setSelected(null); }}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader><DialogTitle>Detail SOP persetujuan</DialogTitle><DialogDescription>{selected?.code} · {selected?.version} · {selected?.sop_reference ?? 'Referensi belum diisi'}</DialogDescription></DialogHeader>
                    {selected?.trigger_condition && <p className="whitespace-pre-wrap text-sm">{selected.trigger_condition}</p>}
                    <dl className="grid gap-3 sm:grid-cols-2">
                        {(Object.entries(labels) as [keyof Explanation, string][]).map(([key, label]) => <div key={key} className="rounded-lg border border-neutral-200 p-3"><dt className="text-sm font-semibold">{label}</dt><dd className="mt-1 break-words whitespace-pre-wrap text-sm text-neutral-600">{selected?.explanation?.[key] || 'Belum diisi'}</dd></div>)}
                    </dl>
                </DialogContent>
            </Dialog>
            <Dialog open={editorOpen} onOpenChange={setEditorOpen}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
                    <DialogHeader><DialogTitle>{editing ? 'Edit template' : 'Tambah template'}</DialogTitle><DialogDescription>Gunakan kerangka draf ini untuk menyalin materi dari SOP atau PPK yang berlaku.</DialogDescription></DialogHeader>
                    <form className="space-y-4" onSubmit={submit}>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Field label="Kode ICD-9-CM" htmlFor="template-code" error={form.errors.code}><Input id="template-code" disabled={editingPublished} required value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} /></Field>
                            <Field label="Rilis katalog" htmlFor="template-release" error={form.errors.release}><Select id="template-release" disabled={editingPublished} required value={form.data.release} onChange={(e) => form.setData('release', e.target.value)}><option value="">Pilih rilis</option>{releases.map((release) => <option key={release} value={release}>{release}</option>)}</Select></Field>
                            <Field label="Poli" htmlFor="template-clinic" error={form.errors.clinic_type}><Select id="template-clinic" disabled={editingPublished} required value={form.data.clinic_type} onChange={(e) => form.setData('clinic_type', e.target.value)}><option value="">Pilih poli</option><option value="umum">Umum</option><option value="gigi">Gigi</option></Select></Field>
                            <Field label="Versi" htmlFor="template-version" error={form.errors.version}><Input id="template-version" required maxLength={40} value={form.data.version} onChange={(e) => form.setData('version', e.target.value)} /></Field>
                            <Field label="Bentuk persetujuan" htmlFor="template-mode" error={form.errors.mode}><Select id="template-mode" required value={form.data.mode} onChange={(e) => form.setData('mode', e.target.value)}><option value="">Pilih bentuk</option>{Object.entries(modes).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</Select></Field>
                            <Field label="Referensi SOP / PPK" htmlFor="template-sop" error={form.errors.sop_reference}><Input id="template-sop" maxLength={255} value={form.data.sop_reference ?? ''} onChange={(e) => form.setData('sop_reference', e.target.value)} /></Field>
                        </div>
                        {form.data.mode === 'conditional' && <Field label="Kondisi yang memerlukan persetujuan tertulis" htmlFor="template-condition" error={form.errors.trigger_condition}><Textarea id="template-condition" maxLength={2000} rows={2} value={form.data.trigger_condition ?? ''} onChange={(e) => form.setData('trigger_condition', e.target.value)} /></Field>}
                        <div className="grid gap-4 sm:grid-cols-2">
                            {(Object.entries(labels) as [keyof Explanation, string][]).map(([key, label]) => <Field key={key} label={label} htmlFor={`template-${key}`} error={(form.errors as Record<string, string>)[`explanation.${key}`]}><Textarea id={`template-${key}`} placeholder={explanationPrompts[key]} maxLength={3000} rows={3} value={form.data.explanation?.[key] ?? ''} onChange={(e) => form.setData('explanation', { ...form.data.explanation, [key]: e.target.value })} /></Field>)}
                        </div>
                        {editingPublished && <Field label="Pengesahan perubahan" htmlFor="template-approved" error={form.errors.approved}>
                            <label className="flex items-start gap-2 text-sm"><Checkbox id="template-approved" checked={form.data.approved} onCheckedChange={(value) => form.setData('approved', value === true)} /><span>Perubahan sudah disahkan Penanggung Jawab Klinik.</span></label>
                        </Field>}
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="secondary" onClick={() => setEditorOpen(false)}>Batal</Button>
                            <Button type="submit" disabled={form.processing || (editingPublished && !form.data.approved)}>{editingPublished ? 'Simpan perubahan' : 'Simpan draf'}</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </>;
}
