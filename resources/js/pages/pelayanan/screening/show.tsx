import { AssociatedSymptoms } from '@/components/kunjungan/associated-symptoms';
import { ChoiceGroup } from '@/components/kunjungan/assessment-choice-group';
import { ScreeningVersionHistory, type ScreeningVersionSummary } from '@/components/kunjungan/screening-version-history';
import { GeneralNursingAssessment, type GeneralNursingData } from '@/components/kunjungan/general-assessment';
import { AssistanceReasonField } from '@/components/kunjungan/assistance-reason-field';
import { isNoAllergyReport } from '@/lib/allergy-status';
import { allTriageAnswersNormal, hasAbnormalTriageAnswer, quickNormalTriagePatch } from '@/lib/triage-quick-normal';
import type { Page } from '@inertiajs/core';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, ClipboardCheck, Play, Printer, Save, UserRound } from 'lucide-react';
import { useRef, useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { VisitAttachments, type ClinicalAttachment } from '@/components/kunjungan/visit-attachments';

type ScreeningValues = Record<ScalarField, string> & {
    mukosa_mulut: MucosaAnswers;
    kebiasaan_gigi_opsi: string[];
    screening_revision?: string;
    dental_vitals_unmeasurable: boolean;
    dental_medication_categories: string[];
    dental_red_flags: string[];
    pemicu_nyeri_gigi: string[];
    risiko_medis_gigi: string[];
    riwayat_infeksi_gigi: string[];
    general_assessment: GeneralNursingData;
    vitals_unmeasurable: boolean;
    konfirmasi_riwayat: boolean;
    assistance_reason: string;
};
const mucosaSites = { lidah: 'Lidah', pipi: 'Pipi', bibir: 'Bibir', palatum: 'Palatum', gusi: 'Gusi' };
type MucosaSite = keyof typeof mucosaSites;
type MucosaAnswers = Partial<Record<MucosaSite, string>> & { catatan?: Partial<Record<MucosaSite, string>> };
const mucosaOptions: Option[] = [['normal', 'Normal'], ['abnormal', 'Abnormal'], ['belum_dinilai', 'Belum dapat dinilai']];
const mucosaEntries = Object.entries(mucosaSites) as [MucosaSite, string][];

const scalarFields = ['revision_reason', 'status_riwayat_obat', 'kelainan_mukosa',"dental_red_flags_other","risiko_medis_gigi_lainnya","pemicu_nyeri_gigi_lainnya","riwayat_infeksi_gigi_lainnya","dental_medication_categories_other","alergi_keparahan","alergi_verifikasi","nyeri_karakter","nyeri_pereda","nyeri_dampak","riwayat_kondisi_khusus","observasi_mulut","kebiasaan_perawatan_mulut",'riwayat_perawatan_gigi', 'kebiasaan_gigi', 'extraoral_symmetry', 'extraoral_swelling', 'extraoral_notes', 'petugas_id', 'keluhan', 'keluhan_tambahan', 'lama_keluhan', 'sumber_informasi', 'td_sistole', 'td_diastole', 'nadi', 'suhu', 'respirasi', 'spo2', 'berat_badan', 'tinggi_badan', 'lingkar_perut', 'alasan_pengukuran_tidak_lengkap', 'riwayat_alergi', 'status_alergi', 'alergi_jenis', 'alergi_reaksi', 'status_riwayat_penyakit', 'riwayat_penyakit', 'penyakit_nama', 'penyakit_keterangan', 'riwayat_obat', 'riwayat_penyakit_keluarga', 'skala_nyeri', 'skrining_gizi', 'pemeriksaan_fisik', 'nyeri_dada', 'kejang', 'nadi_teraba', 'pola_pernapasan', 'kesadaran', 'kondisi_psikiatri', 'risiko_jatuh_visual', 'catatan_triase', 'lokasi_nyeri_gigi', 'durasi_keluhan_gigi', 'catatan_medis_gigi'] as const;
type ScalarField = typeof scalarFields[number];
const assessmentPlaceholders = {
    revision_reason: 'Jelaskan perubahan dan alasan revisi',
    alergi_keparahan: "Keparahan alergi jika diketahui",
    alergi_verifikasi: "Perbedaan alergi yang perlu diverifikasi",
    nyeri_karakter: "Karakter nyeri dan penjalarannya",
    nyeri_pereda: "Hal yang mengurangi nyeri",
    nyeri_dampak: "Dampak nyeri pada aktivitas atau tidur",
    riwayat_kondisi_khusus: "Kondisi khusus, operasi, atau rawat inap yang relevan",
    observasi_mulut: "Observasi awal mulut dan rahang",
    kebiasaan_perawatan_mulut: "Perawatan mulut dan kebiasaan sehari-hari",
    kelainan_mukosa: 'Lokasi, tampilan, dan kelainan yang terlihat saat observasi',
    keluhan: 'Keluhan utama yang disampaikan pasien',
    keluhan_tambahan: 'Keluhan lain yang menyertai keluhan utama',
    lama_keluhan: 'Contoh: sejak kemarin atau selama 3 hari',
    alasan_pengukuran_tidak_lengkap: 'Kendala pengukuran atau catatan pengkajian lainnya',
    riwayat_alergi: 'Obat, makanan, atau pemicu alergi yang diketahui',
    alergi_jenis: 'Nama obat, makanan, atau pemicu lainnya',
    alergi_reaksi: 'Reaksi yang dialami setelah terpapar pemicu',
    riwayat_penyakit: 'Penyakit yang pernah atau sedang dialami pasien',
    penyakit_nama: 'Nama penyakit yang telah diketahui',
    penyakit_keterangan: 'Sejak kapan, pengobatan, dan kondisi terakhir',
    riwayat_perawatan_gigi: 'Tambal, cabut, PSA, trauma, atau komplikasi anestesi. Catat waktu dan lokasi bila diketahui.',
    kebiasaan_gigi: 'Bruxism, merokok, mengunyah satu sisi, atau menggigit benda keras',
    riwayat_obat: 'Nama obat, dosis, dan frekuensi penggunaan yang diketahui',
    riwayat_penyakit_keluarga: 'Riwayat penyakit dan hubungan keluarga terkait',
    pemeriksaan_fisik: 'Temuan objektif yang diperiksa saat pengkajian',
    catatan_triase: 'Rincian temuan kondisi awal yang belum tercakup pada pilihan di atas',
    lokasi_nyeri_gigi: 'Lokasi gigi atau gusi serta sisi yang dikeluhkan',
    durasi_keluhan_gigi: 'Pola nyeri dan lama setiap episode',
    extraoral_notes: 'Lokasi, sisi, lama dan perkembangan pembengkakan / asimetri, kendala penilaian, serta komunikasi kepada dokter',
    catatan_medis_gigi: 'Informasi yang perlu dikonfirmasi kepada dokter gigi',
} satisfies Partial<Record<ScalarField, string>>;
const otherDetails = {"dental_red_flags": {"option": "other", "field": "dental_red_flags_other", "label": "Keterangan tanda bahaya lainnya"}, "risiko_medis_gigi": {"option": "lainnya", "field": "risiko_medis_gigi_lainnya", "label": "Keterangan kondisi sistemik lainnya"}, "pemicu_nyeri_gigi": {"option": "lainnya", "field": "pemicu_nyeri_gigi_lainnya", "label": "Keterangan pemicu lainnya"}, "riwayat_infeksi_gigi": {"option": "infeksi_lain", "field": "riwayat_infeksi_gigi_lainnya", "label": "Keterangan infeksi lainnya"}, "dental_medication_categories": {"option": "other", "field": "dental_medication_categories_other", "label": "Keterangan obat lainnya"}} as const;
type Option = [string, string];
interface Visit {
    submittedAt?: string | null; submittedBy?: string | null; reviewedAt?: string | null;
    screeningRevision?: string;
    oralHabitOptions?: Record<string, string>;
    medicationCategories?: Record<string, string>;
    attachments?: ClinicalAttachment[]; attachmentUploadUrl?: string;
    versions?: ScreeningVersionSummary[];
    id: number; number: string; status: string; examinationStarted: boolean; editable: boolean; submitted: boolean; clinic: string; clinicType: 'umum' | 'gigi'; doctor: string;
    patient: { name: string; medicalRecordNumber: string; gender: string | null; age: number | null; bloodType: string | null; allergyHistory: string | null };
    screening: Record<string, string | number | boolean | string[] | Record<string, string> | null>;
    vitalsRecordedAt?: string | null; triageRecordedAt?: string | null;
    triage: string | null; priority: string | null; printUrl: string | null; startedAt: string | null;
}
interface Props { visit: Visit; staff: { id: number; name: string; position: string }[]; canSelectStaff: boolean; currentStaff: string | null }
const sections = ['complaint', 'history', 'vitals', 'anthropometry', 'dental', 'triage', 'additional', 'ringkas', 'systems', 'nutrition', 'attachments'] as const;
type Section = typeof sections[number];
const triageFields: { name: ScalarField; label: string; options: Option[] }[] = [
    { name: 'nyeri_dada', label: 'Nyeri dada', options: [['tidak', 'Tidak'], ['ya', 'Ya']] },
    { name: 'kejang', label: 'Kejang', options: [['tidak', 'Tidak'], ['ya', 'Ya']] },
    { name: 'nadi_teraba', label: 'Nadi', options: [['teraba', 'Teraba'], ['tidak_teraba', 'Tidak teraba']] },
    { name: 'pola_pernapasan', label: 'Pola pernapasan', options: [['normal', 'Normal'], ['tidak_normal', 'Tidak normal']] },
    { name: 'kesadaran', label: 'Kesadaran', options: [['sadar', 'Sadar'], ['menurun', 'Menurun'], ['tidak_sadar', 'Tidak sadar']] },
    { name: 'kondisi_psikiatri', label: 'Kondisi psikiatri', options: [['normal', 'Normal'], ['terganggu', 'Terganggu']] },
    { name: 'risiko_jatuh_visual', label: 'Risiko jatuh visual', options: [['rendah', 'Rendah'], ['sedang', 'Sedang'], ['tinggi', 'Tinggi']] },
];
const historyOptions: Option[] = [['ada', 'Ada riwayat'], ['tidak_ada', 'Tidak ada, sudah ditanyakan'], ['belum_diketahui', 'Belum dapat diketahui']];

export default function ScreeningForm({ visit, staff, canSelectStaff, currentStaff }: Props) {
    const values = Object.fromEntries(scalarFields.map((key) => [key, visit.screening[key] == null ? '' : String(visit.screening[key])])) as Record<ScalarField, string>;
    const allergyHistory = visit.patient.allergyHistory?.trim() ?? '';
    const knownNoAllergy = isNoAllergyReport(allergyHistory);
    if (!values.status_alergi && allergyHistory) {
        values.status_alergi = knownNoAllergy ? 'tidak_ada' : 'ada';
    }
    if (!values.status_riwayat_penyakit && values.riwayat_penyakit) {
        values.status_riwayat_penyakit = /^(pasien menyangkal|tidak ada)/i.test(values.riwayat_penyakit.trim()) ? 'tidak_ada' : 'ada';
    }
    if (!values.status_riwayat_penyakit && Array.isArray(visit.screening.risiko_medis_gigi) && visit.screening.risiko_medis_gigi.some(value => !['tidak_ada', 'belum_diketahui'].includes(String(value)))) values.status_riwayat_penyakit = 'ada';
    if (values.status_riwayat_penyakit !== 'ada') {
        values.riwayat_penyakit = '';
        values.penyakit_nama = '';
        values.penyakit_keterangan = '';
    }
    const list = (key: string): string[] => Array.isArray(visit.screening[key]) ? visit.screening[key].map(String) : [];
    const savedGeneral = (visit.screening.general_assessment as GeneralNursingData | null) ?? {};
    const { systems: _savedSystems, ...dentalGeneral } = savedGeneral;
    const form = useForm<ScreeningValues>({ ...values, general_assessment: visit.clinicType === 'gigi' ? dentalGeneral : savedGeneral, vitals_unmeasurable: visit.screening.vitals_unmeasurable === true, status_riwayat_obat: values.status_riwayat_obat || (list('dental_medication_categories').includes('none') ? 'tidak_ada' : values.riwayat_obat || list('dental_medication_categories').some(v => !['unknown', 'none'].includes(v)) ? 'ada' : ''), mukosa_mulut: typeof visit.screening.mukosa_mulut === 'object' && !Array.isArray(visit.screening.mukosa_mulut) ? visit.screening.mukosa_mulut as MucosaAnswers ?? {} : {}, kebiasaan_gigi_opsi: list('kebiasaan_gigi_opsi'), dental_vitals_unmeasurable: visit.screening.dental_vitals_unmeasurable === true, dental_medication_categories: list('dental_medication_categories'), dental_red_flags: list('dental_red_flags'), pemicu_nyeri_gigi: list('pemicu_nyeri_gigi'), risiko_medis_gigi: list('risiko_medis_gigi'), riwayat_infeksi_gigi: list('riwayat_infeksi_gigi'), konfirmasi_riwayat: false, assistance_reason: '' });
    const [expanded, setExpanded] = useState<Record<Section, boolean>>({ triage: true, complaint: true, history: true, vitals: true, anthropometry: false, dental: false, additional: false, ringkas: true, systems: false, nutrition: false, attachments: false });
    const [attachmentPending, setAttachmentPending] = useState(false);
    const [starting, setStarting] = useState(false);
    const [mode, setMode] = useState('ringkas');
    const requestId = useRef<string | null>(null);
    const requestFingerprint = useRef('');
    const revision = useRef(visit.screeningRevision);
    const updateRevision = (page: Page) => {
        revision.current = (page.props.visit as Visit | undefined)?.screeningRevision;
    };
    const isDental = visit.clinicType === 'gigi';
    const editable = visit.editable;
    const assessmentStarted = Boolean(visit.startedAt) || visit.submitted;
    const canFill = editable && assessmentStarted;
    const bmi = Number(form.data.berat_badan) > 0 && Number(form.data.tinggi_badan) > 0 ? (Number(form.data.berat_badan) / (Number(form.data.tinggi_badan) / 100) ** 2).toFixed(2) : null;
    const dentalEmergency = isDental && form.data.dental_red_flags.some((flag) => ['breathing', 'swallowing', 'rapid_swelling', 'severe_allergy', 'heavy_bleeding', 'critical_vitals'].includes(flag));
    const emergency = dentalEmergency || form.data.nyeri_dada === 'ya' || form.data.kejang === 'ya' || form.data.nadi_teraba === 'tidak_teraba' || form.data.kesadaran === 'tidak_sadar' || form.data.pola_pernapasan === 'tidak_normal';
    const generalNeedsReview = !isDental && (form.data.general_assessment.general_condition === 'lemah' || [form.data.general_assessment.airway, form.data.general_assessment.circulation].includes('perlu_penilaian'));
    const generalNotAssessed = !isDental && [form.data.general_assessment.general_condition, form.data.general_assessment.airway, form.data.general_assessment.circulation].includes('belum_dinilai');
    const priority = generalNeedsReview || (!isDental && form.data.vitals_unmeasurable) || (isDental && (form.data.dental_vitals_unmeasurable || form.data.extraoral_swelling === 'present' || form.data.dental_red_flags.some((flag) => ['trismus', 'fever', 'trauma'].includes(flag)))) || form.data.kesadaran === 'menurun' || form.data.kondisi_psikiatri === 'terganggu' || ['sedang', 'tinggi'].includes(form.data.risiko_jatuh_visual);
    const triageComplete = triageFields.every(({ name }) => form.data[name] !== '');
    const normalGeneral = { general_condition: 'baik', ...(!isDental ? { airway: 'bebas', circulation: 'tanpa_kelainan' } : {}) };
    const triageAnswersNormal = allTriageAnswersNormal(form.data) && Object.entries(normalGeneral).every(([key, value]) => form.data.general_assessment[key] === value);
    const triageHasAbnormal = hasAbnormalTriageAnswer(form.data) || Object.entries(normalGeneral).some(([key, value]) => Boolean(form.data.general_assessment[key]) && form.data.general_assessment[key] !== value);
    const triage = emergency ? 'merah' : priority ? 'kuning' : !generalNotAssessed && triageComplete && (!isDental || form.data.dental_red_flags.includes('none')) ? 'hijau' : null;
    const responseLabel = triage === 'merah' ? 'Merah · perlu respons segera' : triage === 'kuning' ? 'Kuning · perlu penilaian lanjut' : 'Hijau · prioritas rutin';
    const quickNormalTriage = (id: string) => <label className={`flex items-center gap-2 text-sm leading-6 ${triageHasAbnormal ? 'cursor-not-allowed text-neutral-400' : 'cursor-pointer text-neutral-700'}`} htmlFor={id}>
        <Checkbox checked={triageAnswersNormal} className="shrink-0" disabled={triageHasAbnormal} id={id} onCheckedChange={(checked) => form.setData((data) => ({ ...data, ...quickNormalTriagePatch(checked === true), general_assessment: { ...data.general_assessment, ...Object.fromEntries(Object.entries(normalGeneral).map(([key, value]) => [key, checked === true ? value : ''])) } }))} />
        <span>Semua jawaban kondisi awal normal setelah diperiksa{triageHasAbnormal && <span className="text-neutral-500"> · ada temuan tidak normal</span>}</span>
    </label>;
    const update = (name: ScalarField, value: string) => form.setData(data => ({
        ...data, [name]: value, konfirmasi_riwayat: false,
        ...(name === 'status_riwayat_penyakit' && value !== 'ada' ? { risiko_medis_gigi: value ? [value] : [], risiko_medis_gigi_lainnya: '', riwayat_penyakit: '', penyakit_nama: '', penyakit_keterangan: '' } : {}),
        ...(name === 'status_riwayat_penyakit' && value === 'ada' ? { risiko_medis_gigi: data.risiko_medis_gigi.filter(v => !['tidak_ada', 'belum_diketahui'].includes(v)) } : {}),
        ...(name === 'status_riwayat_obat' && value !== 'ada' ? { dental_medication_categories: value === 'tidak_ada' ? ['none'] : value ? ['unknown'] : [], dental_medication_categories_other: '', riwayat_obat: '' } : {}),
        ...(name === 'status_riwayat_obat' && value === 'ada' ? { dental_medication_categories: data.dental_medication_categories.filter(v => !['none', 'unknown'].includes(v)) } : {}),
    }));
    const setChoices = (name: keyof typeof otherDetails, selected: string[]) => {
        const item = otherDetails[name];
        form.setData(data => ({ ...data, [name]: selected, [item.field]: selected.includes(item.option) ? data[item.field] : '', konfirmasi_riwayat: false }));
    };
    const otherInput = (name: keyof typeof otherDetails) => {
        const item = otherDetails[name];
        return form.data[name].includes(item.option) ? <Field htmlFor={item.field} label={item.label} required error={form.errors[item.field]}><Textarea placeholder={`Catat ${item.label.toLocaleLowerCase('id')}`} required id={item.field} maxLength={2000} value={form.data[item.field]} onChange={e => update(item.field, e.target.value)} /></Field> : null;
    };
    const allSections = (open: boolean) => setExpanded(Object.fromEntries(sections.map((key) => [key, open])) as Record<Section, boolean>);
    const onError = () => { setMode('lengkap'); allSections(true); };
    function save(action: 'draf' | 'kirim') {
        if (!canFill) return;
        if (attachmentPending) { setExpanded((state) => ({ ...state, attachments: true })); return; }
        const fingerprint = JSON.stringify({ ...form.data, tindakan: action, screening_revision: revision.current });
        if (fingerprint !== requestFingerprint.current || !requestId.current) { requestId.current = crypto.randomUUID(); requestFingerprint.current = fingerprint; }
        form.transform((data) => ({ ...data, tindakan: action, screening_revision: revision.current, request_id: requestId.current }));
        form.post(`/pelayanan/screening/${visit.id}`, { preserveScroll: true, onError, onSuccess: (page) => { updateRevision(page); requestId.current = null; form.setDefaults(); } });
    }
    function submit(event: FormEvent<HTMLFormElement>) { event.preventDefault(); save('kirim'); }
    function start() {
        setStarting(true);
        router.post(`/pelayanan/screening/${visit.id}/mulai`, { petugas_id: form.data.petugas_id, assistance_reason: form.data.assistance_reason }, { preserveScroll: true, onFinish: () => setStarting(false), onSuccess: updateRevision, onError: (errors) => { form.setError(errors); onError(); } });
    }
    const section = (id: Section, title: string, description: string, children: ReactNode) =>
        <AssessmentSection key={id} id={id} title={title} description={description} open={expanded[id]} onToggle={() => setExpanded((state) => ({ ...state, [id]: !state[id] }))}>{children}</AssessmentSection>;
    const showOptional = (...names: (ScalarField | 'dental_red_flags' | 'pemicu_nyeri_gigi' | 'risiko_medis_gigi' | 'riwayat_infeksi_gigi')[]) => mode === 'lengkap' || names.some((name) => Boolean(form.errors[name]) || form.data[name].length > 0);
    const text = (name: keyof typeof assessmentPlaceholders, label: string, required = false, multiline = false, visibleInCompact = false) => (required || visibleInCompact || showOptional(name)) && <Field error={form.errors[name]} htmlFor={name} label={label} required={required}>{multiline ? <Textarea id={name} maxLength={name === 'alasan_pengukuran_tidak_lengkap' ? 2000 : 5000} onChange={(event) => update(name, event.target.value)} placeholder={assessmentPlaceholders[name]} required={required} value={form.data[name]} /> : <Input id={name} maxLength={255} onChange={(event) => update(name, event.target.value)} placeholder={assessmentPlaceholders[name]} required={required} value={form.data[name]} />}</Field>;
    const numeric = (name: ScalarField, label: string, unit: string, required = false, step = '1', max = 300, visibleInCompact = false) => (required || visibleInCompact || showOptional(name)) && <Field error={form.errors[name]} htmlFor={name} label={`${label} (${unit})`} required={required && !(isDental ? form.data.dental_vitals_unmeasurable : form.data.vitals_unmeasurable)}><Input id={name} inputMode="decimal" min="0" max={max} onChange={(event) => update(name, event.target.value)} placeholder="Hasil pengukuran" step={step} type="number" value={form.data[name]} /></Field>;
    const single = (name: ScalarField, label: string, options: Option[], required = true) => <ChoiceGroup error={form.errors[name]} label={label} name={name} options={options} required={required} compact={!required} selected={form.data[name] ? [form.data[name]] : []} onChange={(value) => update(name, value[0] ?? '')} single />;
    const bloodPressure = () => <Field htmlFor="td_sistole" label="Tekanan darah (mmHg)" required={!(isDental ? form.data.dental_vitals_unmeasurable : form.data.vitals_unmeasurable)} error={[form.errors.td_sistole, form.errors.td_diastole].filter(Boolean).join(' ')}>
        <div role="group" aria-label="Tekanan darah dalam milimeter raksa" className="flex h-10 min-w-0 items-center overflow-hidden rounded-lg border border-neutral-200 bg-surface shadow-sm focus-within:ring-2 focus-within:ring-neutral-400">
            <Input className="h-full min-w-0 rounded-none border-0 text-center shadow-none focus-visible:ring-0" aria-label="Tekanan sistole" id="td_sistole" inputMode="numeric" min="0" max="300" onChange={(event) => update('td_sistole', event.target.value)} placeholder="Sistole" step="1" type="number" value={form.data.td_sistole} />
            <span className="shrink-0 text-neutral-500" aria-hidden="true">/</span>
            <Input className="h-full min-w-0 rounded-none border-0 text-center shadow-none focus-visible:ring-0" aria-label="Tekanan diastole" id="td_diastole" inputMode="numeric" min="0" max="200" onChange={(event) => update('td_diastole', event.target.value)} placeholder="Diastole" step="1" type="number" value={form.data.td_diastole} />
        </div>
    </Field>;
    const anthropometry = <>
        <div className="grid min-w-0 items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {numeric('berat_badan', 'Berat badan', 'kg', false, '0.1', 500, true)}
            {numeric('tinggi_badan', 'Tinggi badan', 'cm', false, '0.1', 300, true)}
            {numeric('lingkar_perut', 'Lingkar perut', 'cm', false, '0.1', 300, true)}
            <Field htmlFor="bmi" label="IMT (kg/m²)"><Input id="bmi" readOnly value={bmi ?? ''} placeholder="Belum dihitung" className="bg-neutral-50 tabular-nums" /></Field>
        </div>
        <p className="text-xs leading-5 text-neutral-500">IMT dihitung dari berat dan tinggi badan. Interpretasi sesuai usia dan kondisi klinis.</p>
        {text('alasan_pengukuran_tidak_lengkap', 'Catatan pengkajian', false, true, true)}
    </>;
    const assistance = canSelectStaff && <Card><CardHeader><CardTitle>Bantuan pengkajian admin</CardTitle></CardHeader><CardContent className="space-y-4">
        <p className="text-sm text-neutral-600">Anda menginput sebagai operator bantuan admin. Pilih petugas yang melakukan pengkajian; tanda tangannya tidak ditambahkan otomatis pada hasil input admin.</p>
        <AssistanceReasonField error={form.errors.assistance_reason} id="assistance-reason" value={form.data.assistance_reason} onChange={(value) => form.setData('assistance_reason', value)} disabled={form.processing} />
    </CardContent></Card>;
    const historyConfirmation = <>
        <label className="flex cursor-pointer items-start gap-2 text-sm leading-6">
            <Checkbox className="mt-1 shrink-0" id="konfirmasi_riwayat" aria-invalid={Boolean(form.errors.konfirmasi_riwayat)} aria-describedby={form.errors.konfirmasi_riwayat ? "konfirmasi-riwayat-error" : undefined} checked={form.data.konfirmasi_riwayat} onCheckedChange={(checked) => form.setData('konfirmasi_riwayat', checked === true)} />
            <span>Riwayat alergi dan penyakit sudah dikonfirmasi; informasi yang belum diketahui telah ditandai. <span className="text-red-600">*</span></span>
        </label>
        {form.errors.konfirmasi_riwayat && <p className="text-xs text-red-600" id="konfirmasi-riwayat-error" role="alert">{form.errors.konfirmasi_riwayat}</p>}
    </>;
    const painScale = <Field error={form.errors.skala_nyeri} htmlFor="skala_nyeri" label="Skala nyeri (0–10)"><Select id="skala_nyeri" value={form.data.skala_nyeri} onChange={(event) => update('skala_nyeri', event.target.value)}><option value="">Belum dikaji</option>{Array.from({ length: 11 }, (_, value) => <option key={value} value={value}>{value}{value === 0 ? ' · Tidak nyeri' : ''}</option>)}</Select></Field>;
    const allergyDetails = <div className="grid min-w-0 gap-4 md:grid-cols-2"><div className="min-w-0 col-span-full">{text('riwayat_alergi', 'Rincian alergi obat / makanan / lainnya', true, true)}</div>{text('alergi_reaksi', 'Reaksi alergi', true, true)}{text('alergi_keparahan', 'Keparahan alergi (jika diketahui)', false, true, true)}{text('alergi_jenis', 'Jenis / pemicu alergi')}</div>;
    const generalExtra = (part: 'visit' | 'triage' | 'complaint' | 'history' | 'systems' | 'additional' | 'conclusion', nutritionField?: ReactNode) => <GeneralNursingAssessment nutritionField={nutritionField} dental={isDental} informationSource={form.data.sumber_informasi} section={part} complaint={form.data.keluhan} value={form.data.general_assessment} errors={form.errors} onChange={value => form.setData('general_assessment', value)} />;
    const generalSafety = (
        <section className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4 sm:p-5">
            {isDental && <AssessmentHeading title="Kondisi umum pasien" description="Catat hasil penilaian kondisi umum. Jawaban kosong berarti belum dinilai." />}
            <div className="grid min-w-0 items-stretch gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {triageFields.map((field) => <div key={field.name} className="min-w-0 rounded-lg border border-neutral-200 bg-surface p-3">{single(field.name, field.label, field.options, false)}</div>)}
                <div className="min-w-0 xl:col-span-2">{text('catatan_triase', 'Catatan kondisi awal / triase', false, true, true)}</div>
            </div>
            {isDental ? <ChoiceGroup label="Keadaan umum" name="general-general_condition" options={[["baik", "Baik"], ["cukup", "Cukup"], ["lemah", "Lemah"], ["belum_dinilai", "Belum dinilai"]]} selected={form.data.general_assessment.general_condition ? [String(form.data.general_assessment.general_condition)] : []} single compact onChange={items => form.setData('general_assessment', { ...form.data.general_assessment, general_condition: items[0] ?? '' })} /> : generalExtra('triage')}
            {quickNormalTriage('triage-quick-normal-compact')}
            {triage && <div role="status" className={`rounded-lg border p-3 text-sm ${triage === 'merah' ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300' : triage === 'kuning' ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300' : 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'}`}><strong>Kebutuhan respons awal: {responseLabel}.</strong> {triage === 'merah' ? 'Segera hubungi dokter dan ikuti prosedur kegawatdaruratan klinik; jangan menunda pertolongan untuk melengkapi formulir.' : 'Gunakan hasil ini sebagai informasi awal dan tetap ikuti penilaian klinis serta prosedur klinik.'}</div>}
        </section>
    );
    const dentalSafety = <section className="space-y-5 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4 sm:p-5" aria-label="Observasi awal wajah dan mulut">
            <AssessmentHeading title="Observasi ekstraoral dan kondisi umum" description="Amati wajah dan rahang, tanyakan tanda bahaya, lalu catat kondisi umum pasien. Sampaikan segera kepada dokter bila ada temuan yang mengkhawatirkan." />
            <div className="grid min-w-0 gap-4 xl:grid-cols-2">
                {single('extraoral_symmetry', 'Simetri wajah', [['symmetric', 'Tampak simetris'], ['asymmetric', 'Tampak asimetris'], ['unknown', 'Belum dapat dinilai']], false)}
                {single('extraoral_swelling', 'Pembengkakan wajah / rahang', [['none', 'Tidak tampak'], ['present', 'Tampak pembengkakan'], ['unknown', 'Belum dapat dinilai']], false)}
            </div>
            <ChoiceGroup error={form.errors.dental_red_flags} label="Tanda bahaya gigi / mulut" name="dental_red_flags" options={[
                ['none', 'Tidak ditemukan setelah ditanyakan / diamati'], ['breathing', 'Sulit bernapas'], ['swallowing', 'Sulit menelan'], ['rapid_swelling', 'Pembengkakan cepat meluas / menutup mata'], ['severe_allergy', 'Dugaan reaksi alergi berat'], ['heavy_bleeding', 'Perdarahan hebat'], ['critical_vitals', 'TTV sangat abnormal menurut penilaian petugas'], ['trismus', 'Sulit membuka mulut'], ['fever', 'Demam / kondisi umum memburuk'], ['trauma', 'Trauma wajah atau rahang'], ['other', 'Lainnya'], ['unknown', 'Belum dapat dinilai'],
            ]} selected={form.data.dental_red_flags} onChange={(value) => setChoices('dental_red_flags', value)} />{otherInput('dental_red_flags')}
            {text('extraoral_notes', 'Rincian temuan observasi ekstraoral', form.data.extraoral_symmetry === 'asymmetric' || form.data.extraoral_swelling === 'present' || form.data.dental_red_flags.some((flag) => flag !== 'none'), true, true)}
            {dentalEmergency && <p role="alert" className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">Perlu respons segera. Hubungi dokter gigi penanggung jawab atau petugas medis yang sedang bertugas. Sampaikan tanda bahaya dan hasil penilaian awal secara verbal. Dahulukan pertolongan awal sesuai SOP dan kemampuan klinik. Koordinasikan rujukan ke fasilitas kesehatan eksternal sesuai kebutuhan pasien.</p>}
        {generalSafety}
        </section>;

    const dentalDiseaseDetails = form.data.status_riwayat_penyakit === 'ada' && <div className="space-y-4">
<ChoiceGroup error={form.errors.risiko_medis_gigi} label="Penyakit atau kondisi yang dilaporkan" name="risiko_medis_gigi" required options={[
            ['hipertensi', 'Hipertensi'], ['diabetes', 'Diabetes melitus'], ['penyakit_jantung', 'Penyakit jantung'], ['gangguan_pembekuan', 'Gangguan pembekuan'],
            ['antikoagulan', 'Obat antikoagulan'], ['lainnya', 'Lainnya'],
        ]} selected={form.data.risiko_medis_gigi} onChange={(value) => setChoices('risiko_medis_gigi', value)} />{otherInput('risiko_medis_gigi')}
        <div className="min-w-0 col-span-full">{text('riwayat_penyakit', 'Rincian riwayat penyakit (bila diperlukan)', false, true, true)}</div>
    </div>;
    const dentalHealth = <div className="space-y-4 border-t border-neutral-200 pt-4">
        {single('status_riwayat_obat', 'Penggunaan obat saat ini', [['ada', 'Ada obat yang digunakan'], ['tidak_ada', 'Tidak menggunakan obat'], ['belum_diketahui', 'Belum diketahui']])}
        {form.data.status_riwayat_obat === 'ada' && <>
            <div className="space-y-3 md:col-span-2">
                {visit.medicationCategories && <ChoiceGroup
                    name="dental_medication_categories"
                    label="Kategori obat yang dilaporkan"
                    options={Object.entries(visit.medicationCategories).filter(([key]) => !['none', 'unknown'].includes(key))}
                    selected={form.data.dental_medication_categories}
                    error={form.errors.dental_medication_categories}
                    onChange={(value) => setChoices('dental_medication_categories', value)}
                />}{otherInput('dental_medication_categories')}
                {text('riwayat_obat', 'Obat yang sedang digunakan', false, true, true)}
                <p className="mt-1 text-xs text-neutral-600">Tanyakan antikoagulan, antiplatelet, obat diabetes dan antihipertensi. Catat nama dan dosis bila diketahui.</p>
            </div></>}
    </div>;
    const mucosa = form.data.mukosa_mulut;
    const allMucosaNormal = mucosaEntries.every(([site]) => mucosa[site] === 'normal');
    const hasProtectedMucosa = (answers: MucosaAnswers, note: string) => Boolean(note.trim()) || mucosaEntries.some(([site]) =>
        ['abnormal', 'belum_dinilai'].includes(answers[site] ?? '') || Boolean(answers.catatan?.[site]?.trim()),
    );
    const mucosaShortcutDisabled = hasProtectedMucosa(mucosa, form.data.kelainan_mukosa);
    const setAllMucosaNormal = (checked: boolean) => form.setData(data => {
        if (hasProtectedMucosa(data.mukosa_mulut, data.kelainan_mukosa)) return data;
        const statuses = Object.fromEntries(mucosaEntries.map(([site]) => [site, checked ? 'normal' : '']));
        return { ...data, mukosa_mulut: { ...data.mukosa_mulut, ...statuses } };
    });
    const mucosaSummary = <section className="space-y-2 rounded-lg border border-neutral-200 p-4" aria-label="Ringkasan observasi mukosa mulut">
        <h4 className="text-sm font-semibold">Ringkasan observasi mukosa mulut</h4>
        {mucosaEntries.filter(([site]) => mucosa[site] === 'abnormal' || mucosa.catatan?.[site]?.trim()).map(([site, label]) =>
            <p key={site} className="wrap-break-word whitespace-pre-wrap text-sm">
                <strong>{label}:</strong> {mucosa.catatan?.[site]?.trim() || 'Abnormal'}
            </p>,
        )}
        {form.data.kelainan_mukosa.trim() && <p className="wrap-break-word whitespace-pre-wrap text-sm"><strong>Catatan umum:</strong> {form.data.kelainan_mukosa}</p>}
        <p className="text-xs text-neutral-500">Belum diamati: {mucosaEntries.filter(([site]) => !mucosa[site]).map(([, label]) => label).join(', ') || 'Tidak ada'}</p>
        {mucosaEntries.some(([site]) => mucosa[site] === 'belum_dinilai') && <p className="text-xs text-neutral-500">Belum dapat dinilai: {mucosaEntries.filter(([site]) => mucosa[site] === 'belum_dinilai').map(([, label]) => label).join(', ')}</p>}
    </section>;
    const dentalProgression = <GeneralNursingAssessment section="complaint" dental value={form.data.general_assessment} complaint="" errors={form.errors} onChange={value => form.setData('general_assessment', value)} />;
    const dentalAssessment = <>
        {dentalSafety}
        <section className="space-y-4">
            <AssessmentHeading title="Rincian nyeri gigi dan gusi" description="Catat lokasi, skala, pola, dan perkembangan nyeri yang dilaporkan pasien." />
            <div className="grid min-w-0 gap-4 md:grid-cols-2">
                <div className="grid min-w-0 content-start gap-4">
                    {text('lokasi_nyeri_gigi', 'Lokasi nyeri gigi / gusi', false, false, true)}
                    {painScale}
                </div>
                <Field className="md:grid-rows-[auto_minmax(0,1fr)]" htmlFor="durasi_keluhan_gigi" label="Pola dan durasi episode nyeri" error={form.errors.durasi_keluhan_gigi}>
                    <Textarea className="md:h-full md:min-h-16" id="durasi_keluhan_gigi" rows={2} maxLength={255} value={form.data.durasi_keluhan_gigi} placeholder={assessmentPlaceholders.durasi_keluhan_gigi} onChange={event => update('durasi_keluhan_gigi', event.target.value)} />
                </Field>
            </div>
            <ChoiceGroup error={form.errors.pemicu_nyeri_gigi} label="Pemicu nyeri gigi (opsional)" name="pemicu_nyeri_gigi" options={[
                ['dingin', 'Dingin'], ['manis', 'Manis'], ['mengunyah', 'Mengunyah'], ['panas', 'Panas'], ['spontan', 'Spontan'], ['lainnya', 'Lainnya'], ['tidak_ada', 'Tidak ada pemicu'],
            ]} selected={form.data.pemicu_nyeri_gigi} onChange={(value) => setChoices('pemicu_nyeri_gigi', value)} />{otherInput('pemicu_nyeri_gigi')}
            {text('nyeri_karakter', 'Karakter nyeri dan penjalarannya', false, true)}
            {text('nyeri_pereda', 'Hal yang mengurangi nyeri', false, true)}
            {text('nyeri_dampak', 'Dampak nyeri pada aktivitas atau tidur', false, true)}
            {dentalProgression}
        </section>
        <section className="space-y-4 border-t border-neutral-200 pt-4">
            <AssessmentHeading title="Riwayat dan kebiasaan gigi dan mulut" description="Tanyakan riwayat infeksi, perawatan sebelumnya, dan kebiasaan sehari-hari." />
            <ChoiceGroup error={form.errors.riwayat_infeksi_gigi} label="Riwayat infeksi yang diketahui (opsional)" name="riwayat_infeksi_gigi" options={[
                ['hepatitis', 'Hepatitis'], ['hiv', 'HIV'], ['infeksi_lain', 'Infeksi lain'], ['tidak_ada', 'Tidak ada riwayat yang diketahui'],
            ]} selected={form.data.riwayat_infeksi_gigi} onChange={(value) => setChoices('riwayat_infeksi_gigi', value)} />{otherInput('riwayat_infeksi_gigi')}
            {text('riwayat_perawatan_gigi', 'Riwayat perawatan gigi dan komplikasi', false, true, true)}
            <ChoiceGroup name="kebiasaan_gigi_opsi" label="Kebiasaan yang dilaporkan pasien" options={Object.entries(visit.oralHabitOptions ?? { teh_kopi: 'Minum teh atau kopi', alkohol: 'Minum minuman beralkohol', soda: 'Minum minuman bersoda', merokok: 'Merokok', satu_sisi: 'Mengunyah satu sisi', sirih_tembakau: 'Mengunyah sirih atau tembakau', benda_keras: 'Menggigit benda keras', bruksisme: 'Menggemeretakkan gigi (bruksisme)', lainnya: 'Lainnya', tidak_ada: 'Tidak ada kebiasaan yang dilaporkan', belum_diketahui: 'Belum diketahui' })} error={form.errors.kebiasaan_gigi_opsi} selected={form.data.kebiasaan_gigi_opsi} onChange={value => form.setData(data => ({ ...data, kebiasaan_gigi_opsi: value, konfirmasi_riwayat: false }))} />
            {text('kebiasaan_gigi', 'Keterangan kebiasaan (bila diperlukan)', form.data.kebiasaan_gigi_opsi.includes('lainnya'), true, Boolean(form.data.kebiasaan_gigi))}
            {text('kebiasaan_perawatan_mulut', 'Perawatan dan kebersihan mulut sehari-hari', false, true)}
        </section>
        <section className="space-y-4 border-t border-neutral-200 pt-4">
            <AssessmentHeading title="Observasi mukosa mulut" description="Pilih hasil untuk setiap lokasi yang diamati. Tambahkan uraian bila ada temuan abnormal." />
            <div className="grid min-w-0 items-start gap-4 md:grid-cols-2">
                {mucosaEntries.map(([site, label]) => {
                    const status = mucosa[site];
                    const notes = mucosa.catatan?.[site] ?? '';
                    const notesError = form.errors[`mukosa_mulut.catatan.${site}`];
                    const abnormal = status === 'abnormal';
                    const notesLabel = abnormal ? 'Uraian temuan' : 'Catatan tambahan (bila diperlukan)';
                    return <section key={site} className="min-w-0 space-y-3 rounded-lg border border-neutral-200 p-4">
                        <ChoiceGroup name={`mukosa-${site}`} label={label} options={mucosaOptions} selected={status ? [status] : []} single compact
                            error={form.errors[`mukosa_mulut.${site}`]} onChange={items => form.setData(data => ({ ...data, mukosa_mulut: { ...data.mukosa_mulut, [site]: items[0] ?? '' } }))} />
                        <details open={abnormal || Boolean(notes || notesError)} className="space-y-2">
                            <summary className="cursor-pointer text-xs font-medium text-neutral-600">{notesLabel}</summary>
                            <Field htmlFor={`mukosa-${site}-notes`} label={`${notesLabel} - ${label}`} required={abnormal && !form.data.kelainan_mukosa.trim()} error={notesError}>
                                <Textarea id={`mukosa-${site}-notes`} rows={2} maxLength={2000} value={notes} onChange={event => {
                                    const note = event.target.value;
                                    form.setData(data => ({ ...data, mukosa_mulut: { ...data.mukosa_mulut, catatan: { ...data.mukosa_mulut.catatan, [site]: note } } }));
                                }} />
                            </Field>
                        </details>
                    </section>;
                })}
            </div>
            <label className="flex items-start gap-2 text-sm leading-6">
                <Checkbox id="mukosa-quick-normal" className="mt-1 shrink-0" checked={allMucosaNormal} disabled={mucosaShortcutDisabled} onCheckedChange={checked => setAllMucosaNormal(checked === true)} />
                <span>Semua lokasi mukosa normal setelah diamati</span>
            </label>
            {mucosaShortcutDisabled && <p className="text-xs text-neutral-500">Pilihan cepat tidak tersedia jika ada temuan, catatan, atau lokasi yang belum dapat dinilai. Tinjau hasil per lokasi.</p>}
            <Field htmlFor="kelainan_mukosa" label="Catatan umum mukosa (bila diperlukan)" error={form.errors.kelainan_mukosa}>
                <Textarea id="kelainan_mukosa" rows={2} maxLength={2000} value={form.data.kelainan_mukosa} placeholder={assessmentPlaceholders.kelainan_mukosa} onChange={event => update('kelainan_mukosa', event.target.value)} />
            </Field>
            {text('observasi_mulut', 'Observasi awal mulut dan rahang', false, true)}
        </section>
    </>;

    const associatedSymptoms = <AssociatedSymptoms value={form.data.keluhan_tambahan} error={form.errors.keluhan_tambahan} onChange={value => update('keluhan_tambahan', value)} />;
    const handover = <>
        <p className="text-xs leading-5 text-neutral-500">Temuan dan riwayat yang tercatat diteruskan kepada dokter. Jika sudah melakukan tindakan, catat respons pasien. Untuk serah terima, catat penerima, waktu, dan tindak lanjut bila ada.</p>
        {isDental ? text('catatan_medis_gigi', 'Hal yang perlu dikonfirmasi dokter gigi', form.data.risiko_medis_gigi.includes('belum_diketahui'), true, true) : text('pemeriksaan_fisik', 'Catatan temuan tambahan (bila diperlukan)', false, true, true)}
        {isDental && mucosaSummary}
        {generalExtra('conclusion')}
    </>;
    const informationSource = <Field error={form.errors.sumber_informasi} htmlFor="sumber_informasi" label="Sumber informasi" required>
        <Select id="sumber_informasi" value={form.data.sumber_informasi} onChange={event => update('sumber_informasi', event.target.value)}>
            <option value="">Pilih sumber informasi</option>
            <option value="pasien">Pasien sendiri</option>
            <option value="keluarga">Keluarga</option>
            <option value="pendamping">Pendamping</option>
            <option value="tidak_dapat_diperoleh">Belum dapat diperoleh</option>
        </Select>
    </Field>;
    const complaintFields = <>
        {text('keluhan', 'Keluhan utama / alasan kunjungan', true, true)}
        {isDental && associatedSymptoms}
        <div className="grid min-w-0 items-start gap-4 md:grid-cols-2">
            {text('lama_keluhan', 'Sejak kapan / lama keluhan', true)}
            {informationSource}
        </div>
        {!isDental && generalExtra('visit')}
    </>;
    const historyFields = <>
        <div className="space-y-4">
            {text('alergi_verifikasi', 'Perbedaan alergi yang perlu diverifikasi', false, true)}{single('status_alergi', 'Konfirmasi alergi pada kunjungan ini', [['ada', 'Ada alergi'], ['tidak_ada', 'Tidak ada alergi'], ['belum_diketahui', 'Belum diketahui'], ['belum_dikonfirmasi', 'Belum dapat dikonfirmasi']])}
            {form.data.status_alergi === 'ada' && allergyDetails}
        </div>
        <div className="space-y-4 border-t border-neutral-100 pt-5">
            {single('status_riwayat_penyakit', 'Riwayat penyakit yang pernah atau sedang dialami', historyOptions)}
            {isDental && dentalDiseaseDetails}
            {!isDental && form.data.status_riwayat_penyakit === 'ada' && <div className="grid min-w-0 gap-4 md:grid-cols-2"><div className="min-w-0 col-span-full">{text('riwayat_penyakit', 'Rincian penyakit yang dilaporkan', true, true)}</div>{text('penyakit_nama', 'Nama penyakit spesifik')}{text('penyakit_keterangan', 'Keterangan riwayat penyakit', false, true)}</div>}
        </div>
        {!isDental && generalExtra('history')}
        {text('riwayat_kondisi_khusus', 'Kondisi khusus, operasi, rawat inap atau kehamilan yang relevan', false, true, !isDental)}
        {isDental && dentalHealth}
        {!isDental && <>{single('status_riwayat_obat', 'Status penggunaan obat', [['ada', 'Ada obat yang digunakan'], ['tidak_ada', 'Tidak menggunakan obat'], ['belum_diketahui', 'Belum diketahui']], false)}{form.data.status_riwayat_obat === 'ada' && text('riwayat_obat', 'Obat rutin / sedang digunakan', true, true, true)}</>}
        {!isDental && text('riwayat_penyakit_keluarga', 'Riwayat penyakit keluarga yang relevan', false, true, true)}
        {historyConfirmation}
    </>;
    const vitalException = isDental ? 'dental_vitals_unmeasurable' : 'vitals_unmeasurable';
    const vitalsFields = <>
        <label className="flex items-start gap-2 text-sm">
            <Checkbox checked={form.data[vitalException]} onCheckedChange={checked => form.setData(vitalException, checked === true)} />
            <span>Sebagian atau seluruh tanda vital belum dapat diukur</span>
        </label>
        {form.data[vitalException] && <div className="space-y-1 text-xs leading-5 text-neutral-600">
            <p>Isi hasil yang berhasil diukur. Jelaskan alasan pengukuran belum lengkap di Catatan pengkajian, pada bagian Berat, tinggi, dan lingkar perut.</p>
            {isDental && <p>Catat kondisi kesadaran dan pernapasan, serta komunikasi dengan dokter gigi pada bagian serah terima. Ulangi pengukuran saat kondisi memungkinkan.</p>}
        </div>}
        <div className="grid min-w-0 items-start gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {bloodPressure()}
            {numeric('nadi', 'Denyut nadi', 'kali/menit', true)}
            {numeric('suhu', 'Suhu tubuh', '°C', true, '0.1', 45)}
            {numeric('respirasi', 'Frekuensi napas', 'kali/menit', true, '1', 100)}
            {numeric('spo2', 'SpO₂', '%', false, '1', 100, true)}
        </div>
    </>;
    const nutrition = single('skrining_gizi', 'Hasil pengkajian gizi', [['normal', 'Tidak ditemukan masalah'], ['kurang', 'Gizi kurang'], ['lebih', 'Gizi lebih']], false);
    const parts: Array<{ id: Section; title: string; description: string; content: ReactNode }> = [
        { id: 'complaint', title: 'Keluhan dan informasi pasien', description: 'Catat alasan kunjungan, sejak kapan keluhan dirasakan, dan siapa yang menyampaikan informasi.', content: complaintFields },
        { id: 'history', title: 'Riwayat kesehatan umum', description: 'Konfirmasi alergi, riwayat penyakit, penggunaan obat, dan kondisi khusus yang relevan.', content: historyFields },
        { id: 'vitals', title: 'Tanda vital', description: 'Catat hasil pengukuran pada kunjungan ini.', content: vitalsFields },
        { id: 'anthropometry', title: 'Berat, tinggi, dan lingkar perut', description: 'Isi hasil pengukuran yang tersedia. IMT dihitung dari berat dan tinggi badan.', content: anthropometry },
    ];
    if (isDental) {
        parts.push({ id: 'dental', title: 'Pengkajian gigi dan mulut', description: 'Catat keluhan yang dilaporkan pasien dan temuan yang Anda amati.', content: dentalAssessment });
        if (showOptional('skrining_gizi', 'riwayat_penyakit_keluarga', 'pemeriksaan_fisik')) {
            parts.push({ id: 'additional', title: 'Pengkajian tambahan', description: 'Isi hasil pengkajian gizi, riwayat keluarga, atau catatan tambahan jika relevan.', content: <>
                {nutrition}
                {text('riwayat_penyakit_keluarga', 'Riwayat penyakit keluarga', false, true)}
                {text('pemeriksaan_fisik', 'Catatan pemeriksaan awal', false, true)}
            </> });
        }
    } else {
        parts.push(
            { id: 'triage', title: 'Kondisi awal dan triase', description: 'Catat kondisi umum dan tanda bahaya yang sudah dinilai. Jawaban kosong berarti belum dinilai.', content: generalSafety },
            { id: 'dental', title: 'Rincian keluhan', description: 'Lengkapi ciri keluhan, gejala penyerta, dan skala nyeri sesuai informasi pasien.', content: <>{generalExtra('complaint')}{associatedSymptoms}{painScale}</> },
            { id: 'systems', title: 'Pengkajian sistem tubuh', description: 'Pilih hasil setelah dikaji dan jelaskan temuan abnormal.', content: generalExtra('systems') },
            { id: 'nutrition', title: 'Nutrisi dan kebutuhan pasien', description: 'Lengkapi sesuai kondisi dan kebutuhan pasien.', content: generalExtra('additional', nutrition) },
        );
    }
    parts.push({ id: 'ringkas', title: 'Ringkasan dan serah terima', description: 'Catat hal yang perlu diketahui dokter untuk melanjutkan pelayanan.', content: handover });

    const assessment = mode === 'ringkas'
        ? section('ringkas', 'Pengkajian awal', 'Isi bagian yang sesuai dengan kunjungan ini. Buka tab Lengkap untuk melihat seluruh isian.', parts.map(({ id, title, description, content }) =>
            <section key={id} aria-labelledby={`compact-${id}-title`} className="min-w-0 space-y-4 border-t border-neutral-100 pt-5 first:border-t-0 first:pt-0">
                <AssessmentHeading id={`compact-${id}-title`} title={title} description={description} />
                {content}
            </section>,
        ))
        : parts.map(({ id, title, description, content }, index) => section(id, `${index + 1}. ${title}`, description, content));

    return <><Head title={`Pengkajian awal ${isDental ? 'gigi' : 'umum'} - ${visit.patient.name}`} />
        {assistance}
        <form className="mx-auto max-w-5xl space-y-6" noValidate onSubmit={submit}>
            <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div className="min-w-0">
                    <p className="text-sm font-medium text-neutral-500">{visit.clinic}</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pengkajian awal {isDental ? 'gigi dan mulut' : 'poli umum'}</h2>
                </div>
                <Button asChild className="self-start sm:shrink-0" variant="secondary">
                    <Link href="/pelayanan/screening"><ArrowLeft className="size-4" />Antrean pengkajian awal</Link>
                </Button>
            </header>

            <Card aria-label="Profil pasien" className="overflow-hidden">
                <CardContent className="p-0 sm:p-0">
                    <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div className="flex min-w-0 items-center gap-3 sm:gap-4">
                            <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600">
                                <UserRound aria-hidden="true" className="size-6" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-xs font-medium text-neutral-500">Pasien</p>
                                <h3 className="mt-1 break-words text-lg font-semibold leading-snug text-neutral-950">{visit.patient.name}</h3>
                                <p className="mt-1 break-words text-sm text-neutral-600">No. RM <span className="font-medium tabular-nums">{visit.patient.medicalRecordNumber}</span></p>
                            </div>
                        </div>
                        <div className="shrink-0 self-start sm:self-center"><StatusBadge context="nurse" examinationStarted={visit.examinationStarted} status={visit.status} /></div>
                    </div>
                    <dl className="grid grid-cols-2 gap-x-5 gap-y-4 border-t border-neutral-100 bg-neutral-50/60 px-5 py-4 sm:grid-cols-3 sm:px-6">
                        <ProfileDetail label="Jenis kelamin">{visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : 'Belum dicatat'}</ProfileDetail>
                        <ProfileDetail label="Usia">{visit.patient.age == null ? 'Belum diketahui' : `${visit.patient.age} tahun`}</ProfileDetail>
                        <ProfileDetail label="Golongan darah">{visit.patient.bloodType || 'Belum diketahui'}</ProfileDetail>
                        <ProfileDetail label="No. kunjungan">{visit.number}</ProfileDetail>
                        <ProfileDetail label="Dokter">{visit.doctor || 'Belum ditentukan'}</ProfileDetail>
                        <ProfileDetail label="Mulai pengkajian">{visit.startedAt || 'Belum dimulai'}</ProfileDetail><ProfileDetail label="TTV terakhir dicatat">{visit.vitalsRecordedAt || 'Waktu belum tercatat'}</ProfileDetail><ProfileDetail label="Triase terakhir dicatat">{visit.triageRecordedAt || 'Waktu belum tercatat'}</ProfileDetail>
                    </dl>
                    <div className="flex flex-col gap-4 border-t border-neutral-100 p-5 sm:flex-row sm:items-end sm:justify-between sm:px-6 sm:py-4">
                        <div className="min-w-0 sm:w-full sm:max-w-md">
                            {canSelectStaff
                                ? <Field error={form.errors.petugas_id} htmlFor="petugas_id" label="Petugas pengkajian" required>
                                    <Select disabled={!editable || starting || form.processing} id="petugas_id" value={form.data.petugas_id} onChange={(event) => update('petugas_id', event.target.value)}>
                                        <option value="">Pilih petugas</option>
                                        {staff.map((person) => <option key={person.id} value={person.id}>{person.name} · {person.position}</option>)}
                                    </Select>
                                </Field>
                                : <dl><ProfileDetail label="Petugas pengkajian">{currentStaff ?? 'Mengikuti akun perawat'}</ProfileDetail></dl>}
                        </div>
                        {!assessmentStarted && <Button className="w-full shrink-0 sm:w-auto" disabled={starting || form.processing} type="button" onClick={start}>
                            <Play className="size-4" />{starting ? 'Memulai…' : 'Mulai pengkajian'}
                        </Button>}
                    </div>
                </CardContent>
            </Card>
            {visit.submitted && <div className="rounded-xl border border-neutral-200 bg-surface p-4 text-sm"><strong>{visit.reviewedAt ? 'Telah ditinjau dokter' : 'Menunggu evaluasi dokter'}</strong><p>Dikirim: {visit.submittedAt ?? 'Waktu tidak tercatat'} · {visit.submittedBy ?? currentStaff ?? 'Petugas pengkajian'} · Penerima: {visit.doctor}</p>{visit.reviewedAt && <p>Ditinjau dan ditandatangani dokter: {visit.reviewedAt}</p>}</div>}
            {visit.submitted && <div role="status" className="rounded-xl border border-neutral-200 bg-neutral-100 p-4 text-sm text-neutral-800"><strong>Pengkajian sudah dikirim.</strong> {editable ? 'Anda dapat memperbarui catatan sebelum dokter mulai.' : 'Pengkajian tersedia untuk dibaca dan dicetak.'}</div>}
            {visit.patient.allergyHistory && <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"><strong>Riwayat alergi tersimpan:</strong> {visit.patient.allergyHistory}<p className="mt-1 text-xs">Konfirmasi kembali dengan pasien. Riwayat sebelumnya tetap tersedia untuk membantu pengkajian.</p></div>}
            {Object.keys(form.errors).length > 0 && <div data-validation-summary tabIndex={-1} role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"><strong>Pengkajian belum dapat disimpan.</strong><ul className="mt-2 list-inside list-disc">{Object.entries(form.errors).map(([name, error]) => <li key={name}>{error}</li>)}</ul></div>}
            <ScreeningVersionHistory versions={visit.versions} />
            {!assessmentStarted && <p role="status" className="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm">Klik Mulai pengkajian untuk membuka isian pasien.</p>}
            <Tabs value={mode} onValueChange={value => { setMode(value); if (!isDental) setExpanded({ complaint: true, triage: true, vitals: true, history: true, ringkas: true, dental: value === 'lengkap', additional: value === 'lengkap', anthropometry: value === 'lengkap', systems: value === 'lengkap', nutrition: value === 'lengkap', attachments: false }); }} className="space-y-4">
                <div className="rounded-xl border border-neutral-200 bg-surface">
                    <div className="flex flex-col gap-3 px-4 pt-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div>
                            <h3 className="text-sm font-semibold text-neutral-900">Pengkajian pasien</h3>
                    <p className="mt-1 text-xs leading-5 text-neutral-500"><span className="font-semibold text-red-600">*</span> Lengkapi isian bertanda wajib sebelum mengirim pengkajian.</p>
                        </div>
                    </div>
                    <TabsList aria-label="Tingkat detail pengkajian" className="mt-3 px-2 sm:px-3">
                        <TabsTrigger aria-controls="screening-assessment" value="ringkas">Ringkas</TabsTrigger>
                        <TabsTrigger aria-controls="screening-assessment" value="lengkap">Lengkap</TabsTrigger>
                    </TabsList>
                    <p className="px-4 py-3 text-xs leading-5 text-neutral-500 sm:px-5">
                        {mode === 'ringkas' ? 'Isian utama untuk pengkajian pada kunjungan ini.' : 'Seluruh bagian pengkajian dapat dibuka sesuai kebutuhan pasien.'}
                    </p>
                </div>
                <TabsContent id="screening-assessment" value={mode} className="rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400">
                <fieldset disabled={!canFill || form.processing || starting} className="min-w-0 space-y-4">
                    {assessment}
                </fieldset>
                </TabsContent>
            </Tabs>
            {section('attachments', 'Berkas pendukung', 'Tambahkan hasil pemeriksaan atau dokumen lain yang membantu dokter.', <VisitAttachments attachments={visit.attachments} uploadUrl={canFill ? visit.attachmentUploadUrl : undefined} disabled={!canFill || form.processing || starting} onPendingChange={setAttachmentPending} />)}
            {visit.submitted && <div className="rounded-xl border border-neutral-200 bg-surface p-4">{text('revision_reason', 'Alasan revisi pengkajian terkirim', true, true)}</div>}
            <footer className="rounded-xl border border-neutral-200 bg-surface p-4 shadow-sm sm:sticky sm:bottom-3 sm:z-10 sm:p-5">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div className="min-w-0 text-xs leading-5 text-neutral-500" aria-live="polite">
                        <p>{visit.submitted ? (editable ? 'Simpan perubahan pengkajian sebelum dokter memulai pemeriksaan.' : 'Pengkajian terkunci.') : 'Kirim pengkajian awal kepada dokter setelah semua isian wajib lengkap.'}</p>
                        {form.isDirty && <p className="font-medium text-amber-700">Perubahan belum disimpan.</p>}
                        {attachmentPending && <p className="font-medium text-amber-700">Unggah atau batalkan berkas di bagian Lampiran pasien terlebih dahulu.</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        <Button disabled={!visit.printUrl || form.isDirty || form.processing || starting || attachmentPending} onClick={() => visit.printUrl && window.location.assign(visit.printUrl)} title={form.isDirty ? 'Simpan perubahan sebelum mencetak' : !visit.printUrl ? 'Simpan pengkajian sebelum mencetak' : 'Cetak pengkajian tersimpan'} type="button" variant="secondary">
                            <Printer className="size-4" />Cetak pengkajian
                        </Button>
                        {!visit.submitted && <Button disabled={form.processing || starting || !canFill || attachmentPending} onClick={() => save('draf')} type="button" variant="secondary">
                            <Save className="size-4" />Simpan draf
                        </Button>}
                        <Button className="col-span-2" disabled={form.processing || starting || !canFill || attachmentPending} type="submit">
                            <ClipboardCheck className="size-4" />{form.processing ? 'Menyimpan…' : visit.submitted ? 'Simpan perubahan' : 'Simpan & kirim ke dokter'}
                        </Button>
                    </div>
                </div>
            </footer>
        </form>
    </>;
}

function ProfileDetail({ label, children }: { label: string; children: ReactNode }) {
    return <div className="min-w-0">
        <dt className="text-xs text-neutral-500">{label}</dt>
        <dd className="mt-1 break-words text-sm font-medium leading-6 text-neutral-800">{children}</dd>
    </div>;
}

function AssessmentHeading({ id, title, description, level = 4 }: { id?: string; title: string; description: string; level?: 3 | 4 }) {
    const Heading = level === 3 ? 'h3' : 'h4';

    return <div className="min-w-0">
        <Heading id={id} className="text-sm font-semibold text-neutral-900">{title}</Heading>
        <p className="mt-1 text-xs leading-5 text-neutral-500">{description}</p>
    </div>;
}

function AssessmentSection({ id, title, description, open, onToggle, children }: { id: string; title: string; description: string; open: boolean; onToggle: () => void; children: ReactNode }) {
    return <details className="min-w-0 overflow-hidden rounded-xl border border-neutral-200 bg-surface" open={open} id={`section-${id}`}>
        <summary
            className="flex cursor-pointer list-none items-start justify-between gap-4 rounded-xl p-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400 sm:px-5 [&::-webkit-details-marker]:hidden"
            onClick={(event) => { event.preventDefault(); onToggle(); }}
            aria-expanded={open}
        >
            <AssessmentHeading title={title} description={description} level={3} />
            <ChevronDown aria-hidden="true" className={`mt-0.5 size-4 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} />
        </summary>
        <div className="space-y-5 border-t border-neutral-100 p-4 sm:p-5">{children}</div>
    </details>;
}
