import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { FormEvent } from 'react';

interface ReleaseEvent { id: number; requestId: string; version: number; state: string; at: string; data: { requester: string; recipient: string; scope: string; purpose: string; identity_verification: string; authority: string; reason?: string; delivery?: { receipt: string } } }
interface Props { visit: { id: number; number: string; patient: string }; canAuthorize: boolean; documents: { id: number; jenis: string; nomor_surat: string | null }[]; events: { data: ReleaseEvent[]; links: { url: string | null; label: string }[] } }
const labels: Record<string, string> = { requested: 'Diajukan', authorized: 'Diotorisasi', denied: 'Ditolak', revoked: 'Dicabut', delivered: 'Diserahkan' };

export default function InformationRelease({ visit, canAuthorize, documents, events }: Props) {
    const form = useForm({ request_id: crypto.randomUUID() as string, base_version: 0, action: 'requested', requester: '', relationship: 'patient', identity_verification: '', authority: '', recipient: '', purpose: '', scope: 'ringkasan', reason: '', document_id: '', delivery_method: 'hand', receipt: '', recipient_verified: false, signature_password: '' });
    const field = (key: 'requester' | 'identity_verification' | 'authority' | 'recipient' | 'purpose' | 'reason' | 'receipt', label: string) => <Field label={label} htmlFor={`release-${key}`} error={form.errors[key]} required><Textarea id={`release-${key}`} value={form.data[key]} onChange={(event) => form.setData(key, event.target.value)} required maxLength={key === 'authority' || key === 'reason' ? 2000 : 1000} /></Field>;
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/pelepasan-informasi/${visit.id}`, { preserveScroll: true, onFinish: () => form.setData('signature_password', '') });
    }
    function choose(event: ReleaseEvent) {
        form.setData((data) => ({ ...data, request_id: event.requestId, base_version: event.version, action: event.state === 'requested' ? (canAuthorize ? 'authorized' : 'requested') : 'delivered', scope: event.data.scope, document_id: '', reason: '', receipt: '', recipient_verified: false, signature_password: '' }));
    }
    return <>
        <Head title="Pelepasan informasi medis" />
        <div className="mx-auto max-w-5xl space-y-6">
            <header><h1 className="text-2xl font-semibold">Pelepasan informasi medis</h1><p className="mt-1 text-sm text-neutral-500">{visit.patient} · {visit.number}</p></header>
            <Card><CardHeader><CardTitle>Register permintaan</CardTitle><CardDescription>Verifikasi kewenangan pemohon dan cakupan minimum. General consent tidak menggantikan otorisasi pelepasan ini.</CardDescription></CardHeader><CardContent className="space-y-4">
                <Button variant="secondary" onClick={() => form.setData((data) => ({ ...data, request_id: crypto.randomUUID(), base_version: 0, action: 'requested', signature_password: '' }))}>Permintaan baru</Button>
                {events.data.map((event) => <div key={event.id} className="space-y-2 border-t border-neutral-200 py-3 dark:border-neutral-800"><p className="text-sm font-medium">{labels[event.state]} · {event.at} · Versi {event.version}</p><p className="text-sm text-neutral-500">{event.data.requester} → {event.data.recipient} · {event.data.scope}</p><p className="text-sm">{event.data.purpose}</p><details className="text-sm"><summary className="cursor-pointer">Dasar dan bukti verifikasi</summary><div className="mt-2 space-y-2"><p>Identitas: {event.data.identity_verification}</p><p>Kewenangan: {event.data.authority}</p>{event.data.reason && <p>Catatan: {event.data.reason}</p>}{event.data.delivery && <p>Tanda terima: {event.data.delivery.receipt}</p>}</div></details>{((event.state === 'requested' && canAuthorize) || event.state === 'authorized') && <Button variant="secondary" onClick={() => choose(event)}>Tindak lanjuti</Button>}</div>)}
                <div className="flex gap-3">{events.links.map((link, index) => link.url ? <Link key={index} href={link.url} className="text-sm underline">{link.label.replace(/&laquo;|&raquo;/g, '')}</Link> : null)}</div>
            </CardContent></Card>
            <Card><CardHeader><CardTitle>{form.data.base_version === 0 ? 'Permintaan baru' : `Tindak lanjut versi ${form.data.base_version}`}</CardTitle></CardHeader><CardContent>
                <form onSubmit={submit} className="space-y-4">
                    {form.errors.base_version && <p role="alert" className="text-sm text-red-600">{form.errors.base_version}</p>}
                    {form.data.base_version === 0 ? <>
                        {field('requester', 'Nama pemohon')}
                        <Field label="Kedudukan pemohon" htmlFor="release-relationship" error={form.errors.relationship}><Select id="release-relationship" value={form.data.relationship} onChange={(event) => form.setData('relationship', event.target.value)}><option value="patient">Pasien sendiri</option><option value="representative">Wakil yang sah</option><option value="institution">Institusi berwenang</option></Select></Field>
                        {field('identity_verification', 'Hasil verifikasi identitas (hindari nomor identitas lengkap)')}{field('authority', 'Dasar persetujuan / kewenangan dan referensi bukti')}{field('recipient', 'Penerima yang dituju')}{field('purpose', 'Tujuan permintaan')}
                        <Field label="Cakupan dokumen" htmlFor="release-scope" error={form.errors.scope}><Select id="release-scope" value={form.data.scope} onChange={(event) => form.setData('scope', event.target.value)}><option value="ringkasan">Resume medis</option><option value="rujukan">Surat rujukan</option><option value="sakit">Surat sakit</option><option value="sehat">Surat sehat</option></Select></Field>
                    </> : <>
                        <Field label="Keputusan" htmlFor="release-action" error={form.errors.action}><Select id="release-action" value={form.data.action} onChange={(event) => form.setData('action', event.target.value)}>{form.data.action === 'authorized' || form.data.action === 'denied' ? <><option value="authorized">Otorisasi</option><option value="denied">Tolak</option></> : <><option value="delivered">Catat penyerahan</option>{canAuthorize && <option value="revoked">Cabut otorisasi</option>}</>}</Select></Field>
                        {form.data.action === 'authorized' && <Field label="Dokumen yang diotorisasi" htmlFor="release-document" error={form.errors.document_id} required><Select id="release-document" value={form.data.document_id} onChange={(event) => form.setData('document_id', event.target.value)}><option value="">Pilih dokumen</option>{documents.filter((doc) => doc.jenis === form.data.scope).map((doc) => <option key={doc.id} value={doc.id}>{doc.nomor_surat ?? `Dokumen #${doc.id}`}</option>)}</Select></Field>}
                        {field('reason', 'Alasan / catatan keputusan')}
                        {form.data.action === 'delivered' && <>
                            <Field label="Metode penyerahan" htmlFor="release-method" error={form.errors.delivery_method}><Select id="release-method" value={form.data.delivery_method} onChange={(event) => form.setData('delivery_method', event.target.value)}><option value="hand">Diserahkan langsung</option><option value="secure_channel">Saluran aman yang disetujui klinik</option></Select></Field>
                            {field('receipt', 'Referensi bukti penerimaan / tanda terima yang diarsipkan')}
                            <Field label="Identitas penerima sesuai otorisasi telah diperiksa" htmlFor="release-recipient-verified" error={form.errors.recipient_verified}><input id="release-recipient-verified" type="checkbox" checked={form.data.recipient_verified} onChange={(event) => form.setData('recipient_verified', event.target.checked)} /></Field>
                        </>}
                        <Field label="Kata sandi petugas" htmlFor="release-password" error={form.errors.signature_password} required><Input id="release-password" type="password" autoComplete="current-password" value={form.data.signature_password} onChange={(event) => form.setData('signature_password', event.target.value)} /></Field>
                    </>}
                    <Button type="submit" disabled={form.processing}>Simpan register</Button>
                </form>
            </CardContent></Card>
        </div>
    </>;
}
