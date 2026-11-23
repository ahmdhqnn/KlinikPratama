import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Download } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export interface ConsentSummary { status: string; currentId: number | null; recordedAt: string | null; versionCurrent: boolean; required: boolean; url: string }
export const consentLabels: Record<string, string> = { undocumented: 'Belum terdokumentasi', accepted: 'Disetujui', refused: 'Ditolak setelah penjelasan', withdrawn: 'Persetujuan dicabut', invalid: 'Integritas catatan perlu diperiksa' };

interface Props {
    visit: { id: number; number: string; patientName: string; medicalRecordNumber: string };
    summary: ConsentSummary; text: string; textVersion: string; canWrite: boolean; storeUrl: string; requestId: string; backUrl: string; releaseUrl?: string | null;
    history: { id: number; decision: string; version: string; signer: string; relationship: string; reason: string | null; at: string; evidenceUrl: string | null; verificationUrl?: string | null }[];
}

export default function GeneralConsent({ visit, summary, text, textVersion, canWrite, storeUrl, requestId, backUrl, history, releaseUrl }: Props) {
    const [withdrawalFor, setWithdrawalFor] = useState<number | null>(null);
    const approved = summary.status === 'accepted' && summary.versionCurrent;
    const withdrawing = approved && withdrawalFor !== null && withdrawalFor === summary.currentId;
    const currentProof = history.find((item) => item.id === summary.currentId);
    const form = useForm({ request_id: requestId, current_id: summary.currentId, text_version: textVersion, decision: '', signer_name: '', signer_relationship: 'patient', representative_verification: '', reason: '', explained: false, in_person: true });
    useEffect(() => {
        form.setData({ request_id: requestId, current_id: summary.currentId, text_version: textVersion, decision: '', signer_name: '', signer_relationship: 'patient', representative_verification: '', reason: '', explained: false, in_person: true });
        setWithdrawalFor(null);
    }, [summary.currentId, visit.id]);

    return <>
        <Head title="Persetujuan umum" />
        {releaseUrl && <Button asChild variant="secondary"><Link href={releaseUrl}>Register pelepasan informasi</Link></Button>}
        <div className="space-y-6">
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-neutral-700">Administrasi pelayanan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Persetujuan umum</h2><p className="mt-1 text-sm text-neutral-500">{visit.patientName} · {visit.medicalRecordNumber} · {visit.number}</p></div><Button asChild variant="secondary"><Link href={backUrl}><ArrowLeft className="size-4" />Kembali</Link></Button></div>
            <Alert><AlertTitle>{consentLabels[summary.status] ?? summary.status}</AlertTitle><AlertDescription>{summary.recordedAt ? `Tercatat ${summary.recordedAt}.` : 'Belum ada bukti keputusan pasien. Data lama tidak dianggap otomatis setuju.'} {!summary.versionCurrent && summary.currentId && 'Versi penjelasan perlu diperbarui.'} Persetujuan tindakan dan pelepasan informasi dicatat tersendiri.</AlertDescription></Alert>
            {approved && <Card>
                <CardHeader><CardTitle>Bukti persetujuan</CardTitle><CardDescription>Keputusan sudah tercatat untuk versi penjelasan yang berlaku.</CardDescription></CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                    {currentProof?.verificationUrl && <Button asChild><a href={currentProof.verificationUrl}>Lihat / cetak bukti verifikasi</a></Button>}
                    {currentProof?.evidenceUrl && <Button asChild><a href={currentProof.evidenceUrl}><Download className="size-4" />Unduh bukti</a></Button>}
                    {canWrite && !withdrawing && <Button variant="secondary" onClick={() => { form.clearErrors(); form.setData({ request_id: requestId, current_id: summary.currentId, text_version: textVersion, decision: 'withdrawn', signer_name: '', signer_relationship: 'patient', representative_verification: '', reason: '', explained: false, in_person: true }); setWithdrawalFor(summary.currentId); }}>Catat pencabutan</Button>}
                </CardContent>
            </Card>}
            {(!approved || withdrawing) && <Card><CardHeader><CardTitle>Penjelasan persetujuan umum</CardTitle><CardDescription>Versi {textVersion}. Bacakan atau berikan kesempatan pasien membaca dan bertanya.</CardDescription></CardHeader><CardContent><p className="whitespace-pre-wrap text-sm leading-7 text-neutral-700">{text}</p></CardContent></Card>}
            {canWrite && (!approved || withdrawing) && <Card><CardHeader><CardTitle>{withdrawing ? 'Catat pencabutan persetujuan' : 'Catat keputusan pasien'}</CardTitle><CardDescription>Catat keputusan yang disampaikan langsung oleh pasien atau wakilnya. Akun petugas dan waktu konfirmasi tersimpan otomatis.</CardDescription></CardHeader><CardContent>
                <form className="space-y-5" onSubmit={(event) => { event.preventDefault(); form.post(storeUrl, { preserveScroll: true }); }}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {!withdrawing && <Field label="Keputusan" htmlFor="consent-decision" error={form.errors.decision} required><Select id="consent-decision" value={form.data.decision} onChange={(event) => form.setData('decision', event.target.value)} required><option value="">Pilih keputusan</option><option value="accepted">Setuju</option><option value="refused">Menolak setelah penjelasan</option>{summary.status === 'accepted' && <option value="withdrawn">Mencabut persetujuan</option>}</Select></Field>}
                        {form.data.signer_relationship !== 'patient' && <Field label="Nama wakil" htmlFor="consent-signer" error={form.errors.signer_name} required><Input id="consent-signer" required maxLength={150} value={form.data.signer_name} onChange={(event) => form.setData('signer_name', event.target.value)} /></Field>}
                        <Field label="Hubungan dengan pasien" htmlFor="consent-relationship" error={form.errors.signer_relationship} required><Select id="consent-relationship" value={form.data.signer_relationship} onChange={(event) => { form.setData('signer_relationship', event.target.value); form.setData('representative_verification', ''); }}><option value="patient">Pasien sendiri</option><option value="parent">Orang tua</option><option value="spouse">Suami / istri</option><option value="guardian">Wali / pengampu</option><option value="other">Wakil lain yang sah</option></Select></Field>
                        {form.data.signer_relationship !== 'patient' && <Field label="Dasar kewenangan dan verifikasi wakil" htmlFor="consent-representative" error={form.errors.representative_verification} required><Textarea id="consent-representative" required minLength={10} maxLength={1000} value={form.data.representative_verification} onChange={(event) => form.setData('representative_verification', event.target.value)} placeholder="Catat dokumen/dasar kewenangan yang diperiksa; hindari menyalin nomor identitas lengkap." /></Field>}
                    </div>
                    <Field label="Alasan / catatan keputusan" htmlFor="consent-reason" error={form.errors.reason} required={form.data.decision !== 'accepted'}><Textarea id="consent-reason" required={form.data.decision !== 'accepted'} minLength={10} maxLength={2000} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></Field>

                    <Field htmlFor="consent-explained" label="Konfirmasi penjelasan" error={form.errors.explained} required><label className="flex items-start gap-3 text-sm leading-6 text-neutral-700"><Checkbox id="consent-explained" checked={form.data.explained} onCheckedChange={(value) => form.setData('explained', value === true)} /><span>Saya telah memberi penjelasan, memeriksa identitas, dan menerima langsung keputusan yang dipilih di atas.</span></label></Field>
                    {(['current_id', 'request_id', 'text_version'] as const).map((key) => form.errors[key] && <p key={key} role="alert" className="text-sm text-red-600">{form.errors[key]}</p>)}
                    {withdrawing && <Button type="button" variant="secondary" className="mr-2" disabled={form.processing} onClick={() => { form.reset(); form.clearErrors(); setWithdrawalFor(null); }}>Batal</Button>}
                    <Button type="submit" disabled={form.processing || !form.data.explained || !form.data.decision}>{form.processing ? 'Menyimpan…' : withdrawing ? 'Konfirmasi pencabutan' : 'Konfirmasi keputusan pasien'}</Button>
                </form>
            </CardContent></Card>}
            {!canWrite && !approved && <Alert><AlertDescription>Petugas Pendaftaran atau Admin yang mencatat keputusan pasien.</AlertDescription></Alert>}
            <Card><CardHeader><CardTitle>Riwayat keputusan</CardTitle><CardDescription>Bukti tersimpan menurut waktu server. Riwayat tidak dapat ditimpa atau dihapus.</CardDescription></CardHeader><CardContent>{history.length ? <ul className="divide-y divide-neutral-100">{history.map((item) => <li key={item.id} className="flex flex-col justify-between gap-3 py-4 sm:flex-row"><div className="space-y-1"><p className="text-sm font-semibold text-neutral-900">{consentLabels[item.decision]} · {item.at}</p><p className="text-sm text-neutral-600">{item.signer} · {item.relationship} · {item.version}</p>{item.reason && <p className="whitespace-pre-wrap text-sm text-neutral-600">{item.reason}</p>}</div>{item.verificationUrl && <Button asChild variant="secondary" size="sm"><a href={item.verificationUrl}>Lihat / cetak bukti verifikasi</a></Button>}{item.evidenceUrl && <Button asChild variant="secondary" size="sm"><a href={item.evidenceUrl}><Download className="size-4" />Unduh bukti</a></Button>}</li>)}</ul> : <p className="text-sm text-neutral-500">Belum ada keputusan yang tercatat.</p>}</CardContent></Card>
        </div>
    </>;
}
