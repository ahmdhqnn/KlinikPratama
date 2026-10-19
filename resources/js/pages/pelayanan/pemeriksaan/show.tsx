import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, ClipboardPlus, FileText, HeartPulse, Lightbulb, Pill, Plus, Search, Stethoscope, Trash2, Undo2, UserRound } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { DatePicker } from '@/components/ui/date-picker';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { toast } from 'sonner';
import { OdontogramFdi } from '@/components/odontogram/odontogram-fdi';
import { AnatomyBodyMap, type BodyOrganMeta } from '@/components/anatomy/anatomy-body-map';
import { optionalAnswers } from '@/lib/optional-answers';

interface Option { id: number; name: string; }
interface Diagnosis { id: number; code: string; codeSystem?: string; name: string; type: string; }
interface Treatment { id: number; name: string; code: string | null; codeSystem: string | null; codeRelease: string | null; quantity: number; toothFdi: string | null; note: string | null; }
interface ProcedureSuggestion { id: number; code: string; display: string; release: string; }
interface DentalFinding { toothFdi: string; surface: string; findingCode: string; notes: string | null; }
interface PrescriptionItem { id: number; name: string; type: string; quantity: number; unit: string | null; instructions: string | null; external: boolean; }
interface Letter { id: number; type: string; number: string | null; content: string | null; date: string | null; printUrl: string; }
interface Referral { id: number; fromClinic: string | null; toClinic: string | null; notes: string | null; status: string; number: string | null; content: string | null; printUrl: string; }
interface CorrespondenceOption { id: number; name: string; type: string; content: string; }
interface Examination {
    doctorId: number | null;
    canClaim: boolean;
    startedAt: string | null;
    anamnesis: string | null;
    currentHistory: string | null;
    pastHistory: string | null;
    familyHistory: string | null;
    allergyHistory: string | null;
    physicalExam: string | null;
    physicalSystems: Record<string, string>;
    extraoralExam: string | null;
    oralHygieneIndex: number | null;
    differentialDiagnosis: string | null;
    followUpDate: string | null;
    notes: string | null;
    education: string | null;
    status: string;
    integrityValid: boolean | null;
    signedAt: string | null;
    signedBy: string | null;
    versions: Array<{ version: number; kind: string; reason: string | null; actor: string | null; recordedAt: string | null; anamnesis: string | null; physicalExam: string | null; notes: string | null; education: string | null; odontogram: DentalFinding[] }>;
}
interface Visit {
    id: number;
    number: string;
    status: string;
    canClaim: boolean;
    doctorId: number | null;
    clinicId: number;
    clinicType: string | null;
    clinic: string;
    patient: { id: number; name: string; medicalRecordNumber: string; gender: string | null; age: number | null; bloodType: string | null; allergies: string | null; };
    examinationFormDefaults: { anamnesis: string | null; currentHistory: string | null; pastHistory: string | null; familyHistory: string | null; allergyHistory: string | null; };
    screening: { systolic: number | null; diastolic: number | null; pulse: number | null; temperature: number | null; oxygenSaturation: number | null; weight: number | null; height: number | null; bmi: number | null; respiration: number | null; complaint: string | null; medicalHistory: string | null; familyHistory: string | null; allergyHistory: string | null; painScale: number | null; fallRisk: string | null; triage: string | null; priority: string | null; dentalPainLocation: string | null; dentalPainTriggers: string[]; dentalPainDuration: string | null; dentalMedicalRisks: string[]; dentalInfectionHistory: string[]; dentalNotes: string | null; } | null;
    examination: Examination | null;
    diagnoses: Diagnosis[];
    treatments: Treatment[];
    odontogram: DentalFinding[];
    prescription: { status: string | null; items: PrescriptionItem[]; printUrl: string | null };
    letters: Letter[];
    referrals: Referral[];
}
interface Props {
    visit: Visit;
    doctors: Option[];
    treatments: Option[];
    medicines: (Option & { stock: number; unit: string | null })[];
    clinics: Option[];
    diagnosisCodeSystems: Array<{ value: string; label: string }>;
    procedureCatalogAvailable: boolean;
    procedureSearchUrl: string;
    dentalOptions: { teeth: string[]; surfaces: Record<string, string>; findings: Record<string, string> };
    today: string;
    correspondenceTemplates: CorrespondenceOption[];
}
interface IcdSuggestion { kode: string; nama: string; code_system: string; release: string; }
type Tab = 'anamnesis' | 'diagnosa' | 'tindakan' | 'resep' | 'resep-luar' | 'surat' | 'rujukan';


const tabLabels: Record<Tab, string> = {
    anamnesis: 'Anamnesis & fisik',
    diagnosa: 'Diagnosis',
    tindakan: 'Tindakan',
    resep: 'Resep klinik',
    'resep-luar': 'Resep luar',
    surat: 'Surat medis',
    rujukan: 'Rujukan internal',
};

const tabs: { id: Tab; label: string; icon: typeof Stethoscope }[] = [
    { id: 'anamnesis', label: 'Anamnesis & fisik', icon: Stethoscope },
    { id: 'diagnosa', label: 'Diagnosis', icon: ClipboardPlus },
    { id: 'tindakan', label: 'Tindakan', icon: HeartPulse },
    { id: 'resep', label: 'Resep klinik', icon: Pill },
    { id: 'resep-luar', label: 'Resep luar', icon: Pill },
    { id: 'surat', label: 'Surat medis', icon: FileText },
    { id: 'rujukan', label: 'Rujukan internal', icon: ArrowLeft },
];

export default function ExaminationDetail({ visit, doctors, treatments, medicines, clinics, diagnosisCodeSystems, procedureCatalogAvailable, procedureSearchUrl, dentalOptions, today, correspondenceTemplates }: Props) {
    const [activeTab, setActiveTab] = useState<Tab>(() => {
        const requestedTab = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('tab');

        return tabs.some(({ id }) => id === requestedTab) ? requestedTab as Tab : 'anamnesis';
    });
    const [icdQuery, setIcdQuery] = useState('');
    const [diagnosisCodeSystem, setDiagnosisCodeSystem] = useState('icd10_who');
    const [icdResults, setIcdResults] = useState<IcdSuggestion[]>([]);
    const [icdLoading, setIcdLoading] = useState(false);
    const [suggestions, setSuggestions] = useState<IcdSuggestion[]>([]);
    const [suggestionsOpen, setSuggestionsOpen] = useState(false);
    const [signatureDialogOpen, setSignatureDialogOpen] = useState(false);
    const [dentition, setDentition] = useState<'adult' | 'primary'>('adult');
    const [treatmentMode, setTreatmentMode] = useState<'icd9cm' | 'clinic' | 'manual'>('icd9cm');
    const [procedureQuery, setProcedureQuery] = useState('');
    const [procedureResults, setProcedureResults] = useState<ProcedureSuggestion[]>([]);
    const [procedureLoading, setProcedureLoading] = useState(false);
    const [procedureSearchFailed, setProcedureSearchFailed] = useState(false);
    const [selectedProcedure, setSelectedProcedure] = useState<ProcedureSuggestion | null>(null);
    const [selectedDentalFindingKeys, setSelectedDentalFindingKeys] = useState<string[]>([]);
    const [undoFinding, setUndoFinding] = useState<DentalFinding | null>(null);
    const [selectedBodySystem, setSelectedBodySystem] = useState('Thorax');
    const [selectedBodyOrganId, setSelectedBodyOrganId] = useState('lungs');
    const defaultPhysicalSystems: Record<string, string> = {
        'Kepala dan leher': visit.examination?.physicalSystems?.['Kepala dan leher'] ?? '',
        Thorax: visit.examination?.physicalSystems?.Thorax ?? '',
        Abdomen: visit.examination?.physicalSystems?.Abdomen ?? '',
        Ekstremitas: visit.examination?.physicalSystems?.Ekstremitas ?? '',
        Neurologis: visit.examination?.physicalSystems?.Neurologis ?? '',
        Kulit: visit.examination?.physicalSystems?.Kulit ?? '',
    };
    const examinationForm = useForm({
        dokter_id: (visit.examination?.doctorId ?? visit.doctorId ?? '').toString(),
        anamnesis: visit.examinationFormDefaults.anamnesis ?? '',
        riwayat_penyakit_sekarang: visit.examinationFormDefaults.currentHistory ?? '',
        riwayat_penyakit_dahulu: visit.examinationFormDefaults.pastHistory ?? '',
        riwayat_penyakit_keluarga: visit.examinationFormDefaults.familyHistory ?? '',
        riwayat_alergi: visit.examinationFormDefaults.allergyHistory ?? '',
        pemeriksaan_fisik: visit.examination?.physicalExam ?? '',
        pemeriksaan_fisik_terstruktur: { ...defaultPhysicalSystems },
        pemeriksaan_ekstraoral: visit.examination?.extraoralExam ?? '',
        oral_hygiene_index: visit.examination?.oralHygieneIndex?.toString() ?? '',
        diagnosis_banding: visit.examination?.differentialDiagnosis ?? '',
        kontrol_berikutnya: visit.examination?.followUpDate ?? '',
        catatan: visit.examination?.notes ?? '',
        edukasi: visit.examination?.education ?? '',
    });
    const diagnosisForm = useForm({ kode_icd10: '', code_system: 'icd10_who', code_release: '', nama_diagnosa: '', jenis: 'utama' });
    const treatmentForm = useForm({ terminology_id: '', tindakan_id: '', nama_tindakan_manual: '', jumlah: '1', tooth_fdis: [] as string[], catatan: '' });
    const dentalForm = useForm({ tooth_fdi: '', surface: 'W', finding_code: 'caries', notes: '', reason: '' });
    const odontogramUndoForm = useForm({ tooth_fdi: '', surface: '', reason: '' });
    const prescriptionForm = useForm({ obat_id: '', nama_obat: '', jumlah: '1', satuan: '', aturan_pakai: '', catatan: '', jenis: 'jadi', is_resep_luar: false });
    const externalPrescriptionForm = useForm({ obat_id: '', nama_obat: '', jumlah: '1', satuan: '', aturan_pakai: '', catatan: '', jenis: 'jadi', is_resep_luar: true });
    const letterForm = useForm({ jenis: 'sakit', nomor_surat: '', surat_template_id: '', tanggal: today, konten: '' });
    const referralForm = useForm({ ke_poli_id: '', surat_template_id: '', catatan: '', konten_surat: '' });
    const signatureForm = useForm({ signature_password: '' });
    const addendumForm = useForm({
        reason: '',
        anamnesis: visit.examination?.anamnesis ?? '',
        riwayat_penyakit_sekarang: visit.examination?.currentHistory ?? '',
        riwayat_penyakit_dahulu: visit.examination?.pastHistory ?? '',
        riwayat_penyakit_keluarga: visit.examination?.familyHistory ?? '',
        riwayat_alergi: visit.examination?.allergyHistory ?? '',
        pemeriksaan_fisik: visit.examination?.physicalExam ?? '',
        pemeriksaan_fisik_terstruktur: { ...defaultPhysicalSystems },
        pemeriksaan_ekstraoral: visit.examination?.extraoralExam ?? '',
        oral_hygiene_index: visit.examination?.oralHygieneIndex?.toString() ?? '',
        diagnosis_banding: visit.examination?.differentialDiagnosis ?? '',
        catatan: visit.examination?.notes ?? '',
        edukasi: visit.examination?.education ?? '',
        kontrol_berikutnya: visit.examination?.followUpDate ?? '',
    });

    useEffect(() => {
        const query = icdQuery.trim();
        if (query.length < 2) {
            setIcdResults([]);
            setIcdLoading(false);
            return;
        }

        let active = true;
        setIcdLoading(true);
        const timeout = window.setTimeout(() => {
            fetch(`/pelayanan/icd10/search?q=${encodeURIComponent(query.slice(0, 100))}&code_system=${encodeURIComponent(diagnosisCodeSystem)}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() as Promise<IcdSuggestion[]> : Promise.reject(new Error('Pencarian ICD-10 gagal.')))
                .then((results) => { if (active) setIcdResults(results); })
                .catch(() => { if (active) setIcdResults([]); })
                .finally(() => { if (active) setIcdLoading(false); });
        }, 250);

        return () => { active = false; window.clearTimeout(timeout); };
    }, [icdQuery, diagnosisCodeSystem]);

    useEffect(() => {
        const query = procedureQuery.trim();
        setProcedureResults([]);
        setProcedureSearchFailed(false);
        if (query.length < 2 || treatmentMode !== 'icd9cm' || !procedureCatalogAvailable || selectedProcedure) {
            setProcedureLoading(false);
            return;
        }

        const controller = new AbortController();
        setProcedureLoading(true);
        const timeout = window.setTimeout(async () => {
            try {
                const response = await fetch(`${procedureSearchUrl}?q=${encodeURIComponent(query.slice(0, 100))}`, {
                    headers: { Accept: 'application/json' }, signal: controller.signal,
                });
                if (!response.ok) throw new Error('Pencarian tindakan gagal.');
                const results = await response.json() as ProcedureSuggestion[];
                if (!controller.signal.aborted) setProcedureResults(results);
            } catch {
                if (!controller.signal.aborted) setProcedureSearchFailed(true);
            } finally {
                if (!controller.signal.aborted) setProcedureLoading(false);
            }
        }, 250);

        return () => { controller.abort(); window.clearTimeout(timeout); };
    }, [procedureQuery, procedureSearchUrl, treatmentMode, procedureCatalogAvailable, selectedProcedure]);

    function changeTreatmentMode(mode: 'icd9cm' | 'clinic' | 'manual') {
        setTreatmentMode(mode);
        setSelectedProcedure(null);
        setProcedureQuery('');
        setProcedureResults([]);
        treatmentForm.clearErrors();
        treatmentForm.setData((data) => ({ ...data, terminology_id: '', tindakan_id: '', nama_tindakan_manual: '' }));
    }

    function submitForm(event: FormEvent<HTMLFormElement>, form: typeof examinationForm | typeof diagnosisForm | typeof treatmentForm | typeof prescriptionForm | typeof externalPrescriptionForm | typeof letterForm | typeof referralForm, url: string, nextTab?: Tab) {
        event.preventDefault();
        form.post(url, { preserveScroll: true, onSuccess: () => {
            if (form === diagnosisForm) diagnosisForm.reset();
            if (form === treatmentForm) {
                treatmentForm.reset();
                setSelectedProcedure(null);
                setProcedureQuery('');
                setProcedureResults([]);
                setSelectedDentalFindingKeys([]);
            }
            if (form === prescriptionForm) prescriptionForm.reset();
            if (form === externalPrescriptionForm) externalPrescriptionForm.reset();
            if (form === letterForm) letterForm.reset();
            if (form === referralForm) referralForm.reset();
            if (nextTab) setActiveTab(nextTab);
        } });
    }

    function selectDentalTooth(tooth: string): void {
        dentalForm.setData('tooth_fdi', tooth);
        const matchingFindings = visit.odontogram.filter((finding) => finding.toothFdi === tooth);
        setSelectedDentalFindingKeys(matchingFindings.map((finding) => `${finding.toothFdi}-${finding.surface}`));
        treatmentForm.setData('tooth_fdis', matchingFindings.length > 0 ? [tooth] : []);
    }

    function setDentalFindingSelection(finding: DentalFinding, selected: boolean): void {
        const key = `${finding.toothFdi}-${finding.surface}`;
        const nextKeys = selected
            ? [...new Set([...selectedDentalFindingKeys, key])]
            : selectedDentalFindingKeys.filter((selectedKey) => selectedKey !== key);
        const selectedTeeth = [...new Set(visit.odontogram
            .filter((item) => nextKeys.includes(`${item.toothFdi}-${item.surface}`))
            .map((item) => item.toothFdi))];

        setSelectedDentalFindingKeys(nextKeys);
        treatmentForm.setData('tooth_fdis', selectedTeeth);
        if (selected) {
            dentalForm.setData('tooth_fdi', finding.toothFdi);
        }
    }

    function undoDentalFinding(): void {
        if (!undoFinding) return;

        odontogramUndoForm.delete(`/pelayanan/pemeriksaan/${visit.id}/odontogram`, {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedDentalFindingKeys((keys) => keys.filter((key) => key !== `${undoFinding.toothFdi}-${undoFinding.surface}`));
                treatmentForm.setData('tooth_fdis', treatmentForm.data.tooth_fdis.filter((tooth) => tooth !== undoFinding.toothFdi));
                setUndoFinding(null);
                odontogramUndoForm.reset();
            },
        });
    }

    function renderTabNavigation(tab: Tab, nextTab?: Tab, nextLabel?: string) {
        const tabIndex = tabs.findIndex((item) => item.id === tab);
        const previousTab = tabIndex > 0 ? tabs[tabIndex - 1].id : null;

        if (!previousTab && !nextTab) return null;

        return <div className="flex flex-col justify-between gap-3 border-t border-neutral-200 pt-4 sm:flex-row sm:items-center">
            {previousTab ? <Button onClick={() => setActiveTab(previousTab)} size="sm" type="button" variant="secondary"><ArrowLeft className="size-4" />Kembali ke {tabLabels[previousTab]}</Button> : <span />}
            {nextTab && <Button className="sm:ml-auto" onClick={() => setActiveTab(nextTab)} size="sm" type="button">{nextLabel ?? `Lanjut ke ${tabLabels[nextTab]}`}<ArrowLeft className="size-4 rotate-180" /></Button>}
        </div>;
    }

    function chooseDiagnosis(code: string, name: string, codeSystem = diagnosisCodeSystem, release = '') {
        diagnosisForm.setData((data) => ({ ...data, kode_icd10: code, code_system: codeSystem, code_release: release, nama_diagnosa: name }));
        setIcdQuery('');
        setIcdResults([]);
    }

    async function suggestDiagnoses() {
        const query = examinationForm.data.anamnesis.trim().split(/\s+/).filter((word) => word.length > 3).slice(0, 6).join(' ');
        if (!query) {
            toast.error('Tuliskan keluhan atau anamnesis terlebih dahulu.');
            return;
        }
        try {
            const response = await fetch(`/pelayanan/icd10/search?q=${encodeURIComponent(query)}&code_system=${encodeURIComponent(diagnosisCodeSystem)}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Pencarian terminologi gagal.');
            const results = await response.json() as IcdSuggestion[];
            const next = results.map((item) => ({ ...item }));
            setSuggestions(next);
        } catch {
            setSuggestions([]);
        }
        setSuggestionsOpen(true);
    }

    function deleteItem(url: string, confirmation: string) {
        confirmAction(confirmation, () => { router.delete(url, { preserveScroll: true }); }, 'Lanjutkan');
    }

    function finishExamination() {
        setSignatureDialogOpen(true);
    }

    function signExamination(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        signatureForm.post(`/pelayanan/pemeriksaan/${visit.id}/selesai`, {
            preserveScroll: true,
            onSuccess: () => {
                setSignatureDialogOpen(false);
                signatureForm.reset();
            },
        });
    }

    const canEdit = !visit.canClaim && visit.status === 'pemeriksaan' && visit.examination?.status !== 'selesai';
    const prescriptionEditable = canEdit && (!visit.prescription.status || visit.prescription.status === 'menunggu');
    const allergyWarning = [visit.patient.allergies, visit.screening?.allergyHistory].find(isReportedAllergy);
    return (
        <>
            <Head title={`Pemeriksaan ${visit.patient.name}`} />
            <div className="space-y-6">
                <Card><CardContent className="space-y-4 p-5 sm:p-6">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div className="flex min-w-0 items-center gap-3"><span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-lg font-bold text-neutral-800">{visit.patient.name.slice(0, 1).toLocaleUpperCase()}</span><div className="min-w-0 text-left"><p className="text-xs font-medium text-neutral-500">{visit.number} · {visit.clinic}</p><h2 className="mt-0.5 text-lg font-semibold text-neutral-950">{visit.patient.name}</h2><p className="mt-1 text-sm text-neutral-600">{visit.patient.medicalRecordNumber} · {visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : 'Belum diketahui'} · {visit.patient.age === null ? 'umur belum diketahui' : `${visit.patient.age} th`} · Gol. {visit.patient.bloodType ?? 'Belum diketahui'}</p>{visit.examination?.startedAt && <p className="mt-1 text-xs text-neutral-500">Pemeriksaan dimulai {visit.examination.startedAt}</p>}</div></div>
                        <div className="flex shrink-0 flex-wrap justify-start gap-2 sm:justify-end"><Button asChild size="sm" variant="secondary"><Link href={`/pelayanan/pasien/${visit.patient.id}/rekam-medis`}><UserRound className="size-4" />Riwayat RME</Link></Button>{visit.canClaim && <Button onClick={() => router.post(`/pelayanan/pemeriksaan/${visit.id}/ambil`, {}, { preserveScroll: true })} size="sm"><Stethoscope className="size-4" />Ambil kunjungan</Button>}<Button disabled={!visit.examination || !canEdit} onClick={finishExamination} size="sm"><Check className="size-4" />Selesai periksa</Button></div>
                    </div>
                    {allergyWarning && <Alert className="text-left" variant="destructive"><AlertTriangle aria-hidden="true" /><AlertTitle>Peringatan alergi</AlertTitle><AlertDescription>{allergyWarning}</AlertDescription></Alert>}
                </CardContent></Card>

                {visit.canClaim && <Alert><AlertTitle>Kunjungan belum ditugaskan</AlertTitle><AlertDescription>Anda memiliki jadwal aktif pada poli dan tanggal kunjungan ini. Ambil kunjungan untuk menugaskannya ke akun Anda sebelum mengisi pemeriksaan.</AlertDescription></Alert>}

                <Card><CardHeader><CardTitle>Ringkasan skrining perawat</CardTitle><CardDescription>Data skrining {visit.clinicType === 'gigi' ? 'Poli Gigi' : 'Poli Umum'} menjadi konteks awal pemeriksaan dokter.</CardDescription></CardHeader><CardContent className="space-y-4">
                    {visit.screening ? <>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{[
                            ['Tekanan darah', formatBloodPressure(visit.screening.systolic, visit.screening.diastolic)], ['Nadi', formatMeasurement(visit.screening.pulse, 'x/menit')], ['Suhu', formatMeasurement(visit.screening.temperature, '°C')], ['SpO₂', formatMeasurement(visit.screening.oxygenSaturation, '%')], ['Respirasi', formatMeasurement(visit.screening.respiration, 'x/menit')], ['Berat / tinggi', `${formatMeasurement(visit.screening.weight, 'kg')} / ${formatMeasurement(visit.screening.height, 'cm')}`], ['IMT', visit.screening.bmi === null ? 'Belum dihitung' : `${visit.screening.bmi} kg/m²`], ['Nyeri / jatuh', `${visit.screening.painScale === null ? 'Belum dinilai' : `${visit.screening.painScale} / 10`} · ${visit.screening.fallRisk ?? 'Belum dinilai'}`],
                        ].map(([label, value]) => <div className="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-left" key={label}><p className="text-xs text-neutral-500">{label}</p><p className="mt-1 text-sm font-semibold text-neutral-900">{value}</p></div>)}</div>
                        <div className="grid gap-4 md:grid-cols-2"><div className="space-y-2 text-sm"><p><strong>Keluhan awal:</strong> {visit.screening.complaint || optionalAnswers.notRecorded}</p><p><strong>Riwayat penyakit:</strong> {visit.screening.medicalHistory || optionalAnswers.notRecorded}</p><p><strong>Riwayat keluarga:</strong> {visit.screening.familyHistory || optionalAnswers.notRecorded}</p><p><strong>Riwayat alergi:</strong> {visit.screening.allergyHistory || visit.patient.allergies || optionalAnswers.notRecorded}</p><p><strong>Triase / prioritas:</strong> {visit.screening.triage || 'Belum ditentukan'} / {visit.screening.priority || 'Belum ditentukan'}</p></div>
                            {visit.clinicType === 'gigi' && <div className="space-y-2 rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-sm"><p className="font-semibold text-neutral-900">Skrining dental</p><p><strong>Lokasi nyeri:</strong> {visit.screening.dentalPainLocation || optionalAnswers.notRecorded}</p><p><strong>Pemicu:</strong> {displayDentalValues(visit.screening.dentalPainTriggers, optionalAnswers.notRecorded)}</p><p><strong>Durasi:</strong> {visit.screening.dentalPainDuration || optionalAnswers.notRecorded}</p><p><strong>Risiko sistemik:</strong> {displayDentalValues(visit.screening.dentalMedicalRisks, optionalAnswers.notRecorded)}</p><p><strong>Riwayat infeksi:</strong> {displayDentalValues(visit.screening.dentalInfectionHistory, optionalAnswers.notRecorded)}</p><p><strong>Catatan perawat:</strong> {visit.screening.dentalNotes || optionalAnswers.notRecorded}</p></div>}
                        </div>
                    </> : <Empty className="min-h-0 py-5" title="Skrining perawat belum tercatat" />}
                </CardContent></Card>

                {!canEdit && <Alert><AlertTitle>Pemeriksaan terkunci</AlertTitle><AlertDescription>Data kunjungan ini sudah melewati tahap pemeriksaan. Koreksi dicatat sebagai addendum beralasan tanpa mengubah versi final.</AlertDescription></Alert>}
                {visit.examination?.integrityValid === false && <Alert variant="destructive"><AlertTriangle aria-hidden="true" /><AlertTitle>Integritas catatan perlu diperiksa</AlertTitle><AlertDescription>Rantai versi catatan tidak cocok. Addendum dihentikan sampai data diperiksa oleh pengelola rekam medis.</AlertDescription></Alert>}

                {visit.clinicType === 'gigi' && <Card>
                    <CardHeader>
                        <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                            <div>
                                <CardTitle>Odontogram Kunjungan (Peta Gigi FDI)</CardTitle>
                                <CardDescription>Catat nomor gigi FDI, permukaan, dan temuan. Koreksi setelah finalisasi membuat versi baru.</CardDescription>
                            </div>
                            <div className="flex gap-2">
                                <Button onClick={() => { setDentition('adult'); selectDentalTooth(''); }} size="sm" type="button" variant={dentition === 'adult' ? 'default' : 'secondary'}>Permanen · 32</Button>
                                <Button onClick={() => { setDentition('primary'); selectDentalTooth(''); }} size="sm" type="button" variant={dentition === 'primary' ? 'default' : 'secondary'}>Desidui · 20</Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <section aria-label="Diagram odontogram" className="space-y-4 rounded-2xl border border-neutral-200/90 bg-neutral-50/50 p-4 sm:p-5">
                            <OdontogramFdi
                                dentition={dentition}
                                disabled={!canEdit}
                                findingLabels={dentalOptions.findings}
                                surfaceLabels={dentalOptions.surfaces}
                                findings={visit.odontogram}
                                onSelect={selectDentalTooth}
                                selectedTooth={dentalForm.data.tooth_fdi}
                            />
                        </section>
                        {visit.odontogram.length > 0 ? (
                            <section aria-labelledby="odontogram-findings-title" className="space-y-3">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <h3 className="text-sm font-semibold text-neutral-900" id="odontogram-findings-title">Temuan untuk tindakan</h3>
                                        <p className="mt-1 text-sm leading-5 text-neutral-600">Pilih satu atau beberapa temuan. Tindakan yang disimpan akan dicatat pada setiap gigi terpilih.</p>
                                    </div>
                                    <Button
                                        disabled={!canEdit}
                                        onClick={() => {
                                            const allSelected = selectedDentalFindingKeys.length === visit.odontogram.length;
                                            const nextFindings = allSelected ? [] : visit.odontogram;
                                            setSelectedDentalFindingKeys(nextFindings.map((finding) => `${finding.toothFdi}-${finding.surface}`));
                                            treatmentForm.setData('tooth_fdis', [...new Set(nextFindings.map((finding) => finding.toothFdi))]);
                                        }}
                                        size="sm"
                                        type="button"
                                        variant="secondary"
                                    >
                                        {selectedDentalFindingKeys.length === visit.odontogram.length ? 'Batal pilih semua' : 'Pilih semua temuan'}
                                    </Button>
                                </div>
                                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                    {visit.odontogram.map((finding) => {
                                        const findingKey = `${finding.toothFdi}-${finding.surface}`;
                                        const selected = selectedDentalFindingKeys.includes(findingKey);

                                        return (
                                            <div
                                                className={`flex min-w-0 items-start justify-between gap-3 rounded-xl border p-3.5 transition-all ${
                                                    selected
                                                        ? 'border-neutral-500 bg-neutral-100/90 shadow-2xs'
                                                        : 'border-neutral-200 bg-surface hover:border-neutral-300'
                                                }`}
                                                key={findingKey}
                                            >
                                                <label className="flex min-w-0 flex-1 cursor-pointer items-start gap-3">
                                                    <Checkbox
                                                        checked={selected}
                                                        disabled={!canEdit}
                                                        onCheckedChange={(checked) => setDentalFindingSelection(finding, checked === true)}
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        <span className="flex flex-wrap items-center gap-2">
                                                            <strong className="font-mono text-sm font-bold text-neutral-900">
                                                                Gigi {finding.toothFdi}
                                                            </strong>
                                                            <Badge variant="default">
                                                                {dentalOptions.surfaces[finding.surface] ?? finding.surface}
                                                            </Badge>
                                                        </span>
                                                        <span className="mt-1.5 flex items-center gap-1.5 text-sm font-medium leading-5 text-neutral-800">
                                                            {finding.findingCode === 'caries' ? (
                                                                <span className="inline-block size-2 shrink-0 rounded-full bg-red-500" />
                                                            ) : (
                                                                <span className="inline-block size-2 shrink-0 rounded-full bg-neutral-400" />
                                                            )}
                                                            {dentalOptions.findings[finding.findingCode] ?? finding.findingCode}
                                                        </span>
                                                        {finding.notes && (
                                                            <span className="mt-1 block whitespace-pre-wrap break-words text-xs leading-5 text-neutral-600">
                                                                {finding.notes}
                                                            </span>
                                                        )}
                                                    </span>
                                                </label>
                                                <Button
                                                    aria-label={`Batalkan temuan gigi ${finding.toothFdi}, ${dentalOptions.surfaces[finding.surface] ?? finding.surface}`}
                                                    onClick={() => {
                                                        odontogramUndoForm.setData({
                                                            tooth_fdi: finding.toothFdi,
                                                            surface: finding.surface,
                                                            reason: '',
                                                        });
                                                        setUndoFinding(finding);
                                                    }}
                                                    size="icon"
                                                    type="button"
                                                    variant="ghost"
                                                    className="text-neutral-400 hover:text-neutral-900"
                                                >
                                                    <Undo2 className="size-4" />
                                                </Button>
                                            </div>
                                        );
                                    })}
                                </div>
                                {selectedDentalFindingKeys.length > 0 && <p className="text-sm font-medium text-neutral-700">{selectedDentalFindingKeys.length} temuan dari {treatmentForm.data.tooth_fdis.length} gigi dipilih untuk tindakan.</p>}
                            </section>
                        ) : (
                            <Empty className="min-h-0 py-5" description="Temuan gigi perlu dicatat sebelum pemeriksaan gigi difinalisasi." title="Belum ada temuan odontogram" />
                        )}
                        {(canEdit || visit.examination?.signedAt) && (
                            <form className="space-y-4 border-t border-neutral-200 pt-5" onSubmit={(event) => { event.preventDefault(); dentalForm.post(`/pelayanan/pemeriksaan/${visit.id}/odontogram${canEdit ? '' : '/addendum'}`, { preserveScroll: true, onSuccess: () => dentalForm.reset('notes', 'reason') }); }}>
                                <div className="grid gap-4 md:grid-cols-3">
                                    <Field error={dentalForm.errors.tooth_fdi} htmlFor="dental-tooth" label="Nomor gigi FDI" required>
                                        <Select id="dental-tooth" onChange={(event) => selectDentalTooth(event.target.value)} required value={dentalForm.data.tooth_fdi}>
                                            <option value="">Pilih gigi</option>
                                            {dentalOptions.teeth.map((tooth) => <option key={tooth} value={tooth}>{tooth}</option>)}
                                        </Select>
                                    </Field>
                                    <Field error={dentalForm.errors.surface} htmlFor="dental-surface" label="Permukaan" required>
                                        <Select id="dental-surface" onChange={(event) => dentalForm.setData('surface', event.target.value)} value={dentalForm.data.surface}>
                                            {Object.entries(dentalOptions.surfaces).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                                        </Select>
                                    </Field>
                                    <Field error={dentalForm.errors.finding_code} htmlFor="dental-finding" label="Temuan" required>
                                        <Select id="dental-finding" onChange={(event) => dentalForm.setData('finding_code', event.target.value)} value={dentalForm.data.finding_code}>
                                            {Object.entries(dentalOptions.findings).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                                        </Select>
                                    </Field>
                                </div>
                                <Field error={dentalForm.errors.notes} htmlFor="dental-notes" label="Catatan temuan">
                                    <Textarea id="dental-notes" placeholder="Catatan tambahan temuan gigi (opsional)" onChange={(event) => dentalForm.setData('notes', event.target.value)} rows={2} value={dentalForm.data.notes} />
                                </Field>
                                {!canEdit && (
                                    <Field error={dentalForm.errors.reason} htmlFor="dental-reason" label="Alasan addendum" required>
                                        <Textarea id="dental-reason" minLength={10} onChange={(event) => dentalForm.setData('reason', event.target.value)} required rows={2} value={dentalForm.data.reason} />
                                    </Field>
                                )}
                                <div className="flex justify-end">
                                    <Button disabled={dentalForm.processing || visit.examination?.integrityValid === false} type="submit">{canEdit ? 'Simpan temuan' : 'Simpan addendum odontogram'}</Button>
                                </div>
                            </form>
                        )}
                    </CardContent>
                </Card>}

                {visit.clinicType !== 'gigi' && (
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                <div>
                                    <CardTitle>Diagram Anatomi & Lokasi Keluhan Tubuh (Body Map)</CardTitle>
                                    <CardDescription>
                                        Visualisasi lokasi keluhan dan sistem organ terstandar. Klik organ pada diagram untuk panduan klinis dan saran diagnosis ICD-10.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <section aria-label="Diagram anatomi tubuh" className="space-y-4 rounded-2xl border border-neutral-200/90 bg-neutral-50/50 p-4 sm:p-5">
                                <AnatomyBodyMap
                                    disabled={!canEdit}
                                    onApplyToExam={(organ, findingNote) => {
                                        const currentFinding = examinationForm.data.pemeriksaan_fisik_terstruktur[organ.physicalExamCategory] ?? '';
                                        const nextFinding = currentFinding ? `${currentFinding}\n- ${findingNote}` : `- ${findingNote}`;
                                        examinationForm.setData('pemeriksaan_fisik_terstruktur', {
                                            ...examinationForm.data.pemeriksaan_fisik_terstruktur,
                                            [organ.physicalExamCategory]: nextFinding,
                                        });
                                        toast.success(`Temuan klinis ditambahkan ke bagian ${organ.physicalExamCategory}`);
                                    }}
                                    onQuickSearchIcd={(query) => {
                                        setIcdQuery(query);
                                        setActiveTab('diagnosa');
                                    }}
                                    onSelectOrgan={(organ) => {
                                        setSelectedBodyOrganId(organ.id);
                                        setSelectedBodySystem(organ.physicalExamCategory);
                                    }}
                                    recordedFindings={examinationForm.data.pemeriksaan_fisik_terstruktur}
                                    selectedOrganId={selectedBodyOrganId}
                                />
                            </section>
                        </CardContent>
                    </Card>
                )}

                <AlertDialog onOpenChange={(open) => { if (!open) { setUndoFinding(null); odontogramUndoForm.reset(); } }} open={undoFinding !== null}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>Batalkan temuan odontogram?</AlertDialogTitle>
                            <AlertDialogDescription>{undoFinding ? `Gigi ${undoFinding.toothFdi} · ${dentalOptions.surfaces[undoFinding.surface] ?? undoFinding.surface} · ${dentalOptions.findings[undoFinding.findingCode] ?? undoFinding.findingCode}` : ''}. Temuan akan dihapus dari catatan kunjungan.</AlertDialogDescription>
                        </AlertDialogHeader>
                        {visit.examination?.signedAt && <Field error={odontogramUndoForm.errors.reason} htmlFor="odontogram-undo-reason" label="Alasan koreksi addendum" required><Textarea id="odontogram-undo-reason" minLength={10} onChange={(event) => odontogramUndoForm.setData('reason', event.target.value)} placeholder="Jelaskan alasan pembatalan temuan" required rows={3} value={odontogramUndoForm.data.reason} /></Field>}
                        <AlertDialogFooter className="border-t border-neutral-200 pt-4">
                            <AlertDialogCancel asChild>
                                <Button type="button" variant="secondary">Kembali</Button>
                            </AlertDialogCancel>
                            <Button disabled={odontogramUndoForm.processing || (Boolean(visit.examination?.signedAt) && odontogramUndoForm.data.reason.trim().length < 10)} onClick={undoDentalFinding} type="button" variant="destructive">Batalkan temuan</Button>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                {visit.examination?.signedAt && <Card><CardHeader><CardTitle>Finalisasi dan riwayat koreksi</CardTitle><CardDescription>Difinalisasi oleh {visit.examination.signedBy ?? 'dokter'} pada {visit.examination.signedAt}. Setiap versi menyimpan penulis, waktu, dan alasan koreksi.</CardDescription></CardHeader><CardContent className="space-y-5">
                    <div className="space-y-3">{visit.examination.versions.map((version) => <article className="rounded-xl border border-neutral-200 p-4" key={version.version}><div className="flex flex-wrap items-center gap-2"><Badge variant={version.kind === 'addendum' ? 'waiting' : 'complete'}>Versi {version.version} · {version.kind === 'addendum' ? 'Addendum' : 'Final'}</Badge><span className="text-xs text-neutral-600">{version.actor ?? 'Dokter'} · {version.recordedAt ?? 'Belum diisi'}</span></div>{version.reason && <p className="mt-2 text-sm font-medium text-neutral-800">Alasan: {version.reason}</p>}<div className="mt-3 grid gap-2 text-sm text-neutral-700 md:grid-cols-2"><p className="whitespace-pre-wrap"><strong>Anamnesis:</strong> {version.anamnesis || 'Belum diisi'}</p><p className="whitespace-pre-wrap"><strong>Pemeriksaan fisik:</strong> {version.physicalExam || 'Belum diisi'}</p><p className="whitespace-pre-wrap"><strong>Catatan:</strong> {version.notes || 'Belum diisi'}</p><p className="whitespace-pre-wrap"><strong>Edukasi:</strong> {version.education || 'Belum diisi'}</p></div>{visit.clinicType === 'gigi' && version.odontogram.length > 0 && <p className="mt-3 text-sm text-neutral-700"><strong>Odontogram:</strong> {version.odontogram.map((finding) => `${finding.toothFdi} ${dentalOptions.surfaces[finding.surface] ?? finding.surface}: ${dentalOptions.findings[finding.findingCode] ?? finding.findingCode}`).join('; ')}</p>}</article>)}</div>
                    <form className="space-y-4 border-t border-neutral-200 pt-5" onSubmit={(event) => { event.preventDefault(); addendumForm.post(`/pelayanan/pemeriksaan/${visit.id}/addendum`, { preserveScroll: true, onSuccess: () => addendumForm.reset('reason') }); }}><div><h3 className="text-sm font-semibold text-neutral-950">Buat addendum</h3><p className="mt-1 text-sm text-neutral-600">Ubah bagian yang perlu dikoreksi. Nilai versi sebelumnya tetap tersimpan.</p></div><Field error={addendumForm.errors.reason} htmlFor="addendum-reason" label="Alasan koreksi" required><Textarea id="addendum-reason" placeholder="Contoh: koreksi setelah verifikasi ulang" minLength={10} onChange={(event) => addendumForm.setData('reason', event.target.value)} required rows={2} value={addendumForm.data.reason} /></Field><div className="grid gap-4 md:grid-cols-2"><Field error={addendumForm.errors.anamnesis} htmlFor="addendum-anamnesis" label="Keluhan utama"><Textarea id="addendum-anamnesis" placeholder="Keluhan utama yang disampaikan pasien" onChange={(event) => addendumForm.setData('anamnesis', event.target.value)} rows={3} value={addendumForm.data.anamnesis} /></Field><Field error={addendumForm.errors.riwayat_penyakit_sekarang} htmlFor="addendum-rps" label="Riwayat penyakit sekarang"><Textarea id="addendum-rps" placeholder="Awal keluhan, durasi, dan perkembangannya" onChange={(event) => addendumForm.setData('riwayat_penyakit_sekarang', event.target.value)} rows={3} value={addendumForm.data.riwayat_penyakit_sekarang} /></Field><Field error={addendumForm.errors.riwayat_penyakit_dahulu} htmlFor="addendum-rpd" label="Riwayat penyakit dahulu"><Textarea id="addendum-rpd" placeholder="Riwayat penyakit, operasi, atau pengobatan rutin" onChange={(event) => addendumForm.setData('riwayat_penyakit_dahulu', event.target.value)} rows={3} value={addendumForm.data.riwayat_penyakit_dahulu} /></Field><Field error={addendumForm.errors.riwayat_penyakit_keluarga} htmlFor="addendum-riwayat-keluarga" label="Riwayat penyakit keluarga"><Textarea id="addendum-riwayat-keluarga" placeholder="Riwayat penyakit keluarga yang relevan" onChange={(event) => addendumForm.setData('riwayat_penyakit_keluarga', event.target.value)} rows={3} value={addendumForm.data.riwayat_penyakit_keluarga} /></Field><Field error={addendumForm.errors.riwayat_alergi} htmlFor="addendum-alergi" label="Riwayat alergi"><Textarea id="addendum-alergi" placeholder="Obat, makanan, dan reaksi alergi" onChange={(event) => addendumForm.setData('riwayat_alergi', event.target.value)} rows={3} value={addendumForm.data.riwayat_alergi} /></Field><Field error={addendumForm.errors.pemeriksaan_fisik} htmlFor="addendum-fisik" label={visit.clinicType === 'gigi' ? 'Pemeriksaan intraoral' : 'Ringkasan pemeriksaan fisik'}><Textarea id="addendum-fisik" placeholder="Ringkasan temuan pemeriksaan fisik" onChange={(event) => addendumForm.setData('pemeriksaan_fisik', event.target.value)} rows={3} value={addendumForm.data.pemeriksaan_fisik} /></Field>{visit.clinicType === 'gigi' && <><Field error={addendumForm.errors.pemeriksaan_ekstraoral} htmlFor="addendum-extraoral" label="Pemeriksaan ekstraoral"><Textarea id="addendum-extraoral" placeholder="Catat temuan ekstraoral yang diperiksa" onChange={(event) => addendumForm.setData('pemeriksaan_ekstraoral', event.target.value)} rows={3} value={addendumForm.data.pemeriksaan_ekstraoral} /></Field><Field error={addendumForm.errors.oral_hygiene_index} htmlFor="addendum-ohis" label="OHIS"><Input id="addendum-ohis" max="6" placeholder="0–6" min="0" onChange={(event) => addendumForm.setData('oral_hygiene_index', event.target.value)} step="0.01" type="number" value={addendumForm.data.oral_hygiene_index} /></Field></>}<Field error={addendumForm.errors.diagnosis_banding} htmlFor="addendum-diagnosis-banding" label="Diagnosis banding"><Textarea id="addendum-diagnosis-banding" placeholder="Diagnosis alternatif yang dipertimbangkan" onChange={(event) => addendumForm.setData('diagnosis_banding', event.target.value)} rows={3} value={addendumForm.data.diagnosis_banding} /></Field><Field error={addendumForm.errors.catatan} htmlFor="addendum-catatan" label="Catatan dokter"><Textarea id="addendum-catatan" placeholder="Catatan klinis tambahan" onChange={(event) => addendumForm.setData('catatan', event.target.value)} rows={3} value={addendumForm.data.catatan} /></Field><Field error={addendumForm.errors.edukasi} htmlFor="addendum-edukasi" label="Edukasi pasien"><Textarea id="addendum-edukasi" placeholder="Anjuran atau edukasi yang diberikan" onChange={(event) => addendumForm.setData('edukasi', event.target.value)} rows={3} value={addendumForm.data.edukasi} /></Field></div>{visit.clinicType === 'umum' && <div className="grid gap-4 md:grid-cols-2">{Object.entries(addendumForm.data.pemeriksaan_fisik_terstruktur).map(([system, value]) => <Field htmlFor={`addendum-system-${system}`} key={system} label={`Temuan · ${system}`}><Textarea id={`addendum-system-${system}`} onChange={(event) => addendumForm.setData('pemeriksaan_fisik_terstruktur', { ...addendumForm.data.pemeriksaan_fisik_terstruktur, [system]: event.target.value })} rows={2} value={value} /></Field>)}</div>}<Field error={addendumForm.errors.kontrol_berikutnya} htmlFor="addendum-kontrol" label="Kontrol berikutnya"><DatePicker id="addendum-kontrol" onChange={(event) => addendumForm.setData('kontrol_berikutnya', event.target.value)} value={addendumForm.data.kontrol_berikutnya} /></Field><div className="flex justify-end"><Button disabled={addendumForm.processing} type="submit">Simpan addendum</Button></div></form>
                </CardContent></Card>}

                <Card className="overflow-hidden">
                    <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as Tab)}>
                    <TabsList aria-label="Bagian pemeriksaan">{tabs.map(({ id, icon: Icon, label }) => <TabsTrigger className="min-h-12 px-3 sm:px-4" key={id} value={id}><Icon aria-hidden="true" className="size-4" />{label}{id === 'diagnosa' && <Badge>{visit.diagnoses.length}</Badge>}{id === 'tindakan' && <Badge>{visit.treatments.length}</Badge>}{(id === 'resep' || id === 'resep-luar') && <Badge>{visit.prescription.items.filter((item) => item.external === (id === 'resep-luar')).length}</Badge>}</TabsTrigger>)}</TabsList>

                    <fieldset className="min-w-0" disabled={!canEdit}>

                    <TabsContent className="space-y-5 p-5 sm:p-6" value="anamnesis">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-anamnesis">Anamnesis dan pemeriksaan fisik</h3><p className="mt-1 text-sm text-neutral-500">Catat kondisi klinis, rencana kontrol, dan edukasi pasien.</p></div>
                        <form className="space-y-5" onSubmit={(event) => submitForm(event, examinationForm, `/pelayanan/pemeriksaan/${visit.id}`)}>
                            <Field error={examinationForm.errors.dokter_id} htmlFor="dokter_id" label="Dokter pemeriksa"><Select id="dokter_id" onChange={(event) => examinationForm.setData('dokter_id', event.target.value)} value={examinationForm.data.dokter_id}><option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select></Field>
                        <div className="grid gap-4 md:grid-cols-2"><Field error={examinationForm.errors.anamnesis} htmlFor="anamnesis" label="Keluhan utama" required><Textarea id="anamnesis" onChange={(event) => examinationForm.setData('anamnesis', event.target.value)} placeholder="Catat keluhan utama yang disampaikan pasien." required rows={3} value={examinationForm.data.anamnesis} /></Field><Field error={examinationForm.errors.riwayat_penyakit_sekarang} htmlFor="rps" label="Riwayat penyakit sekarang"><Textarea id="rps" onChange={(event) => examinationForm.setData('riwayat_penyakit_sekarang', event.target.value)} placeholder="Awal keluhan, durasi, perkembangan, tingkat keparahan, faktor pemicu/pereda." rows={3} value={examinationForm.data.riwayat_penyakit_sekarang} /></Field></div>
                            <div className="grid gap-4 md:grid-cols-3"><Field error={examinationForm.errors.riwayat_penyakit_dahulu} htmlFor="rpd" label="Riwayat penyakit dahulu"><Textarea id="rpd" onChange={(event) => examinationForm.setData('riwayat_penyakit_dahulu', event.target.value)} placeholder="Riwayat penyakit, operasi, dan pengobatan rutin." rows={3} value={examinationForm.data.riwayat_penyakit_dahulu} /></Field><Field error={examinationForm.errors.riwayat_penyakit_keluarga} htmlFor="riwayat-keluarga" label="Riwayat penyakit keluarga"><Textarea id="riwayat-keluarga" onChange={(event) => examinationForm.setData('riwayat_penyakit_keluarga', event.target.value)} placeholder="Diabetes, hipertensi, atau kondisi herediter lain." rows={3} value={examinationForm.data.riwayat_penyakit_keluarga} /></Field><Field error={examinationForm.errors.riwayat_alergi} htmlFor="riwayat-alergi-dokter" label="Riwayat alergi"><Textarea className={visit.patient.allergies ? 'border-red-300 focus-visible:ring-red-500' : undefined} id="riwayat-alergi-dokter" onChange={(event) => examinationForm.setData('riwayat_alergi', event.target.value)} placeholder="Obat, makanan, reaksi alergi. Pastikan bila tidak ada." rows={3} value={examinationForm.data.riwayat_alergi} /></Field></div>
                            {suggestionsOpen && <div className="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4"><div className="flex justify-between gap-3"><div><p className="text-sm font-semibold text-neutral-950">Hasil katalog terminologi</p><p className="text-xs text-neutral-800">Kandidat berasal dari katalog yang diimpor. Dokter tetap menilai dan mengonfirmasi kode.</p></div><Button onClick={() => setSuggestionsOpen(false)} size="sm" type="button" variant="ghost">Tutup</Button></div>{suggestions.length ? <div className="flex flex-wrap gap-2">{suggestions.map((item) => <Button key={`${item.code_system}-${item.kode}-${item.nama}`} onClick={() => { chooseDiagnosis(item.kode, item.nama, item.code_system, item.release); setDiagnosisCodeSystem(item.code_system); setActiveTab('diagnosa'); }} size="sm" type="button" variant="secondary">{item.kode} · {item.nama}</Button>)}</div> : <p className="text-sm text-neutral-500">Tidak ada kecocokan pada katalog untuk kata yang dipilih.</p>}</div>}
                            {visit.clinicType === 'umum' && (
                                <section className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50/50 p-4 sm:p-5">
                                    <div className="flex flex-col justify-between gap-1 sm:flex-row sm:items-center">
                                        <div>
                                            <h4 className="text-sm font-semibold text-neutral-950">
                                                Pemeriksaan fisik per sistem terarah
                                            </h4>
                                            <p className="mt-0.5 text-xs text-neutral-500">
                                                Pilih kategori regio untuk mengisi temuan klinis terarah. Tersinkronisasi dengan Diagram Anatomi Tubuh di atas.
                                            </p>
                                        </div>
                                        <Badge variant="default" className="w-fit text-xs font-medium">
                                            {Object.values(examinationForm.data.pemeriksaan_fisik_terstruktur).filter((val) => val.trim()).length} dari 6 sistem terisi
                                        </Badge>
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-[14rem_minmax(0,1fr)]">
                                        <div className="flex flex-col gap-1.5 rounded-lg border border-neutral-200/80 bg-surface p-2.5">
                                            <span className="px-2 py-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                                                Daftar Regio / Sistem
                                            </span>
                                            {['Kepala dan leher', 'Thorax', 'Abdomen', 'Ekstremitas', 'Neurologis', 'Kulit'].map((system) => {
                                                const hasFinding = Boolean(examinationForm.data.pemeriksaan_fisik_terstruktur[system]?.trim());
                                                const isSelected = selectedBodySystem === system;
                                                return (
                                                    <button
                                                        key={system}
                                                        type="button"
                                                        aria-pressed={isSelected}
                                                        onClick={() => setSelectedBodySystem(system)}
                                                        className={`flex items-center justify-between rounded-lg px-2.5 py-2 text-left text-xs font-medium transition-colors ${
                                                            isSelected
                                                                ? 'bg-neutral-900 text-neutral-50 shadow-2xs'
                                                                : 'text-neutral-700 hover:bg-neutral-100'
                                                        }`}
                                                    >
                                                        <span>{system}</span>
                                                        <span
                                                            className={`size-2 rounded-full ${
                                                                hasFinding
                                                                    ? isSelected
                                                                        ? 'bg-emerald-400 dark:bg-emerald-600'
                                                                        : 'bg-emerald-500 dark:bg-emerald-400'
                                                                    : 'bg-neutral-200'
                                                            }`}
                                                            title={hasFinding ? 'Sudah ada catatan temuan' : 'Belum ada catatan'}
                                                        />
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        <div className="space-y-3">
                                            <Field
                                                error={examinationForm.errors[`pemeriksaan_fisik_terstruktur.${selectedBodySystem}` as keyof typeof examinationForm.errors]}
                                                htmlFor="body-system-finding"
                                                label={`Temuan Klinis · ${selectedBodySystem}`}
                                            >
                                                <Textarea
                                                    id="body-system-finding"
                                                    onChange={(event) =>
                                                        examinationForm.setData('pemeriksaan_fisik_terstruktur', {
                                                            ...examinationForm.data.pemeriksaan_fisik_terstruktur,
                                                            [selectedBodySystem]: event.target.value,
                                                        })
                                                    }
                                                    placeholder={`Dokumentasikan hasil inspeksi, palpasi, perkusi, auskultasi untuk ${selectedBodySystem.toLowerCase()}...`}
                                                    rows={5}
                                                    value={examinationForm.data.pemeriksaan_fisik_terstruktur[selectedBodySystem] ?? ''}
                                                />
                                            </Field>
                                            <div className="flex flex-wrap gap-2">
                                                {Object.entries(examinationForm.data.pemeriksaan_fisik_terstruktur)
                                                    .filter(([, value]) => value.trim())
                                                    .map(([system, value]) => (
                                                        <button
                                                            key={system}
                                                            type="button"
                                                            onClick={() => setSelectedBodySystem(system)}
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-surface px-2.5 py-1 text-xs text-neutral-700 shadow-2xs hover:border-neutral-400"
                                                        >
                                                            <span className="font-semibold text-neutral-900">{system}:</span>
                                                            <span className="max-w-[200px] truncate text-neutral-500">
                                                                {value}
                                                            </span>
                                                        </button>
                                                    ))}
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            )}
                            <Field error={examinationForm.errors.pemeriksaan_fisik} htmlFor="pemeriksaan_fisik" label={visit.clinicType === 'gigi' ? 'Pemeriksaan intraoral' : 'Ringkasan pemeriksaan fisik'}><Textarea id="pemeriksaan_fisik" onChange={(event) => examinationForm.setData('pemeriksaan_fisik', event.target.value)} placeholder={visit.clinicType === 'gigi' ? 'Mukosa, gingiva, gigi, oklusi, dan temuan intraoral lain.' : 'Ringkasan temuan pemeriksaan fisik.'} rows={4} value={examinationForm.data.pemeriksaan_fisik} /></Field>
                            {visit.clinicType === 'gigi' && <div className="grid gap-4 md:grid-cols-2"><Field error={examinationForm.errors.pemeriksaan_ekstraoral} htmlFor="extraoral" label="Pemeriksaan ekstraoral"><Textarea id="extraoral" onChange={(event) => examinationForm.setData('pemeriksaan_ekstraoral', event.target.value)} placeholder="Asimetri/pembengkakan wajah, bibir, kelenjar getah bening, bukaan mulut." rows={3} value={examinationForm.data.pemeriksaan_ekstraoral} /></Field><Field error={examinationForm.errors.oral_hygiene_index} htmlFor="ohis" label="Indeks kebersihan mulut (OHIS)"><Input id="ohis" max="6" min="0" onChange={(event) => examinationForm.setData('oral_hygiene_index', event.target.value)} placeholder="0–6" step="0.01" type="number" value={examinationForm.data.oral_hygiene_index} /></Field></div>}
                            <Field error={examinationForm.errors.diagnosis_banding} htmlFor="diagnosis-banding" label="Diagnosis banding"><Textarea id="diagnosis-banding" onChange={(event) => examinationForm.setData('diagnosis_banding', event.target.value)} placeholder="Diagnosis alternatif atau kemungkinan lain yang dipertimbangkan." rows={2} value={examinationForm.data.diagnosis_banding} /></Field>
                            <div className="flex flex-wrap gap-2"><Button onClick={suggestDiagnoses} size="sm" type="button" variant="secondary"><Lightbulb className="size-4" />Saran diagnosis dari keluhan</Button></div>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={examinationForm.errors.kontrol_berikutnya} htmlFor="kontrol_berikutnya" label="Jadwal kontrol berikutnya"><DatePicker id="kontrol_berikutnya" onChange={(event) => examinationForm.setData('kontrol_berikutnya', event.target.value)}  value={examinationForm.data.kontrol_berikutnya} /></Field><Field error={examinationForm.errors.catatan} htmlFor="catatan" label="Catatan khusus dokter"><Input id="catatan" onChange={(event) => examinationForm.setData('catatan', event.target.value)} placeholder="Catatan internal" value={examinationForm.data.catatan} /></Field></div>
                            <Field error={examinationForm.errors.edukasi} htmlFor="edukasi" label="Edukasi pasien"><Textarea id="edukasi" onChange={(event) => examinationForm.setData('edukasi', event.target.value)} placeholder="Anjuran, cara penggunaan obat, larangan, dan jadwal kontrol" rows={3} value={examinationForm.data.edukasi} /></Field>
                            <div className="flex justify-end"><Button disabled={examinationForm.processing} type="submit">Simpan pemeriksaan</Button></div>
                        </form>
                        {renderTabNavigation('anamnesis', 'diagnosa', 'Lanjut ke diagnosis')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="diagnosa">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-diagnosa">Diagnosis terstandar</h3><p className="mt-1 text-sm text-neutral-500">Cari dan catat kode dari sistem terminologi yang sesuai dengan katalog klinik.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, diagnosisForm, `/pelayanan/pemeriksaan/${visit.id}/diagnosa`)}>
                        <div className="grid gap-4 md:grid-cols-[16rem_minmax(0,1fr)]"><Field error={diagnosisForm.errors.code_system} htmlFor="diagnosis-code-system" label="Sistem kode"><Select id="diagnosis-code-system" onChange={(event) => { setDiagnosisCodeSystem(event.target.value); diagnosisForm.setData((data) => ({ ...data, code_system: event.target.value, code_release: '', kode_icd10: '', nama_diagnosa: '' })); }} value={diagnosisCodeSystem}>{diagnosisCodeSystems.map((system) => <option key={system.value} value={system.value}>{system.label}</option>)}</Select></Field><div className="relative"><Field htmlFor="icd-search" label="Cari kode atau uraian"><div className="relative"><Search className="absolute left-3 top-3 size-4 text-neutral-400" /><Input autoComplete="off" className="pl-9" id="icd-search" onChange={(event) => setIcdQuery(event.target.value)} placeholder="Ketik kode atau nama diagnosis" value={icdQuery} /></div></Field>{(icdResults.length > 0 || icdLoading) && <div className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-xl border border-neutral-200 bg-surface shadow-lg">{icdLoading && <p className="p-3 text-sm text-neutral-500">Mencari…</p>}{icdResults.map((result) => <Button className="h-auto w-full justify-between rounded-none border-b border-neutral-100 px-3 py-2 text-left font-normal hover:bg-neutral-50 hover:text-neutral-900" key={`${result.code_system}-${result.release}-${result.kode}`} onClick={() => chooseDiagnosis(result.kode, result.nama, result.code_system, result.release)} type="button" variant="ghost"><span className="font-mono font-semibold text-neutral-700">{result.kode}</span><span>{result.nama}</span></Button>)}</div>}</div></div>
                            <div className="grid gap-4 md:grid-cols-3"><Field error={diagnosisForm.errors.kode_icd10} htmlFor="kode_icd10" label="Kode diagnosis" required><Input className="font-mono uppercase" id="kode_icd10" onChange={(event) => diagnosisForm.setData('kode_icd10', event.target.value.toUpperCase())} required value={diagnosisForm.data.kode_icd10} /></Field><Field error={diagnosisForm.errors.nama_diagnosa} htmlFor="nama_diagnosa" label="Nama diagnosis" required><Input id="nama_diagnosa" onChange={(event) => diagnosisForm.setData('nama_diagnosa', event.target.value)} required value={diagnosisForm.data.nama_diagnosa} /></Field><Field error={diagnosisForm.errors.jenis} htmlFor="jenis-diagnosa" label="Jenis diagnosis"><Select id="jenis-diagnosa" onChange={(event) => diagnosisForm.setData('jenis', event.target.value)} value={diagnosisForm.data.jenis}><option value="utama">Utama</option><option value="tambahan">Tambahan</option></Select></Field></div>
                            <div className="flex justify-end border-t border-neutral-200 pt-4"><Button disabled={diagnosisForm.processing || !diagnosisForm.data.kode_icd10 || !diagnosisForm.data.nama_diagnosa} type="submit"><Plus className="size-4" />Tambah diagnosis</Button></div>
                        </form>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Diagnosis tercatat</h4>{visit.diagnoses.length ? visit.diagnoses.map((diagnosis) => <div className="flex flex-col justify-between gap-3 rounded-xl border border-neutral-200 p-4 sm:flex-row sm:items-center" key={diagnosis.id}><div className="flex flex-wrap items-center gap-3"><Badge variant="examination">{diagnosis.code}</Badge><span className="text-sm font-medium text-neutral-900">{diagnosis.name}</span><Badge>{diagnosis.type === 'utama' ? 'Utama' : 'Tambahan'}</Badge></div><Button onClick={() => deleteItem(`/pelayanan/pemeriksaan/diagnosa/${diagnosis.id}`, 'Hapus diagnosis ini?')} size="sm" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" />Hapus</Button></div>) : <Empty className="min-h-0 py-6" description="Diagnosis yang dicatat selama pemeriksaan akan tampil di sini." title="Belum ada diagnosis" />}</div>
                        {renderTabNavigation('diagnosa', 'tindakan', 'Lanjut ke tindakan')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="tindakan">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-tindakan">Tindakan medis</h3><p className="mt-1 text-sm text-neutral-500">Cari kode atau uraian ICD-9-CM Volume 3, lalu catat tindakan yang sudah dilakukan. Input manual tersedia bila belum ada padanan kode.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, treatmentForm, `/pelayanan/pemeriksaan/${visit.id}/tindakan`)}>
                            <div className="grid gap-4 md:grid-cols-3">
                                <Field htmlFor="treatment-entry-mode" label="Sumber tindakan">
                                    <Select id="treatment-entry-mode" onChange={(event) => changeTreatmentMode(event.target.value as 'icd9cm' | 'clinic' | 'manual')} value={treatmentMode}>
                                        <option value="icd9cm">Terminologi ICD-9-CM</option>
                                        <option value="clinic">Daftar tindakan klinik</option>
                                        <option value="manual">Input tindakan manual</option>
                                    </Select>
                                </Field>
                                {treatmentMode === 'manual' ? (
                                    <Field error={treatmentForm.errors.nama_tindakan_manual} htmlFor="nama-tindakan-manual" label="Nama tindakan" required>
                                        <Input id="nama-tindakan-manual" maxLength={255} minLength={3} onChange={(event) => treatmentForm.setData('nama_tindakan_manual', event.target.value)} placeholder="Tuliskan tindakan yang dilakukan" required value={treatmentForm.data.nama_tindakan_manual} />
                                    </Field>
                                ) : treatmentMode === 'clinic' ? (
                                    <Field error={treatmentForm.errors.tindakan_id} htmlFor="tindakan_id" label="Tindakan klinik" required>
                                        <Select id="tindakan_id" onChange={(event) => treatmentForm.setData('tindakan_id', event.target.value)} required value={treatmentForm.data.tindakan_id}>
                                            <option value="">Pilih tindakan</option>
                                            {treatments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                                        </Select>
                                    </Field>
                                ) : <div className="relative">
                                    <Field error={treatmentForm.errors.terminology_id} htmlFor="procedure-search" label="Cari kode atau uraian ICD-9-CM" required>
                                        <div className="relative">
                                            <Search aria-hidden="true" className="absolute left-3 top-3 size-4 text-neutral-400" />
                                            <Input autoComplete="off" className="pl-9" disabled={!procedureCatalogAvailable} id="procedure-search" maxLength={100} onChange={(event) => {
                                                setProcedureQuery(event.target.value);
                                                setSelectedProcedure(null);
                                                treatmentForm.setData((data) => ({ ...data, terminology_id: '', tindakan_id: '' }));
                                            }} placeholder="Ketik kode atau uraian tindakan" value={procedureQuery} />
                                        </div>
                                    </Field>
                                    {procedureCatalogAvailable && procedureQuery.trim().length >= 2 && !selectedProcedure && (
                                        <div aria-label="Hasil pencarian tindakan ICD-9-CM" className="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-neutral-200 bg-surface shadow-lg">
                                            {procedureLoading ? <p className="p-3 text-sm text-neutral-500" role="status">Mencari tindakan…</p> : procedureSearchFailed ? <p className="p-3 text-sm text-red-600" role="alert">Pencarian gagal. Ubah kata pencarian untuk mencoba kembali.</p> : procedureResults.length > 0 ? procedureResults.map((result) => (
                                                <Button className="h-auto w-full items-start justify-start gap-3 whitespace-normal rounded-none border-b border-neutral-100 px-3 py-3 text-left font-normal" key={result.id} onClick={() => {
                                                    setSelectedProcedure(result);
                                                    setProcedureQuery(`${result.code} · ${result.display}`);
                                                    setProcedureResults([]);
                                                    treatmentForm.setData('terminology_id', result.id.toString());
                                                    treatmentForm.clearErrors('terminology_id', 'tindakan_id');
                                                }} type="button" variant="ghost">
                                                    <span className="shrink-0 font-mono font-semibold">{result.code}</span>
                                                    <span className="min-w-0">{result.display}<span className="mt-1 block text-xs text-neutral-500">{result.release || 'Rilis tidak ditentukan'}</span></span>
                                                </Button>
                                            )) : <div className="space-y-2 p-3"><p className="text-sm text-neutral-500" role="status">Kode atau uraian tidak ditemukan. Gunakan uraian sesuai katalog, atau catat secara manual.</p><Button onClick={() => changeTreatmentMode('manual')} size="sm" type="button" variant="secondary">Input manual</Button></div>}
                                        </div>
                                    )}
                                </div>}
                                <Field error={treatmentForm.errors.jumlah} htmlFor="jumlah-tindakan" label="Jumlah" required>
                                    <Input id="jumlah-tindakan" min="1" onChange={(event) => treatmentForm.setData('jumlah', event.target.value)} required type="number" value={treatmentForm.data.jumlah} />
                                </Field>
                            </div>
                            {treatmentForm.errors.tindakan_id && treatmentMode !== 'clinic' && <p className="text-sm text-red-600" role="alert">{treatmentForm.errors.tindakan_id}</p>}
                            {treatmentMode === 'icd9cm' && !procedureCatalogAvailable && <p className="text-sm text-neutral-600" role="status">Katalog ICD-9-CM Volume 3 belum tersedia. Admin perlu mengimpor katalog prosedur di Terminologi Klinis. Sementara itu, pilih input manual atau tindakan klinik.</p>}
                            {treatmentMode === 'manual' && <p className="text-xs text-neutral-500">Tindakan disimpan sebagai uraian manual tanpa kode ICD-9-CM.</p>}
                            {selectedProcedure && treatmentMode === 'icd9cm' && <div className="space-y-3 rounded-lg border border-neutral-200 bg-surface p-3">
                                <p className="text-sm font-medium"><span className="font-mono">{selectedProcedure.code}</span> · {selectedProcedure.display}</p>
                                <p className="text-xs text-neutral-500">ICD-9-CM Volume 3 · {selectedProcedure.release || 'Rilis tidak ditentukan'}</p>
                                {treatments.length > 0 && <Field error={treatmentForm.errors.tindakan_id} htmlFor="procedure-clinic-treatment" label="Tindakan klinik terkait (opsional)">
                                    <Select id="procedure-clinic-treatment" onChange={(event) => treatmentForm.setData('tindakan_id', event.target.value)} value={treatmentForm.data.tindakan_id}>
                                        <option value="">Tanpa tindakan klinik terkait</option>
                                        {treatments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}
                                    </Select>
                                    <p className="text-xs text-neutral-500">Pilih tindakan yang sesuai untuk pencatatan BHP klinik.</p>
                                </Field>}
                            </div>}
                            {visit.clinicType === 'gigi' && (
                                <p className="text-xs leading-5 text-neutral-500">
                                    {treatmentForm.data.tooth_fdis.length > 0 ? `Tindakan akan dicatat untuk ${treatmentForm.data.tooth_fdis.map((tooth) => `gigi ${tooth}`).join(', ')}. Jumlah berlaku untuk setiap gigi.` : 'Pilih temuan di atas untuk menautkan tindakan ke gigi terkait. Tanpa pilihan, tindakan dicatat tanpa lokasi gigi spesifik.'}
                                </p>
                            )}
                            {treatmentForm.errors.tooth_fdis && <p className="text-sm text-red-600" role="alert">{treatmentForm.errors.tooth_fdis}</p>}
                            <Field error={treatmentForm.errors.catatan} htmlFor="catatan-tindakan" label="Catatan tindakan (opsional)">
                                <Textarea id="catatan-tindakan" maxLength={2000} onChange={(event) => treatmentForm.setData('catatan', event.target.value)} placeholder="Lokasi, teknik, hasil tindakan, atau catatan tambahan" rows={2} value={treatmentForm.data.catatan} />
                            </Field>
                            <div className="flex justify-end border-t border-neutral-200 pt-4">
                                <Button disabled={treatmentForm.processing || (treatmentMode === 'icd9cm' && !selectedProcedure) || (treatmentMode === 'clinic' && !treatmentForm.data.tindakan_id) || (treatmentMode === 'manual' && treatmentForm.data.nama_tindakan_manual.trim().length < 3)} type="submit">
                                    <Plus className="size-4" />
                                    Tambah tindakan
                                </Button>
                            </div>
                        </form>
                        <div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Kode ICD-9-CM</TableHead><TableHead>Nama tindakan</TableHead><TableHead>Jumlah</TableHead><TableHead>Catatan</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{visit.treatments.length ? visit.treatments.map((item) => <TableRow key={item.id}><TableCell><span className="font-mono font-medium">{item.code || 'Tanpa kode'}</span>{item.codeRelease && <span className="mt-1 block text-xs text-neutral-500">{item.codeRelease}</span>}</TableCell><TableCell className="whitespace-normal font-medium">{item.name}{item.toothFdi && <span className="ml-2 text-xs text-neutral-500">Gigi {item.toothFdi}</span>}</TableCell><TableCell>{item.quantity}x</TableCell><TableCell className="max-w-xs whitespace-pre-wrap text-sm text-neutral-600">{item.note || optionalAnswers.notRecorded}</TableCell><TableCell className="text-right"><Button onClick={() => deleteItem(`/pelayanan/pemeriksaan/tindakan/${item.id}`, 'Hapus tindakan ini?')} size="sm" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" />Hapus</Button></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada tindakan medis." /></TableCell></TableRow>}</TableBody></Table></div>
                        {renderTabNavigation('tindakan', 'resep', 'Lanjut ke resep klinik')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="resep">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-resep">Resep obat klinik</h3><p className="mt-1 text-sm text-neutral-500">Pilih obat dari stok klinik; jumlah akan diperiksa sebelum disimpan.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, prescriptionForm, `/pelayanan/pemeriksaan/${visit.id}/resep`)}>
                            <div className="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-4"><Field error={prescriptionForm.errors.jenis} htmlFor="jenis-resep" label="Jenis resep"><Select id="jenis-resep" onChange={(event) => prescriptionForm.setData('jenis', event.target.value)} value={prescriptionForm.data.jenis}><option value="jadi">Obat jadi</option><option value="racikan">Obat racikan</option></Select></Field><Field error={prescriptionForm.errors.obat_id} htmlFor="obat_id" label="Obat dari stok klinik" required><Select id="obat_id" onChange={(event) => { const medicine = medicines.find((item) => item.id.toString() === event.target.value); prescriptionForm.setData((data) => ({ ...data, obat_id: event.target.value, satuan: medicine?.unit ?? '' })); }} required value={prescriptionForm.data.obat_id}><option value="">Pilih obat</option>{medicines.map((medicine) => <option key={medicine.id} value={medicine.id}>{medicine.name} · stok {medicine.stock} {medicine.unit}</option>)}</Select></Field><Field error={prescriptionForm.errors.jumlah} htmlFor="jumlah-obat" label="Jumlah" required><Input id="jumlah-obat" min="1" onChange={(event) => prescriptionForm.setData('jumlah', event.target.value)} required type="number" value={prescriptionForm.data.jumlah} /></Field><Field error={prescriptionForm.errors.satuan} htmlFor="satuan-obat" label="Satuan"><Input id="satuan-obat" onChange={(event) => prescriptionForm.setData('satuan', event.target.value)} placeholder="tablet, kapsul, botol" value={prescriptionForm.data.satuan} /></Field></div>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={prescriptionForm.errors.aturan_pakai} htmlFor="aturan_pakai" label="Aturan pakai"><Input id="aturan_pakai" onChange={(event) => prescriptionForm.setData('aturan_pakai', event.target.value)} placeholder="3 x 1 tablet setelah makan" value={prescriptionForm.data.aturan_pakai} /></Field><Field error={prescriptionForm.errors.catatan} htmlFor="catatan-resep" label="Catatan"><Input id="catatan-resep" onChange={(event) => prescriptionForm.setData('catatan', event.target.value)} placeholder="Habiskan, bila demam..." value={prescriptionForm.data.catatan} /></Field></div>
                            <div className="flex justify-end border-t border-neutral-200 pt-4"><Button disabled={prescriptionForm.processing} type="submit"><Plus className="size-4" />Tambahkan resep klinik</Button></div>
                        </form>
                        {renderPrescriptionItems(visit.prescription.items.filter((item) => !item.external), prescriptionEditable, deleteItem)}
                        {renderTabNavigation('resep')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="resep-luar">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-resep-luar">Resep luar</h3><p className="mt-1 text-sm text-neutral-500">Catat obat yang diresepkan untuk ditebus di luar klinik. Entri ini tidak mengurangi stok klinik.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, externalPrescriptionForm, `/pelayanan/pemeriksaan/${visit.id}/resep`)}>
                            <div className="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-4"><Field error={externalPrescriptionForm.errors.jenis} htmlFor="jenis-resep-luar" label="Jenis resep"><Select id="jenis-resep-luar" onChange={(event) => externalPrescriptionForm.setData('jenis', event.target.value)} value={externalPrescriptionForm.data.jenis}><option value="jadi">Obat jadi</option><option value="racikan">Obat racikan</option></Select></Field><Field error={externalPrescriptionForm.errors.nama_obat} htmlFor="nama-obat-luar" label="Nama obat" required><Input id="nama-obat-luar" onChange={(event) => externalPrescriptionForm.setData('nama_obat', event.target.value)} required value={externalPrescriptionForm.data.nama_obat} /></Field><Field error={externalPrescriptionForm.errors.jumlah} htmlFor="jumlah-obat-luar" label="Jumlah" required><Input id="jumlah-obat-luar" min="0.01" onChange={(event) => externalPrescriptionForm.setData('jumlah', event.target.value)} required step="0.01" type="number" value={externalPrescriptionForm.data.jumlah} /></Field><Field error={externalPrescriptionForm.errors.satuan} htmlFor="satuan-obat-luar" label="Satuan"><Input id="satuan-obat-luar" onChange={(event) => externalPrescriptionForm.setData('satuan', event.target.value)} placeholder="tablet, kapsul, botol" value={externalPrescriptionForm.data.satuan} /></Field></div>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={externalPrescriptionForm.errors.aturan_pakai} htmlFor="aturan-pakai-luar" label="Aturan pakai"><Input id="aturan-pakai-luar" onChange={(event) => externalPrescriptionForm.setData('aturan_pakai', event.target.value)} placeholder="3 x 1 tablet setelah makan" value={externalPrescriptionForm.data.aturan_pakai} /></Field><Field error={externalPrescriptionForm.errors.catatan} htmlFor="catatan-resep-luar" label="Catatan"><Input id="catatan-resep-luar" placeholder="Catatan khusus resep luar (opsional)" onChange={(event) => externalPrescriptionForm.setData('catatan', event.target.value)} value={externalPrescriptionForm.data.catatan} /></Field></div>
                            <div className="flex justify-end border-t border-neutral-200 pt-4"><Button disabled={externalPrescriptionForm.processing} type="submit"><Plus className="size-4" />Simpan resep luar</Button></div>
                        </form>
                        {visit.prescription.items.some((item) => item.external) && <Button asChild variant="secondary"><a href={visit.prescription.printUrl ?? '#'} onClick={(event) => { if (!visit.prescription.printUrl) event.preventDefault(); }}><FileText className="size-4" />Cetak resep luar</a></Button>}
                        {renderPrescriptionItems(visit.prescription.items.filter((item) => item.external), prescriptionEditable, deleteItem)}
                        {renderTabNavigation('resep-luar')}
                    </TabsContent>
                    <TabsContent className="space-y-6 p-5 sm:p-6" value="surat">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-surat">Surat medis</h3><p className="mt-1 text-sm text-neutral-500">Terbitkan surat keterangan yang berkaitan dengan kunjungan.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, letterForm, `/pelayanan/pemeriksaan/${visit.id}/surat`)}><div className="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-3"><Field error={letterForm.errors.jenis} htmlFor="jenis-surat" label="Jenis surat" required><Select id="jenis-surat" onChange={(event) => { letterForm.setData((data) => ({ ...data, jenis: event.target.value, surat_template_id: '' })); }} value={letterForm.data.jenis}><option value="sakit">Surat keterangan sakit</option><option value="sehat">Surat keterangan sehat</option><option value="rujukan">Surat rujukan eksternal</option><option value="lainnya">Lainnya</option></Select></Field><Field error={letterForm.errors.surat_template_id} htmlFor="letter-template" label="Template surat"><Select id="letter-template" onChange={(event) => letterForm.setData('surat_template_id', event.target.value)} value={letterForm.data.surat_template_id}><option value="">Tanpa template</option>{correspondenceTemplates.filter((template) => template.type === letterForm.data.jenis).map((template) => <option key={template.id} value={template.id}>{template.name}</option>)}</Select></Field><Field error={letterForm.errors.tanggal} htmlFor="tanggal-surat" label="Tanggal surat" required><DatePicker id="tanggal-surat" onChange={(event) => letterForm.setData('tanggal', event.target.value)} required value={letterForm.data.tanggal} /></Field></div><p className="text-xs text-neutral-500">Nomor surat dibuat otomatis dari template yang dipilih. Kosongkan isi untuk menggunakan isi template.</p><Field error={letterForm.errors.konten} htmlFor="konten-surat" label="Isi keterangan"><Textarea id="konten-surat" placeholder="Kosongkan untuk menggunakan isi template" onChange={(event) => letterForm.setData('konten', event.target.value)} rows={4} value={letterForm.data.konten} /></Field><div className="flex justify-end"><Button disabled={letterForm.processing} type="submit"><Plus className="size-4" />Buat surat medis</Button></div></form>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Surat yang diterbitkan</h4>{visit.letters.length ? visit.letters.map((letter) => <article className="flex flex-col justify-between gap-2 rounded-xl border border-neutral-200 p-4 sm:flex-row sm:items-center" key={letter.id}><div><Badge variant="examination">{letter.type}</Badge><p className="mt-2 text-sm font-medium text-neutral-900">No: {letter.number ?? 'Belum diisi'}</p>{letter.content && <p className="mt-1 whitespace-pre-wrap text-sm text-neutral-600">{letter.content}</p>}</div><div className="flex items-center justify-between gap-3 sm:flex-col sm:items-end"><span className="text-xs text-neutral-500">{letter.date ?? 'Belum diisi'}</span><Button asChild size="sm" variant="secondary"><a href={letter.printUrl}><FileText className="size-4" />Cetak</a></Button></div></article>) : <Empty className="min-h-0 py-6" description="Surat medis yang diterbitkan akan tercatat di sini." title="Belum ada surat medis" />}</div>
                        {renderTabNavigation('surat')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="rujukan">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-rujukan">Rujukan internal</h3><p className="mt-1 text-sm text-neutral-500">Arahkan pasien untuk konsultasi ke poliklinik lain.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, referralForm, `/pelayanan/pemeriksaan/${visit.id}/rujukan`)}><div className="grid items-end gap-4 md:grid-cols-2"><Field error={referralForm.errors.ke_poli_id} htmlFor="ke_poli_id" label="Poliklinik tujuan" required><Select id="ke_poli_id" onChange={(event) => referralForm.setData('ke_poli_id', event.target.value)} required value={referralForm.data.ke_poli_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field><Field error={referralForm.errors.surat_template_id} htmlFor="referral-template" label="Template rujukan"><Select id="referral-template" onChange={(event) => referralForm.setData('surat_template_id', event.target.value)} value={referralForm.data.surat_template_id}><option value="">Tanpa template</option>{correspondenceTemplates.filter((template) => template.type === 'rujukan_internal').map((template) => <option key={template.id} value={template.id}>{template.name}</option>)}</Select></Field></div><Field error={referralForm.errors.konten_surat} htmlFor="referral-content" label="Isi surat rujukan"><Textarea id="referral-content" onChange={(event) => referralForm.setData('konten_surat', event.target.value)} placeholder="Kosongkan untuk menggunakan isi template" rows={4} value={referralForm.data.konten_surat} /></Field><Field error={referralForm.errors.catatan} htmlFor="catatan-rujukan" label="Catatan konsultasi"><Textarea id="catatan-rujukan" placeholder="Keterangan tambahan untuk konsultasi" onChange={(event) => referralForm.setData('catatan', event.target.value)} rows={3} value={referralForm.data.catatan} /></Field><div className="flex justify-end"><Button disabled={referralForm.processing} type="submit"><Plus className="size-4" />Kirim rujukan</Button></div></form>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Riwayat rujukan</h4>{visit.referrals.length ? visit.referrals.map((referral) => <article className="flex flex-col justify-between gap-2 rounded-xl border border-neutral-200 p-4 sm:flex-row sm:items-center" key={referral.id}><div><p className="text-sm font-medium text-neutral-900">{referral.fromClinic ?? 'Belum diisi'} → {referral.toClinic ?? 'Belum diisi'}</p><p className="mt-1 text-xs text-neutral-500">No: {referral.number ?? 'Belum diisi'}</p><p className="mt-1 text-sm text-neutral-600">{referral.notes ?? 'Belum diisi'}</p></div><div className="flex items-center justify-between gap-3"><Badge variant={referral.status === 'selesai' ? 'complete' : 'waiting'}>{referral.status}</Badge><Button asChild size="sm" variant="secondary"><a href={referral.printUrl}><FileText className="size-4" />Cetak</a></Button></div></article>) : <Empty className="min-h-0 py-6" description="Rujukan internal untuk kunjungan ini akan muncul di sini." title="Belum ada rujukan" />}</div>
                        {renderTabNavigation('rujukan')}
                    </TabsContent>
                    </fieldset>
                    </Tabs>
                </Card>
            </div>
            <Dialog onOpenChange={setSignatureDialogOpen} open={signatureDialogOpen}>
                <DialogContent>
                    <DialogHeader><DialogTitle>Tandatangani dan finalisasi</DialogTitle><DialogDescription>Masukkan kata sandi akun dokter untuk mengunci catatan, memberi cap waktu, dan membuat versi final yang tidak dapat ditimpa.</DialogDescription></DialogHeader>
                    <form className="space-y-4" onSubmit={signExamination}>
                        <Field error={signatureForm.errors.signature_password} htmlFor="signature-password" label="Kata sandi akun" required><Input autoComplete="current-password" id="signature-password" onChange={(event) => signatureForm.setData('signature_password', event.target.value)} required type="password" value={signatureForm.data.signature_password} /></Field>
                        <div className="flex justify-end gap-2"><Button onClick={() => setSignatureDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={signatureForm.processing} type="submit"><Check className="size-4" />Tandatangani pemeriksaan</Button></div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function renderPrescriptionItems(items: PrescriptionItem[], editable: boolean, onDelete: (url: string, confirmation: string) => void) {
    return <div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Nama obat</TableHead><TableHead>Jenis</TableHead><TableHead>Jumlah</TableHead><TableHead>Aturan pakai</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{items.length ? items.map((item) => <TableRow key={item.id}><TableCell className="font-medium">{item.name}</TableCell><TableCell>{item.type === 'jadi' ? 'Jadi' : 'Racikan'}</TableCell><TableCell>{item.quantity} {item.unit}</TableCell><TableCell>{item.instructions ?? 'Belum ditetapkan oleh dokter'}</TableCell><TableCell className="text-right"><Button disabled={!editable} onClick={() => onDelete(`/pelayanan/pemeriksaan/resep/${item.id}`, 'Hapus obat dari resep? Stok akan dikembalikan bila sebelumnya dikurangi.')} size="sm" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" />Hapus</Button></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada obat dalam resep." /></TableCell></TableRow>}</TableBody></Table></div>;
}

function formatMeasurement(value: number | null, unit: string): string {
    return value === null ? 'Belum diukur' : `${value} ${unit}`;
}

function formatBloodPressure(systolic: number | null, diastolic: number | null): string {
    if (systolic === null && diastolic === null) {
        return 'Belum diukur';
    }

    return `${systolic ?? 'Belum diukur'} / ${diastolic ?? 'Belum diukur'} mmHg`;
}

function displayDentalValues(values: string[], fallback: string): string {
    const labels: Record<string, string> = {
        dingin: 'Dingin',
        manis: 'Manis',
        mengunyah: 'Saat mengunyah',
        panas: 'Panas',
        spontan: 'Spontan',
        lainnya: 'Lainnya',
        hipertensi: 'Hipertensi',
        diabetes: 'Diabetes melitus',
        penyakit_jantung: 'Penyakit jantung',
        gangguan_pembekuan: 'Gangguan pembekuan darah',
        antikoagulan: 'Antikoagulan',
        hepatitis: 'Hepatitis',
        hiv: 'HIV',
        infeksi_lain: 'Infeksi lain',
        tidak_ada: 'Tidak ada',
    };

    return values.length > 0 ? values.map((value) => labels[value] ?? value).join(', ') : fallback;
}

function isReportedAllergy(value: string | null | undefined): value is string {
    const trimmedValue = value?.trim();

    return trimmedValue !== undefined && trimmedValue !== '' && !trimmedValue.toLocaleLowerCase().startsWith('tidak ada');
}
