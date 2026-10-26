import { BodyExaminationRecord } from '@/components/anatomy/body-examination-record';
import type { BodyMapData } from '@/lib/body-map';
import { AllergyHistory } from '@/components/kunjungan/allergy-history';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { ArrowDown, ArrowLeft, ArrowUp, Printer, Search } from 'lucide-react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatNumber } from '@/lib/format';
import { displayOptionalValue } from '@/lib/optional-answers';
import { VisitAttachments, type ClinicalAttachment } from '@/components/kunjungan/visit-attachments';

interface Diagnosis { code: string | null; codeSystem?: string | null; name: string; note?: string | null; type: string }
interface Screening { summary?: Record<string, string | null>; systolic: number | null; diastolic: number | null; pulse: number | null; temperature: number | null; oxygenSaturation: number | null; weight: number | null; height: number | null; bmi: number | null; respiration: number | null; complaint: string | null; medicalHistory: string | null; familyHistory: string | null; allergyHistory: string | null; fallRisk: string | null; painScale: number | null; dentalPainLocation: string | null; dentalPainTriggers: string[]; dentalPainDuration: string | null; dentalMedicalRisks: string[]; dentalInfectionHistory: string[]; dentalNotes: string | null }
interface DentalFinding { toothFdi: string; surface: string; findingCode: string; notes: string | null }
interface Examination { anamnesis: string | null; currentHistory: string | null; pastHistory: string | null; familyHistory: string | null; allergyHistory: string | null; physicalExamination: string | null; physicalSystems: Record<string, string>; bodyMap?: BodyMapData | null; extraoralExamination: string | null; oralHygieneIndex: number | null; differentialDiagnosis: string | null; education: string | null; notes: string | null; nextControl: string | null; signedAt: string | null; signedBy: string | null; signatureDataUrl?: string | null; integrityValid: boolean | null; versions: Array<{ version: number; kind: string; reason: string | null; actor: string | null; recordedAt: string | null; anamnesis: string | null; currentHistory: string | null; pastHistory: string | null; familyHistory: string | null; allergyHistory: string | null; physicalExamination: string | null; physicalSystems: Record<string, string>; bodyMap?: BodyMapData | null; extraoralExamination: string | null; oralHygieneIndex: number | null; differentialDiagnosis: string | null; odontogram: DentalFinding[] }>; diagnoses: Diagnosis[] }
interface Prescription { id: number; name: string; quantity: string | number; unit: string | null; instructions: string | null }
interface Treatment { id: number; name: string; code: string | null; codeSystem: string | null; codeRelease: string | null; quantity: number; toothFdi: string | null; note: string | null }
interface Visit { id: number; number: string; clinic: string; clinicType: string | null; doctor: string; date: string | null; status: string; examinationStarted?: boolean; isReservation?: boolean; screening: Screening | null; examination: Examination | null; prescriptions: Prescription[]; treatments: Treatment[]; odontogram: DentalFinding[]; attachments?: ClinicalAttachment[] }
interface Patient { id: number; name: string; medicalRecordNumber: string; nik: string | null; gender: string | null; age: number | null; bloodType: string | null; allergies: string | null }
interface Props { patient: Patient; visits: Visit[]; backUrl: string; canSelectClinic: boolean; filters: { search: string; poliklinik_id: string | number; status: string; dari: string; sampai: string }; clinics: Array<{ id: number; name: string }>; pagination: PaginationData }

export default function PatientMedicalRecord({ patient, visits, backUrl, canSelectClinic, filters, clinics, pagination }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [clinicId, setClinicId] = useState(String(filters.poliklinik_id));
    const [status, setStatus] = useState(filters.status);
    const [from, setFrom] = useState(filters.dari);
    const [to, setTo] = useState(filters.sampai);
    const [expandedVisitId, setExpandedVisitId] = useState<number | null>(null);

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

    return <><Head title={`Rekam Medis ${patient.name}`} /><div className="min-w-0 w-full space-y-6"><div className="flex flex-col justify-between gap-3 print:hidden sm:flex-row sm:items-center"><Button onClick={returnToPreviousPage} size="sm" variant="ghost"><ArrowLeft className="size-4" />Kembali</Button><Button onClick={() => window.print()}><Printer className="size-4" />Cetak rekam medis</Button></div>
        <Card className="medical-record-print">
            <CardHeader className="items-start text-left">
                <p className="text-xs font-semibold uppercase tracking-wider text-neutral-700">Berkas rekam medis pasien</p>
                <CardTitle className="break-words text-2xl">{patient.name}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 pt-0">
                <div className="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <IdentityDetail label="Nomor rekam medis" value={patient.medicalRecordNumber} />
                    <IdentityDetail label="NIK" value={patient.nik || 'Belum diisi'} />
                    <IdentityDetail label="Jenis kelamin" value={patient.gender === 'L' ? 'Laki-laki' : patient.gender === 'P' ? 'Perempuan' : 'Belum diketahui'} />
                    <IdentityDetail label="Usia" value={patient.age === null ? 'Belum diketahui' : `${patient.age} tahun`} />
                    <IdentityDetail className="sm:col-span-2 xl:col-span-1" label="Golongan darah" value={patient.bloodType || 'Belum diketahui'} />
                </div>
                <AllergyHistory value={patient.allergies} />
            </CardContent>
        </Card>
        <div className="print:hidden"><FilterPanel><form className={`grid gap-4 sm:grid-cols-2 ${canSelectClinic ? 'xl:grid-cols-4' : 'xl:grid-cols-3'}`} onSubmit={applyFilters}>
                <Field htmlFor="medical-record-search" label="Kata kunci"><div className="relative"><Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-neutral-500" /><Input className="pl-9" id="medical-record-search" onChange={(event) => setSearch(event.target.value)} placeholder="No. kunjungan, keluhan, diagnosis…" value={search} /></div></Field>
                {canSelectClinic && <Field htmlFor="medical-record-clinic" label="Poliklinik"><Select id="medical-record-clinic" onChange={(event) => setClinicId(event.target.value)} value={clinicId}><option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>}
            <Field htmlFor="medical-record-status" label="Status kunjungan"><Select id="medical-record-status" onChange={(event) => setStatus(event.target.value)} value={status}><option value="">Semua status</option><option value="menunggu">Menunggu pengkajian awal</option><option value="screening">Pengkajian awal berlangsung</option><option value="pemeriksaan">Menunggu / sedang diperiksa</option><option value="farmasi">Pelayanan farmasi</option><option value="kasir">Perlu penyelesaian kunjungan</option><option value="selesai">Selesai</option><option value="batal">Dibatalkan</option></Select></Field>
                <Field htmlFor="medical-record-period" label="Periode kunjungan"><DateRangePicker id="medical-record-period" from={from} onChange={(range) => { setFrom(range.from); setTo(range.to); }} to={to} /></Field>
                <Button className="col-span-full" type="submit"><Search className="size-4" />Terapkan filter</Button>
            </form></FilterPanel></div>
        <div className="flex flex-wrap items-end justify-between gap-2"><div><h2 className="text-lg font-semibold text-neutral-900">Riwayat kunjungan</h2><p className="text-sm text-neutral-500">{pagination.total ? `${pagination.total.toLocaleString('id-ID')} kunjungan ditemukan` : 'Tidak ada kunjungan pada filter ini'}. Buka detail hanya pada kunjungan yang diperlukan.</p></div></div>
        {visits.length ? <div className="space-y-4">{visits.map((visit) => <Card className="medical-record-print min-w-0 w-full overflow-hidden" key={visit.id}><button aria-controls={`medical-record-visit-${visit.id}`} aria-expanded={expandedVisitId === visit.id} className="flex w-full flex-col gap-3 border-b border-neutral-100 bg-neutral-50 px-5 py-4 text-left transition hover:bg-neutral-100 sm:flex-row sm:items-center sm:justify-between" onClick={() => setExpandedVisitId((current) => current === visit.id ? null : visit.id)} type="button"><div className="min-w-0 space-y-2"><div className="flex flex-wrap items-center gap-2"><span className="font-mono text-sm font-bold text-neutral-900">{visit.number}</span><span className="rounded-full bg-surface px-2.5 py-1 text-xs font-semibold text-neutral-700">{visit.clinic}</span><StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} status={visit.status} /></div><p className="text-sm text-neutral-600">{visit.date ?? 'Belum diisi'} <span aria-hidden="true">·</span> Dokter: <strong>{visit.doctor}</strong></p><p className="line-clamp-2 text-sm text-neutral-700">{visit.screening?.complaint || visit.examination?.anamnesis || visit.examination?.currentHistory || visit.examination?.diagnoses[0]?.name || 'Keluhan dan ringkasan klinis belum dicatat.'}</p><p className="text-xs text-neutral-500">{visit.examination?.diagnoses.length ?? 0} diagnosis · {visit.prescriptions.length} obat · {visit.treatments.length} tindakan{visit.clinicType === 'gigi' ? ` · ${visit.odontogram.length} temuan odontogram` : ''}</p></div><span className="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-neutral-700 print:hidden">{expandedVisitId === visit.id ? 'Tutup detail' : 'Lihat detail'}{expandedVisitId === visit.id ? <ArrowUp className="size-4" /> : <ArrowDown className="size-4" />}</span></button><CardContent id={`medical-record-visit-${visit.id}`} className={`min-w-0 space-y-6 p-4 sm:p-6 print:block ${expandedVisitId === visit.id ? 'block' : 'hidden'}`}>
            {visit.examination?.integrityValid === false && <p className="rounded-xl border border-red-300 bg-red-50 p-4 text-sm font-semibold text-red-900">Integritas riwayat catatan ini tidak valid. Gunakan sumber klinis terverifikasi sebelum mengambil keputusan.</p>}
            {!!visit.attachments?.length && <section className="space-y-3"><SectionTitle>Lampiran pasien</SectionTitle><VisitAttachments attachments={visit.attachments} /></section>}

            {visit.clinicType === 'gigi' && visit.examination?.signedAt && <section><SectionTitle>Riwayat versi odontogram</SectionTitle><div className="space-y-2">{visit.examination.versions.map((version) => <div className="rounded-xl bg-neutral-50 p-3 text-sm" key={version.version}><p className="font-semibold text-neutral-900">Versi {version.version} · {version.kind === 'addendum' ? 'Addendum' : 'Final'}</p><p className="mt-1 text-neutral-700">{version.odontogram.length ? version.odontogram.map((finding) => `${finding.toothFdi} ${dentalSurface(finding.surface)}: ${dentalFinding(finding.findingCode)}`).join('; ') : 'Temuan tidak tercatat'}</p></div>)}</div></section>}
            {visit.screening && <section className="space-y-4"><div><SectionTitle>Tanda vital & pengkajian awal</SectionTitle><div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6"><Vital label="Tekanan darah" value={bloodPressure(visit.screening.systolic, visit.screening.diastolic)} /><Vital label="Nadi" value={withUnit(visit.screening.pulse, 'x/m')} /><Vital label="Suhu" value={withUnit(visit.screening.temperature, '°C')} /><Vital label="SpO₂" value={withUnit(visit.screening.oxygenSaturation, '%')} /><Vital label="Berat / tinggi" value={bodyMetrics(visit.screening.weight, visit.screening.height, visit.screening.bmi)} /><Vital label="Respirasi" value={withUnit(visit.screening.respiration, 'x/m')} /></div><p className="mt-3 whitespace-pre-wrap text-sm text-neutral-700"><strong>Keluhan utama:</strong> {displayOptionalValue(visit.screening.complaint, 'Belum dicatat')}</p></div>
                <div className="grid min-w-0 gap-4">
                    <AssessmentGroup title="Serah terima pengkajian awal" items={Object.entries(visit.screening.summary ?? {})} /><AssessmentGroup title={visit.clinicType === 'gigi' ? 'Riwayat medis' : 'Riwayat kesehatan'} items={[
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
                <section><SectionTitle>Anamnesis</SectionTitle><div className="grid min-w-0 gap-4 rounded-xl border border-neutral-200 p-4 text-sm sm:grid-cols-2 xl:grid-cols-3"><MedicalNote label="Keluhan utama" value={visit.examination.anamnesis} /><MedicalNote label="Riwayat penyakit sekarang" value={visit.examination.currentHistory} /><MedicalNote label="Riwayat penyakit dahulu" value={visit.examination.pastHistory} /><MedicalNote label="Riwayat keluarga" value={visit.examination.familyHistory} /><MedicalNote label="Riwayat alergi" value={visit.examination.allergyHistory} /></div></section>
                <section className="space-y-4"><SectionTitle>{visit.clinicType === 'gigi' ? 'Pemeriksaan gigi & mulut' : 'Pemeriksaan fisik'}</SectionTitle>
                    <div className="min-w-0 rounded-xl border border-neutral-200 bg-neutral-50 p-4"><MedicalNote label={visit.clinicType === 'gigi' ? 'Pemeriksaan intraoral' : 'Ringkasan pemeriksaan fisik'} value={visit.examination.physicalExamination} /></div>
                    {visit.clinicType === 'umum' && <BodyExaminationRecord data={visit.examination.bodyMap} systems={visit.examination.physicalSystems ?? {}} gender={patient.gender} />}
                    {Object.keys(visit.examination.physicalSystems ?? {}).length > 0 && <div className="grid min-w-0 gap-4 sm:grid-cols-2">{Object.entries(visit.examination.physicalSystems ?? {}).map(([system, value]) => <div className="min-w-0 rounded-xl border border-neutral-200 p-4" key={system}><MedicalNote label={system} value={value} /></div>)}</div>}
                    {visit.clinicType === 'gigi' && <div className="rounded-xl border border-neutral-200 p-4"><MedicalNote label="Pemeriksaan ekstraoral" value={visit.examination.extraoralExamination} />{typeof visit.examination.oralHygieneIndex === 'number' && <p className="mt-3 text-sm text-neutral-700">OHIS: {visit.examination.oralHygieneIndex}</p>}</div>}
                </section>
                <section className="space-y-4"><SectionTitle>Diagnosis</SectionTitle><div className="space-y-2">{visit.examination.diagnoses.length ? visit.examination.diagnoses.map((diagnosis, index) => <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-neutral-50 p-3 text-sm" key={diagnosis.code + '-' + index}><p className="min-w-0 break-words"><span className="mr-2 font-mono font-bold text-neutral-700">{diagnosis.code || 'Manual'}</span><span className="font-medium text-neutral-800">{diagnosis.name}</span></p><span className="shrink-0 rounded-full bg-surface px-2 py-1 text-xs capitalize text-neutral-700">{diagnosis.type}</span>{diagnosis.note && <p className="w-full whitespace-pre-wrap break-words text-neutral-600">{diagnosis.note}</p>}</div>) : <Empty className="min-h-0 px-3 py-5" description="Diagnosis pada kunjungan ini akan ditampilkan di sini." title="Belum ada diagnosis" />}</div><div className="rounded-xl border border-neutral-200 p-4"><MedicalNote label="Diagnosis banding" value={visit.examination.differentialDiagnosis} /></div></section>
                <section><SectionTitle>Rencana tindak lanjut & edukasi</SectionTitle><div className="grid min-w-0 gap-4 rounded-xl border border-neutral-200 p-4 sm:grid-cols-2"><MedicalNote label="Edukasi" value={visit.examination.education} /><MedicalNote label="Catatan dokter" value={visit.examination.notes} />{visit.examination.nextControl && <MedicalNote label="Kontrol berikutnya" value={visit.examination.nextControl} />}</div></section>
            </div>}
            {visit.clinicType === 'gigi' && <section><SectionTitle>Odontogram</SectionTitle>{visit.odontogram.length ? <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{visit.odontogram.map((finding) => <div className="rounded-xl border border-neutral-200 p-3 text-sm" key={`${finding.toothFdi}-${finding.surface}`}><p className="font-semibold text-neutral-900">Gigi {finding.toothFdi} · {dentalSurface(finding.surface)}</p><p className="mt-1 text-neutral-700">{dentalFinding(finding.findingCode)}</p>{finding.notes && <p className="mt-1 whitespace-pre-wrap text-neutral-600">{finding.notes}</p>}</div>)}</div> : <Empty className="min-h-0 w-full px-3 py-4" description="Temuan per gigi akan dicatat dokter saat pemeriksaan." title="Belum ada temuan odontogram" />}</section>}
            <div className="min-w-0 space-y-6"><section><SectionTitle>Resep obat</SectionTitle>{visit.prescriptions.length ? <div className="overflow-x-auto rounded-xl border border-neutral-100"><Table><TableHeader><tr><TableHead>Nama obat</TableHead><TableHead>Jumlah</TableHead><TableHead>Aturan pakai</TableHead></tr></TableHeader><TableBody>{visit.prescriptions.map((item) => <TableRow key={item.id}><TableCell className="font-medium">{item.name}</TableCell><TableCell className="whitespace-nowrap font-mono">{formatNumber(Number(item.quantity))} {item.unit ?? ''}</TableCell><TableCell>{item.instructions || 'Belum diisi'}</TableCell></TableRow>)}</TableBody></Table></div> : <Empty className="min-h-0 px-3 py-5" description="Obat yang diresepkan selama kunjungan akan muncul di sini." title="Belum ada resep obat" />}</section><section><SectionTitle>Tindakan medis</SectionTitle>{visit.treatments.length ? <div className="space-y-2">{visit.treatments.map((item) => <div className="flex justify-between gap-3 rounded-lg bg-neutral-50 px-3 py-2.5 text-sm" key={item.id}><div className="min-w-0"><p className="font-medium text-neutral-800">{item.name}{item.toothFdi && <span className="ml-2 text-neutral-500">Gigi {item.toothFdi}</span>}</p><p className="mt-1 text-xs text-neutral-500">{item.code ? <><span className="font-mono">{item.code}</span> · ICD-9-CM{item.codeRelease && ` · ${item.codeRelease}`}</> : 'Tanpa kode ICD-9-CM'}</p>{item.note && <p className="mt-1 whitespace-pre-wrap text-xs text-neutral-600">{item.note}</p>}</div><span className="shrink-0 text-neutral-500">{item.quantity}×</span></div>)}</div> : <Empty className="min-h-0 px-3 py-5" description="Tindakan yang tercatat selama kunjungan akan muncul di sini." title="Belum ada tindakan medis" />}</section></div>
            {visit.examination?.signedAt && <section className="rounded-xl border border-neutral-200 p-4"><SectionTitle>Finalisasi & riwayat koreksi</SectionTitle><p className="text-sm text-neutral-700">Difinalisasi oleh {visit.examination.signedBy ?? 'dokter'} pada {visit.examination.signedAt}.</p>{visit.examination.integrityValid === true && visit.examination.signatureDataUrl && <div className="mt-4 flex break-inside-avoid flex-col items-end"><div className="text-center"><img alt={`Tanda tangan ${visit.examination.signedBy ?? visit.doctor}`} className="mx-auto h-20 max-w-48 bg-white object-contain" src={visit.examination.signatureDataUrl} /><p className="mt-1 text-sm font-medium">{visit.examination.signedBy ?? visit.doctor}</p></div></div>}<details className="mt-3"><summary className="cursor-pointer text-sm font-semibold text-neutral-700">Lihat riwayat versi pemeriksaan</summary><div className="mt-3 space-y-3">{visit.examination.versions.map((version) => <div className="rounded-lg bg-neutral-50 p-3 text-sm" key={version.version}><p className="font-semibold text-neutral-900">Versi {version.version} · {version.kind === 'addendum' ? 'Addendum' : 'Final'} <span className="font-normal text-neutral-600">· {version.actor ?? 'Dokter'} · {version.recordedAt ?? 'Belum diisi'}</span></p>{version.reason && <p className="mt-1 text-neutral-700">Alasan: {version.reason}</p>}<p className="mt-2 whitespace-pre-wrap text-neutral-700"><strong>Anamnesis:</strong> {version.anamnesis || 'Belum diisi'}</p><p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Riwayat penyakit sekarang:</strong> {version.currentHistory || 'Belum diisi'}</p><p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Riwayat penyakit dahulu:</strong> {version.pastHistory || 'Belum diisi'}</p><p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Pemeriksaan fisik:</strong> {version.physicalExamination || 'Belum diisi'}</p>{Object.entries(version.physicalSystems ?? {}).map(([system, value]) => <p className="mt-1 whitespace-pre-wrap text-neutral-700" key={system}><strong>{system}:</strong> {value}</p>)}{visit.clinicType === 'umum' && <div className="mt-4"><BodyExaminationRecord data={version.bodyMap} systems={version.physicalSystems ?? {}} gender={patient.gender} /></div>}{visit.clinicType === 'gigi' && version.extraoralExamination && <p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Ekstraoral:</strong> {version.extraoralExamination}</p>}{visit.clinicType === 'gigi' && typeof version.oralHygieneIndex === 'number' && <p className="mt-1 text-neutral-700"><strong>OHIS:</strong> {version.oralHygieneIndex}</p>}<p className="mt-1 whitespace-pre-wrap text-neutral-700"><strong>Diagnosis banding:</strong> {version.differentialDiagnosis || 'Belum diisi'}</p></div>)}</div></details></section>}
        </CardContent></Card>)}</div> : <Card className="medical-record-print"><CardContent className="p-5"><Empty className="border-0 bg-transparent" description={pagination.total === 0 ? 'Riwayat pemeriksaan, resep, dan tindakan pasien akan muncul setelah kunjungan tercatat.' : 'Ubah filter pencarian untuk melihat kunjungan lainnya.'} title={pagination.total === 0 ? 'Belum ada riwayat rekam medis' : 'Tidak ada kunjungan yang cocok'} /></CardContent></Card>}
        <div className="print:hidden"><Pagination pagination={pagination} /></div>
    </div></>;
}

function SectionTitle({ children }: { children: string }) { return <h3 className="mb-3 text-xs font-bold uppercase tracking-wider text-neutral-500">{children}</h3>; }
function IdentityDetail({ label, value, className = '' }: { label: string; value: string; className?: string }) {
    return <div className={`min-w-0 rounded-xl bg-neutral-50 px-4 py-3 text-left ${className}`}>
        <p className="text-xs font-medium text-neutral-500">{label}</p>
        <p className="mt-1 break-words text-sm font-semibold text-neutral-900">{value}</p>
    </div>;
}
function Vital({ label, value }: { label: string; value: string }) { return <div className="min-w-0 rounded-xl border border-neutral-200 bg-neutral-50 p-3 text-center"><p className="text-xs text-neutral-500">{label}</p><p className="mt-1 break-words text-sm font-semibold text-neutral-800">{value}</p></div>; }
function MedicalNote({ label, value }: { label: string; value: string | null }) { return <div className="min-w-0 space-y-1.5"><p className="text-xs font-medium text-neutral-500">{label}</p><p className="whitespace-pre-wrap break-words text-sm text-neutral-800 [overflow-wrap:anywhere]">{displayOptionalValue(value)}</p></div>; }
function AssessmentGroup({ title, items }: { title: string; items: Array<[string, string | null]> }) {
    return <section className="min-w-0 rounded-xl border border-neutral-200 p-4">
        <SectionTitle>{title}</SectionTitle>
        <dl className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {items.map(([label, value]) => <div className="min-w-0" key={label}>
                <dt className="text-xs font-medium text-neutral-500">{label}</dt>
                <dd className="mt-1 whitespace-pre-wrap break-words text-sm text-neutral-800">{displayOptionalValue(value)}</dd>
            </div>)}
        </dl>
    </section>;
}
function displayCodes(values: string[]): string | null {
    const labels: Record<string, string> = { dingin: 'Dingin', manis: 'Manis', mengunyah: 'Saat mengunyah', panas: 'Panas', spontan: 'Spontan', lainnya: 'Lainnya', hipertensi: 'Hipertensi', diabetes: 'Diabetes melitus', penyakit_jantung: 'Penyakit jantung', gangguan_pembekuan: 'Gangguan pembekuan darah', antikoagulan: 'Antikoagulan', hepatitis: 'Hepatitis', hiv: 'HIV', infeksi_lain: 'Infeksi lain', tidak_ada: 'Tidak ada riwayat yang diketahui', belum_diketahui: 'Belum dapat diketahui' };
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
