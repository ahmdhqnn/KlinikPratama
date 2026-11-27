import { Head, Link, useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Attachment } from '@/components/ui/attachment';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { FormActionFooter } from '@/components/ui/form-action-footer';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export interface ConsentSummary { status: string; satusehatSharing?: string; currentId: number | null; recordedAt: string | null; versionCurrent: boolean; required: boolean; url: string }
export const consentLabels: Record<string, string> = { undocumented: 'Belum terdokumentasi', accepted: 'Disetujui', refused: 'Ditolak setelah penjelasan', withdrawn: 'Persetujuan dicabut', invalid: 'Integritas catatan perlu diperiksa' };
const satusehatLabels: Record<string, string> = { opt_in: 'SATUSEHAT: Setuju berbagi (Opt-In)', opt_out: 'SATUSEHAT: Menolak berbagi (Opt-Out)', undocumented: 'SATUSEHAT: Belum ditetapkan' };

interface Props {
    visit: { id: number; number: string; patientName: string; medicalRecordNumber: string };
    summary: ConsentSummary; text: string; textVersion: string; canWrite: boolean; storeUrl: string; requestId: string; backUrl: string; releaseUrl?: string | null;
    history: { id: number; decision: string; satusehatSharing?: string; version: string; signer: string; relationship: string; reason: string | null; at: string; evidenceUrl: string | null; verificationUrl?: string | null }[];
}

export default function GeneralConsent({ visit, summary, text, textVersion, canWrite, storeUrl, requestId, backUrl, history, releaseUrl }: Props) {
    const [withdrawalFor, setWithdrawalFor] = useState<number | null>(null);
    const approved = summary.status === 'accepted' && summary.versionCurrent;
    const withdrawing = approved && withdrawalFor !== null && withdrawalFor === summary.currentId;
    const currentProof = history.find((item) => item.id === summary.currentId);
    const form = useForm<{
        request_id: string;
        current_id: number | null;
        text_version: string;
        decision: string;
        satusehat_sharing_decision: string;
        signer_name: string;
        signer_relationship: string;
        representative_verification: string;
        reason: string;
        explained: boolean;
        in_person: boolean;
        evidence: File | null;
    }>({
        request_id: requestId,
        current_id: summary.currentId,
        text_version: textVersion,
        decision: '',
        satusehat_sharing_decision: 'undocumented',
        signer_name: '',
        signer_relationship: 'patient',
        representative_verification: '',
        reason: '',
        explained: false,
        in_person: true,
        evidence: null,
    });
    useEffect(() => {
        form.setData({ request_id: requestId, current_id: summary.currentId, text_version: textVersion, decision: '', satusehat_sharing_decision: 'undocumented', signer_name: '', signer_relationship: 'patient', representative_verification: '', reason: '', explained: false, in_person: true, evidence: null });
        setWithdrawalFor(null);
    }, [summary.currentId, visit.id]);

    return <>
        <Head title="Persetujuan umum" />
        <div className="space-y-6">
            <PageHeaderWithActions
                actions={releaseUrl ? (
                    <Button asChild size="sm" variant="secondary">
                        <Link href={releaseUrl}>Register pelepasan informasi</Link>
                    </Button>
                ) : undefined}
                backHref={backUrl}
                backLabel="Kembali"
                eyebrow="Administrasi pelayanan"
                subtitle={`${visit.patientName} · ${visit.medicalRecordNumber} · ${visit.number}`}
                title="Persetujuan umum"
            />
            <Alert dismissible={false} variant={summary.status === 'accepted' ? 'success' : summary.status === 'refused' ? 'destructive' : 'info'}>
                <AlertTitle>{consentLabels[summary.status] ?? summary.status}</AlertTitle>
                <AlertDescription>{summary.recordedAt ? `Tercatat ${summary.recordedAt}.` : 'Belum ada bukti keputusan pasien. Data lama tidak dianggap otomatis setuju.'} {!summary.versionCurrent && summary.currentId && 'Versi penjelasan perlu diperbarui.'} Persetujuan tindakan dan pelepasan informasi dicatat tersendiri.</AlertDescription>
            </Alert>
            {approved && <Card>
                <CardHeader><CardTitle>Bukti persetujuan</CardTitle><CardDescription>Keputusan sudah tercatat untuk versi penjelasan yang berlaku.</CardDescription></CardHeader>
                <CardContent className="flex flex-wrap gap-2">
                    {currentProof?.verificationUrl && <Button asChild><a href={currentProof.verificationUrl}>Lihat / cetak bukti verifikasi</a></Button>}
                    {currentProof?.evidenceUrl && <Button asChild><a href={currentProof.evidenceUrl}><Download className="size-4" />Unduh bukti</a></Button>}
                    {canWrite && !withdrawing && <Button variant="secondary" onClick={() => { form.clearErrors(); form.setData({ request_id: requestId, current_id: summary.currentId, text_version: textVersion, decision: 'withdrawn', satusehat_sharing_decision: 'opt_out', signer_name: '', signer_relationship: 'patient', representative_verification: '', reason: '', explained: false, in_person: true, evidence: null }); setWithdrawalFor(summary.currentId); }}>Catat pencabutan</Button>}
                </CardContent>
            </Card>}
            {(!approved || withdrawing) && <Card><CardHeader><CardTitle>Penjelasan persetujuan umum</CardTitle><CardDescription>Versi {textVersion}. Bacakan atau berikan kesempatan pasien membaca dan bertanya.</CardDescription></CardHeader><CardContent><p className="whitespace-pre-wrap text-sm leading-7 text-neutral-700">{text}</p></CardContent></Card>}
            {canWrite && (!approved || withdrawing) && <Card><CardHeader><CardTitle>{withdrawing ? 'Catat pencabutan persetujuan' : 'Catat keputusan pasien'}</CardTitle><CardDescription>Catat keputusan yang disampaikan langsung oleh pasien atau wakilnya. Akun petugas dan waktu konfirmasi tersimpan otomatis.</CardDescription></CardHeader><CardContent>
                <form className="space-y-5" onSubmit={(event) => { event.preventDefault(); form.post(storeUrl, { forceFormData: !form.data.in_person && Boolean(form.data.evidence), preserveScroll: true }); }}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {!withdrawing && <Field label="Keputusan" htmlFor="consent-decision" error={form.errors.decision} required><Select id="consent-decision" value={form.data.decision} onChange={(event) => form.setData('decision', event.target.value)} required><option value="">Pilih keputusan</option><option value="accepted">Setuju</option><option value="refused">Menolak setelah penjelasan</option>{summary.status === 'accepted' && <option value="withdrawn">Mencabut persetujuan</option>}</Select></Field>}
                        {!withdrawing && form.data.decision === 'accepted' && <Field label="Pertukaran data SATUSEHAT Kemenkes (opsional)" htmlFor="consent-satusehat" error={form.errors.satusehat_sharing_decision}><Select id="consent-satusehat" value={form.data.satusehat_sharing_decision} onChange={(event) => form.setData('satusehat_sharing_decision', event.target.value)}><option value="undocumented">Belum ditetapkan saat ini</option><option value="opt_in">Setuju berbagi ke SATUSEHAT (Opt-In)</option><option value="opt_out">Menolak berbagi ke SATUSEHAT (Opt-Out)</option></Select></Field>}
                        {form.data.signer_relationship !== 'patient' && <Field label="Nama wakil" htmlFor="consent-signer" error={form.errors.signer_name} required><Input id="consent-signer" required maxLength={150} value={form.data.signer_name} onChange={(event) => form.setData('signer_name', event.target.value)} /></Field>}
                        <Field label="Hubungan dengan pasien" htmlFor="consent-relationship" error={form.errors.signer_relationship} required><Select id="consent-relationship" value={form.data.signer_relationship} onChange={(event) => { form.setData('signer_relationship', event.target.value); form.setData('representative_verification', ''); }}><option value="patient">Pasien sendiri</option><option value="parent">Orang tua</option><option value="spouse">Suami / istri</option><option value="guardian">Wali / pengampu</option><option value="other">Wakil lain yang sah</option></Select></Field>
                        {form.data.signer_relationship !== 'patient' && <Field label="Dasar kewenangan dan verifikasi wakil" htmlFor="consent-representative" error={form.errors.representative_verification} required><Textarea id="consent-representative" required minLength={10} maxLength={1000} value={form.data.representative_verification} onChange={(event) => form.setData('representative_verification', event.target.value)} placeholder="Catat dokumen/dasar kewenangan yang diperiksa; hindari menyalin nomor identitas lengkap." /></Field>}
                    </div>
                    <div className="space-y-4">
                        <Field label="Metode dokumentasi bukti" htmlFor="consent-method" error={form.errors.in_person}>
                            <Select id="consent-method" value={form.data.in_person ? 'in_person' : 'upload'} onChange={(event) => {
                                const inPerson = event.target.value === 'in_person';
                                form.setData((current) => ({ ...current, in_person: inPerson, evidence: inPerson ? null : current.evidence }));
                            }}>
                                <option value="in_person">Verifikasi tatap muka (atestasi petugas)</option>
                                <option value="upload">Unggah dokumen bermeterai / tanda tangan (PDF/JPG/PNG)</option>
                            </Select>
                        </Field>
                        {!form.data.in_person && (
                            <Attachment
                                accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                                disabled={form.processing}
                                error={form.errors.evidence}
                                fileName={form.data.evidence?.name}
                                helperText="Format yang didukung: PDF, JPG, atau PNG dengan ukuran maksimal 5 MB."
                                id="consent-evidence"
                                label="Bukti tertulis"
                                onFileChange={(file) => {
                                    form.clearErrors('evidence');
                                    form.setData('evidence', file);
                                }}
                                required
                            />
                        )}
                    </div>
                    <Field label="Alasan / catatan keputusan" htmlFor="consent-reason" error={form.errors.reason} required={form.data.decision !== 'accepted'}><Textarea id="consent-reason" required={form.data.decision !== 'accepted'} minLength={10} maxLength={2000} value={form.data.reason} onChange={(event) => form.setData('reason', event.target.value)} /></Field>

                    <Field htmlFor="consent-explained" label="Konfirmasi penjelasan" error={form.errors.explained} required><label className="flex items-start gap-3 text-sm leading-6 text-neutral-700"><Checkbox id="consent-explained" checked={form.data.explained} onCheckedChange={(value) => form.setData('explained', value === true)} /><span>Saya telah memberi penjelasan, memeriksa identitas, dan menerima langsung keputusan yang dipilih di atas.</span></label></Field>
                    {(['current_id', 'request_id', 'text_version'] as const).map((key) => form.errors[key] && <p key={key} role="alert" className="text-sm text-red-600">{form.errors[key]}</p>)}
                    <FormActionFooter>
                        {withdrawing && <Button type="button" variant="secondary" disabled={form.processing} onClick={() => { form.reset(); form.clearErrors(); setWithdrawalFor(null); }}>Batal</Button>}
                        <Button type="submit" disabled={form.processing || !form.data.explained || !form.data.decision || (!form.data.in_person && !form.data.evidence)}>{form.processing ? 'Menyimpan…' : withdrawing ? 'Konfirmasi pencabutan' : 'Konfirmasi keputusan pasien'}</Button>
                    </FormActionFooter>
                </form>
            </CardContent></Card>}
            {!canWrite && !approved && <Alert dismissible={false} variant="info"><AlertDescription>Petugas Pendaftaran atau Admin yang mencatat keputusan pasien.</AlertDescription></Alert>}
            <Card><CardHeader><CardTitle>Riwayat keputusan</CardTitle><CardDescription>Bukti tersimpan menurut waktu server. Riwayat tidak dapat ditimpa atau dihapus.</CardDescription></CardHeader><CardContent>{history.length ? <ul className="divide-y divide-neutral-100">{history.map((item) => <li key={item.id} className="flex flex-col justify-between gap-3 py-4 sm:flex-row"><div className="space-y-1"><p className="text-sm font-semibold text-neutral-900">{consentLabels[item.decision]} · {item.at}</p><p className="text-sm text-neutral-600">{item.signer} · {item.relationship} · {item.version}{item.satusehatSharing && item.satusehatSharing !== 'undocumented' ? ` · ${satusehatLabels[item.satusehatSharing] ?? item.satusehatSharing}` : ''}</p>{item.reason && <p className="whitespace-pre-wrap text-sm text-neutral-600">{item.reason}</p>}</div>{item.verificationUrl && <Button asChild variant="secondary" size="sm"><a href={item.verificationUrl}>Lihat / cetak bukti verifikasi</a></Button>}{item.evidenceUrl && <Button asChild variant="secondary" size="sm"><a href={item.evidenceUrl}><Download className="size-4" />Unduh bukti</a></Button>}</li>)}</ul> : <p className="text-sm text-neutral-500">Belum ada keputusan yang tercatat.</p>}</CardContent></Card>
        </div>
    </>;
}
