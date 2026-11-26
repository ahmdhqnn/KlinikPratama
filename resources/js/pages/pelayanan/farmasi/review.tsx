import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { FormActionFooter } from '@/components/ui/form-action-footer';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { FormEvent, useEffect } from 'react';

interface SafetyFlag { name: string; isHighAlert?: boolean; isLasa?: boolean; allergyOverrideReason?: string | null; allergyMatchedTerm?: string | null }
interface Props { visit: { id: number; number: string; patient: string }; maternal?: { hasRisk?: boolean; summaryText?: string | null } | null; safetyFlags?: SafetyFlag[]; qualification: string; sourceHash: string; currentId: number | null; canReview: boolean; history: { data: { id: number; decision: string; at: string; data: { notes?: string; clarification?: string; clarification_reference?: string; recipient?: { recipient_name: string; recipient_relationship: string; counseling_notes: string } } }[] } }
export default function PharmacyReview({ visit, maternal = null, safetyFlags = [], qualification, sourceHash, currentId, canReview, history, indications = [], alreadyDispensed = false, correctionNotice = null }: Props & { indications?: { code: string | null; name: string | null }[]; alreadyDispensed?: boolean; correctionNotice?: { version: number; afterDispensing: boolean } | null }) {
    const form = useForm({ source_hash: sourceHash, current_id: currentId, decision: alreadyDispensed ? 'follow_up' : 'approved', administrative_review: false, pharmaceutical_review: false, clinical_review: false, notes: '', clarification: 'not_needed', clarification_reference: '', signature_password: '' });
    useEffect(() => { form.setData((data) => ({ ...data, source_hash: sourceHash, current_id: currentId, signature_password: '' })); }, [sourceHash, currentId]);
    function submit(event: FormEvent) {
        event.preventDefault();
        form.post(`/pelayanan/farmasi/${visit.id}/review`, { preserveScroll: true, onFinish: () => form.setData('signature_password', '') });
    }
    return <><Head title="Telaah resep klinis" /><div className="mx-auto max-w-3xl space-y-6">
        {correctionNotice && <Card><CardHeader><CardTitle>Koreksi dokter memerlukan telaah ulang farmasi</CardTitle><CardDescription>Catatan dokter versi {correctionNotice.version} belum ditinjau berdasarkan sumber terbaru. {correctionNotice.afterDispensing ? 'Obat sudah diserahkan; periksa dampak dan catat komunikasi serta tindak lanjut pasien.' : 'Periksa kembali resep dan kondisi pasien sebelum penyerahan.'}</CardDescription></CardHeader></Card>}
        <PageHeaderWithActions
            backHref={`/pelayanan/farmasi/${visit.id}`}
            backLabel="Kembali ke resep dan penyerahan"
            eyebrow="Pelayanan kefarmasian"
            subtitle={`${visit.patient} · ${visit.number} · Kualifikasi: ${qualification === 'unverified' ? 'Belum diverifikasi' : qualification}`}
            title="Telaah resep klinis"
        />
        <Card><CardHeader><CardTitle>Telaah resep oleh apoteker</CardTitle><CardDescription>Simpan rencana penyerahan terlebih dahulu. Periksa resep, alergi, berat badan, dan SIP dokter pada halaman penyerahan. Perubahan sumber memerlukan telaah baru.</CardDescription></CardHeader><CardContent>
            <div className="mb-4 space-y-2"><p className="text-sm font-medium">Indikasi dari diagnosis final dokter</p>{indications.length ? indications.map((item, index) => <p key={index} className="text-sm">{item.code ? `${item.code} · ` : ''}{item.name ?? 'Belum dicatat'}</p>) : <p className="text-sm text-neutral-500">Belum tersedia. Klarifikasi kepada dokter bila diperlukan.</p>}{maternal?.summaryText && <div className={maternal.hasRisk ? 'rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950' : 'rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-sm text-neutral-700'}><strong>Status kehamilan & laktasi:</strong> {maternal.summaryText}</div>}{safetyFlags.length > 0 && <div className="space-y-1 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900"><strong>Penanda keselamatan obat pada resep ini:</strong><ul className="list-inside list-disc text-xs">{safetyFlags.map((flag, idx) => <li key={idx}><strong>{flag.name}</strong>{flag.isHighAlert ? ' · HIGH ALERT' : ''}{flag.isLasa ? ' · LASA (periksa ulang nama & kekuatan)' : ''}{flag.allergyOverrideReason ? ` · Override alergi (${flag.allergyMatchedTerm}): ${flag.allergyOverrideReason}` : ''}</li>)}</ul></div>}</div>
            {!canReview ? <p className="text-sm text-neutral-500">Telaah klinis hanya dapat disimpan oleh apoteker aktif dengan kualifikasi terverifikasi.</p> : <form onSubmit={submit} className="space-y-4">
                {(form.errors.source_hash || form.errors.current_id) && <p role="alert" className="text-sm text-red-600">{form.errors.source_hash ?? form.errors.current_id}</p>}
                <div className="space-y-3 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4">
                    {([['administrative_review', 'Identitas pasien, kelengkapan resep dan legalitas penulis resep telah diperiksa'], ['pharmaceutical_review', 'Bentuk, kekuatan, stabilitas, kompatibilitas, jumlah dan aturan pakai telah diperiksa'], ['clinical_review', 'Indikasi, dosis, alergi, interaksi, kontraindikasi dan duplikasi telah diperiksa']] as const).map(([key, label]) => (
                        <div key={key} className="space-y-1">
                            <label className="flex cursor-pointer items-start gap-2.5 text-sm leading-6 text-neutral-800" htmlFor={`review-${key}`}>
                                <Checkbox id={`review-${key}`} className="mt-1 shrink-0" checked={form.data[key]} onCheckedChange={(checked) => form.setData(key, checked === true)} />
                                <span>{label}</span>
                            </label>
                            {form.errors[key] && <p role="alert" className="text-xs text-red-600">{form.errors[key]}</p>}
                        </div>
                    ))}
                </div>
                <Field label="Hasil telaah dan kondisi yang relevan" htmlFor="review-notes" error={form.errors.notes} required><Textarea id="review-notes" value={form.data.notes} onChange={(event) => form.setData('notes', event.target.value)} minLength={10} maxLength={3000} required /></Field>
                <Field label="Klarifikasi dokter" htmlFor="review-clarification" error={form.errors.clarification}><Select id="review-clarification" value={form.data.clarification} onChange={(event) => form.setData('clarification', event.target.value)}><option value="not_needed">Tidak diperlukan setelah telaah</option><option value="pending">Menunggu jawaban dokter</option><option value="resolved">Telah diselesaikan dengan dokter</option></Select></Field>
                {form.data.clarification !== 'not_needed' && <Field label="Masalah, dokter yang dihubungi, waktu dan jawaban / referensi komunikasi" htmlFor="review-reference" error={form.errors.clarification_reference} required><Textarea id="review-reference" value={form.data.clarification_reference} onChange={(event) => form.setData('clarification_reference', event.target.value)} required minLength={10} maxLength={2000} /></Field>}
                {alreadyDispensed && <p role="status" className="text-sm text-neutral-500">Obat sudah diserahkan. Catat penilaian dampak koreksi, komunikasi dokter/pasien dan tindak lanjut. Pencatatan ini tidak mengubah penyerahan atau mengembalikan stok.</p>}
                <Field label="Keputusan telaah" htmlFor="review-decision" error={form.errors.decision}><Select id="review-decision" value={form.data.decision} onChange={(event) => form.setData('decision', event.target.value)}>{alreadyDispensed ? <option value="follow_up">Tindak lanjut pasca-penyerahan</option> : <><option value="approved">Layak dilanjutkan sesuai rencana</option><option value="hold">Tahan untuk tindak lanjut</option></>}</Select></Field>
                <Field label="PIN pengesahan klinis / kata sandi apoteker" htmlFor="review-password" error={form.errors.signature_password} required><Input id="review-password" type="password" autoComplete="current-password" value={form.data.signature_password} onChange={(event) => form.setData('signature_password', event.target.value)} /></Field>
                <FormActionFooter>
                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Menyimpan…' : 'Simpan telaah resep'}</Button>
                </FormActionFooter>
            </form>}
        </CardContent></Card>
        <Card><CardHeader><CardTitle>Riwayat telaah resep</CardTitle></CardHeader><CardContent className="space-y-3">{history.data.length === 0 && <p className="text-sm text-neutral-500">Belum ada telaah apoteker.</p>}{history.data.map((item) => <div key={item.id} className="space-y-1 border-t border-neutral-200 py-3"><p className="text-sm font-medium">{item.decision === 'approved' ? 'Layak dilanjutkan' : item.decision === 'delivered' ? 'Diserahkan' : 'Ditahan'} · {item.at}</p><p className="text-sm">{item.data.notes}</p>{item.data.clarification_reference && <p className="text-sm">Klarifikasi: {item.data.clarification_reference}</p>}{item.data.recipient && <div className="space-y-1 text-sm"><p>Penerima: {item.data.recipient.recipient_name} · {item.data.recipient.recipient_relationship}</p><p>Konseling: {item.data.recipient.counseling_notes}</p></div>}</div>)}</CardContent></Card>
    </div></>;
}
