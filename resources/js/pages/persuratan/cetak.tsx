import { ClinicalDocumentHeader } from '@/components/clinical-document-header';
import { printWithFonts } from '@/lib/print-typography';
import { Head, useForm } from '@inertiajs/react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useState } from 'react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { returnToPreviousScreen } from '@/lib/navigation';
import { letterTypes, toothRows } from '@/components/medical-letter-fields';
import type { ReactNode } from 'react';

export interface DocumentData {
    replacementNumber?: string | null;
    kind: 'resep_luar' | 'surat' | 'rujukan_internal';
    clinic: { name: string; license?: string | null; facilityCode?: string | null; address: string | null; phone: string | null; email: string | null; logoUrl: string | null };
    patient: { name: string; medicalRecordNumber: string; gender: string | null; age: number | null; weight?: string | number | null; address: string | null; birthDate?: string | null; bloodType?: string | null; phone?: string | null; nip?: string | null; department?: string | null; allergy?: string | null };
    visitNumber: string;
    clinicName: string;
    doctor: { name: string; sip: string | null; str: string | null; signatureDataUrl: string | null };
    number: string | null;
    verification?: { status: 'valid' | 'void' | 'invalid'; url: string; qrDataUrl: string; voidUrl: string } | null;
    backUrl: string;
    backLabel: string;
    items?: { name: string; type: string; quantity: number; unit: string | null; instructions: string | null; note: string | null }[];
    type?: string;
    date?: string | null;
    content?: string | null;
    fromClinic?: string | null;
    toClinic?: string | null;
    notes?: string | null;
    clinical?: { anamnesis: string | null; diagnoses: string[]; procedures?: string[]; physicalExamination: string | null; height: string | number | null; weight: string | number | null; bmi?: string | number | null; bloodPressure: string | null; pulse?: string | number | null; temperature?: string | number | null; respiration?: string | number | null; oxygenSaturation?: string | number | null; therapy: string | null; education: string | null; notes: string | null; finalCondition?: string | null; disposition?: string | null; followUpDate?: string | null; date: string | null };
    details?: { rest_start?: string; rest_end?: string; purpose?: string; conclusion?: string; color_vision?: string; visual_acuity?: string; recipient?: string; recipient_address?: string; requested_tests?: string; xray_types?: string[]; teeth?: number[]; result_delivery?: string };
}

function titleOf(doc: DocumentData) {
    if (doc.kind === 'resep_luar') return 'Resep Obat';
    if (doc.kind === 'rujukan_internal') return 'Surat Rujukan Internal';
    return letterTypes.find(([type]) => type === doc.type)?.[1] ?? 'Surat Keterangan';
}
function dateLabel(value?: string) {
    if (!value) return '-';
    const [year, month, day] = value.split('-').map(Number);
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'long', timeZone: 'UTC' }).format(new Date(Date.UTC(year, month - 1, day)));
}
function Row({ label, children }: { label: string; children?: ReactNode }) {
    return <tr><th scope="row">{label}</th><td className="colon">:</td><td>{children || '-'}</td></tr>;
}
function Clinical({ doc, therapy = false }: { doc: DocumentData; therapy?: boolean }) {
    return <table className="letter-data"><tbody>
        <Row label="Anamnesis">{doc.clinical?.anamnesis}</Row>
        <Row label="Diagnosis / keterangan klinis">{doc.clinical?.diagnoses.join('; ')}</Row>
        {therapy && <Row label="Terapi">{doc.clinical?.therapy}</Row>}
    </tbody></table>;
}

export function LetterSheet({ document: doc }: { document: DocumentData }) {
    const details = doc.details ?? {};
    const printDate = doc.date ?? '-';
    const footerRecord = Array.from(`RM: ${doc.patient.medicalRecordNumber}`).map((character) => `\\${character.codePointAt(0)?.toString(16)} `).join('');
    const gender = doc.patient.gender === 'L' ? 'Laki-laki' : doc.patient.gender === 'P' ? 'Perempuan' : '-';
    const referral = doc.kind === 'rujukan_internal' || doc.type === 'rujukan';
    const supporting = ['laboratorium', 'rontgen_gigi'].includes(doc.type ?? '');
    const restDays = details.rest_start && details.rest_end ? Math.round((Date.parse(details.rest_end) - Date.parse(details.rest_start)) / 86400000) + 1 : null;
    return <article className={`medical-letter print-preview-paper${supporting ? ' letter-supporting' : ''}`}>
        <style>{`
            .medical-letter { color:#000; background:#fff; font-family:var(--font-sans); font-size:11pt; line-height:1.5; max-width:100%; min-height:250mm; margin:0 auto; padding:18mm; overflow-wrap:anywhere; }
            .medical-letter * { color:inherit; }
            .letter-head { text-align:center; padding:0; margin-bottom:5mm; break-inside:avoid; }
            .letter-head > strong { display:block; font-size:18pt; }
            .letter-head > p { margin:4px 0 0; font-size:11pt; }
            .letter-title { text-align:center; margin-bottom:24px; break-inside:avoid; }
            .letter-title h1 { font-size:14pt; font-weight:bold; text-transform:uppercase; text-decoration:underline; text-underline-offset:3px; margin:0 0 5px; }
            .medical-letter p { margin-top:0; margin-bottom:12px; }
            .letter-data { width:100%; border-collapse:collapse; margin:12px 0 20px; table-layout:fixed; }
            .letter-data th { width:30%; text-align:left; vertical-align:top; font-weight:normal; padding:3px 6px 3px 0; }
            .letter-data td { padding:3px 0; vertical-align:top; white-space:pre-wrap; }
            .letter-data .colon { width:14px; }
            .letter-content { white-space:pre-wrap; margin:20px 0; }
            .letter-signature { width:48%; margin:30px 0 0 auto; text-align:center; break-inside:avoid; }
            .letter-signature img { height:65px; max-width:180px; margin:6px auto; object-fit:contain; }
            .letter-signature .signature-space { height:65px; }
            .letter-signature p { margin:3px 0; }
            .letter-box { border:1px solid #000; padding:12px; margin:16px 0; white-space:pre-wrap; }
            .letter-box h2 { font-weight:bold; font-size:12pt; margin-bottom:8px; }
            .letter-choices { display:grid; grid-template-columns:1fr 1fr; gap:8px 24px; margin:12px 0 20px; }
            .letter-tooth-row { display:flex; justify-content:center; margin:0 auto; }
            .letter-tooth { width:34px; text-align:center; border-bottom:1px solid #000; padding:8px 0; font-size:10pt; }
            .letter-tooth:nth-child(8), .letter-tooth-row:nth-child(n+3) .letter-tooth:nth-child(5) { border-right:1px solid #000; }
            .letter-tooth-row:nth-child(n+3) .letter-tooth:nth-child(8) { border-right:0; }
            .letter-tooth.selected { font-weight:bold; outline:2px solid #000; outline-offset:-3px; border-radius:50%; }
            .letter-grid { border-collapse:collapse; table-layout:fixed; width:100%; margin:16px 0; font-size:10pt; }
            .letter-grid th,.letter-grid td { border:1px solid #000; padding:8px; vertical-align:top; white-space:pre-wrap; }
            .letter-grid th { font-weight:bold; text-align:center; }
            .letter-grid thead { display:table-header-group; }
            .letter-rx { break-inside:avoid; margin:20px 0; }
            .letter-rx strong { font-size:14pt; }
            .letter-rx p { white-space:pre-line; }
            .letter-supporting { font-size:11pt; line-height:1.3; }
            .letter-supporting .letter-head { margin-bottom:5mm; }
            .letter-supporting .letter-title { margin-bottom:14px; }
            .letter-supporting .letter-data { margin:8px 0 12px; }
            .letter-supporting .letter-data th,.letter-supporting .letter-data td { padding-top:1px; padding-bottom:1px; }
            .letter-supporting .letter-box { margin:12px 0; padding:10px; }
            .letter-supporting .letter-box p { margin:4px 0; }
            .letter-supporting .letter-signature { margin-top:16px; }
            .letter-supporting .signature-space,.letter-supporting .letter-signature img { height:50px; }
            .letter-supporting .letter-tooth { padding:5px 0; }
            .letter-small { font-size:10pt; }
            @media screen and (max-width:640px) { .medical-letter { padding:7mm; font-size:11pt; } .letter-head > strong {font-size:15pt;} .letter-data th {width:35%;} .letter-signature{width:65%;} }
            @media print { @page { size:A4 portrait; margin:15mm 17mm; @bottom-left { content: "${footerRecord}"; font-family:var(--font-sans); font-size:9pt; } @bottom-right { content: "Halaman " counter(page) " dari " counter(pages); font-family:var(--font-sans); font-size:9pt; } } html,body { background:#fff!important; } .medical-letter { width:100%; max-width:none; min-height:0; padding:0; box-shadow:none; font-size:11pt; } .medical-letter.letter-supporting {font-size:11pt;} .letter-toolbar {display:none!important;} .letter-print-main{padding:0!important;margin:0!important;max-width:none!important;} }
        `}</style>
        <div className="letter-head"><ClinicalDocumentHeader clinic={doc.clinic} /></div>
        <div className="letter-title"><h1>{titleOf(doc)}</h1>{doc.number && <p>Nomor: {doc.number}</p>}</div>
        {doc.replacementNumber && <p className="letter-box">Dokumen ini menggantikan dokumen nomor {doc.replacementNumber}.</p>}
        {doc.verification && doc.verification.status !== 'valid' && <p className="letter-box" role="alert"><strong>{doc.verification.status === 'void' ? 'DOKUMEN DIBATALKAN — ARSIP' : 'INTEGRITAS DOKUMEN PERLU DIPERIKSA'}</strong></p>}
        {referral && <><p>Kepada TS Yth.<br />{doc.kind === 'rujukan_internal' ? doc.toClinic : details.recipient || '-'}<br />di {doc.kind === 'rujukan_internal' ? 'Klinik Pratama Kementerian Pariwisata' : details.recipient_address || '-'}</p><p>Mohon pemeriksaan dan penanganan lebih lanjut atas pasien:</p></>}
        {doc.type === 'sakit' && <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>}
        {doc.type === 'sehat' && <p>Yang bertanda tangan di bawah ini menerangkan:</p>}
        <table className="letter-data"><tbody>
            <Row label="Nama">{doc.patient.name}</Row>
            <Row label="No. rekam medis">{doc.patient.medicalRecordNumber}</Row>
            <Row label="Tanggal lahir / umur">{doc.patient.birthDate || '-'} / {doc.patient.age === null ? '-' : `${doc.patient.age} tahun`}</Row>
            <Row label="Jenis kelamin">{gender}</Row>
            {['sakit', 'sehat'].includes(doc.type ?? '') && (doc.patient.nip || doc.patient.department) && <Row label="NIP / unit kerja">{[doc.patient.nip, doc.patient.department].filter(Boolean).join(' · ')}</Row>}
            <Row label="Alamat">{doc.patient.address}</Row>
            {doc.kind === 'resep_luar' && (
                <>
                    <Row label="Berat badan">{doc.patient.weight ? `${doc.patient.weight} kg` : 'Belum diukur'}</Row>
                    <Row label="Alergi obat">{doc.patient.allergy || 'Tidak ada riwayat alergi tercatat'}</Row>
                </>
            )}
            {supporting && <><Row label="No. telepon">{doc.patient.phone}</Row><Row label="Tanggal permintaan">{printDate}</Row><Row label="Dokter pengirim">{doc.doctor.name}</Row></>}
            {doc.type === 'ringkasan' && <><Row label="NIP / bagian">{[doc.patient.nip, doc.patient.department].filter(Boolean).join(' / ')}</Row><Row label="Telepon">{doc.patient.phone}</Row><Row label="Alergi obat">{doc.patient.allergy}</Row></>}
        </tbody></table>
        {doc.type === 'sakit' && <p>Perlu diberi istirahat karena sakit{restDays ? ` selama ${restDays} hari, terhitung mulai tanggal ${dateLabel(details.rest_start)} sampai dengan ${dateLabel(details.rest_end)}.` : ', sesuai keterangan dokter di bawah ini.'}</p>}
        {doc.type === 'sehat' && <>
            <table className="letter-data"><tbody>
                <Row label="Tinggi / berat badan">{[doc.clinical?.height ? `${doc.clinical.height} cm` : null, doc.clinical?.weight ? `${doc.clinical.weight} kg` : null, doc.clinical?.bmi ? `IMT ${doc.clinical.bmi} kg/m²` : null].filter(Boolean).join(' · ') || '-'}</Row>
                <Row label="Tekanan darah">{doc.clinical?.bloodPressure ? `${doc.clinical.bloodPressure} mmHg` : '-'}</Row>
                {(doc.clinical?.pulse || doc.clinical?.temperature || doc.clinical?.respiration) && <Row label="Nadi / suhu / napas">{[doc.clinical?.pulse ? `${doc.clinical.pulse} x/mnt` : null, doc.clinical?.temperature ? `${doc.clinical.temperature} °C` : null, doc.clinical?.respiration ? `${doc.clinical.respiration} x/mnt` : null].filter(Boolean).join(' · ')}</Row>}
                {doc.patient.bloodType && <Row label="Golongan darah">{doc.patient.bloodType}</Row>}
                {details.color_vision && <Row label="Tes buta warna">{details.color_vision}</Row>}
                {details.visual_acuity && <Row label="Tajam penglihatan (visus)">{details.visual_acuity}</Row>}
                <Row label="Tanggal pemeriksaan">{doc.clinical?.date}</Row>
            </tbody></table>
            {details.conclusion && <p className="letter-content">Pada pemeriksaan fisik: {details.conclusion}</p>}
            {details.purpose && <p>Surat ini dipergunakan untuk: {details.purpose}.</p>}
            <p>Demikian surat keterangan ini dibuat untuk dipergunakan sebagaimana mestinya.</p>
        </>}
        {referral && <>
            <table className="letter-data"><tbody>
                <Row label="Anamnesis">{doc.clinical?.anamnesis}</Row>
                {doc.clinical?.physicalExamination && <Row label="Pemeriksaan fisik">{doc.clinical.physicalExamination}</Row>}
                {(doc.clinical?.bloodPressure || doc.clinical?.pulse || doc.clinical?.temperature) && <Row label="Tanda vital">{[doc.clinical?.bloodPressure ? `TD ${doc.clinical.bloodPressure} mmHg` : null, doc.clinical?.pulse ? `Nadi ${doc.clinical.pulse} x/mnt` : null, doc.clinical?.temperature ? `Suhu ${doc.clinical.temperature} °C` : null].filter(Boolean).join(' · ')}</Row>}
                <Row label="Diagnosis / keterangan klinis">{doc.clinical?.diagnoses.join('; ')}</Row>
                <Row label="Terapi / tindakan yang diberikan">{doc.clinical?.therapy}</Row>
            </tbody></table>
            <p>Atas bantuan dan kerja samanya, kami ucapkan terima kasih.</p>
        </>}
        {doc.type === 'laboratorium' && <>
            <Clinical doc={doc} />
            <section className="letter-box"><h2>PEMERIKSAAN YANG DIMINTA</h2>{details.requested_tests?.split('\n').filter((line) => line.trim()).map((line, i) => <p key={i}>[x] {line}</p>)}</section>
        </>}
        {doc.type === 'rontgen_gigi' && <>
            <p>Mohon dilakukan pemeriksaan x-ray foto:</p>
            <div className="letter-choices">{['Dental','Occlusal','Panoramic','TMJ','Cephalometric'].map((type) => <span key={type}>{details.xray_types?.includes(type) ? '[x]' : '[ ]'} {type}</span>)}</div>
            <div aria-label="Diagram lokasi gigi FDI">{toothRows.map((row, i) => <div className="letter-tooth-row" key={i}>{row.map((tooth) => <span className={`letter-tooth${details.teeth?.includes(tooth) ? ' selected' : ''}`} key={tooth}>{tooth}</span>)}</div>)}</div>
            <p className="letter-small">Lokasi gigi menggunakan notasi FDI. Gigi yang diminta ditandai lingkaran.</p>
            <div className="letter-box"><Clinical doc={doc} /></div>
        </>}
        {supporting && <p>Hasil {details.result_delivery === 'dokter' ? 'dikirim kepada dokter pengirim' : 'diserahkan kepada pasien'}.</p>}
        {doc.type === 'ringkasan' && <>
            <table className="letter-grid"><thead><tr>{['Tanggal','Anamnesis & Pemeriksaan','Diagnosis & Tindakan','Terapi','Edukasi & Tindak Lanjut'].map((label) => <th key={label} style={label === 'Tanggal' ? {width:'13%'} : undefined}>{label}</th>)}</tr></thead><tbody><tr><td>{doc.clinical?.date || '-'}</td><td>{[doc.clinical?.anamnesis, doc.clinical?.bloodPressure ? `TD: ${doc.clinical.bloodPressure} mmHg` : null, doc.clinical?.physicalExamination ? `Fisik: ${doc.clinical.physicalExamination}` : null].filter(Boolean).join('\n') || '-'}</td><td>{[doc.clinical?.diagnoses.join('\n'), doc.clinical?.procedures?.length ? `Tindakan:\n${doc.clinical.procedures.join('\n')}` : null].filter(Boolean).join('\n\n') || '-'}</td><td>{doc.clinical?.therapy || '-'}</td><td>{[doc.clinical?.finalCondition ? `Kondisi akhir: ${doc.clinical.finalCondition}` : null, doc.clinical?.disposition ? `Status pulang: ${doc.clinical.disposition}` : null, doc.clinical?.followUpDate ? `Kontrol: ${doc.clinical.followUpDate}` : null, doc.clinical?.education, doc.clinical?.notes].filter(Boolean).join('\n') || '-'}</td></tr></tbody></table>
        </>}
        {doc.kind === 'resep_luar' && <>
            <p style={{textAlign:'right'}}>Jakarta, {printDate}</p>
            {doc.items?.map((item, i) => <div className="letter-rx" key={i}><div style={{display:'flex',justifyContent:'space-between',alignItems:'baseline',gap:'12px'}}><strong>R/ {item.name}</strong><span>No. {item.quantity} {item.unit}</span></div><p>S. {item.instructions || '-'}{item.type === 'racikan' ? '\nRacikan' : ''}{item.note ? `\n${item.note}` : ''}</p></div>)}
            <p className="letter-small"><em>Obat tidak boleh diganti tanpa sepengetahuan dokter.</em></p>
        </>}
        {doc.content && <div className="letter-content">{doc.content}</div>}
        {doc.notes && <div className="letter-content">Catatan konsultasi: {doc.notes}</div>}
        <footer className="letter-signature break-inside-avoid"><p>{printDate}</p><p>{referral ? 'Hormat kami,' : 'Dokter pemeriksa,'}</p>
            {doc.doctor.signatureDataUrl ? <img className="object-contain" alt={`Tanda tangan ${doc.doctor.name}`} src={doc.doctor.signatureDataUrl} /> : <div className="signature-space" />}
            <p><strong><u>{doc.doctor.name}</u></strong></p>
            {doc.doctor.sip && <p className="letter-small">SIP: {doc.doctor.sip}</p>}
            {doc.doctor.str && <p className="letter-small">STR: {doc.doctor.str}</p>}
        </footer>
        {doc.verification && <div className="mt-6 flex break-inside-avoid items-center gap-3 border-t border-neutral-300 pt-3"><div className="relative size-24 shrink-0 bg-white"><img src={doc.verification.qrDataUrl} alt="QR verifikasi arsip dokumen" className="size-full" /><img src="/logo_kemenpar.webp" alt="" className="absolute top-1/2 left-1/2 size-[12.5%] -translate-x-1/2 -translate-y-1/2 rounded-xs bg-white object-contain" /></div><p className="text-xs leading-5">Pindai untuk memeriksa status arsip penerbitan.<br />QR tidak memuat identitas atau isi medis pasien.<br />Bukan tanda tangan elektronik tersertifikasi.</p></div>}
    </article>;
}

export default function Cetak({ document }: { document: DocumentData }) {
    const [voidOpen, setVoidOpen] = useState(false);
    const voidForm = useForm({ reason: '', signature_password: '' });
    return <><Head title={`${titleOf(document)} · ${document.patient.name}`} />
        <div className="letter-print-main mx-auto w-full max-w-[210mm] space-y-4">
            <div className="letter-toolbar flex flex-wrap items-center justify-between gap-3 print:hidden"><Button onClick={() => returnToPreviousScreen(document.backUrl)} type="button" variant="secondary"><ArrowLeft className="size-4" />{document.backLabel}</Button><Button onClick={() => void printWithFonts()}><Printer className="size-4" />Cetak dokumen</Button></div>
            <LetterSheet document={document} />
            {document.verification?.status === 'valid' && <div className="flex justify-end print:hidden"><Button variant="secondary" onClick={() => setVoidOpen(true)}>Batalkan dokumen</Button></div>}
            <Dialog open={voidOpen} onOpenChange={setVoidOpen}><DialogContent><DialogHeader><DialogTitle>Batalkan dokumen</DialogTitle><DialogDescription>Arsip asli dipertahankan. Status pembatalan akan terlihat saat QR diverifikasi.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={(event) => { event.preventDefault(); if (document.verification) voidForm.post(document.verification.voidUrl, { preserveScroll: true, onSuccess: () => setVoidOpen(false), onFinish: () => voidForm.reset('signature_password') }); }}><Field htmlFor="document-void-reason" label="Alasan pembatalan" required error={voidForm.errors.reason}><Textarea id="document-void-reason" required minLength={10} maxLength={2000} rows={3} value={voidForm.data.reason} onChange={(event) => voidForm.setData('reason', event.target.value)} /></Field><Field htmlFor="document-void-password" label="Kata sandi akun dokter" required error={voidForm.errors.signature_password}><Input id="document-void-password" type="password" autoComplete="current-password" required value={voidForm.data.signature_password} onChange={(event) => voidForm.setData('signature_password', event.target.value)} /></Field><div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setVoidOpen(false)}>Batal</Button><Button type="submit" variant="secondary" disabled={voidForm.processing || voidForm.data.reason.trim().length < 10}>Konfirmasi pembatalan</Button></div></form></DialogContent></Dialog>
        </div></>;
}
