import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { FormActionFooter } from '@/components/ui/form-action-footer';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { SharedProps } from '@/types';
import { FormEvent } from 'react';

interface ReleaseEvent { id: number; requestId: string; version: number; isCurrent: boolean; state: string; at: string; data: { requester: string; recipient: string; scope: string; purpose: string; identity_verification: string; authority: string; reason?: string; delivery?: { receipt: string } } }
interface Props { visit: { id: number; number: string; patient: string }; canAuthorize: boolean; profileUrl: string; newRequestId: string; documents: { id: number; jenis: string; nomor_surat: string | null }[]; events: { data: ReleaseEvent[]; links: { url: string | null; label: string }[] } }
const labels: Record<string, string> = { requested: 'Diajukan', authorized: 'Diotorisasi', denied: 'Ditolak', revoked: 'Dicabut', delivered: 'Diserahkan' };
const scopeLabels: Record<string, string> = {
    ringkasan: 'Resume medis',
    rujukan: 'Surat rujukan',
    sakit: 'Surat sakit',
    sehat: 'Surat sehat',
    laboratorium: 'Hasil laboratorium',
    rontgen_gigi: 'Hasil rontgen gigi',
    salinan_rme: 'Salinan rekam medis elektronik',
    lainnya: 'Dokumen medis lainnya',
};

export default function InformationRelease({ visit, canAuthorize, profileUrl, newRequestId, documents, events }: Props) {
    const userRole = usePage<SharedProps>().props?.auth?.user?.role;
    const credentialLabel = userRole === 'dokter' ? 'PIN pengesahan klinis' : 'Kata sandi akun petugas';
    const initial = { request_id: newRequestId, base_version: 0, action: 'requested', requester: '', relationship: 'patient', identity_verification: '', authority: '', recipient: '', purpose: '', scope: 'ringkasan', reason: '', document_id: '', delivery_method: 'hand', receipt: '', recipient_verified: false, signature_password: '' };
    const form = useForm(initial);
    const field = (key: 'requester' | 'identity_verification' | 'authority' | 'recipient' | 'purpose' | 'reason' | 'receipt', label: string) => <Field label={label} htmlFor={`release-${key}`} error={form.errors[key]} required><Textarea id={`release-${key}`} value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)} required maxLength={key === 'authority' || key === 'reason' ? 2000 : 1000} /></Field>;
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/pelepasan-informasi/${visit.id}`, { preserveScroll: true, onSuccess: (page) => form.setData({ ...initial, request_id: page.props.newRequestId as string }), onFinish: () => form.setData('signature_password', '') });
    }
    function choose(event: ReleaseEvent) {
        form.setData((data) => ({ ...data, request_id: event.requestId, base_version: event.version, action: event.state === 'requested' ? (canAuthorize ? 'authorized' : 'requested') : 'delivered', scope: event.data.scope, document_id: '', reason: '', receipt: '', recipient_verified: false, signature_password: '' }));
    }
    const matchingDocuments = documents.filter((doc) => doc.jenis === form.data.scope);
    const requiresDocumentSelection = ['ringkasan', 'rujukan', 'sakit', 'sehat'].includes(form.data.scope);

    return <>
        <Head title="Pelepasan informasi medis" />
        <div className="space-y-6">
            <PageHeaderWithActions
                backHref={profileUrl}
                backLabel="Profil pasien"
                eyebrow="Kepatuhan rekam medis"
                subtitle={`${visit.patient} · ${visit.number}`}
                title="Pelepasan informasi medis"
            />
            <Card><CardHeader><CardTitle>Register permintaan</CardTitle><CardDescription>Verifikasi kewenangan pemohon dan cakupan minimum. Persetujuan umum tidak menggantikan otorisasi pelepasan informasi medis ini.</CardDescription></CardHeader><CardContent className="space-y-4">
                <Button variant="secondary" onClick={() => form.setData(initial)}>Permintaan baru</Button>
                {events.data.map((event) => <div key={event.id} className="space-y-2 border-t border-neutral-200 py-3"><p className="text-sm font-medium">{labels[event.state]} · {event.at} · Versi {event.version}</p><p className="text-sm text-neutral-500">{event.data.requester} → {event.data.recipient} · {scopeLabels[event.data.scope] ?? event.data.scope}</p><p className="text-sm">{event.data.purpose}</p><details className="text-sm"><summary className="cursor-pointer">Dasar dan bukti verifikasi</summary><div className="mt-2 space-y-2"><p>Identitas: {event.data.identity_verification}</p><p>Kewenangan: {event.data.authority}</p>{event.data.reason && <p>Catatan: {event.data.reason}</p>}{event.data.delivery && <p>Tanda terima: {event.data.delivery.receipt}</p>}</div></details>{event.isCurrent && ((event.state === 'requested' && canAuthorize) || event.state === 'authorized') && <Button variant="secondary" onClick={() => choose(event)}>Tindak lanjuti</Button>}</div>)}
                <div className="flex gap-3">{events.links.map((link, index) => link.url ? <Link key={index} href={link.url} className="text-sm underline">{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</div>
            </CardContent></Card>
            <Card><CardHeader><CardTitle>{form.data.base_version === 0 ? 'Permintaan baru' : `Tindak lanjut versi ${form.data.base_version}`}</CardTitle></CardHeader><CardContent>
                <form onSubmit={submit} className="space-y-4">
                    {form.errors.action && <p role="alert" className="text-sm text-red-600">{form.errors.action}</p>}
                    {form.errors.base_version && <p role="alert" className="text-sm text-red-600">{form.errors.base_version}</p>}
                    {form.data.base_version === 0 ? <>
                        {field('requester', 'Nama pemohon')}
                        <Field label="Kedudukan pemohon" htmlFor="release-relationship" error={form.errors.relationship}><Select id="release-relationship" value={form.data.relationship} onChange={(event) => form.setData('relationship', event.target.value)}><option value="patient">Pasien sendiri</option><option value="representative">Wakil yang sah</option><option value="institution">Institusi berwenang</option></Select></Field>
                        {field('identity_verification', 'Hasil verifikasi identitas (hindari nomor identitas lengkap)')}{field('authority', 'Dasar persetujuan / kewenangan dan referensi bukti')}{field('recipient', 'Penerima yang dituju')}{field('purpose', 'Tujuan permintaan')}
                        <Field label="Cakupan dokumen" htmlFor="release-scope" error={form.errors.scope}><Select id="release-scope" value={form.data.scope} onChange={(event) => form.setData('scope', event.target.value)}>
                            {Object.entries(scopeLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </Select></Field>
                    </> : <>
                        <Field label="Keputusan" htmlFor="release-action" error={form.errors.action}><Select id="release-action" value={form.data.action} onChange={(event) => form.setData('action', event.target.value)}>{form.data.action === 'authorized' || form.data.action === 'denied' ? <><option value="authorized">Otorisasi</option><option value="denied">Tolak</option></> : <><option value="delivered">Catat penyerahan</option>{canAuthorize && <option value="revoked">Cabut otorisasi</option>}</>}</Select></Field>
                        {form.data.action === 'authorized' && requiresDocumentSelection && <Field label="Dokumen yang diotorisasi" htmlFor="release-document" error={form.errors.document_id} required><Select id="release-document" value={form.data.document_id} onChange={(event) => form.setData('document_id', event.target.value)}><option value="">Pilih dokumen</option>{matchingDocuments.map((doc) => <option key={doc.id} value={doc.id}>{doc.nomor_surat ?? `Dokumen #${doc.id}`}</option>)}</Select></Field>}
                        {field('reason', 'Alasan / catatan keputusan')}
                        {form.data.action === 'delivered' && <>
                            <Field label="Metode penyerahan" htmlFor="release-method" error={form.errors.delivery_method}><Select id="release-method" value={form.data.delivery_method} onChange={(event) => form.setData('delivery_method', event.target.value)}><option value="hand">Diserahkan langsung</option><option value="secure_channel">Saluran aman yang disetujui klinik</option></Select></Field>
                            {field('receipt', 'Referensi bukti penerimaan / tanda terima yang diarsipkan')}
                            <div className="space-y-1 rounded-xl border border-neutral-200 bg-neutral-50/60 p-3.5">
                                <label className="flex cursor-pointer items-start gap-2.5 text-sm leading-6 text-neutral-800" htmlFor="release-recipient-verified">
                                    <Checkbox id="release-recipient-verified" className="mt-1 shrink-0" checked={form.data.recipient_verified} onCheckedChange={(checked) => form.setData('recipient_verified', checked === true)} />
                                    <span>Identitas penerima sesuai otorisasi telah diperiksa</span>
                                </label>
                                {form.errors.recipient_verified && <p role="alert" className="text-xs text-red-600">{form.errors.recipient_verified}</p>}
                            </div>
                        </>}
                        <Field label={credentialLabel} htmlFor="release-password" error={form.errors.signature_password} required><Input id="release-password" type="password" autoComplete="current-password" value={form.data.signature_password} onChange={(event) => form.setData('signature_password', event.target.value)} /></Field>
                    </>}
                    <FormActionFooter>
                        <Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan register'}</Button>
                    </FormActionFooter>
                </form>
            </CardContent></Card>
        </div>
    </>;
}
