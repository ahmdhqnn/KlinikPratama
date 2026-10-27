import { isNoAllergyReport } from '@/lib/allergy-status';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, ClipboardCheck, Play, Printer, Save, UserRound } from 'lucide-react';
import { useRef, useState, type FormEvent, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { VisitAttachments, type ClinicalAttachment } from '@/components/kunjungan/visit-attachments';

type ScreeningValues = Record<ScalarField, string> & {
    pemicu_nyeri_gigi: string[];
    risiko_medis_gigi: string[];
    riwayat_infeksi_gigi: string[];
    konfirmasi_riwayat: boolean;
};
const scalarFields = ['petugas_id', 'keluhan', 'keluhan_tambahan', 'lama_keluhan', 'sumber_informasi', 'td_sistole', 'td_diastole', 'nadi', 'suhu', 'respirasi', 'spo2', 'berat_badan', 'tinggi_badan', 'lingkar_perut', 'alasan_pengukuran_tidak_lengkap', 'riwayat_alergi', 'status_alergi', 'alergi_jenis', 'alergi_reaksi', 'status_riwayat_penyakit', 'riwayat_penyakit', 'penyakit_nama', 'penyakit_keterangan', 'riwayat_obat', 'riwayat_penyakit_keluarga', 'skala_nyeri', 'skrining_gizi', 'pemeriksaan_fisik', 'nyeri_dada', 'kejang', 'nadi_teraba', 'pola_pernapasan', 'kesadaran', 'kondisi_psikiatri', 'risiko_jatuh_visual', 'catatan_triase', 'lokasi_nyeri_gigi', 'durasi_keluhan_gigi', 'catatan_medis_gigi'] as const;
type ScalarField = typeof scalarFields[number];
const assessmentPlaceholders = {
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
    riwayat_obat: 'Nama obat, dosis, dan frekuensi penggunaan yang diketahui',
    riwayat_penyakit_keluarga: 'Riwayat penyakit dan hubungan keluarga terkait',
    pemeriksaan_fisik: 'Temuan objektif yang diperiksa saat pengkajian',
    catatan_triase: 'Temuan penapisan dan tindak lanjut yang diperlukan',
    lokasi_nyeri_gigi: 'Lokasi gigi atau gusi serta sisi yang dikeluhkan',
    durasi_keluhan_gigi: 'Pola nyeri dan lama setiap episode',
    catatan_medis_gigi: 'Informasi yang perlu dikonfirmasi kepada dokter gigi',
} satisfies Partial<Record<ScalarField, string>>;
type Option = [string, string];
interface Visit {
    attachments?: ClinicalAttachment[]; attachmentUploadUrl?: string;
    id: number; number: string; status: string; clinic: string; clinicType: 'umum' | 'gigi'; doctor: string;
    patient: { name: string; medicalRecordNumber: string; gender: string | null; age: number | null; bloodType: string | null; allergyHistory: string | null };
    screening: Record<string, string | number | string[] | null>;
    triage: string | null; priority: string | null; printUrl: string | null; startedAt: string | null;
}
interface Props { visit: Visit; staff: { id: number; name: string; position: string }[]; canSelectStaff: boolean; currentStaff: string | null }
const sections = ['triage', 'complaint', 'history', 'vitals', 'anthropometry', 'dental', 'additional', 'ringkas', 'attachments'] as const;
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
    const list = (key: string): string[] => Array.isArray(visit.screening[key]) ? visit.screening[key].map(String) : [];
    const form = useForm<ScreeningValues>({ ...values, pemicu_nyeri_gigi: list('pemicu_nyeri_gigi'), risiko_medis_gigi: list('risiko_medis_gigi'), riwayat_infeksi_gigi: list('riwayat_infeksi_gigi'), konfirmasi_riwayat: false });
    const [expanded, setExpanded] = useState<Record<Section, boolean>>({ triage: false, complaint: false, history: false, vitals: true, anthropometry: false, dental: false, additional: false, ringkas: true, attachments: false });
    const [attachmentPending, setAttachmentPending] = useState(false);
    const [starting, setStarting] = useState(false);
    const [mode, setMode] = useState('ringkas');
    const errorSummary = useRef<HTMLDivElement>(null);
    const isDental = visit.clinicType === 'gigi';
    const editable = ['menunggu', 'screening'].includes(visit.status);
    const bmi = Number(form.data.berat_badan) > 0 && Number(form.data.tinggi_badan) > 0 ? (Number(form.data.berat_badan) / (Number(form.data.tinggi_badan) / 100) ** 2).toFixed(2) : null;
    const emergency = form.data.nyeri_dada === 'ya' || form.data.kejang === 'ya' || form.data.nadi_teraba === 'tidak_teraba' || form.data.kesadaran === 'tidak_sadar' || form.data.pola_pernapasan === 'tidak_normal';
    const priority = form.data.kesadaran === 'menurun' || form.data.kondisi_psikiatri === 'terganggu' || ['sedang', 'tinggi'].includes(form.data.risiko_jatuh_visual);
    const triageComplete = triageFields.every(({ name }) => form.data[name] !== '');
    const triage = emergency ? 'merah' : priority ? 'kuning' : triageComplete ? 'hijau' : null;
    const responseLabel = triage === 'merah' ? 'Merah · perlu respons segera' : triage === 'kuning' ? 'Kuning · perlu penilaian lanjut' : 'Hijau · prioritas rutin';
    const update = (name: ScalarField, value: string) => form.setData((data) => ({ ...data, [name]: value, ...(['status_alergi', 'riwayat_alergi', 'alergi_jenis', 'alergi_reaksi', 'status_riwayat_penyakit', 'riwayat_penyakit', 'penyakit_nama', 'penyakit_keterangan', 'riwayat_obat', 'riwayat_penyakit_keluarga'].includes(name) ? { konfirmasi_riwayat: false } : {}) }));
    const allSections = (open: boolean) => setExpanded(Object.fromEntries(sections.map((key) => [key, open])) as Record<Section, boolean>);
    const onError = () => { setMode('lengkap'); allSections(true); requestAnimationFrame(() => errorSummary.current?.focus()); };
    function save(action: 'draf' | 'kirim') {
        if (attachmentPending) { setExpanded((state) => ({ ...state, attachments: true })); return; }
        form.transform((data) => ({ ...data, tindakan: action }));
        form.post(`/pelayanan/screening/${visit.id}`, { preserveScroll: true, onError, onSuccess: () => form.setDefaults() });
    }
    function submit(event: FormEvent<HTMLFormElement>) { event.preventDefault(); save('kirim'); }
    function start() {
        setStarting(true);
        router.post(`/pelayanan/screening/${visit.id}/mulai`, { petugas_id: form.data.petugas_id }, { preserveScroll: true, onFinish: () => setStarting(false), onError: (errors) => { form.setError(errors); onError(); } });
    }
    const section = (id: Section, title: string, description: string, children: ReactNode) => <AssessmentSection id={id} title={title} description={description} open={expanded[id]} onToggle={() => setExpanded((state) => ({ ...state, [id]: !state[id] }))}>{children}</AssessmentSection>;
    const showOptional = (...names: (ScalarField | 'pemicu_nyeri_gigi' | 'risiko_medis_gigi' | 'riwayat_infeksi_gigi')[]) => mode === 'lengkap' || names.some((name) => Boolean(form.errors[name]) || form.data[name].length > 0);
    const text = (name: keyof typeof assessmentPlaceholders, label: string, required = false, multiline = false, visibleInCompact = false) => (required || visibleInCompact || showOptional(name)) && <Field error={form.errors[name]} htmlFor={name} label={label} required={required}>{multiline ? <Textarea id={name} maxLength={name === 'alasan_pengukuran_tidak_lengkap' ? 2000 : 5000} onChange={(event) => update(name, event.target.value)} placeholder={assessmentPlaceholders[name]} required={required} value={form.data[name]} /> : <Input id={name} maxLength={255} onChange={(event) => update(name, event.target.value)} placeholder={assessmentPlaceholders[name]} required={required} value={form.data[name]} />}</Field>;
    const numeric = (name: ScalarField, label: string, unit: string, required = false, step = '1', max = 300, visibleInCompact = false) => (required || visibleInCompact || showOptional(name)) && <Field error={form.errors[name]} htmlFor={name} label={`${label} (${unit})`} required={required}><Input id={name} inputMode="decimal" min="0" max={max} onChange={(event) => update(name, event.target.value)} placeholder="Hasil pengukuran" step={step} type="number" value={form.data[name]} /></Field>;
    const single = (name: ScalarField, label: string, options: Option[], required = true) => <ChoiceGroup error={form.errors[name]} label={label} name={name} options={options} required={required} compact={!required} selected={form.data[name] ? [form.data[name]] : []} onChange={(value) => update(name, value[0] ?? '')} single />;
    const bloodPressure = () => <Field htmlFor="td_sistole" label="Tekanan darah (mmHg)" required error={[form.errors.td_sistole, form.errors.td_diastole].filter(Boolean).join(' ')}>
        <div role="group" aria-label="Tekanan darah dalam milimeter raksa" className="flex h-10 min-w-0 items-center overflow-hidden rounded-lg border border-neutral-200 bg-surface shadow-sm focus-within:ring-2 focus-within:ring-neutral-400">
            <Input className="h-full min-w-0 rounded-none border-0 text-center shadow-none focus-visible:ring-0" aria-label="Tekanan sistole" id="td_sistole" inputMode="numeric" min="0" max="300" onChange={(event) => update('td_sistole', event.target.value)} placeholder="Sistole" step="1" type="number" value={form.data.td_sistole} />
            <span className="shrink-0 text-neutral-500" aria-hidden="true">/</span>
            <Input className="h-full min-w-0 rounded-none border-0 text-center shadow-none focus-visible:ring-0" aria-label="Tekanan diastole" id="td_diastole" inputMode="numeric" min="0" max="200" onChange={(event) => update('td_diastole', event.target.value)} placeholder="Diastole" step="1" type="number" value={form.data.td_diastole} />
        </div>
    </Field>;
    const anthropometry = <>
        <div className="grid min-w-0 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4">
            {numeric('berat_badan', 'Berat badan', 'kg', false, '0.1', 500, true)}
            {numeric('tinggi_badan', 'Tinggi badan', 'cm', false, '0.1', 300, true)}
            {numeric('lingkar_perut', 'Lingkar perut', 'cm', false, '0.1', 300, true)}
            <Field htmlFor="bmi" label="IMT (kg/m²)"><Input id="bmi" readOnly value={bmi ?? ''} placeholder="Belum dihitung" className="bg-neutral-50 tabular-nums" /></Field>
        </div>
        <p className="text-xs leading-5 text-neutral-500">IMT dihitung dari berat dan tinggi badan. Interpretasi sesuai usia dan kondisi klinis.</p>
        {text('alasan_pengukuran_tidak_lengkap', 'Catatan Pengkajian', false, true, true)}
    </>;
    const historyConfirmation = <>
        <label className="flex cursor-pointer items-start gap-2 text-sm leading-6">
            <Checkbox className="mt-1 shrink-0" id="konfirmasi_riwayat" checked={form.data.konfirmasi_riwayat} onCheckedChange={(checked) => form.setData('konfirmasi_riwayat', checked === true)} />
            <span>Riwayat alergi dan penyakit sudah dikonfirmasi; informasi yang belum diketahui telah ditandai. <span className="text-red-600">*</span></span>
        </label>
        {form.errors.konfirmasi_riwayat && <p className="text-xs text-red-600">{form.errors.konfirmasi_riwayat}</p>}
    </>;
    const painScale = <Field error={form.errors.skala_nyeri} htmlFor="skala_nyeri" label="Skala nyeri (0–10)"><Select id="skala_nyeri" value={form.data.skala_nyeri} onChange={(event) => update('skala_nyeri', event.target.value)}><option value="">Belum dikaji</option>{Array.from({ length: 11 }, (_, value) => <option key={value} value={value}>{value}{value === 0 ? ' · Tidak nyeri' : ''}</option>)}</Select></Field>;
    const dentalAssessment = <>
        <ChoiceGroup error={form.errors.risiko_medis_gigi} label="Riwayat sistemik yang perlu diperhatikan dokter gigi" name="risiko_medis_gigi" required options={[
            ['hipertensi', 'Hipertensi'], ['diabetes', 'Diabetes melitus'], ['penyakit_jantung', 'Penyakit jantung'], ['gangguan_pembekuan', 'Gangguan pembekuan'],
            ['antikoagulan', 'Obat antikoagulan'], ['lainnya', 'Lainnya'], ['tidak_ada', 'Tidak ada, sudah ditanyakan'], ['belum_diketahui', 'Belum dapat diketahui'],
        ]} selected={form.data.risiko_medis_gigi} onChange={(value) => form.setData((data) => ({ ...data, risiko_medis_gigi: value, konfirmasi_riwayat: false }))} />
        <div className="grid min-w-0 gap-4 md:grid-cols-2">
            {text('lokasi_nyeri_gigi', 'Lokasi nyeri gigi / gusi', false, false, true)}
            {text('durasi_keluhan_gigi', 'Pola dan durasi episode nyeri', false, false, true)}
            {painScale}
        </div>
        <ChoiceGroup error={form.errors.pemicu_nyeri_gigi} label="Pemicu nyeri gigi (opsional)" name="pemicu_nyeri_gigi" options={[
            ['dingin', 'Dingin'], ['manis', 'Manis'], ['mengunyah', 'Mengunyah'], ['panas', 'Panas'], ['spontan', 'Spontan'], ['lainnya', 'Lainnya'], ['tidak_ada', 'Tidak ada pemicu'],
        ]} selected={form.data.pemicu_nyeri_gigi} onChange={(value) => form.setData('pemicu_nyeri_gigi', value)} />
        <ChoiceGroup error={form.errors.riwayat_infeksi_gigi} label="Riwayat infeksi yang diketahui (opsional)" name="riwayat_infeksi_gigi" options={[
            ['hepatitis', 'Hepatitis'], ['hiv', 'HIV'], ['infeksi_lain', 'Infeksi lain'], ['tidak_ada', 'Tidak ada riwayat yang diketahui'],
        ]} selected={form.data.riwayat_infeksi_gigi} onChange={(value) => form.setData('riwayat_infeksi_gigi', value)} />
        {text('catatan_medis_gigi', 'Catatan / hal yang perlu dikonfirmasi dokter gigi', form.data.risiko_medis_gigi.includes('belum_diketahui'), true, true)}
        {historyConfirmation}
    </>;
    const compactAssessment = <AssessmentSection
        id="ringkas"
        title="Pengkajian awal"
        description="Isian inti pengkajian pasien. Bagian lain tersedia di tab Lengkap."
        open={expanded.ringkas}
        onToggle={() => setExpanded((state) => ({ ...state, ringkas: !state.ringkas }))}
    >
        <section className="space-y-4">
            <div><h4 className="text-sm font-semibold text-neutral-900">Tanda-tanda vital</h4><p className="mt-1 text-xs text-neutral-500">Catat hasil pengukuran, bukan perkiraan.</p></div>
            <div className="grid min-w-0 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {bloodPressure()}{numeric('nadi', 'Denyut nadi', 'kali/menit', true)}
                {numeric('suhu', 'Suhu tubuh', '°C', true, '0.1', 45)}{numeric('respirasi', 'Frekuensi napas', 'kali/menit', true, '1', 100)}{numeric('spo2', 'Saturasi oksigen', '%', false, '1', 100)}
            </div>
        </section>
        <section className="space-y-4 border-t border-neutral-100 pt-5">
            <div><h4 className="text-sm font-semibold text-neutral-900">Antropometri</h4><p className="mt-1 text-xs text-neutral-500">Opsional: berat badan, tinggi badan, dan lingkar perut.</p></div>
            {anthropometry}
        </section>
        <section className="space-y-4 border-t border-neutral-100 pt-5">
            <div><h4 className="text-sm font-semibold text-neutral-900">Riwayat kritis & alergi</h4><p className="mt-1 text-xs text-neutral-500">Riwayat dari kunjungan sebelumnya dapat diperbarui. Konfirmasi kembali dengan pasien sebelum meneruskan ke dokter.</p></div>
            <div className="space-y-4">
                {single('status_alergi', 'Status riwayat alergi', historyOptions)}
                {form.data.status_alergi === 'ada' && <div className="grid min-w-0 gap-4 md:grid-cols-2"><div className="min-w-0 col-span-full">{text('riwayat_alergi', 'Rincian alergi obat / makanan / lainnya', true, true)}</div>{text('alergi_reaksi', 'Reaksi alergi', false, true)}{text('alergi_jenis', 'Jenis / pemicu alergi')}</div>}
            </div>
            <div className="space-y-4 border-t border-neutral-100 pt-5">
                {single('status_riwayat_penyakit', 'Status riwayat penyakit sistemik', historyOptions)}
                {(form.data.status_riwayat_penyakit === 'ada' || showOptional('riwayat_penyakit', 'riwayat_obat', 'penyakit_nama', 'penyakit_keterangan')) && <div className="grid min-w-0 gap-4 md:grid-cols-2"><div className="min-w-0 col-span-full">{text('riwayat_penyakit', 'Riwayat penyakit sistemik', form.data.status_riwayat_penyakit === 'ada', true)}</div>{text('riwayat_obat', 'Obat rutin / sedang digunakan', false, true)}{text('penyakit_nama', 'Nama penyakit spesifik')}{text('penyakit_keterangan', 'Keterangan riwayat penyakit', false, true)}</div>}
            </div>
            {!isDental && historyConfirmation}
        </section>
        <section className="space-y-4 border-t border-neutral-100 pt-5">
            <div><h4 className="text-sm font-semibold text-neutral-900">Anamnesis dasar</h4><p className="mt-1 text-xs text-neutral-500">Keluhan, lama keluhan, dan sumber informasi.</p></div>
            <div className="grid min-w-0 gap-4 md:grid-cols-2">
                <div className="min-w-0 md:col-span-2">{text('keluhan', 'Keluhan utama / alasan kunjungan', true, true)}</div>
                {text('keluhan_tambahan', 'Keluhan tambahan', false, true)}{text('lama_keluhan', 'Sejak kapan / lama keluhan', true)}
                <Field error={form.errors.sumber_informasi} htmlFor="sumber_informasi" label="Sumber informasi" required>
                    <Select id="sumber_informasi" value={form.data.sumber_informasi} onChange={(event) => update('sumber_informasi', event.target.value)}>
                        <option value="">Pilih sumber informasi</option><option value="pasien">Pasien sendiri</option><option value="keluarga">Keluarga</option><option value="pendamping">Pendamping</option><option value="tidak_dapat_diperoleh">Belum dapat diperoleh</option>
                    </Select>
                </Field>
            </div>
        </section>
        {isDental && <section className="space-y-4 border-t border-neutral-100 pt-5" aria-label="Pengkajian khusus gigi dan mulut">
            <div><h4 className="text-sm font-semibold text-neutral-900">Pengkajian khusus gigi & mulut</h4><p className="mt-1 text-xs text-neutral-500">Catat keluhan gigi, risiko medis, dan informasi yang perlu diteruskan kepada dokter gigi.</p></div>
            {dentalAssessment}
        </section>}
        <section className="space-y-4 border-t border-neutral-100 pt-5">
            <div><h4 className="text-sm font-semibold text-neutral-900">Penapisan cepat</h4><p className="mt-1 text-xs text-neutral-500">Opsional. Kosong berarti belum dinilai, bukan hasil normal.</p></div>
            <div className="grid min-w-0 items-stretch gap-3 sm:grid-cols-2 xl:grid-cols-3">
                {triageFields.map((field) => <div key={field.name} className="min-w-0 rounded-lg border border-neutral-200 bg-neutral-50/50 p-3">{single(field.name, field.label, field.options, false)}</div>)}
                <div className="min-w-0 xl:col-span-2">{text('catatan_triase', 'Catatan penapisan / komunikasi kepada dokter', false, true, true)}</div>
            </div>
            {triage && <div role="status" className={`rounded-lg border p-3 text-sm ${triage === 'merah' ? 'border-red-200 bg-red-50 text-red-900' : triage === 'kuning' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'}`}><strong>Kebutuhan respons awal: {responseLabel}.</strong> {triage === 'merah' ? 'Segera hubungi dokter dan ikuti prosedur kegawatdaruratan klinik; jangan menunda pertolongan untuk melengkapi formulir.' : 'Gunakan hasil ini sebagai informasi awal dan tetap ikuti penilaian klinis serta prosedur klinik.'}</div>}
        </section>
    </AssessmentSection>;
    return <><Head title={`Pengkajian awal ${isDental ? 'gigi' : 'umum'} - ${visit.patient.name}`} />
        <form className="mx-auto max-w-6xl space-y-5" noValidate onSubmit={submit}>
            <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
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
                        <div className="shrink-0 self-start sm:self-center"><StatusBadge status={visit.status} /></div>
                    </div>
                    <dl className="grid grid-cols-2 gap-x-5 gap-y-4 border-t border-neutral-100 bg-neutral-50/60 px-5 py-4 sm:grid-cols-3 sm:px-6">
                        <ProfileDetail label="Jenis kelamin">{visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : 'Belum dicatat'}</ProfileDetail>
                        <ProfileDetail label="Usia">{visit.patient.age == null ? 'Belum diketahui' : `${visit.patient.age} tahun`}</ProfileDetail>
                        <ProfileDetail label="Golongan darah">{visit.patient.bloodType || 'Belum diketahui'}</ProfileDetail>
                        <ProfileDetail label="No. kunjungan">{visit.number}</ProfileDetail>
                        <ProfileDetail label="Dokter">{visit.doctor || 'Belum ditentukan'}</ProfileDetail>
                        <ProfileDetail label="Mulai pengkajian">{visit.startedAt || 'Belum dimulai'}</ProfileDetail>
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
                        {visit.status === 'menunggu' && <Button className="w-full shrink-0 sm:w-auto" disabled={starting || form.processing} type="button" onClick={start}>
                            <Play className="size-4" />{starting ? 'Memulai…' : 'Mulai pengkajian'}
                        </Button>}
                    </div>
                </CardContent>
            </Card>
            {visit.patient.allergyHistory && <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"><strong>Riwayat alergi tersimpan:</strong> {visit.patient.allergyHistory}<p className="mt-1 text-xs">Konfirmasi kembali dengan pasien. Riwayat sebelumnya tetap tersedia untuk membantu pengkajian.</p></div>}
            {Object.keys(form.errors).length > 0 && <div ref={errorSummary} tabIndex={-1} role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><strong>Pengkajian belum dapat disimpan.</strong><ul className="mt-2 list-inside list-disc">{Object.entries(form.errors).map(([name, error]) => <li key={name}>{error}</li>)}</ul></div>}
            <Tabs value={mode} onValueChange={setMode} className="space-y-4">
                <div className="rounded-xl border border-neutral-200 bg-surface">
                    <div className="flex flex-col gap-3 px-4 pt-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div>
                            <h3 className="text-sm font-semibold text-neutral-900">Pengkajian pasien</h3>
                            <p className="mt-1 text-xs leading-5 text-neutral-500"><span className="font-semibold text-red-600">*</span> Isian bertanda wajib dilengkapi sebelum dikirim ke dokter.</p>
                        </div>
                    </div>
                    <TabsList aria-label="Tingkat detail pengkajian" className="mt-3 px-2 sm:px-3">
                        <TabsTrigger aria-controls="screening-assessment" value="ringkas">Ringkas</TabsTrigger>
                        <TabsTrigger aria-controls="screening-assessment" value="lengkap">Lengkap</TabsTrigger>
                    </TabsList>
                    <p className="px-4 py-3 text-xs leading-5 text-neutral-500 sm:px-5">
                        {mode === 'ringkas' ? 'Isian inti pengkajian awal pasien.' : 'Seluruh isian pengkajian awal dan informasi tambahan.'}
                    </p>
                </div>
                <TabsContent id="screening-assessment" value={mode} className="rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400">
                <fieldset disabled={!editable || form.processing || starting} className="min-w-0 space-y-4">
                {mode === 'ringkas' && compactAssessment}
                {mode === 'lengkap' && <>
                {section('vitals', '1. Tanda-tanda vital', 'Tekanan darah, nadi, suhu, dan frekuensi napas.', <>
                    <div className="grid min-w-0 items-start gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {bloodPressure()}
                        {numeric('nadi', 'Denyut nadi', 'kali/menit', true)}
                        {numeric('suhu', 'Suhu tubuh', '°C', true, '0.1', 45)}
                        {numeric('respirasi', 'Frekuensi napas', 'kali/menit', true, '1', 100)}
                        {numeric('spo2', 'Saturasi oksigen', '%', false, '1', 100)}
                    </div>
                </>)}

                {section('anthropometry', '2. Antropometri', 'Opsional. Berat badan, tinggi badan, lingkar perut, dan indeks massa tubuh.', anthropometry)}

                {section('history', '3. Riwayat kritis & alergi', 'Konfirmasi kembali alergi dan penyakit pada kunjungan ini.', <>
                    <div className="space-y-4">
                        {single('status_alergi', 'Status riwayat alergi', historyOptions)}
                        {form.data.status_alergi === 'ada' && <div className="grid min-w-0 gap-4">
                            {text('riwayat_alergi', 'Rincian alergi obat / makanan / lainnya', true, true)}
                            {text('alergi_reaksi', 'Reaksi alergi', false, true)}
                            {text('alergi_jenis', 'Jenis / pemicu alergi')}
                        </div>}
                    </div>
                    <div className="space-y-4 border-t border-neutral-100 pt-5">
                        {single('status_riwayat_penyakit', 'Status riwayat penyakit sistemik', historyOptions)}
                        {form.data.status_riwayat_penyakit === 'ada' && <div className="grid min-w-0 gap-4">
                            {text('riwayat_penyakit', 'Riwayat penyakit sistemik', true, true)}
                            {text('penyakit_nama', 'Nama penyakit spesifik')}
                            {text('penyakit_keterangan', 'Keterangan riwayat penyakit', false, true)}
                        </div>}
                        {text('riwayat_obat', 'Obat rutin / sedang digunakan', false, true)}
                    </div>
                    {!isDental && historyConfirmation}
                </>)}

                {section('complaint', '4. Anamnesis dasar', 'Keluhan dan sumber informasi pasien.', <>
                    <div className="grid min-w-0 gap-4 md:grid-cols-2">
                        <div className="min-w-0 md:col-span-2">{text('keluhan', 'Keluhan utama / alasan kunjungan', true, true)}</div>
                        {showOptional('keluhan_tambahan') && <div className="min-w-0 md:col-span-2">{text('keluhan_tambahan', 'Keluhan tambahan', false, true)}</div>}
                        {text('lama_keluhan', 'Sejak kapan / lama keluhan', true)}
                        <Field error={form.errors.sumber_informasi} htmlFor="sumber_informasi" label="Sumber informasi" required>
                            <Select id="sumber_informasi" value={form.data.sumber_informasi} onChange={(event) => update('sumber_informasi', event.target.value)}>
                                <option value="">Pilih sumber informasi</option>
                                <option value="pasien">Pasien sendiri</option>
                                <option value="keluarga">Keluarga</option>
                                <option value="pendamping">Pendamping</option>
                                <option value="tidak_dapat_diperoleh">Belum dapat diperoleh</option>
                            </Select>
                        </Field>
                    </div>
                </>)}

                {section('triage', '5. Penapisan cepat', 'Pilih jawaban sesuai hasil pengkajian. Bagian ini membantu mengenali kebutuhan respons awal.', <>
                    <p className="text-xs leading-5 text-neutral-500">Opsional. Biarkan kosong bila belum dinilai; jawaban kosong tidak dianggap normal.</p>
                    <div className="grid min-w-0 items-stretch gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {triageFields.map((field) => <div key={field.name} className="min-w-0 rounded-lg border border-neutral-200 bg-neutral-50/50 p-3">{single(field.name, field.label, field.options, false)}</div>)}
                        <div className="min-w-0 xl:col-span-2">{text('catatan_triase', 'Catatan penapisan / komunikasi kepada dokter', false, true, true)}</div>
                    </div>
                    {triage && <div role="status" className={`rounded-lg border p-3 text-sm ${triage === 'merah' ? 'border-red-200 bg-red-50 text-red-900' : triage === 'kuning' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900'}`}><strong>Kebutuhan respons awal: {responseLabel}.</strong> {triage === 'merah' ? 'Segera hubungi dokter dan ikuti prosedur kegawatdaruratan klinik; jangan menunda pertolongan untuk melengkapi formulir.' : 'Gunakan hasil ini sebagai informasi awal dan tetap ikuti penilaian klinis serta prosedur klinik.'}</div>}
                </>)}

                {isDental && section('dental', '6. Pengkajian khusus gigi & mulut', 'Keluhan gigi dan mulut, risiko medis, serta informasi untuk dokter gigi.', dentalAssessment)}

                {showOptional('skala_nyeri', 'skrining_gizi', 'riwayat_penyakit_keluarga', 'pemeriksaan_fisik') && section('additional', `${isDental ? '7' : '6'}. Pengkajian tambahan`, 'Opsional sesuai kebutuhan pasien. Jawaban kosong berarti belum dikaji, bukan hasil normal.', <div className="grid gap-4 md:grid-cols-2">{!isDental && painScale}<Field error={form.errors.skrining_gizi} htmlFor="skrining_gizi" label="Hasil pengkajian gizi"><Select id="skrining_gizi" value={form.data.skrining_gizi} onChange={(event) => update('skrining_gizi', event.target.value)}><option value="">Belum dikaji</option><option value="normal">Normal</option><option value="kurang">Gizi kurang</option><option value="lebih">Gizi lebih</option></Select></Field>{text('riwayat_penyakit_keluarga', 'Riwayat penyakit keluarga', false, true)}{text('pemeriksaan_fisik', 'Observasi / catatan pemeriksaan awal', false, true)}</div>)}
                </>}
                </fieldset>
                </TabsContent>
            </Tabs>
            {section('attachments', 'Lampiran pasien', 'Opsional. Hasil lab, surat rujukan, atau riwayat pendukung pemeriksaan.', <VisitAttachments attachments={visit.attachments} uploadUrl={editable ? visit.attachmentUploadUrl : undefined} disabled={form.processing || starting} onPendingChange={setAttachmentPending} />)}
            <footer className="rounded-xl border border-neutral-200 bg-surface p-4 shadow-sm sm:sticky sm:bottom-3 sm:z-10 sm:p-5">
                <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div className="min-w-0 text-xs leading-5 text-neutral-500" aria-live="polite">
                        <p>Kirim pengkajian awal kepada dokter untuk ditindaklanjuti.</p>
                        {form.isDirty && <p className="font-medium text-amber-700">Perubahan belum disimpan.</p>}
                        {attachmentPending && <p className="font-medium text-amber-700">Unggah atau batalkan berkas di bagian Lampiran pasien terlebih dahulu.</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                        <Button disabled={!visit.printUrl || form.isDirty || form.processing || starting || attachmentPending} onClick={() => visit.printUrl && window.location.assign(visit.printUrl)} title={form.isDirty ? 'Simpan perubahan sebelum mencetak' : !visit.printUrl ? 'Simpan pengkajian sebelum mencetak' : 'Cetak pengkajian tersimpan'} type="button" variant="secondary">
                            <Printer className="size-4" />Cetak pengkajian
                        </Button>
                        <Button disabled={form.processing || starting || !editable || attachmentPending} onClick={() => save('draf')} type="button" variant="secondary">
                            <Save className="size-4" />Simpan draf
                        </Button>
                        <Button className="col-span-2" disabled={form.processing || starting || !editable || attachmentPending} type="submit">
                            <ClipboardCheck className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan & kirim ke dokter'}
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

function AssessmentSection({ id, title, description, open, onToggle, children }: { id: string; title: string; description: string; open: boolean; onToggle: () => void; children: ReactNode }) {
    return <details className="min-w-0 rounded-xl border border-neutral-200 bg-surface" open={open} id={`section-${id}`}>
        <summary
            className="flex cursor-pointer list-none items-start justify-between gap-4 rounded-xl p-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-400 sm:px-5 [&::-webkit-details-marker]:hidden"
            onClick={(event) => { event.preventDefault(); onToggle(); }}
            aria-expanded={open}
        >
            <div className="min-w-0">
                <h3 className="text-sm font-semibold text-neutral-900">{title}</h3>
                <p className="mt-1 text-xs leading-5 text-neutral-500">{description}</p>
            </div>
            <ChevronDown aria-hidden="true" className={`mt-0.5 size-4 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} />
        </summary>
        <div className="space-y-5 border-t border-neutral-100 p-4 sm:p-5">{children}</div>
    </details>;
}

function ChoiceGroup({ label, name, options, selected, onChange, single = false, compact = false, required = false, error }: { label: string; name: string; options: Option[]; selected: string[]; onChange: (values: string[]) => void; single?: boolean; compact?: boolean; required?: boolean; error?: string }) {
    function toggle(value: string, checked: boolean) {
        if (single) { onChange(checked ? [value] : []); return; }
        const exclusive = ['tidak_ada', 'belum_diketahui'];
        onChange(!checked ? selected.filter((item) => item !== value) : exclusive.includes(value) ? [value] : [...selected.filter((item) => !exclusive.includes(item)), value]);
    }
    return <fieldset className="min-w-0 space-y-2" aria-describedby={error ? `${name}-error` : undefined}>
        <legend className="text-sm font-medium text-neutral-800">{label}{required && <span className="ml-1 text-red-600">*</span>}</legend>
        <div className="flex min-w-0 flex-wrap gap-x-3 gap-y-2">
            {options.map(([value, text]) => <label key={value} htmlFor={`${name}-${value}`} className={`flex min-w-0 max-w-full cursor-pointer items-start gap-2 text-sm leading-5 transition-colors ${compact ? 'min-h-8 py-1.5' : `min-h-10 rounded-lg border px-3 py-2 ${selected.includes(value) ? 'border-neutral-600 bg-neutral-100 text-neutral-950' : 'border-neutral-200 text-neutral-700 hover:bg-neutral-50'}`}`}>
                <Checkbox className="mt-0.5 size-4 shrink-0" id={`${name}-${value}`} checked={selected.includes(value)} onCheckedChange={(checked) => toggle(value, checked === true)} />
                <span className="min-w-0 whitespace-normal break-words">{text}</span>
            </label>)}
        </div>
        {error && <p id={`${name}-error`} role="alert" className="text-xs text-red-600">{error}</p>}
    </fieldset>;
}
