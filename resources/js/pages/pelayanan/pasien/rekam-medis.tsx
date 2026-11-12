import { ClinicalDocumentHeader, type ClinicProfile } from '@/components/clinical-document-header';
import { flushSync } from 'react-dom';
import { printWithFonts } from '@/lib/print-typography';
import { OdontogramFdi } from '@/components/odontogram/odontogram-fdi';
import { HumanBodyModel } from '@/components/anatomy/human-body-model';
import { OdontogramRecord } from '@/components/odontogram/odontogram-record';
import { BodyExaminationRecord } from '@/components/anatomy/body-examination-record';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { AllergyHistory } from '@/components/kunjungan/allergy-history';
import { VisitAttachments, type ClinicalAttachment } from '@/components/kunjungan/visit-attachments';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { BodyAnnotationView } from '@/components/anatomy/body-annotation';
import { findingLocation, recordedAnnotationNote, type BodyMapData } from '@/lib/body-map';
import { normalizeClinicalAnswer } from '@/lib/clinical-answer';
import { dentalDiagnosisDescription, dentalSurfaceLabel } from '@/lib/dental-diagnosis';
import { formatNumber } from '@/lib/format';
import { displayOptionalValue } from '@/lib/optional-answers';
import { Head, router } from '@inertiajs/react';
import { ArrowDown, ArrowLeft, ArrowUp, Printer, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface Diagnosis { toothFdi?: string | null; surfaces?: string[]; code: string | null; codeSystem?: string | null; name: string; note?: string | null; type: string }
interface Screening { submittedAt?: string | null; recordedBy?: string | null; summary?: Record<string, string | null>; systolic: number | null; diastolic: number | null; pulse: number | null; temperature: number | null; oxygenSaturation: number | null; weight: number | null; height: number | null; bmi: number | null; respiration: number | null; complaint: string | null; medicalHistory: string | null; familyHistory: string | null; allergyHistory: string | null; fallRisk: string | null; painScale: number | null; dentalPainLocation: string | null; dentalPainTriggers: string[]; dentalPainDuration: string | null; dentalMedicalRisks: string[]; dentalInfectionHistory: string[]; dentalNotes: string | null }
interface DentalFinding { toothFdi: string; surface: string; findingCode: string; detailSummary?: string | null; notes: string | null }
interface Examination { generalClinicalSummary?: Record<string, string | null>; dentalScope?: { dental_scope?: string; dental_scope_reason?: string; dental_focus?: string; dental_follow_up_plan?: string; dental_prior_chart?: string } | null; dentalEscalation?: { dental_escalation_decision: string; dental_escalation_note: string } | null; riwayat_obat?: string | null; riwayat_perawatan_gigi?: string | null; kebiasaan_gigi?: string | null;  nonPharmacological: string | null; anamnesis: string | null; currentHistory: string | null; pastHistory: string | null; familyHistory: string | null; allergyHistory: string | null; physicalExamination: string | null; odontogramSummary?: string | null; physicalSystems: Record<string, string>; bodyMap?: BodyMapData | null; extraoralExamination: string | null; oralHygieneIndex: number | null; differentialDiagnosis: string | null; education: string | null; notes: string | null; nextControl: string | null; signedAt: string | null; signedBy: string | null; signatureDataUrl?: string | null; integrityValid: boolean | null; versions: Array<{ generalClinicalSummary?: Record<string, string | null>; nonPharmacological?: string | null; education?: string | null; version: number; kind: string; reason: string | null; actor: string | null; recordedAt: string | null; anamnesis: string | null; currentHistory: string | null; pastHistory: string | null; familyHistory: string | null; allergyHistory: string | null; physicalExamination: string | null; odontogramSummary?: string | null; physicalSystems: Record<string, string>; bodyMap?: BodyMapData | null; extraoralExamination: string | null; oralHygieneIndex: number | null; differentialDiagnosis: string | null; odontogram: DentalFinding[] }>; diagnoses: Diagnosis[] }
interface Prescription { id: number; name: string; quantity: string | number; unit: string | null; instructions: string | null }
interface Treatment { id: number; name: string; code: string | null; codeSystem: string | null; codeRelease: string | null; quantity: number; toothFdi: string | null; note: string | null }
interface Visit { documents?: Array<{ name: string; type: string; printUrl: string }>;  procedureExecutions: Array<{ id: number; name: string; role: string; status: string; result: string | null; performedBy: string | null; performedAt: string | null }>; id: number; number: string; clinic: string; clinicType: string | null; doctor: string; date: string | null; status: string; examinationStarted?: boolean; isReservation?: boolean; screening: Screening | null; examination: Examination | null; prescriptions: Prescription[]; treatments: Treatment[]; odontogram: DentalFinding[]; attachments?: ClinicalAttachment[] }
interface Patient { id: number; name: string; medicalRecordNumber: string; nik: string | null; gender: string | null; age: number | null; bloodType: string | null; birthPlace: string | null; birthDate: string | null; address: string | null; religion: string | null; unit: string | null; allergies: string | null }
interface Props { clinicProfile?: ClinicProfile; patient: Patient; visits: Visit[]; backUrl: string; canSelectClinic: boolean; filters: { search: string; poliklinik_id: string | number; status: string; dari: string; sampai: string }; clinics: Array<{ id: number; name: string }>; pagination: PaginationData }

export default function PatientMedicalRecord({ clinicProfile, patient, visits, backUrl, canSelectClinic, filters, clinics, pagination }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [clinicId, setClinicId] = useState(String(filters.poliklinik_id));
    const [status, setStatus] = useState(filters.status);
    const [from, setFrom] = useState(filters.dari);
    const [to, setTo] = useState(filters.sampai);
    const [expandedVisitId, setExpandedVisitId] = useState<number | null>(null);

    const [printVisitId, setPrintVisitId] = useState<number | null>(null);
    const canPrintVisit = (visit: Visit) => Boolean(visit.examination?.signedAt);
    const canPrintPage = visits.length > 0 && visits.every(canPrintVisit);
    async function printVisits(visitId: number | null = null): Promise<void> {
        if (visitId === null ? !canPrintPage : !visits.some(visit => visit.id === visitId && canPrintVisit(visit))) return;
        flushSync(() => setPrintVisitId(visitId));
        try {
            await printWithFonts();
        } finally {
            flushSync(() => setPrintVisitId(null));
        }
    }

    function applyFilters(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        router.get(`/pelayanan/pasien/${patient.id}/rekam-medis`, {
            search,
            poliklinik_id: clinicId,
            status,
            dari: from,
            sampai: to,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function returnToPreviousPage(): void {
        const previousPage = document.referrer;
        if (previousPage && new URL(previousPage).origin === window.location.origin && new URL(previousPage).pathname !== window.location.pathname) {
            window.history.back();
            return;
        }

        router.visit(backUrl);
    }

    return <><Head title={`Rekam medis ${patient.name}`} /><MedicalRecordPrint clinic={clinicProfile} patient={patient} visits={printVisitId === null ? visits : visits.filter(visit => visit.id === printVisitId)} />
    <div className="min-w-0 w-full space-y-6 print:hidden"><div className="flex flex-col justify-between gap-3 print:hidden sm:flex-row sm:items-center"><Button onClick={returnToPreviousPage} size="sm" variant="ghost"><ArrowLeft className="size-4" />Kembali</Button><Button disabled={!canPrintPage} title={!canPrintPage ? "Cetak tersedia setelah seluruh pemeriksaan pada halaman ini difinalisasi dokter." : undefined} onClick={() => void printVisits()}><Printer className="size-4" />Cetak kunjungan pada halaman ini</Button></div>
        <Card>
            <CardHeader className="items-start text-left">
                <p className="text-xs font-semibold uppercase tracking-wider text-neutral-700">Berkas rekam medis pasien</p>
                <CardTitle className="break-words text-2xl">{patient.name}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 pt-0">
                <div className="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <IdentityDetail label="Nomor rekam medis" value={patient.medicalRecordNumber} />
                    <IdentityDetail label="NIK" value={patient.nik || 'Belum diisi'} />
                    <IdentityDetail label="Jenis kelamin" value={patient.gender === 'L' ? 'Laki-laki' : patient.gender === 'P' ? 'Perempuan' : 'Belum diketahui'} />
                    <IdentityDetail label="Usia" value={patient.age === null ? 'Belum diketahui' : `${patient.age} tahun`} />
                    <IdentityDetail className="sm:col-span-2 xl:col-span-1" label="Golongan darah" value={patient.bloodType || 'Belum diketahui'} />
                    <IdentityDetail label="Tempat, tanggal lahir" value={[patient.birthPlace, patient.birthDate].filter(Boolean).join(', ') || 'Belum diisi'} />
                    <IdentityDetail label="Alamat" value={patient.address || 'Belum diisi'} />
                    <IdentityDetail label="Agama" value={patient.religion || 'Belum diisi'} />
                    <IdentityDetail label="Unit kerja" value={patient.unit || 'Belum diisi'} />
                </div>
                <AllergyHistory value={patient.allergies} />
            </CardContent>
        </Card>
        <div className="print:hidden"><FilterPanel><form className={`grid gap-4 sm:grid-cols-2 ${canSelectClinic ? 'xl:grid-cols-4' : 'xl:grid-cols-3'}`} onSubmit={applyFilters}>
                <Field htmlFor="medical-record-search" label="Kata kunci"><div className="relative"><Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-500" /><Input className="pl-9" id="medical-record-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nomor kunjungan, keluhan, atau diagnosis" value={search} /></div></Field>
                {canSelectClinic && <Field htmlFor="medical-record-clinic" label="Poliklinik"><Select id="medical-record-clinic" onChange={(event) => setClinicId(event.target.value)} value={clinicId}><option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>}
            <Field htmlFor="medical-record-status" label="Status kunjungan"><Select id="medical-record-status" onChange={(event) => setStatus(event.target.value)} value={status}><option value="">Semua status</option><option value="menunggu">Menunggu pengkajian awal</option><option value="screening">Pengkajian awal berlangsung</option><option value="pemeriksaan">Menunggu / sedang diperiksa</option><option value="tindakan">Menunggu tindakan perawat</option><option value="farmasi">Pelayanan farmasi</option><option value="kasir">Perlu penyelesaian kunjungan</option><option value="selesai">Selesai</option><option value="batal">Dibatalkan</option></Select></Field>
                <Field htmlFor="medical-record-period" label="Periode kunjungan"><DateRangePicker id="medical-record-period" from={from} onChange={(range) => { setFrom(range.from); setTo(range.to); }} to={to} /></Field>
                <FilterSubmit className="col-span-full" />
            </form></FilterPanel></div>
        <div className="flex flex-wrap items-end justify-between gap-2 print:hidden"><div><h2 className="text-lg font-semibold text-neutral-900">Riwayat kunjungan</h2><p className="text-sm text-neutral-500">{pagination.total ? `${pagination.total.toLocaleString('id-ID')} kunjungan ditemukan` : 'Tidak ada kunjungan pada filter ini'}. Buka detail hanya pada kunjungan yang diperlukan.</p></div></div>
        {visits.length ? <div className="space-y-4">{visits.map((visit) => <Card className="min-w-0 w-full overflow-hidden" key={visit.id}><button aria-controls={`medical-record-visit-${visit.id}`} aria-expanded={expandedVisitId === visit.id} className="flex w-full flex-col gap-3 border-b border-neutral-100 bg-neutral-50 px-5 py-4 text-left transition hover:bg-neutral-100 sm:flex-row sm:items-center sm:justify-between" onClick={() => setExpandedVisitId((current) => current === visit.id ? null : visit.id)} type="button"><div className="min-w-0 space-y-2"><div className="flex flex-wrap items-center gap-2"><span className="font-sans tabular-nums text-sm font-bold text-neutral-900">{visit.number}</span><span className="rounded-full bg-surface px-2.5 py-1 text-xs font-semibold text-neutral-700">{visit.clinic}</span><StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} status={visit.status} /></div><p className="text-sm text-neutral-600">{visit.date ?? 'Belum diisi'} <span aria-hidden="true">·</span> Dokter: <strong>{visit.doctor}</strong></p><p className="line-clamp-2 text-sm text-neutral-700">{visit.screening?.complaint || visit.examination?.anamnesis || visit.examination?.currentHistory || visit.examination?.diagnoses[0]?.name || 'Keluhan dan ringkasan klinis belum dicatat.'}</p><p className="text-xs text-neutral-500">{visit.examination?.diagnoses.length ?? 0} diagnosis · {visit.prescriptions.length} obat · {visit.treatments.length} tindakan{visit.clinicType === 'gigi' ? ` · ${visit.odontogram.length} temuan odontogram` : ''}</p></div><span className="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-neutral-700 print:hidden">{expandedVisitId === visit.id ? 'Tutup detail' : 'Lihat detail'}{expandedVisitId === visit.id ? <ArrowUp className="size-4" /> : <ArrowDown className="size-4" />}</span></button><div className="flex justify-end border-b border-neutral-100 px-4 py-2 print:hidden"><Button size="sm" variant="secondary" aria-label={`Cetak rekam medis kunjungan ${visit.number}`} disabled={!canPrintVisit(visit)} title={!canPrintVisit(visit) ? "Cetak tersedia setelah pemeriksaan difinalisasi dokter." : undefined} onClick={() => void printVisits(visit.id)}><Printer className="size-4" />Cetak kunjungan ini</Button></div><CardContent id={`medical-record-visit-${visit.id}`} className={`min-w-0 space-y-6 p-4 sm:p-6 ${expandedVisitId === visit.id ? 'block' : 'hidden'}`}>
            {visit.examination?.integrityValid === false && <p className="rounded-xl border border-red-300 bg-red-50 p-4 text-sm font-semibold text-red-900">Integritas riwayat catatan ini tidak valid. Gunakan sumber klinis terverifikasi sebelum mengambil keputusan.</p>}
            {!!visit.documents?.length && <section className="space-y-3 print:hidden"><SectionTitle>Dokumen layanan</SectionTitle><div className="flex flex-wrap gap-2">{visit.documents.map((document) => <Button asChild key={document.printUrl} size="sm" variant="secondary"><a href={document.printUrl}><Printer className="size-4" />{document.type} · {document.name}</a></Button>)}</div></section>}
            {!!visit.attachments?.length && <section className="space-y-3"><SectionTitle>Lampiran pasien</SectionTitle><VisitAttachments attachments={visit.attachments} /></section>}

            {visit.clinicType === 'gigi' && visit.examination?.signedAt && <section><SectionTitle>Riwayat versi odontogram</SectionTitle><div className="space-y-2">{visit.examination.versions.map((version) => <div className="rounded-xl bg-neutral-50 p-3 text-sm" key={version.version}><p className="font-semibold text-neutral-900">Versi {version.version} · {version.kind === 'addendum' ? 'Addendum' : 'Final'}</p><p className="mt-1 text-neutral-700">{version.odontogram.length ? version.odontogram.map((finding) => `${finding.toothFdi} ${dentalSurface(finding.surface)}: ${dentalFinding(finding.findingCode)}${finding.detailSummary ? ` — ${finding.detailSummary}` : ''}`).join('; ') : 'Temuan tidak tercatat'}</p></div>)}</div></section>}
            {visit.screening && <section className="space-y-4"><div><SectionTitle>Tanda vital & pengkajian awal</SectionTitle>{(visit.screening.recordedBy || visit.screening.submittedAt) && <p className="mb-3 text-xs text-neutral-600">Petugas pengkajian: {visit.screening.recordedBy || 'Nama belum tercatat'} · Dikirim: {visit.screening.submittedAt || 'Belum dikirim'}</p>}<div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6"><Vital label="Tekanan darah" value={bloodPressure(visit.screening.systolic, visit.screening.diastolic)} /><Vital label="Nadi" value={withUnit(visit.screening.pulse, 'x/m')} /><Vital label="Suhu" value={withUnit(visit.screening.temperature, '°C')} /><Vital label="SpO₂" value={withUnit(visit.screening.oxygenSaturation, '%')} /><Vital label="Berat / tinggi" value={bodyMetrics(visit.screening.weight, visit.screening.height, visit.screening.bmi)} /><Vital label="Respirasi" value={withUnit(visit.screening.respiration, 'x/m')} /></div><p className="mt-3 whitespace-pre-wrap text-sm text-neutral-700"><strong>Keluhan utama:</strong> {displayOptionalValue(visit.screening.complaint, 'Tidak terdokumentasi pada catatan ini')}</p></div>
                <div className="min-w-0 space-y-4">
                    <p className="text-xs leading-5 text-neutral-500">Ringkasan memuat jawaban yang tercatat. Catatan opsional kosong tidak ditampilkan dan tidak berarti hasil normal atau telah dikaji. Jawaban yang belum dapat dikonfirmasi tetap ditampilkan sesuai sumber.</p><AssessmentGroup grouped title="Serah terima pengkajian awal" items={Object.entries(visit.screening.summary ?? {})} /><AssessmentGroup title={visit.clinicType === 'gigi' ? 'Riwayat medis' : 'Riwayat kesehatan'} items={[
                        ['Riwayat penyakit', visit.screening.medicalHistory], ['Riwayat alergi', visit.screening.allergyHistory],
                        ...(visit.clinicType === 'umum' ? [['Riwayat penyakit keluarga', visit.screening.familyHistory] as [string, string | null], ['Risiko jatuh', visit.screening.fallRisk] as [string, string | null]] : []),
                        ['Skala nyeri', visit.screening.painScale === null ? null : `${visit.screening.painScale} / 10`],
                    ]} />
                    {visit.clinicType === 'gigi' && <AssessmentGroup title="Pengkajian gigi dan mulut" items={[
                        ['Lokasi nyeri', visit.screening.dentalPainLocation], ['Pemicu nyeri', displayCodes(visit.screening.dentalPainTriggers)],
                        ['Durasi keluhan', visit.screening.dentalPainDuration], ['Risiko medis', displayCodes(visit.screening.dentalMedicalRisks)],
                        ['Riwayat infeksi', displayCodes(visit.screening.dentalInfectionHistory)], ['Catatan tambahan', visit.screening.dentalNotes],
                    ]} />}
                </div>
            </section>}
            {visit.examination && <div className="min-w-0 space-y-6">
                <section><SectionTitle>Anamnesis</SectionTitle><div className="grid min-w-0 gap-4 rounded-xl border border-neutral-200 p-4 text-sm sm:grid-cols-2 xl:grid-cols-3"><MedicalNote label="Keluhan utama" value={visit.examination.anamnesis} /><MedicalNote label="Riwayat penyakit sekarang" value={visit.examination.currentHistory} /><MedicalNote label="Riwayat penyakit dahulu" value={visit.examination.pastHistory} /><MedicalNote label="Riwayat keluarga" value={visit.examination.familyHistory} /><MedicalNote label="Riwayat alergi" value={visit.examination.allergyHistory} />{socialHistoryRow(visit.examination).map(([label, value]) => <MedicalNote key={label} label={label} value={value} />)}{visit.examination.riwayat_obat && <MedicalNote label="Obat yang sedang digunakan" value={visit.examination.riwayat_obat} />}{visit.examination.riwayat_perawatan_gigi && <MedicalNote label="Riwayat perawatan gigi" value={visit.examination.riwayat_perawatan_gigi} />}{visit.examination.kebiasaan_gigi && <MedicalNote label="Kebiasaan terkait gigi dan mulut" value={visit.examination.kebiasaan_gigi} />}</div></section>
                <section className="space-y-4"><SectionTitle>{visit.clinicType === 'gigi' ? 'Pemeriksaan gigi & mulut' : 'Pemeriksaan fisik'}</SectionTitle>
                    {visit.examination.physicalExamination?.trim() && <div className="min-w-0 rounded-xl border border-neutral-200 bg-neutral-50 p-4"><MedicalNote label={visit.clinicType === 'gigi' ? 'Pemeriksaan intraoral' : 'Ringkasan pemeriksaan fisik'} value={visit.examination.physicalExamination} /></div>}
                    {visit.clinicType === 'umum' && <AssessmentGroup title="Catatan pemeriksaan dokter" items={doctorSummaryRows(visit.examination)} />}
                    {visit.clinicType === 'umum' && <BodyExaminationRecord data={visit.examination.bodyMap} gender={patient.gender} />}
                    <AssessmentGroup title="Temuan fisik per sistem yang tercatat" items={recordedDoctorRows(visit.examination.physicalSystems)} />
                    {visit.clinicType === 'gigi' && (visit.examination.extraoralExamination?.trim() || typeof visit.examination.oralHygieneIndex === 'number') && <div className="rounded-xl border border-neutral-200 p-4">{visit.examination.extraoralExamination?.trim() && <MedicalNote label="Pemeriksaan ekstraoral" value={visit.examination.extraoralExamination} />}{typeof visit.examination.oralHygieneIndex === 'number' && <p className="mt-3 text-sm text-neutral-700">OHIS: {visit.examination.oralHygieneIndex}</p>}</div>}
                </section>
                <section className="space-y-4"><SectionTitle>Diagnosis</SectionTitle><div className="space-y-2">{visit.examination.diagnoses.length ? visit.examination.diagnoses.map((diagnosis, index) => <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3 text-sm" key={diagnosis.code + '-' + index}><p className="min-w-0 break-words"><span className="mr-2 font-sans tabular-nums font-bold text-neutral-700">{diagnosis.code || 'Manual'}</span><span className="font-medium text-neutral-800">{diagnosis.name}</span></p><span className="shrink-0 rounded-full bg-surface px-2 py-1 text-xs capitalize text-neutral-700">{diagnosis.type}</span>{diagnosis.toothFdi && <p className="w-full font-medium">Gigi {diagnosis.toothFdi} · {dentalSurfaceLabel(diagnosis.surfaces ?? []) || 'Tanpa permukaan spesifik'}</p>}{dentalDiagnosisDescription(diagnosis) && <p className="w-full whitespace-pre-wrap break-words text-neutral-600">{dentalDiagnosisDescription(diagnosis)}</p>}</div>) : <Empty className="min-h-0 px-3 py-5" description="Diagnosis pada kunjungan ini akan ditampilkan di sini." title="Belum ada diagnosis" />}</div><div className="rounded-xl border border-neutral-200 p-4"><MedicalNote label="Diagnosis banding" value={visit.examination.differentialDiagnosis} /></div></section>
                <section><SectionTitle>Rencana tindak lanjut & edukasi</SectionTitle><div className="grid min-w-0 gap-4 rounded-xl border border-neutral-200 p-4 sm:grid-cols-2"><MedicalNote label="Terapi nonfarmakologis" value={visit.examination.nonPharmacological} /><MedicalNote label="Edukasi" value={visit.examination.education} /><MedicalNote label="Catatan dokter" value={visit.examination.notes} />{finalEvaluationRows(visit.examination).map(([label, value]) => <MedicalNote key={label} label={label} value={value} />)}{visit.examination.nextControl && <MedicalNote label="Kontrol berikutnya" value={visit.examination.nextControl} />}</div></section>
            </div>}
            {visit.clinicType === 'gigi' && <section className="space-y-4"><SectionTitle>Odontogram kunjungan (peta gigi FDI)</SectionTitle>{visit.examination?.odontogramSummary?.trim() && <div className="rounded-xl border border-neutral-200 bg-neutral-50 p-4"><MedicalNote label="Ringkasan odontogram kunjungan" value={visit.examination.odontogramSummary} /></div>}<OdontogramRecord findings={visit.odontogram}>{visit.odontogram.length ? <div className="grid gap-3">{visit.odontogram.map((finding) => <div className="rounded-xl border border-neutral-200 p-3 text-sm" key={`${finding.toothFdi}-${finding.surface}`}><p className="font-semibold text-neutral-900">Gigi {finding.toothFdi} · {dentalSurface(finding.surface)}</p><p className="mt-1 text-neutral-700">{dentalFinding(finding.findingCode)}</p>{finding.detailSummary && <p className="mt-1 whitespace-pre-wrap text-neutral-700">{finding.detailSummary}</p>}{finding.notes && <p className="mt-1 whitespace-pre-wrap text-neutral-600">{finding.notes}</p>}</div>)}</div> : <Empty className="min-h-0 w-full px-3 py-4" description="Tidak terdapat temuan per gigi yang terdokumentasi pada kunjungan ini." title="Tidak ada temuan odontogram yang tercatat" />}</OdontogramRecord></section>}
            <div className="min-w-0 space-y-6"><section><SectionTitle>Resep obat</SectionTitle>{visit.prescriptions.length ? <div className="overflow-x-auto rounded-xl border border-neutral-100"><Table><TableHeader><TableRow><TableHead>Nama obat</TableHead><TableHead>Jumlah</TableHead><TableHead>Aturan pakai</TableHead></TableRow></TableHeader><TableBody>{visit.prescriptions.map((item) => <TableRow key={item.id}><TableCell className="font-medium">{item.name}</TableCell><TableCell className="whitespace-nowrap font-sans tabular-nums">{formatNumber(Number(item.quantity))} {item.unit ?? ''}</TableCell><TableCell>{item.instructions || 'Belum diisi'}</TableCell></TableRow>)}</TableBody></Table></div> : <Empty className="min-h-0 px-3 py-5" description={visit.examination?.signedAt ? "Tidak terdapat resep obat yang tercatat pada kunjungan ini." : "Resep ditampilkan setelah dicatat oleh dokter."} title={visit.examination?.signedAt ? "Tidak ada obat yang diresepkan" : "Resep obat belum tersedia"} />}</section><section><SectionTitle>Tindakan medis</SectionTitle>{visit.treatments.length ? <div className="space-y-2">{visit.treatments.map((item) => <div className="flex justify-between gap-3 rounded-lg bg-neutral-50 px-3 py-2.5 text-sm" key={item.id}><div className="min-w-0"><p className="font-medium text-neutral-800">{item.name}{item.toothFdi && <span className="ml-2 text-neutral-500">Gigi {item.toothFdi}</span>}</p><p className="mt-1 text-xs text-neutral-500">{item.code ? <><span className="font-sans tabular-nums">{item.code}</span> · ICD-9-CM{item.codeRelease && ` · ${item.codeRelease}`}</> : 'Tanpa kode ICD-9-CM'}</p>{item.note && <p className="mt-1 whitespace-pre-wrap text-xs text-neutral-600">{item.note}</p>}</div><span className="shrink-0 text-neutral-500">{item.quantity}×</span></div>)}</div> : <Empty className="min-h-0 px-3 py-5" description="Tidak terdapat pencatatan tindakan medis pada kunjungan ini." title="Tidak ada tindakan medis yang tercatat" />}</section></div>
            {visit.procedureExecutions?.length > 0 && <section className="space-y-3"><SectionTitle>Pelaksanaan tindakan</SectionTitle>{visit.procedureExecutions.map((item) => <div className="space-y-1 rounded-xl border border-neutral-200 p-4" key={item.id}><p className="text-sm font-medium">{item.name} · {item.role === 'perawat' ? 'Perawat' : 'Dokter'}</p><p className="text-sm text-muted-foreground">{item.status === 'completed' ? `Dilaksanakan oleh ${item.performedBy} · ${item.performedAt}` : item.status === 'pending' ? 'Menunggu pelaksanaan' : 'Catatan lama; waktu dan petugas pelaksanaan belum tercatat'}</p>{item.result && <p className="whitespace-pre-wrap text-sm">{item.result}</p>}</div>)}</section>}
            {visit.examination?.dentalScope?.dental_scope && <section className="rounded-xl border border-neutral-200 p-4">
                <SectionTitle>Pemeriksaan gigi terbatas</SectionTitle>
                <MedicalNote label="Alasan pembatasan" value={visit.examination.dentalScope.dental_scope_reason ?? null} />
                <MedicalNote label="Fokus dan temuan" value={visit.examination.dentalScope.dental_focus ?? null} />
                <MedicalNote label="Rencana tindak lanjut" value={visit.examination.dentalScope.dental_follow_up_plan ?? null} />
                {visit.examination.dentalScope.dental_prior_chart && <MedicalNote label="Odontogram awal" value={visit.examination.dentalScope.dental_prior_chart} />}
            </section>}
            {visit.examination?.dentalEscalation && <section className="rounded-xl border border-neutral-200 p-4">
                <SectionTitle>Keputusan eskalasi DPJP</SectionTitle>
                <MedicalNote label="Keputusan" value={dentalEscalationLabels[visit.examination.dentalEscalation.dental_escalation_decision] ?? null} />
                <MedicalNote label="Temuan dan tindak lanjut" value={visit.examination.dentalEscalation.dental_escalation_note} />
            </section>}
            {visit.examination?.signedAt && <section className="rounded-xl border border-neutral-200 p-4"><SectionTitle>Finalisasi & riwayat koreksi</SectionTitle><p className="text-sm text-neutral-700">Difinalisasi oleh {visit.examination.signedBy ?? 'dokter'} pada {visit.examination.signedAt}.</p>{visit.examination.integrityValid === true && visit.examination.signatureDataUrl && <div className="mt-4 flex break-inside-avoid flex-col items-end"><div className="text-center"><img alt={`Tanda tangan ${visit.examination.signedBy ?? visit.doctor}`} className="mx-auto h-20 max-w-48 bg-white object-contain" src={visit.examination.signatureDataUrl} /><p className="mt-1 text-sm font-medium">{visit.examination.signedBy ?? visit.doctor}</p></div></div>}<details className="mt-3 print:hidden"><summary className="cursor-pointer text-sm font-semibold text-neutral-700">Lihat riwayat versi pemeriksaan</summary><div className="mt-3 space-y-3">{visit.examination.versions.map((version) => <div className="rounded-lg bg-neutral-50 p-3 text-sm" key={version.version}><p className="font-semibold text-neutral-900">Versi {version.version} · {version.kind === 'addendum' ? 'Addendum' : 'Final'} <span className="font-normal text-neutral-600">· {version.actor ?? 'Dokter'} · {version.recordedAt ?? 'Belum diisi'}</span></p>{version.reason && <p className="mt-1 text-neutral-700">Alasan: {version.reason}</p>}<p className="mt-2 whitespace-pre-wrap text-neutral-700"><strong>Anamnesis:</strong> {version.anamnesis || 'Belum diisi'}</p><p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Riwayat penyakit sekarang:</strong> {version.currentHistory || 'Belum diisi'}</p><p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Riwayat penyakit dahulu:</strong> {version.pastHistory || 'Belum diisi'}</p><p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Pemeriksaan fisik:</strong> {version.physicalExamination || 'Belum diisi'}</p>{visit.clinicType === 'umum' && recordedDoctorRows(version.generalClinicalSummary).map(([label, value]) => <MedicalNote key={label} label={label} value={value} />)}{recordedDoctorRows(version.physicalSystems).map(([system, value]) => <p className="mt-1 whitespace-pre-wrap text-neutral-700" key={system}><strong>{system}:</strong> {value}</p>)}{visit.clinicType === 'umum' && <div className="mt-4"><BodyExaminationRecord data={version.bodyMap} gender={patient.gender} /></div>}{visit.clinicType === 'gigi' && version.extraoralExamination && <p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Ekstraoral:</strong> {version.extraoralExamination}</p>}{visit.clinicType === 'gigi' && typeof version.oralHygieneIndex === 'number' && <p className="mt-1 text-neutral-700"><strong>OHIS:</strong> {version.oralHygieneIndex}</p>}<p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Diagnosis banding:</strong> {version.differentialDiagnosis || 'Belum diisi'}</p><p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Terapi nonfarmakologis:</strong> {version.nonPharmacological || 'Tidak terdokumentasi pada catatan ini'}</p><p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Edukasi / anjuran:</strong> {version.education || 'Tidak terdokumentasi pada catatan ini'}</p></div>)}</div></details></section>}
        </CardContent></Card>)}</div> : <Card><CardContent className="p-5"><Empty className="border-0 bg-transparent" description={pagination.total === 0 ? 'Riwayat pemeriksaan, resep, dan tindakan pasien akan muncul setelah kunjungan tercatat.' : 'Ubah filter pencarian untuk melihat kunjungan lainnya.'} title={pagination.total === 0 ? 'Belum ada riwayat rekam medis' : 'Tidak ada kunjungan yang cocok'} /></CardContent></Card>}
        <div className="print:hidden"><Pagination pagination={pagination} /></div>
    </div></>;
}

type PrintRow = [string, string | null | undefined];

function recordedDoctorRows(summary?: Record<string, string | null>): [string, string][] {
    return Object.entries(summary ?? {}).flatMap(([label, value]) => {
        const answer = normalizeClinicalAnswer(value);
        return answer ? [[label, answer]] : [];
    });
}

function doctorSummaryRows(examination: Examination) {
    return recordedDoctorRows(examination.generalClinicalSummary).filter(([label]) => !['Riwayat sosial', 'Kondisi akhir', 'Status akhir'].includes(label));
}

function socialHistoryRow(examination: Examination) {
    return recordedDoctorRows(examination.generalClinicalSummary).filter(([label]) => label === 'Riwayat sosial');
}

function finalEvaluationRows(examination: Examination) {
    const dispositionLabels: Record<string, string> = { pulang: 'Pulang', observasi: 'Observasi', rujukan: 'Rujukan' };
    return recordedDoctorRows(examination.generalClinicalSummary)
        .filter(([label]) => label === 'Kondisi akhir' || label === 'Status akhir')
        .map(([label, value]): [string, string] => [label, label === 'Status akhir' ? dispositionLabels[value] ?? value : value]);
}

function PrintTable({ title, rows }: { title: string; rows: PrintRow[] }) {
    const recorded = rows.filter(([, value]) => value != null && value.trim() !== '');
    if (!recorded.length) return null;

    return <table className="rme-print-table">
        <colgroup><col className="rme-print-label" /><col /></colgroup>
        <thead><tr><th colSpan={2} scope="colgroup">{title}</th></tr></thead>
        <tbody>{recorded.map(([label, value], index) => <tr key={index} className={value && (value.length > 1600 || value.split('\n').length > 20) ? 'rme-print-long-row' : undefined}><th scope="row">{label}</th><td>{value}</td></tr>)}</tbody>
    </table>;
}

function PrintIdentity({ patient, visit }: { patient: Patient; visit: Visit }) {
    const patientDetails: PrintRow[] = [
        ['NIK', patient.nik],
        ['Jenis kelamin', patient.gender === 'L' ? 'Laki-laki' : patient.gender === 'P' ? 'Perempuan' : null],
        ['Tempat dan tanggal lahir', [patient.birthPlace, patient.birthDate].filter(Boolean).join(', ')],
        ['Usia', patient.age == null ? null : `${patient.age} tahun`],
        ['Golongan darah', patient.bloodType], ['Agama', patient.religion], ['Unit kerja', patient.unit], ['Alamat', patient.address],
    ];
    return <section className="rme-print-identity" aria-label="Identitas pasien dan kunjungan">
        <header className="rme-print-identity-heading">
            <div><p>Identitas pasien</p><h2>{patient.name}</h2></div>
            <div><p>Nomor rekam medis</p><strong>{patient.medicalRecordNumber}</strong></div>
        </header>
        <dl className="rme-print-identity-grid">{patientDetails.filter(([, value]) => value).map(([label, value]) =>
            <div className={label === 'Alamat' ? 'rme-print-address' : undefined} key={label}><dt>{label}</dt><dd>{value}</dd></div>,
        )}</dl>
        <dl className="rme-print-identity-grid rme-print-visit-meta">
            <div><dt>Kunjungan</dt><dd>{visit.number}</dd></div>
            <div><dt>Tanggal</dt><dd>{visit.date}</dd></div>
            <div><dt>Poliklinik</dt><dd>{visit.clinic}</dd></div>
            <div><dt>Dokter</dt><dd>{visit.doctor}</dd></div>
        </dl>
        {patient.allergies && <p className="rme-print-identity-allergy"><strong>Riwayat alergi:</strong> {patient.allergies}</p>}
    </section>;
}

function PrintBodyMaps({ data }: { data: BodyMapData }) {
    const groups = new Map<string, Array<{ finding: BodyMapData['findings'][number]; number: number }>>();
    data.findings.forEach((finding, index) => {
        if (!findingLocation(finding)) return;
        const key = [finding.mode, finding.view, finding.model].join('-');
        const group = groups.get(key) ?? [];
        group.push({ finding, number: index + 1 });
        groups.set(key, group);
    });
    if (!groups.size) return null;

    return <section className="rme-print-maps" aria-label="Peta tubuh pemeriksaan">
        <h2>Peta tubuh pemeriksaan</h2>
        <div className="rme-print-map-grid">{[...groups].map(([key, items]) => {
            const { mode, view, model } = items[0].finding;
            const labels = new Map<string, number[]>();
            for (const { finding, number } of items) {
                labels.set(finding.locationId, [...(labels.get(finding.locationId) ?? []), number]);
            }
            const markers = [...labels].map(([locationId, numbers]) => ({ locationId, label: numbers.join(',') }));
            const modelName = model === 'female' ? 'perempuan' : 'laki-laki';
            const viewName = view === 'front' ? 'tampak depan' : 'tampak belakang';
            return <section className="rme-print-figure rme-print-map-card rme-print-body-map" key={key}>
                <header className="rme-print-map-heading">
                    <h3>{mode === 'organs' ? 'Peta organ' : 'Peta seluruh tubuh'}</h3>
                    <p>Model {modelName}, {viewName}. Kanan dan kiri mengikuti sisi pasien.</p>
                </header>
                <div className="rme-print-map-stage">
                    <HumanBodyModel mode={mode} view={view} model={model} selectedId="" highlightedIds={[...labels.keys()]} markers={markers} disabled onSelect={() => undefined} />
                </div>
                <p className="rme-print-map-detail">{items.length} temuan tercatat. Nomor pada peta sesuai dengan tabel temuan lokasi.</p>
            </section>;
        })}</div>
    </section>;
}

function MedicalRecordPrint({ clinic, patient, visits }: { clinic?: ClinicProfile; patient: Patient; visits: Visit[] }) {
    return <article className="print-only rme-print-document" aria-label="Dokumen cetak rekam medis">
        {visits.map(visit => {
            const { examination: exam, screening, prescriptions, treatments } = visit;
            const bodyMap = exam?.bodyMap;
            const report = screening?.summary ?? {};
            return <section className="rme-print-episode" key={visit.id}>
                <ClinicalDocumentHeader clinic={clinic} />
                <h1>Rekam medis rawat jalan</h1>
                <p className="rme-print-confidential">Dokumen rahasia</p>
                <PrintIdentity patient={patient} visit={visit} />
                {!exam?.signedAt && <p className="rme-print-alert">Draf · belum difinalisasi dokter</p>}
                {exam?.integrityValid === false && <p className="rme-print-alert">Integritas riwayat catatan tidak valid. Gunakan sumber klinis terverifikasi.</p>}
                {screening && <>
                    <PrintTable title="Tanda vital dan pengukuran" rows={[
                        ['Tekanan darah', screening.systolic != null || screening.diastolic != null ? bloodPressure(screening.systolic, screening.diastolic) : null],
                        ['Nadi', screening.pulse == null ? null : withUnit(screening.pulse, 'kali/menit')],
                        ['Suhu', screening.temperature == null ? null : withUnit(screening.temperature, '°C')],
                        ['Frekuensi napas', screening.respiration == null ? null : withUnit(screening.respiration, 'kali/menit')],
                        ['SpO₂', screening.oxygenSaturation == null ? null : withUnit(screening.oxygenSaturation, '%')],
                        ['Berat badan', screening.weight == null ? null : withUnit(screening.weight, 'kg')],
                        ['Tinggi badan', screening.height == null ? null : withUnit(screening.height, 'cm')],
                        ['IMT', screening.bmi == null ? null : withUnit(screening.bmi, 'kg/m²')],
                    ]} />
                    <PrintTable title="Pengkajian awal perawat" rows={[
                        ['Petugas', screening.recordedBy], ['Waktu dikirim', screening.submittedAt],
                        ['Keluhan utama', normalizeClinicalAnswer(screening.complaint)],
                        ['Riwayat kesehatan', normalizeClinicalAnswer(screening.medicalHistory)],
                        ['Riwayat keluarga', normalizeClinicalAnswer(screening.familyHistory)],
                        ['Riwayat alergi', normalizeClinicalAnswer(screening.allergyHistory)],
                        ['Risiko jatuh', screening.fallRisk], ['Skala nyeri', screening.painScale == null ? null : `${screening.painScale}/10`],
                        ...Object.entries(report).filter(([label, value]) => value != null && !['Keluhan utama', 'Keluhan', 'Riwayat penyakit', 'Riwayat penyakit keluarga', 'Riwayat alergi', 'Skala nyeri', 'Risiko jatuh'].includes(label)),
                    ]} />
                </>}
                {screening && visit.clinicType === 'gigi' && <PrintTable title="Keluhan dan riwayat gigi" rows={[
                    ['Lokasi nyeri', report['Lokasi keluhan / nyeri'] ? null : normalizeClinicalAnswer(screening.dentalPainLocation)],
                    ['Pemicu nyeri', report['Pemicu keluhan'] ? null : displayCodes(screening.dentalPainTriggers ?? [])],
                    ['Durasi keluhan', report['Pola / lama keluhan'] ? null : normalizeClinicalAnswer(screening.dentalPainDuration)],
                    ['Risiko medis', report['Kondisi kesehatan yang perlu diketahui dokter gigi'] ? null : displayCodes(screening.dentalMedicalRisks ?? [])],
                    ['Riwayat infeksi', report['Riwayat infeksi atau bengkak'] ? null : displayCodes(screening.dentalInfectionHistory ?? [])],
                    ['Catatan tambahan', report['Catatan untuk dokter gigi'] ? null : normalizeClinicalAnswer(screening.dentalNotes)],
                ]} />}
                {exam && <>
                    <PrintTable title="Anamnesis dokter" rows={[
                        ['Keluhan utama', normalizeClinicalAnswer(exam.anamnesis)],
                        ['Riwayat penyakit sekarang', normalizeClinicalAnswer(exam.currentHistory)],
                        ['Riwayat penyakit dahulu', normalizeClinicalAnswer(exam.pastHistory)],
                        ['Riwayat keluarga', normalizeClinicalAnswer(exam.familyHistory)],
                        ['Riwayat alergi', normalizeClinicalAnswer(exam.allergyHistory)],
                        ['Obat yang digunakan', exam.riwayat_obat], ['Riwayat perawatan gigi', exam.riwayat_perawatan_gigi],
                        ['Kebiasaan terkait gigi dan mulut', exam.kebiasaan_gigi], ...socialHistoryRow(exam),
                    ]} />
                    <PrintTable title={visit.clinicType === 'gigi' ? 'Pemeriksaan gigi dan mulut' : 'Pemeriksaan fisik dokter'} rows={[
                        [visit.clinicType === 'gigi' ? 'Pemeriksaan intraoral' : 'Ringkasan pemeriksaan', normalizeClinicalAnswer(exam.physicalExamination)],
                        ['Ringkasan odontogram kunjungan', normalizeClinicalAnswer(exam.odontogramSummary)],
                        ['Pemeriksaan ekstraoral', exam.extraoralExamination === 'Belum diperiksa' ? null : normalizeClinicalAnswer(exam.extraoralExamination)],
                        ['OHIS', exam.oralHygieneIndex == null ? null : String(exam.oralHygieneIndex)],
                        ...doctorSummaryRows(exam), ...recordedDoctorRows(exam.physicalSystems),
                        ['Fokus pemeriksaan gigi terbatas', exam.dentalScope?.dental_focus],
                        ['Alasan pembatasan', exam.dentalScope?.dental_scope_reason], ['Odontogram awal', exam.dentalScope?.dental_prior_chart],
                        ['Rencana pemeriksaan gigi terbatas', exam.dentalScope?.dental_follow_up_plan],
                        ['Keputusan eskalasi DPJP', dentalEscalationLabels[exam.dentalEscalation?.dental_escalation_decision ?? '']],
                        ['Temuan dan tindak lanjut eskalasi', exam.dentalEscalation?.dental_escalation_note],
                    ]} />
                    {visit.clinicType === 'umum' && bodyMap && <PrintBodyMaps data={bodyMap} />}
                    <PrintTable title="Temuan lokasi dan anotasi" rows={[
                        ...(bodyMap?.findings ?? []).map((finding, index): PrintRow => [
                            `${index + 1}. ${findingLocation(finding)?.label ?? finding.locationLabel ?? 'Pemeriksaan fisik'}`,
                            [
                                finding.side && `Sisi: ${finding.side}`, finding.detail && `Lokasi rinci: ${finding.detail}`,
                                finding.method && [finding.method, finding.methodDetail].filter(Boolean).join(' · '),
                                finding.symptom && `Keluhan lokal: ${finding.symptom}`, finding.finding,
                                finding.pain != null && `Nyeri: ${finding.pain}/10`,
                                finding.annotationNote && `Keterangan anotasi: ${finding.annotationNote}`,
                            ].filter(Boolean).join('\n'),
                        ]),
                        ['Keterangan anotasi', recordedAnnotationNote(bodyMap)],
                    ]} />
                    {!!bodyMap?.annotation.strokes.length && <section className="rme-print-figure rme-print-annotation">
                        <h2>Gambar anotasi pemeriksaan</h2>
                        <BodyAnnotationView value={{ strokes: bodyMap.annotation.strokes, note: '' }} />
                    </section>}
                    <PrintTable title="Diagnosis" rows={[
                        ...exam.diagnoses.map((item): PrintRow => [
                            [item.type === 'utama' ? 'Utama' : 'Tambahan', item.code, item.codeSystem].filter(Boolean).join(' · '),
                            [item.name, item.toothFdi && `Gigi ${item.toothFdi} ${dentalSurfaceLabel(item.surfaces ?? [])}`, dentalDiagnosisDescription(item)].filter(Boolean).join('\n'),
                        ]),
                        ['Diagnosis banding', normalizeClinicalAnswer(exam.differentialDiagnosis)],
                    ]} />
                    <PrintTable title="Rencana tindak lanjut dan evaluasi akhir" rows={[
                        ['Terapi nonfarmakologis', exam.nonPharmacological], ['Edukasi', normalizeClinicalAnswer(exam.education)],
                        ['Catatan dokter', normalizeClinicalAnswer(exam.notes)], ['Kontrol berikutnya', exam.nextControl], ...finalEvaluationRows(exam),
                    ]} />
                </>}
                {visit.clinicType === 'gigi' && visit.odontogram.length > 0 && <div className="rme-print-map-grid">{(['adult', 'primary'] as const).map(dentition => {
                    const findings = visit.odontogram.filter(item => dentition === 'primary' ? Number(item.toothFdi) >= 50 : Number(item.toothFdi) < 50);
                    return findings.length > 0 && <section className="rme-print-figure rme-print-map-card rme-print-odontogram" key={dentition}>
                        <OdontogramFdi dentition={dentition} findings={findings} selectedTooth="" disabled recordView printView onSelect={() => undefined} />
                    </section>;
                })}</div>}
                <PrintTable title="Odontogram" rows={visit.odontogram.map(item => [
                    `Gigi ${item.toothFdi} · ${dentalSurface(item.surface)}`,
                    [dentalFinding(item.findingCode), item.detailSummary, item.notes].filter(Boolean).join('\n'),
                ])} />
                <PrintTable title="Resep obat" rows={prescriptions.map(item => [
                    item.name, [`Jumlah: ${formatNumber(Number(item.quantity))} ${item.unit ?? ''}`.trim(), item.instructions].filter(Boolean).join('\n'),
                ])} />
                <PrintTable title="Tindakan medis" rows={treatments.map(item => [
                    item.name, [item.code && [item.codeSystem ?? 'ICD-9-CM', item.code, item.codeRelease].filter(Boolean).join(' '), item.toothFdi && `Gigi ${item.toothFdi}`, `Jumlah: ${item.quantity}`, item.note].filter(Boolean).join('\n'),
                ])} />
                <PrintTable title="Pelaksanaan tindakan" rows={(visit.procedureExecutions ?? []).map(item => [
                    item.name, [item.role === 'perawat' ? 'Perawat' : 'Dokter', item.status === 'completed' ? [item.performedBy, item.performedAt].filter(Boolean).join(' · ') : item.status === 'pending' ? 'Menunggu pelaksanaan' : 'Waktu dan petugas tidak tercatat', item.result].filter(Boolean).join('\n'),
                ])} />
                <PrintTable title="Daftar lampiran pasien" rows={(visit.attachments ?? []).map(item => [item.name, item.note || 'Berkas terlampir pada rekam medis'])} />
                {exam?.signedAt && <div className="rme-print-signature">
                    <h2>Finalisasi pemeriksaan</h2>
                    <p>{exam.signedAt}</p>
                    {exam.integrityValid === true && exam.signatureDataUrl && <img src={exam.signatureDataUrl} alt={`Tanda tangan ${exam.signedBy ?? visit.doctor}`} />}
                    <p><strong>{exam.signedBy ?? visit.doctor}</strong></p>
                </div>}
            </section>;
        })}
    </article>;
}

const dentalEscalationLabels: Record<string, string> = {
    deferred: 'Tindakan ditunda',
    stabilized: 'Penanganan awal sesuai kemampuan klinik',
    referred: 'Koordinasi penilaian lanjutan atau rujukan',
    reassessed: 'Dinilai ulang dan dapat melanjutkan',
};

function SectionTitle({ children }: { children: string }) { return <h3 className="mb-3 text-xs font-bold uppercase tracking-wider text-neutral-500">{children}</h3>; }
function IdentityDetail({ label, value, className = '' }: { label: string; value: string; className?: string }) {
    return <div className={`min-w-0 rounded-xl bg-neutral-50 px-4 py-3 text-left ${className}`}>
        <p className="text-xs font-medium text-neutral-500">{label}</p>
        <p className="mt-1 break-words text-sm font-semibold text-neutral-900">{value}</p>
    </div>;
}
function Vital({ label, value }: { label: string; value: string }) { return <div className="min-w-0 rounded-xl border border-neutral-200 bg-neutral-50 p-3 text-center"><p className="text-xs text-neutral-500">{label}</p><p className="mt-1 break-words text-sm font-semibold text-neutral-800">{value}</p></div>; }
function MedicalNote({ label, value }: { label: string; value: string | null }) { return <div className="min-w-0 space-y-1.5"><p className="text-xs font-medium text-neutral-500">{label}</p><p className="whitespace-pre-wrap break-words text-sm text-neutral-800 [overflow-wrap:anywhere]">{displayOptionalValue(value)}</p></div>; }
function AssessmentGroup({ title, items, grouped = false }: { title: string; items: Array<[string, string | null]>; grouped?: boolean }) {
    const recordedItems = items.filter(([, value]) => value != null && !['', '-', '\u2014', 'belum dicatat', 'belum diisi'].includes(value.trim().toLocaleLowerCase('id-ID')));
    if (!recordedItems.length) return null;
    const categories = [
        { title: 'Keluhan dan nyeri', matches: /keluhan|nyeri|pemicu/i },
        { title: 'Riwayat kesehatan, alergi dan obat', matches: /riwayat|penyakit|alergi|obat|infeksi|kondisi sistemik|kondisi khusus|sumber informasi/i },
        { title: 'Observasi gigi dan mulut', matches: /mukosa|mulut|gigi|ekstraoral|kebiasaan/i },
        { title: 'Kondisi awal dan pengukuran', matches: /./ },
    ];
    const groups = grouped ? categories.map((category, index) => ({
        title: category.title,
        items: recordedItems.filter(([label]) => categories.findIndex(({ matches }) => matches.test(label)) === index),
    })).filter((group) => group.items.length > 0) : [{ title: '', items: recordedItems }];
    return <section className="min-w-0 w-full rounded-xl border border-neutral-200 p-4">
        <SectionTitle>{title}</SectionTitle>
        <div className="space-y-4">
            {groups.map((group) => <div className="min-w-0 space-y-3" key={group.title}>
                {group.title && <h4 className="border-b border-neutral-100 pb-2 text-sm font-semibold text-neutral-900">{group.title}</h4>}
                <dl className="grid min-w-0 gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-3">
                    {group.items.map(([label, value]) => <div className="min-w-0 break-inside-avoid" key={label}>
                        <dt className="text-xs font-medium text-neutral-500">{label}</dt>
                        <dd className="mt-1 whitespace-pre-wrap break-words text-sm text-neutral-800 [overflow-wrap:anywhere]">{displayOptionalValue(value)}</dd>
                    </div>)}
                </dl>
            </div>)}
        </div>
    </section>;
}

function displayCodes(values: string[]): string | null {
    const labels: Record<string, string> = { dingin: 'Dingin', manis: 'Manis', mengunyah: 'Saat mengunyah', panas: 'Panas', spontan: 'Spontan', lainnya: 'Lainnya', hipertensi: 'Hipertensi', diabetes: 'Diabetes melitus', penyakit_jantung: 'Penyakit jantung', gangguan_pembekuan: 'Gangguan pembekuan darah', antikoagulan: 'Antikoagulan', hepatitis: 'Hepatitis', hiv: 'HIV', infeksi_lain: 'Infeksi lain', tidak_ada: 'Tidak ada, telah dikaji', belum_diketahui: 'Belum dapat diketahui' };
    return values.length ? values.map((value) => labels[value] ?? value).join(', ') : null;
}
function withUnit(value: number | null, unit: string): string { return value === null ? 'Belum diukur' : `${value} ${unit}`; }
function bloodPressure(systolic: number | null, diastolic: number | null): string {
    if (systolic === null && diastolic === null) {
        return 'Belum diukur';
    }

    return `${systolic ?? 'Belum diukur'}/${diastolic ?? 'Belum diukur'} mmHg`;
}
function bodyMetrics(weight: number | null, height: number | null, bmi: number | null): string {
    const weightValue = weight === null ? 'Belum diukur' : `${weight} kg`;
    const heightValue = height === null ? 'Belum diukur' : `${height} cm`;
    const bmiValue = bmi === null ? 'Belum dihitung' : String(bmi);

    return `${weightValue} / ${heightValue} · IMT ${bmiValue}`;
}
function dentalSurface(code: string): string { return ({ W: 'Seluruh gigi', M: 'Mesial', O: 'Oklusal', D: 'Distal', V: 'Vestibular', L: 'Lingual' } as Record<string, string>)[code] ?? code; }
function dentalFinding(code: string): string { return ({ sound: 'Sehat', caries: 'Karies', missing: 'Hilang', unerupted: 'Belum erupsi', fractured_crown: 'Fraktur mahkota', root_remnant: 'Sisa akar', restored: 'Restorasi' } as Record<string, string>)[code] ?? code; }
