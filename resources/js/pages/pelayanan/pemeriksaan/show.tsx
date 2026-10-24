import { confirmedAbsentHistory, normalizeClinicalAnswer } from '@/lib/clinical-answer';
import { selectedOdontogramTeeth } from '@/lib/odontogram-selection';
import { isReportedAllergy } from '@/lib/allergy-status';
import { ActionButton } from '@/components/ui/action-button';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, ChevronDown, ClipboardPlus, FileText, HeartPulse, Pill, Plus, Search, Stethoscope, UserRound } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
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
import { DentalExaminationSummary, DiagnosisSummary, GeneralExaminationSummary } from '@/components/kunjungan/clinical-workflow-summary';
import { AnatomyBodyMap } from '@/components/anatomy/anatomy-body-map';
import { optionalAnswers } from '@/lib/optional-answers';
import { VisitAttachments, type ClinicalAttachment } from '@/components/kunjungan/visit-attachments';

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
    attachments?: ClinicalAttachment[];
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
    screening: { summary?: Record<string, string | null>; systolic: number | null; diastolic: number | null; pulse: number | null; temperature: number | null; oxygenSaturation: number | null; weight: number | null; height: number | null; bmi: number | null; respiration: number | null; complaint: string | null; medicalHistory: string | null; familyHistory: string | null; allergyHistory: string | null; painScale: number | null; fallRisk: string | null; triage: string | null; priority: string | null; dentalPainLocation: string | null; dentalPainTriggers: string[]; dentalPainDuration: string | null; dentalMedicalRisks: string[]; dentalInfectionHistory: string[]; dentalNotes: string | null; } | null;
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
    initialTab?: string;
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
type Tab = 'pemeriksaan' | 'odontogram' | 'anamnesis' | 'diagnosa' | 'tindakan' | 'resep' | 'resep-luar' | 'surat' | 'rujukan';


const commonTabs: { id: Tab; label: string; icon: typeof Stethoscope }[] = [
    { id: 'anamnesis', label: 'Anamnesis', icon: Stethoscope },
    { id: 'pemeriksaan', label: 'Pemeriksaan fisik', icon: HeartPulse },
    { id: 'diagnosa', label: 'Diagnosis', icon: ClipboardPlus },
    { id: 'tindakan', label: 'Tindakan', icon: HeartPulse },
    { id: 'resep', label: 'Resep klinik', icon: Pill },
    { id: 'resep-luar', label: 'Resep luar', icon: Pill },
    { id: 'surat', label: 'Surat medis', icon: FileText },
    { id: 'rujukan', label: 'Rujukan internal', icon: ArrowLeft },
];

export default function ExaminationDetail({ visit, initialTab, doctors, treatments, medicines, clinics, diagnosisCodeSystems, procedureCatalogAvailable, procedureSearchUrl, dentalOptions, today, correspondenceTemplates }: Props) {
    const isDental = visit.clinicType === 'gigi';
    const physicalTab: Tab = isDental ? 'odontogram' : 'pemeriksaan';
    const tabs = commonTabs.map((tab) => tab.id === 'pemeriksaan' && isDental
        ? { ...tab, id: 'odontogram' as Tab, label: 'Pemeriksaan & odontogram' }
        : tab);
    const tabLabels = Object.fromEntries(tabs.map((tab) => [tab.id, tab.label]));
    const [activeTab, setActiveTab] = useState<Tab>(() => {
        const requestedTab = initialTab;

        return tabs.some(({ id }) => id === requestedTab) ? requestedTab as Tab : 'anamnesis';
    });
    const [icdQuery, setIcdQuery] = useState('');
    const [diagnosisCodeSystem, setDiagnosisCodeSystem] = useState('icd10_who');
    const [icdResults, setIcdResults] = useState<IcdSuggestion[]>([]);
    const [icdLoading, setIcdLoading] = useState(false);
    const [icdSearchFailed, setIcdSearchFailed] = useState(false);
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
        section: 'anamnesis',
        dokter_id: (visit.examination?.doctorId ?? visit.doctorId ?? '').toString(),
        anamnesis: normalizeClinicalAnswer(visit.examinationFormDefaults.anamnesis) ?? '',
        riwayat_penyakit_sekarang: normalizeClinicalAnswer(visit.examinationFormDefaults.currentHistory) ?? '',
        riwayat_penyakit_dahulu: normalizeClinicalAnswer(visit.examinationFormDefaults.pastHistory) ?? '',
        riwayat_penyakit_keluarga: normalizeClinicalAnswer(visit.examinationFormDefaults.familyHistory) ?? '',
        riwayat_alergi: normalizeClinicalAnswer(visit.examinationFormDefaults.allergyHistory) ?? '',
    });
    const physicalForm = useForm({
        section: 'pemeriksaan',
        pemeriksaan_fisik: normalizeClinicalAnswer(visit.examination?.physicalExam) ?? '',
        pemeriksaan_fisik_terstruktur: { ...defaultPhysicalSystems },
        pemeriksaan_ekstraoral: normalizeClinicalAnswer(visit.examination?.extraoralExam) ?? '',
    });
    const differentialForm = useForm({ section: 'diagnosis', diagnosis_banding: normalizeClinicalAnswer(visit.examination?.differentialDiagnosis) ?? '' });
    const followUpForm = useForm({
        section: 'rencana',
        kontrol_berikutnya: visit.examination?.followUpDate ?? '',
        catatan: normalizeClinicalAnswer(visit.examination?.notes) ?? '',
        edukasi: normalizeClinicalAnswer(visit.examination?.education) ?? '',
    });
    const diagnosisForm = useForm({ kode_icd10: '', code_system: 'icd10_who', code_release: '', nama_diagnosa: '', jenis: visit.diagnoses.some((diagnosis) => diagnosis.type === 'utama') ? 'tambahan' : 'utama' });
    const treatmentForm = useForm({ terminology_id: '', tindakan_id: '', nama_tindakan_manual: '', jumlah: '1', tooth_fdis: [] as string[], catatan: '' });
    const dentalForm = useForm({ tooth_fdi: '', surface: 'W', finding_code: '', notes: '', reason: '' });
    const odontogramUndoForm = useForm({ tooth_fdi: '', surface: '', reason: '' });
    const prescriptionForm = useForm({ obat_id: '', nama_obat: '', jumlah: '1', satuan: '', aturan_pakai: '', catatan: '', jenis: 'jadi', is_resep_luar: false });
    const externalPrescriptionForm = useForm({ obat_id: '', nama_obat: '', jumlah: '1', satuan: '', aturan_pakai: '', catatan: '', jenis: 'jadi', is_resep_luar: true });
    const letterForm = useForm({ jenis: 'sakit', nomor_surat: '', surat_template_id: '', tanggal: today, konten: '' });
    const referralForm = useForm({ ke_poli_id: '', surat_template_id: '', catatan: '', konten_surat: '' });
    const signatureForm = useForm({ signature_password: '' });
    const addendumForm = useForm({
        reason: '',
        anamnesis: normalizeClinicalAnswer(visit.examination?.anamnesis) ?? '',
        riwayat_penyakit_sekarang: normalizeClinicalAnswer(visit.examination?.currentHistory) ?? '',
        riwayat_penyakit_dahulu: normalizeClinicalAnswer(visit.examination?.pastHistory) ?? '',
        riwayat_penyakit_keluarga: normalizeClinicalAnswer(visit.examination?.familyHistory) ?? '',
        riwayat_alergi: normalizeClinicalAnswer(visit.examination?.allergyHistory) ?? '',
        pemeriksaan_fisik: normalizeClinicalAnswer(visit.examination?.physicalExam) ?? '',
        pemeriksaan_fisik_terstruktur: { ...defaultPhysicalSystems },
        pemeriksaan_ekstraoral: normalizeClinicalAnswer(visit.examination?.extraoralExam) ?? '',
        diagnosis_banding: normalizeClinicalAnswer(visit.examination?.differentialDiagnosis) ?? '',
        catatan: normalizeClinicalAnswer(visit.examination?.notes) ?? '',
        edukasi: normalizeClinicalAnswer(visit.examination?.education) ?? '',
        kontrol_berikutnya: visit.examination?.followUpDate ?? '',
    });

    useEffect(() => {
        const query = icdQuery.trim();
        setIcdResults([]);
        setIcdSearchFailed(false);
        if (query.length < 2) {
            setIcdLoading(false);
            setIcdLoading(false);
            return;
        }

        let active = true;
        setIcdLoading(true);
        const timeout = window.setTimeout(() => {
            fetch(`/pelayanan/icd10/search?q=${encodeURIComponent(query.slice(0, 100))}&code_system=${encodeURIComponent(diagnosisCodeSystem)}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() as Promise<IcdSuggestion[]> : Promise.reject(new Error('Pencarian ICD-10 gagal.')))
                .then((results) => { if (active) setIcdResults(results); })
                .catch(() => { if (active) { setIcdResults([]); setIcdSearchFailed(true); } })
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

    function navigateSection(section: Tab | 'rencana'): void {
        if (section === 'rencana') {
            const details = document.getElementById('rencana-details') as HTMLDetailsElement | null;
            if (details) details.open = true;
            document.getElementById('rencana-tindak-lanjut')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.getElementById('kontrol_berikutnya')?.focus({ preventScroll: true });
            return;
        }
        setActiveTab(section);
        document.getElementById('pemeriksaan-sections')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function confirmSave(label: string, save: () => void): void {
        confirmAction('Simpan data ini? Setelah tersimpan, Anda tetap berada di bagian ini.', save, label, label);
    }

    function submitClinicalSection(event: FormEvent<HTMLFormElement>, section: 'anamnesis' | 'pemeriksaan' | 'diagnosis' | 'rencana'): void {
        event.preventDefault();
        const sections = {
            anamnesis: { form: examinationForm, label: 'Simpan anamnesis', saved: () => examinationForm.setDefaults(examinationForm.data) },
            pemeriksaan: { form: physicalForm, label: 'Simpan pemeriksaan fisik', saved: () => physicalForm.setDefaults(physicalForm.data) },
            diagnosis: { form: differentialForm, label: 'Simpan diagnosis banding', saved: () => differentialForm.setDefaults(differentialForm.data) },
            rencana: { form: followUpForm, label: 'Simpan rencana tindak lanjut', saved: () => followUpForm.setDefaults(followUpForm.data) },
        };
        const selected = sections[section];
        if (selected.form.processing) return;

        confirmSave(selected.label, () => selected.form.post(`/pelayanan/pemeriksaan/${visit.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: selected.saved,
        }));
    }

    function submitForm(event: FormEvent<HTMLFormElement>, form: typeof diagnosisForm | typeof treatmentForm | typeof prescriptionForm | typeof externalPrescriptionForm | typeof letterForm | typeof referralForm, url: string): void {
        event.preventDefault();
        if (form.processing) return;
        const label = form === diagnosisForm ? 'Tambah diagnosis'
            : form === treatmentForm ? 'Tambah tindakan'
                : form === prescriptionForm ? 'Tambah obat resep klinik'
                    : form === externalPrescriptionForm ? 'Simpan resep luar'
                        : form === letterForm ? 'Simpan surat medis' : 'Simpan rujukan internal';

        confirmSave(label, () => form.post(url, { preserveScroll: true, preserveState: true, onSuccess: () => {
            if (form === diagnosisForm) {
                const data = { kode_icd10: '', code_system: diagnosisCodeSystem, code_release: '', nama_diagnosa: '', jenis: 'tambahan' };
                diagnosisForm.setData(data);
                diagnosisForm.setDefaults(data);
            }
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
        } }));
    }

    function selectDentalRecord(tooth: string, surface = dentalForm.data.surface): void {
        const applySelection = () => {
            const existing = visit.odontogram.find((finding) => finding.toothFdi === tooth && finding.surface === surface);
            const data = { tooth_fdi: tooth, surface, finding_code: existing?.findingCode ?? '', notes: existing?.notes ?? '', reason: '' };
            dentalForm.setData(data);
            dentalForm.setDefaults(data);
            dentalForm.clearErrors();
            if (tooth) setDentition(Number(tooth[0]) > 4 ? 'primary' : 'adult');
        };
        if (dentalForm.isDirty) {
            confirmAction('Temuan yang sedang diisi belum disimpan. Ganti pilihan dan buang perubahan ini?', applySelection, 'Ganti pilihan');
            return;
        }
        applySelection();
    }

    function selectDentalTooth(tooth: string): void {
        selectDentalRecord(tooth);
    }

    function changeDentition(next: 'adult' | 'primary'): void {
        const apply = () => {
            const data = { tooth_fdi: '', surface: 'W', finding_code: '', notes: '', reason: '' };
            dentalForm.setData(data);
            dentalForm.setDefaults(data);
            dentalForm.clearErrors();
            setDentition(next);
        };
        if (dentalForm.isDirty) {
            confirmAction('Isian temuan belum disimpan. Ganti kelompok gigi dan buang perubahan ini?', apply, 'Ganti kelompok');
            return;
        }
        apply();
    }

    function setDentalFindingSelection(finding: DentalFinding, selected: boolean): void {
        const key = `${finding.toothFdi}-${finding.surface}`;
        const nextKeys = selected
            ? [...new Set([...selectedDentalFindingKeys, key])]
            : selectedDentalFindingKeys.filter((selectedKey) => selectedKey !== key);
        const selectedTeeth = selectedOdontogramTeeth(visit.odontogram, nextKeys);

        setSelectedDentalFindingKeys(nextKeys);
        treatmentForm.setData('tooth_fdis', selectedTeeth);
    }

    function undoDentalFinding(): void {
        if (!undoFinding) return;

        odontogramUndoForm.delete(`/pelayanan/pemeriksaan/${visit.id}/odontogram`, {
            preserveScroll: true,
            onSuccess: () => {
                const nextKeys = selectedDentalFindingKeys.filter((key) => key !== `${undoFinding.toothFdi}-${undoFinding.surface}`);
                setSelectedDentalFindingKeys(nextKeys);
                treatmentForm.setData('tooth_fdis', selectedOdontogramTeeth(visit.odontogram, nextKeys));
                if (dentalForm.data.tooth_fdi === undoFinding.toothFdi && dentalForm.data.surface === undoFinding.surface) {
                    const cleared = { tooth_fdi: '', surface: 'W', finding_code: '', notes: '', reason: '' };
                    dentalForm.setData(cleared);
                    dentalForm.setDefaults(cleared);
                }
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
            {previousTab ? <Button onClick={() => navigateSection(previousTab)} size="sm" type="button" variant="secondary"><ArrowLeft className="size-4" />Kembali ke {tabLabels[previousTab]}</Button> : <span />}
            {nextTab && <Button className="sm:ml-auto" onClick={() => navigateSection(nextTab)} size="sm" type="button">{nextLabel ?? `Lanjut ke ${tabLabels[nextTab]}`}<ArrowLeft className="size-4 rotate-180" /></Button>}
        </div>;
    }

    function chooseDiagnosis(code: string, name: string, codeSystem = diagnosisCodeSystem, release = '') {
        diagnosisForm.setData((data) => ({ ...data, kode_icd10: code, code_system: codeSystem, code_release: release, nama_diagnosa: name }));
        setIcdQuery('');
        setIcdResults([]);
    }

    function deleteItem(url: string, confirmation: string) {
        confirmAction(confirmation, () => { router.delete(url, { preserveScroll: true }); }, 'Lanjutkan');
    }

    function discardSection(tab: Tab | 'rencana'): void {
        confirmAction('Batalkan isian yang belum disimpan pada bagian ini? Data yang sudah tersimpan tetap tersedia.', () => {
            if (tab === 'anamnesis') examinationForm.resetAndClearErrors();
            if (tab === physicalTab) { physicalForm.resetAndClearErrors(); dentalForm.resetAndClearErrors(); }
            if (tab === 'diagnosa') { diagnosisForm.resetAndClearErrors(); differentialForm.resetAndClearErrors(); }
            if (tab === 'rencana') followUpForm.resetAndClearErrors();
            if (tab === 'tindakan') { treatmentForm.resetAndClearErrors(); setSelectedProcedure(null); setProcedureQuery(''); setSelectedDentalFindingKeys([]); }
            if (tab === 'resep') prescriptionForm.resetAndClearErrors();
            if (tab === 'resep-luar') externalPrescriptionForm.resetAndClearErrors();
            if (tab === 'surat') letterForm.resetAndClearErrors();
            if (tab === 'rujukan') referralForm.resetAndClearErrors();
        }, 'Batalkan isian');
    }

    function finishExamination() {
        if (unsavedSections.length > 0 || saving) {
            toast.error('Simpan atau kosongkan isian yang belum disimpan sebelum finalisasi.');
            if (unsavedSections[0]) navigateSection(unsavedSections[0].tab);
            return;
        }
        const missing = readiness.find((item) => !item.complete);
        if (missing) {
            navigateSection(missing.tab);
            toast.error(`Lengkapi ${missing.label.toLocaleLowerCase()} sebelum finalisasi.`);
            return;
        }
        setSignatureDialogOpen(true);
    }

    function signExamination(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (unsavedSections.length > 0 || saving || readiness.some((item) => !item.complete)) {
            setSignatureDialogOpen(false);
            finishExamination();
            return;
        }
        signatureForm.post(`/pelayanan/pemeriksaan/${visit.id}/selesai`, {
            preserveScroll: true,
            onSuccess: () => {
                setSignatureDialogOpen(false);
                signatureForm.reset();
            },
        });
    }

    const canEdit = !visit.canClaim && visit.examination?.integrityValid !== false && visit.status === 'pemeriksaan' && visit.examination?.status !== 'selesai';
    const prescriptionEditable = canEdit && (!visit.prescription.status || visit.prescription.status === 'menunggu');
    const canCorrect = !visit.canClaim && Boolean(visit.examination?.signedAt) && visit.examination?.integrityValid === true && ['farmasi', 'kasir', 'selesai'].includes(visit.status);
    const saving = [examinationForm, physicalForm, differentialForm, followUpForm, diagnosisForm, treatmentForm, dentalForm, prescriptionForm, externalPrescriptionForm, letterForm, referralForm, odontogramUndoForm].some((form) => form.processing);
    const unsavedSections: { tab: Tab | 'rencana'; label: string }[] = [
        ...(examinationForm.isDirty ? [{ tab: 'anamnesis' as Tab, label: 'Anamnesis' }] : []),
        ...(physicalForm.isDirty ? [{ tab: physicalTab, label: isDental ? 'Pemeriksaan gigi & mulut' : 'Pemeriksaan fisik' }] : []),
        ...(followUpForm.isDirty ? [{ tab: 'rencana' as const, label: 'Rencana tindak lanjut & kontrol' }] : []),
        ...(isDental && dentalForm.isDirty ? [{ tab: 'odontogram' as Tab, label: 'Odontogram' }] : []),
        ...((diagnosisForm.isDirty || differentialForm.isDirty) ? [{ tab: 'diagnosa' as Tab, label: 'Diagnosis' }] : []),
        ...((treatmentForm.data.terminology_id || treatmentForm.data.tindakan_id || treatmentForm.data.nama_tindakan_manual.trim() || treatmentForm.data.catatan.trim()) ? [{ tab: 'tindakan' as Tab, label: 'Tindakan' }] : []),
        ...(prescriptionForm.isDirty ? [{ tab: 'resep' as Tab, label: 'Resep klinik' }] : []),
        ...(externalPrescriptionForm.isDirty ? [{ tab: 'resep-luar' as Tab, label: 'Resep luar' }] : []),
        ...(letterForm.isDirty ? [{ tab: 'surat' as Tab, label: 'Surat medis' }] : []),
        ...(referralForm.isDirty ? [{ tab: 'rujukan' as Tab, label: 'Rujukan internal' }] : []),
    ];
    const readiness: { tab: Tab; label: string; complete: boolean }[] = [
        { tab: 'anamnesis', label: 'Anamnesis', complete: Boolean(visit.examination?.anamnesis?.trim()) },
        { tab: physicalTab, label: isDental ? 'Pemeriksaan intraoral' : 'Pemeriksaan fisik', complete: Boolean(visit.examination?.physicalExam?.trim()) },
        ...(isDental ? [{ tab: 'odontogram' as Tab, label: 'Temuan odontogram', complete: visit.odontogram.length > 0 }] : []),
        { tab: 'diagnosa', label: 'Satu diagnosis utama', complete: visit.diagnoses.filter((diagnosis) => diagnosis.type === 'utama').length === 1 },
    ];
    const allergyWarning = [examinationForm.data.riwayat_alergi, visit.patient.allergies, visit.screening?.allergyHistory].find(isReportedAllergy);
    const triageSummary = visit.screening?.triage === 'merah' ? 'Perlu respons segera' : visit.screening?.triage === 'kuning' ? 'Perlu penilaian lanjut' : visit.screening?.triage === 'hijau' ? 'Prioritas rutin' : null;
    const prioritySummary = visit.screening?.priority === 'darurat' ? 'Respons darurat' : visit.screening?.priority === 'segera' ? 'Perlu penilaian segera' : visit.screening?.priority === 'normal' ? 'Rutin' : null;
    return (
        <>
            <Head title={`Pemeriksaan ${visit.patient.name}`} />
            <div className="space-y-6">
                <Card><CardContent className="space-y-4 p-5 sm:p-6">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div className="flex min-w-0 items-center gap-3"><span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-lg font-bold text-neutral-800">{visit.patient.name.slice(0, 1).toLocaleUpperCase()}</span><div className="min-w-0 text-left"><p className="text-xs font-medium text-neutral-500">{visit.number} · {visit.clinic}</p><h2 className="mt-0.5 text-lg font-semibold text-neutral-950">{visit.patient.name}</h2><p className="mt-1 text-sm text-neutral-600">{visit.patient.medicalRecordNumber} · {visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : 'Belum diketahui'} · {visit.patient.age === null ? 'umur belum diketahui' : `${visit.patient.age} th`} · Gol. {visit.patient.bloodType ?? 'Belum diketahui'}</p>{visit.examination?.startedAt && <p className="mt-1 text-xs text-neutral-500">Pemeriksaan dimulai {visit.examination.startedAt}</p>}</div></div>
                        <div className="flex shrink-0 flex-wrap justify-start gap-2 sm:justify-end"><Button asChild size="sm" variant="secondary"><Link href={`/pelayanan/pasien/${visit.patient.id}/rekam-medis`}><UserRound className="size-4" />Riwayat RME</Link></Button>{visit.canClaim && <Button onClick={() => router.post(`/pelayanan/pemeriksaan/${visit.id}/ambil`, {}, { preserveScroll: true })} size="sm"><Stethoscope className="size-4" />Ambil kunjungan</Button>}<Button disabled={!canEdit || saving} onClick={finishExamination} size="sm"><Check className="size-4" />Selesai periksa</Button></div>
                    </div>
                    {allergyWarning && <Alert className="text-left" variant="destructive"><AlertTriangle aria-hidden="true" /><AlertTitle>Peringatan alergi</AlertTitle><AlertDescription>{allergyWarning}</AlertDescription></Alert>}
                </CardContent></Card>

                {canEdit && <Card><CardHeader><CardTitle>Kesiapan finalisasi</CardTitle><CardDescription>Setiap bagian disimpan terpisah. Periksa kelengkapan sebelum menandatangani catatan.</CardDescription></CardHeader><CardContent className="space-y-3">
                    <div className="flex flex-wrap gap-2">{readiness.map((item) => <Button key={item.label} onClick={() => navigateSection(item.tab)} size="sm" type="button" variant="secondary"><Badge variant={item.complete ? 'complete' : 'waiting'}>{item.complete ? 'Lengkap' : 'Belum lengkap'}</Badge>{item.label}</Button>)}</div>
                    {unsavedSections.length > 0 && <Alert><AlertTitle>Isian belum disimpan</AlertTitle><AlertDescription>{unsavedSections.map((item) => item.label).join(', ')}. Simpan atau kosongkan isian tersebut sebelum finalisasi.<div className="mt-2 flex flex-wrap gap-2">{unsavedSections.map((item) => <Button disabled={saving} key={item.label} onClick={() => discardSection(item.tab)} size="sm" type="button" variant="secondary">Batalkan isian {item.label}</Button>)}</div></AlertDescription></Alert>}
                </CardContent></Card>}
                {visit.canClaim && <Alert><AlertTitle>Kunjungan belum ditugaskan</AlertTitle><AlertDescription>Anda memiliki jadwal aktif pada poli dan tanggal kunjungan ini. Ambil kunjungan untuk menugaskannya ke akun Anda sebelum mengisi pemeriksaan.</AlertDescription></Alert>}

                <Card><CardHeader><CardTitle>Ringkasan pengkajian awal perawat</CardTitle><CardDescription>Data pengkajian {visit.clinicType === 'gigi' ? 'Poli Gigi dan Mulut' : 'Poli Umum'} menjadi konteks awal pemeriksaan dokter.</CardDescription></CardHeader><CardContent className="space-y-4">
                    {visit.screening ? <>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">{[
                            ['Tekanan darah', formatBloodPressure(visit.screening.systolic, visit.screening.diastolic)], ['Nadi', formatMeasurement(visit.screening.pulse, 'x/menit')], ['Suhu', formatMeasurement(visit.screening.temperature, '°C')], ['SpO₂', formatMeasurement(visit.screening.oxygenSaturation, '%')], ['Respirasi', formatMeasurement(visit.screening.respiration, 'x/menit')], ['Berat / tinggi', `${formatMeasurement(visit.screening.weight, 'kg')} / ${formatMeasurement(visit.screening.height, 'cm')}`], ['IMT', visit.screening.bmi === null ? 'Belum dihitung' : `${visit.screening.bmi} kg/m²`], ['Nyeri / jatuh', `${visit.screening.painScale === null ? 'Belum dinilai' : `${visit.screening.painScale} / 10`} · ${visit.screening.fallRisk ?? 'Belum dinilai'}`],
                        ].map(([label, value]) => <div className="rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 text-left" key={label}><p className="text-xs text-neutral-500">{label}</p><p className="mt-1 text-sm font-semibold text-neutral-900">{value}</p></div>)}</div>
                        <div className="grid gap-4 md:grid-cols-2"><div className="space-y-2 text-sm"><div className="space-y-2">{Object.entries(visit.screening.summary ?? {}).map(([label, value]) => <p key={label}><strong>{label}:</strong> {value || optionalAnswers.notRecorded}</p>)}</div><p><strong>Keluhan awal:</strong> {visit.screening.complaint || optionalAnswers.notRecorded}</p><p><strong>Riwayat penyakit:</strong> {visit.screening.medicalHistory || optionalAnswers.notRecorded}</p><p><strong>Riwayat keluarga:</strong> {visit.screening.familyHistory || optionalAnswers.notRecorded}</p><p><strong>Riwayat alergi:</strong> {visit.screening.allergyHistory || visit.patient.allergies || optionalAnswers.notRecorded}</p><p><strong>Kebutuhan respons awal:</strong> {triageSummary ?? 'Belum ditentukan'} / {prioritySummary ?? 'Belum ditentukan'}</p></div>
                            {visit.clinicType === 'gigi' && <div className="space-y-2 rounded-lg border border-neutral-200 bg-neutral-50 p-3 text-sm"><p className="font-semibold text-neutral-900">Pengkajian gigi dan mulut</p><p><strong>Lokasi nyeri:</strong> {visit.screening.dentalPainLocation || optionalAnswers.notRecorded}</p><p><strong>Pemicu:</strong> {displayDentalValues(visit.screening.dentalPainTriggers, optionalAnswers.notRecorded)}</p><p><strong>Durasi:</strong> {visit.screening.dentalPainDuration || optionalAnswers.notRecorded}</p><p><strong>Risiko sistemik:</strong> {displayDentalValues(visit.screening.dentalMedicalRisks, optionalAnswers.notRecorded)}</p><p><strong>Riwayat infeksi:</strong> {displayDentalValues(visit.screening.dentalInfectionHistory, optionalAnswers.notRecorded)}</p><p><strong>Catatan perawat:</strong> {visit.screening.dentalNotes || optionalAnswers.notRecorded}</p></div>}
                        </div>
                    </> : <Empty className="min-h-0 py-5" title="Pengkajian awal perawat belum tercatat" />}
                </CardContent></Card>

                {!canEdit && !visit.canClaim && <Alert><AlertTitle>Pemeriksaan terkunci</AlertTitle><AlertDescription>Data kunjungan ini sudah melewati tahap pemeriksaan. Koreksi dicatat sebagai addendum beralasan tanpa mengubah versi final.</AlertDescription></Alert>}
                {visit.examination?.integrityValid === false && <Alert variant="destructive"><AlertTriangle aria-hidden="true" /><AlertTitle>Integritas catatan perlu diperiksa</AlertTitle><AlertDescription>Rantai versi catatan tidak cocok. Addendum dihentikan sampai data diperiksa oleh pengelola rekam medis.</AlertDescription></Alert>}

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
                    {canCorrect && <fieldset className="min-w-0" disabled={addendumForm.processing}><form className="space-y-6 border-t border-neutral-200 pt-5" onSubmit={(event) => { event.preventDefault(); if (addendumForm.processing) return; confirmSave('Simpan addendum', () => addendumForm.post(`/pelayanan/pemeriksaan/${visit.id}/addendum`, { preserveScroll: true, preserveState: true, onSuccess: () => addendumForm.reset('reason') })); }}>
                        <div><h3 className="text-sm font-semibold text-neutral-950">Buat addendum</h3><p className="mt-1 text-sm text-neutral-600">Ubah bagian yang perlu dikoreksi. Nilai versi sebelumnya tetap tersimpan.</p></div>
                        <Field error={addendumForm.errors.reason} htmlFor="addendum-reason" label="Alasan koreksi" required><Textarea id="addendum-reason" placeholder="Contoh: koreksi setelah verifikasi ulang" minLength={10} onChange={(event) => addendumForm.setData('reason', event.target.value)} required rows={2} value={addendumForm.data.reason} /></Field>
                        <section className="space-y-4" aria-labelledby="addendum-history-title"><h4 className="font-semibold text-neutral-900" id="addendum-history-title">Anamnesis</h4><div className="grid gap-4 md:grid-cols-2"><Field error={addendumForm.errors.anamnesis} htmlFor="addendum-anamnesis" label="Keluhan utama"><Textarea id="addendum-anamnesis" placeholder="Keluhan utama yang disampaikan pasien" onChange={(event) => addendumForm.setData('anamnesis', event.target.value)} rows={3} value={addendumForm.data.anamnesis} /></Field><Field error={addendumForm.errors.riwayat_penyakit_sekarang} htmlFor="addendum-rps" label="Riwayat penyakit sekarang"><Textarea id="addendum-rps" placeholder="Awal keluhan, durasi, dan perkembangannya" onChange={(event) => addendumForm.setData('riwayat_penyakit_sekarang', event.target.value)} rows={3} value={addendumForm.data.riwayat_penyakit_sekarang} /></Field><ConfirmedHistoryField error={addendumForm.errors.riwayat_penyakit_dahulu} id="addendum-rpd" kind="past" label="Riwayat penyakit dahulu" onChange={(value) => addendumForm.setData('riwayat_penyakit_dahulu', value)} placeholder="Riwayat penyakit, operasi, dan pengobatan rutin." value={addendumForm.data.riwayat_penyakit_dahulu} /><ConfirmedHistoryField error={addendumForm.errors.riwayat_penyakit_keluarga} id="addendum-riwayat-keluarga" kind="family" label="Riwayat penyakit keluarga" onChange={(value) => addendumForm.setData('riwayat_penyakit_keluarga', value)} placeholder="Riwayat penyakit keluarga yang relevan." value={addendumForm.data.riwayat_penyakit_keluarga} /><ConfirmedHistoryField error={addendumForm.errors.riwayat_alergi} id="addendum-alergi" kind="allergy" label="Riwayat alergi" onChange={(value) => addendumForm.setData('riwayat_alergi', value)} placeholder="Obat, makanan, dan reaksi alergi." value={addendumForm.data.riwayat_alergi} /></div></section>
                        <section className="space-y-4 border-t border-neutral-200 pt-5" aria-labelledby="addendum-exam-title"><h4 className="font-semibold text-neutral-900" id="addendum-exam-title">{isDental ? 'Pemeriksaan gigi & mulut' : 'Pemeriksaan fisik'}</h4><div className="grid gap-4 md:grid-cols-2"><Field error={addendumForm.errors.pemeriksaan_fisik} htmlFor="addendum-fisik" label={visit.clinicType === 'gigi' ? 'Pemeriksaan intraoral' : 'Ringkasan pemeriksaan fisik'}><Textarea id="addendum-fisik" placeholder="Ringkasan temuan pemeriksaan fisik" onChange={(event) => addendumForm.setData('pemeriksaan_fisik', event.target.value)} rows={3} value={addendumForm.data.pemeriksaan_fisik} /></Field>{isDental && <Field error={addendumForm.errors.pemeriksaan_ekstraoral} htmlFor="addendum-extraoral" label="Pemeriksaan ekstraoral"><Textarea id="addendum-extraoral" placeholder="Catat temuan ekstraoral yang diperiksa" onChange={(event) => addendumForm.setData('pemeriksaan_ekstraoral', event.target.value)} rows={3} value={addendumForm.data.pemeriksaan_ekstraoral} /></Field>}</div>{visit.clinicType === 'umum' && <div className="grid gap-4 md:grid-cols-2">{Object.entries(addendumForm.data.pemeriksaan_fisik_terstruktur).map(([system, value]) => <Field htmlFor={`addendum-system-${system}`} key={system} label={`Temuan · ${system}`}><Textarea id={`addendum-system-${system}`} onChange={(event) => addendumForm.setData('pemeriksaan_fisik_terstruktur', { ...addendumForm.data.pemeriksaan_fisik_terstruktur, [system]: event.target.value })} rows={2} value={value} /></Field>)}</div>}</section>
                        <section className="space-y-4 border-t border-neutral-200 pt-5" aria-labelledby="addendum-diagnosis-title"><h4 className="font-semibold text-neutral-900" id="addendum-diagnosis-title">Diagnosis</h4><Field error={addendumForm.errors.diagnosis_banding} htmlFor="addendum-diagnosis-banding" label="Diagnosis banding"><Textarea id="addendum-diagnosis-banding" placeholder="Diagnosis alternatif yang dipertimbangkan" onChange={(event) => addendumForm.setData('diagnosis_banding', event.target.value)} rows={3} value={addendumForm.data.diagnosis_banding} /></Field></section>
                        <section className="space-y-4 border-t border-neutral-200 pt-5" aria-labelledby="addendum-plan-title"><h4 className="font-semibold text-neutral-900" id="addendum-plan-title">Rencana tindak lanjut & kontrol</h4><div className="grid gap-4 md:grid-cols-2"><Field error={addendumForm.errors.kontrol_berikutnya} htmlFor="addendum-kontrol" label="Kontrol berikutnya"><DatePicker id="addendum-kontrol" onChange={(event) => addendumForm.setData('kontrol_berikutnya', event.target.value)} value={addendumForm.data.kontrol_berikutnya} /></Field><Field error={addendumForm.errors.catatan} htmlFor="addendum-catatan" label="Catatan dokter"><Textarea id="addendum-catatan" placeholder="Catatan klinis tambahan" onChange={(event) => addendumForm.setData('catatan', event.target.value)} rows={3} value={addendumForm.data.catatan} /></Field></div><Field error={addendumForm.errors.edukasi} htmlFor="addendum-edukasi" label="Edukasi pasien"><Textarea id="addendum-edukasi" placeholder="Anjuran atau edukasi yang diberikan" onChange={(event) => addendumForm.setData('edukasi', event.target.value)} rows={3} value={addendumForm.data.edukasi} /></Field></section>
                        <div className="flex justify-end"><Button disabled={addendumForm.processing} type="submit">Simpan addendum</Button></div>
                    </form></fieldset>}
                </CardContent></Card>}

                {!!visit.attachments?.length && <Card><CardHeader><CardTitle>Lampiran pasien</CardTitle><CardDescription>Dokumen pendukung yang dibawa pasien pada kunjungan ini.</CardDescription></CardHeader><CardContent><VisitAttachments attachments={visit.attachments} /></CardContent></Card>}
                <Card className="scroll-mt-6 overflow-hidden" id="pemeriksaan-sections">
                    <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as Tab)}>
                    <TabsList aria-label="Bagian pemeriksaan">{tabs.map(({ id, icon: Icon, label }) => <TabsTrigger className="min-h-12 px-3 sm:px-4" key={id} value={id}><Icon aria-hidden="true" className="size-4" />{label}{id === 'diagnosa' && <Badge>{visit.diagnoses.length}</Badge>}{id === 'tindakan' && <Badge>{visit.treatments.length}</Badge>}{(id === 'resep' || id === 'resep-luar') && <Badge>{visit.prescription.items.filter((item) => item.external === (id === 'resep-luar')).length}</Badge>}</TabsTrigger>)}</TabsList>

                    <TabsContent className="space-y-5 p-5 sm:p-6" value={physicalTab}>
                    <div><h3 className="text-base font-semibold text-neutral-950">{isDental ? 'Pemeriksaan gigi & mulut dan odontogram' : 'Pemeriksaan fisik'}</h3><p className="mt-1 text-sm text-neutral-500">Dokumentasikan temuan objektif yang diperiksa, lalu simpan hasil pemeriksaan.</p></div>
                    <fieldset className="min-w-0" disabled={!canEdit || physicalForm.processing}><form className="space-y-5" onSubmit={(event) => submitClinicalSection(event, 'pemeriksaan')}>
                        {visit.clinicType !== 'gigi' && (
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                <div>
                                    <CardTitle>Diagram Anatomi & Lokasi Keluhan Tubuh (Body Map)</CardTitle>
                                    <CardDescription>
                                        Catat temuan berdasarkan lokasi dan sistem organ yang diperiksa.
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <section aria-label="Diagram anatomi tubuh" className="space-y-4 rounded-2xl border border-neutral-200/90 bg-neutral-50/50 p-4 sm:p-5">
                                <AnatomyBodyMap
                                    disabled={!canEdit || physicalForm.processing}
                                    onApplyToExam={(organ, findingNote) => {
                                        const currentFinding = physicalForm.data.pemeriksaan_fisik_terstruktur[organ.physicalExamCategory] ?? '';
                                        const nextFinding = currentFinding ? `${currentFinding}\n- ${findingNote}` : `- ${findingNote}`;
                                        physicalForm.setData('pemeriksaan_fisik_terstruktur', {
                                            ...physicalForm.data.pemeriksaan_fisik_terstruktur,
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
                                    recordedFindings={physicalForm.data.pemeriksaan_fisik_terstruktur}
                                    selectedOrganId={selectedBodyOrganId}
                                />
                            </section>
                        </CardContent>
                    </Card>
                )}
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
                                            {Object.values(physicalForm.data.pemeriksaan_fisik_terstruktur).filter((val) => val.trim()).length} dari 6 sistem terisi
                                        </Badge>
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-[14rem_minmax(0,1fr)]">
                                        <div className="flex flex-col gap-1.5 rounded-lg border border-neutral-200/80 bg-surface p-2.5">
                                            <span className="px-2 py-1 text-[11px] font-semibold uppercase tracking-wider text-neutral-400">
                                                Daftar Regio / Sistem
                                            </span>
                                            {['Kepala dan leher', 'Thorax', 'Abdomen', 'Ekstremitas', 'Neurologis', 'Kulit'].map((system) => {
                                                const hasFinding = Boolean(physicalForm.data.pemeriksaan_fisik_terstruktur[system]?.trim());
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
                                                error={physicalForm.errors[`pemeriksaan_fisik_terstruktur.${selectedBodySystem}` as keyof typeof physicalForm.errors]}
                                                htmlFor="body-system-finding"
                                                label={`Temuan Klinis · ${selectedBodySystem}`}
                                            >
                                                <Textarea
                                                    id="body-system-finding"
                                                    onChange={(event) =>
                                                        physicalForm.setData('pemeriksaan_fisik_terstruktur', {
                                                            ...physicalForm.data.pemeriksaan_fisik_terstruktur,
                                                            [selectedBodySystem]: event.target.value,
                                                        })
                                                    }
                                                    placeholder={`Dokumentasikan hasil inspeksi, palpasi, perkusi, auskultasi untuk ${selectedBodySystem.toLowerCase()}...`}
                                                    rows={5}
                                                    value={physicalForm.data.pemeriksaan_fisik_terstruktur[selectedBodySystem] ?? ''}
                                                />
                                            </Field>
                                            <div className="flex flex-wrap gap-2">
                                                {Object.entries(physicalForm.data.pemeriksaan_fisik_terstruktur)
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
                        <Field error={physicalForm.errors.pemeriksaan_fisik} htmlFor="pemeriksaan_fisik" label={visit.clinicType === 'gigi' ? 'Pemeriksaan intraoral' : 'Ringkasan pemeriksaan fisik'}><Textarea id="pemeriksaan_fisik" onChange={(event) => physicalForm.setData('pemeriksaan_fisik', event.target.value)} placeholder={visit.clinicType === 'gigi' ? 'Mukosa, gingiva, gigi, oklusi, dan temuan intraoral lain.' : 'Ringkasan temuan pemeriksaan fisik.'} rows={4} value={physicalForm.data.pemeriksaan_fisik} /></Field>
{isDental && <Field error={physicalForm.errors.pemeriksaan_ekstraoral} htmlFor="extraoral" label="Pemeriksaan ekstraoral"><Textarea id="extraoral" onChange={(event) => physicalForm.setData('pemeriksaan_ekstraoral', event.target.value)} placeholder="Asimetri/pembengkakan wajah, bibir, kelenjar getah bening, bukaan mulut." rows={3} value={physicalForm.data.pemeriksaan_ekstraoral} /></Field>}
                        <div className="flex justify-end"><Button disabled={!canEdit || physicalForm.processing} type="submit">Simpan pemeriksaan fisik</Button></div>
                    </form></fieldset>
                    {isDental && (
                <Card className="space-y-5 border-0 shadow-none">
                    <CardHeader className="p-0 sm:p-0">
                        <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                            <div className="min-w-0 space-y-1.5">
                                <CardTitle>Odontogram Kunjungan (Peta Gigi FDI)</CardTitle>
                                <CardDescription>Catat nomor gigi FDI, permukaan, dan temuan. Koreksi setelah finalisasi membuat versi baru.</CardDescription>
                            </div>
                            <div className="flex shrink-0 flex-wrap gap-2">
                                <Button onClick={() => changeDentition('adult')} size="sm" type="button" variant={dentition === 'adult' ? 'default' : 'secondary'}>Permanen · 32</Button>
                                <Button onClick={() => changeDentition('primary')} size="sm" type="button" variant={dentition === 'primary' ? 'default' : 'secondary'}>Desidui · 20</Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-5 p-0 sm:p-0">
                        <section aria-label="Diagram odontogram" className="space-y-4 rounded-2xl border border-neutral-200/90 bg-neutral-50/50 p-4 sm:p-5">
                            <OdontogramFdi
                                dentition={dentition}
                                disabled={!canEdit && !canCorrect}
                                findingLabels={dentalOptions.findings}
                                surfaceLabels={dentalOptions.surfaces}
                                findings={visit.odontogram}
                                onSelect={selectDentalTooth}
                                selectedTooth={dentalForm.data.tooth_fdi}
                            />
                        </section>
                        {(canEdit || canCorrect) && (
                            <fieldset className="min-w-0" disabled={dentalForm.processing}><form className="space-y-4 border-t border-neutral-200 pt-5" onSubmit={(event) => { event.preventDefault(); if (dentalForm.processing) return; confirmSave(canEdit ? 'Simpan temuan odontogram' : 'Simpan addendum odontogram', () => dentalForm.post(`/pelayanan/pemeriksaan/${visit.id}/odontogram${canEdit ? '' : '/addendum'}`, { preserveScroll: true, preserveState: true, onSuccess: () => { const data = { tooth_fdi: '', surface: 'W', finding_code: '', notes: '', reason: '' }; dentalForm.setData(data); dentalForm.setDefaults(data); } })); }}>
                                <div className="grid gap-4 md:grid-cols-3">
                                    <Field error={dentalForm.errors.tooth_fdi} htmlFor="dental-tooth" label="Nomor gigi FDI" required>
                                        <Select id="dental-tooth" onChange={(event) => selectDentalTooth(event.target.value)} required value={dentalForm.data.tooth_fdi}>
                                            <option value="">Pilih gigi</option>
                                            {dentalOptions.teeth.filter((tooth) => dentition === 'adult' ? Number(tooth[0]) <= 4 : Number(tooth[0]) > 4).map((tooth) => <option key={tooth} value={tooth}>{tooth}</option>)}
                                        </Select>
                                    </Field>
                                    <Field error={dentalForm.errors.surface} htmlFor="dental-surface" label="Permukaan" required>
                                        <Select disabled={['missing', 'unerupted'].includes(dentalForm.data.finding_code)} id="dental-surface" onChange={(event) => selectDentalRecord(dentalForm.data.tooth_fdi, event.target.value)} value={dentalForm.data.surface}>
                                            {Object.entries(dentalOptions.surfaces).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
                                        </Select>
                                    </Field>
                                    <Field error={dentalForm.errors.finding_code} htmlFor="dental-finding" label="Temuan" required>
                                        <Select required id="dental-finding" onChange={(event) => dentalForm.setData((data) => ({ ...data, finding_code: event.target.value, surface: ['missing', 'unerupted'].includes(event.target.value) ? 'W' : data.surface }))} value={dentalForm.data.finding_code}>
                                            <option value="">Pilih temuan</option>{Object.entries(dentalOptions.findings).map(([code, label]) => <option key={code} value={code}>{label}</option>)}
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
                                <div className="flex flex-wrap justify-end gap-2">
                                    <Button disabled={dentalForm.processing} onClick={() => changeDentition(dentition)} type="button" variant="secondary">Kosongkan isian</Button>
                                    <Button disabled={dentalForm.processing || !dentalForm.isDirty || !dentalForm.data.tooth_fdi || !dentalForm.data.finding_code} type="submit">{canEdit ? visit.odontogram.some((finding) => finding.toothFdi === dentalForm.data.tooth_fdi && finding.surface === dentalForm.data.surface) ? 'Perbarui temuan' : 'Simpan temuan' : 'Simpan addendum odontogram'}</Button>
                                </div>
                            </form></fieldset>
                        )}

                        <section aria-labelledby="odontogram-findings-title" className="space-y-3 border-t border-neutral-200 pt-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div><h3 className="text-sm font-semibold text-neutral-900" id="odontogram-findings-title">Tabel temuan odontogram</h3><p className="mt-1 text-sm text-neutral-600">Pilih temuan pada gigi yang akan ditangani, lalu lanjutkan ke diagnosis sebelum mencatat tindakan. Setiap gigi dihitung satu kali meskipun beberapa permukaan dipilih.</p></div>
                                {canEdit && visit.odontogram.length > 0 && <Button onClick={() => {
                                    const nextFindings = selectedDentalFindingKeys.length === visit.odontogram.length ? [] : visit.odontogram;
                                    setSelectedDentalFindingKeys(nextFindings.map((finding) => `${finding.toothFdi}-${finding.surface}`));
                                    treatmentForm.setData('tooth_fdis', selectedOdontogramTeeth(nextFindings, nextFindings.map((finding) => `${finding.toothFdi}-${finding.surface}`)));
                                }} size="sm" type="button" variant="secondary">{selectedDentalFindingKeys.length === visit.odontogram.length ? 'Batal pilih semua' : 'Pilih semua temuan'}</Button>}
                            </div>
                            <div className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>Pilih</TableHead><TableHead>Gigi FDI</TableHead><TableHead>Permukaan</TableHead><TableHead>Temuan</TableHead><TableHead>Catatan</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>
                                {visit.odontogram.length ? visit.odontogram.map((finding) => <TableRow key={`${finding.toothFdi}-${finding.surface}`}>
                                    <TableCell><Checkbox aria-label={`Pilih gigi ${finding.toothFdi} permukaan ${finding.surface} untuk tindakan`} checked={selectedDentalFindingKeys.includes(`${finding.toothFdi}-${finding.surface}`)} disabled={!canEdit} onCheckedChange={(checked) => setDentalFindingSelection(finding, checked === true)} /></TableCell>
                                    <TableCell className="font-mono font-semibold">{finding.toothFdi}</TableCell>
                                    <TableCell>{dentalOptions.surfaces[finding.surface] ?? finding.surface}</TableCell>
                                    <TableCell>{dentalOptions.findings[finding.findingCode] ?? finding.findingCode}</TableCell>
                                    <TableCell className="max-w-xs whitespace-pre-wrap break-words">{finding.notes || optionalAnswers.notRecorded}</TableCell>
                                    <TableCell><div className="flex justify-center gap-2">
                                        <ActionButton action="edit" disabled={!canEdit && !canCorrect} label={`Ubah temuan gigi ${finding.toothFdi} permukaan ${finding.surface}`} onClick={() => selectDentalRecord(finding.toothFdi, finding.surface)} type="button" />
                                        <ActionButton action="delete" disabled={!canEdit && !canCorrect} label={`Batalkan temuan gigi ${finding.toothFdi} permukaan ${finding.surface}`} onClick={() => { odontogramUndoForm.setData({ tooth_fdi: finding.toothFdi, surface: finding.surface, reason: '' }); setUndoFinding(finding); }} type="button" />
                                    </div></TableCell>
                                </TableRow>) : <TableRow><TableCell colSpan={6}><Empty size="compact" title="Belum ada temuan odontogram" description="Catat temuan melalui input FDI di atas sebelum finalisasi." /></TableCell></TableRow>}
                            </TableBody></Table></div>
                            {selectedDentalFindingKeys.length > 0 && <div className="space-y-1"><p className="text-sm font-medium text-neutral-700">{selectedDentalFindingKeys.length} temuan dari {treatmentForm.data.tooth_fdis.length} gigi dipilih.</p><p className="text-sm text-neutral-600">Pilihan gigi tetap tersimpan untuk pencatatan tindakan setelah diagnosis.</p></div>}
                        </section>
                    </CardContent>
                </Card>
                )}
                {renderTabNavigation(physicalTab, 'diagnosa')}
                </TabsContent>

                    <fieldset className="min-w-0" disabled={!canEdit}>

                    <TabsContent className="space-y-5 p-5 sm:p-6" value="anamnesis">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-anamnesis">Anamnesis</h3><p className="mt-1 text-sm text-neutral-500">Catat keluhan dan riwayat pasien. Riwayat yang belum diketahui boleh dikosongkan; pilih tidak ada riwayat hanya setelah dikonfirmasi.</p></div>
                        <fieldset className="min-w-0" disabled={examinationForm.processing}><form className="space-y-5" onSubmit={(event) => submitClinicalSection(event, 'anamnesis')}>
                            <Field error={examinationForm.errors.dokter_id} htmlFor="dokter_id" label="Dokter pemeriksa"><Select id="dokter_id" onChange={(event) => examinationForm.setData('dokter_id', event.target.value)} value={examinationForm.data.dokter_id}><option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select></Field>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={examinationForm.errors.anamnesis} htmlFor="anamnesis" label="Keluhan utama" required><Textarea id="anamnesis" onChange={(event) => examinationForm.setData('anamnesis', event.target.value)} placeholder="Catat keluhan utama yang disampaikan pasien." required rows={3} value={examinationForm.data.anamnesis} /></Field><Field error={examinationForm.errors.riwayat_penyakit_sekarang} htmlFor="rps" label="Riwayat penyakit sekarang"><Textarea id="rps" onChange={(event) => examinationForm.setData('riwayat_penyakit_sekarang', event.target.value)} placeholder="Awal keluhan, durasi, perkembangan, tingkat keparahan, faktor pemicu/pereda." rows={3} value={examinationForm.data.riwayat_penyakit_sekarang} /></Field></div>
                            <div className="grid gap-4 md:grid-cols-3"><ConfirmedHistoryField error={examinationForm.errors.riwayat_penyakit_dahulu} id="rpd" kind="past" label="Riwayat penyakit dahulu" onChange={(value) => examinationForm.setData('riwayat_penyakit_dahulu', value)} placeholder="Riwayat penyakit, operasi, dan pengobatan rutin." value={examinationForm.data.riwayat_penyakit_dahulu} /><ConfirmedHistoryField error={examinationForm.errors.riwayat_penyakit_keluarga} id="riwayat-keluarga" kind="family" label="Riwayat penyakit keluarga" onChange={(value) => examinationForm.setData('riwayat_penyakit_keluarga', value)} placeholder="Riwayat penyakit keluarga yang relevan." value={examinationForm.data.riwayat_penyakit_keluarga} /><ConfirmedHistoryField error={examinationForm.errors.riwayat_alergi} id="riwayat-alergi-dokter" kind="allergy" label="Riwayat alergi" onChange={(value) => examinationForm.setData('riwayat_alergi', value)} placeholder="Obat, makanan, dan reaksi alergi." value={examinationForm.data.riwayat_alergi} /></div>
                            <div className="flex justify-end"><Button disabled={examinationForm.processing} type="submit">Simpan anamnesis</Button></div>
                        </form></fieldset>
                        {renderTabNavigation('anamnesis', physicalTab)}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="diagnosa">
                        {isDental ? <DentalExaminationSummary
                            anamnesis={visit.examination?.anamnesis}
                            intraoralExam={visit.examination?.physicalExam}
                            extraoralExam={visit.examination?.extraoralExam}
                            findings={visit.odontogram}
                            options={dentalOptions}
                            hasUnsavedChanges={examinationForm.isDirty || physicalForm.isDirty || dentalForm.isDirty}
                            onReview={() => navigateSection('odontogram')}
                        /> : <GeneralExaminationSummary
                            anamnesis={visit.examination?.anamnesis}
                            physicalExam={visit.examination?.physicalExam}
                            physicalSystems={visit.examination?.physicalSystems ?? {}}
                            hasUnsavedChanges={examinationForm.isDirty || physicalForm.isDirty}
                            onReview={() => navigateSection('pemeriksaan')}
                        />}
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-diagnosa">Diagnosis terstandar</h3><p className="mt-1 text-sm text-neutral-500">Cari dan catat kode dari sistem terminologi yang sesuai dengan katalog klinik.</p></div>
                                                <fieldset className="min-w-0" disabled={diagnosisForm.processing}><form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, diagnosisForm, `/pelayanan/pemeriksaan/${visit.id}/diagnosa`)}>
                        <div className="grid gap-4 md:grid-cols-[16rem_minmax(0,1fr)]"><Field error={diagnosisForm.errors.code_system} htmlFor="diagnosis-code-system" label="Sistem kode"><Select id="diagnosis-code-system" onChange={(event) => { setDiagnosisCodeSystem(event.target.value); diagnosisForm.setData((data) => ({ ...data, code_system: event.target.value, code_release: '', kode_icd10: '', nama_diagnosa: '' })); }} value={diagnosisCodeSystem}>{diagnosisCodeSystems.map((system) => <option key={system.value} value={system.value}>{system.label}</option>)}</Select></Field><div className="relative"><Field htmlFor="icd-search" label="Cari kode atau uraian"><div className="relative"><Search className="absolute left-3 top-3 size-4 text-neutral-400" /><Input autoComplete="off" className="pl-9" id="icd-search" onChange={(event) => setIcdQuery(event.target.value)} placeholder="Ketik kode atau nama diagnosis" value={icdQuery} /></div></Field>{(icdResults.length > 0 || icdLoading) && <div className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-xl border border-neutral-200 bg-surface shadow-lg">{icdLoading && <p className="p-3 text-sm text-neutral-500">Mencari…</p>}{icdResults.map((result) => <Button className="h-auto w-full justify-between rounded-none border-b border-neutral-100 px-3 py-2 text-left font-normal hover:bg-neutral-50 hover:text-neutral-900" key={`${result.code_system}-${result.release}-${result.kode}`} onClick={() => chooseDiagnosis(result.kode, result.nama, result.code_system, result.release)} type="button" variant="ghost"><span className="font-mono font-semibold text-neutral-700">{result.kode}</span><span>{result.nama}</span></Button>)}</div>}</div></div>
                            {icdSearchFailed && <p className="text-sm text-red-600" role="alert">Pencarian diagnosis gagal. Periksa koneksi dan coba kembali.</p>}
                            {!icdLoading && !icdSearchFailed && icdQuery.trim().length >= 2 && icdResults.length === 0 && <p className="text-sm text-neutral-600" role="status">Tidak ada diagnosis yang cocok pada katalog terpilih.</p>}
                            <div className="grid gap-4 md:grid-cols-3"><Field error={diagnosisForm.errors.kode_icd10} htmlFor="kode_icd10" label="Kode diagnosis" required><Input className="font-mono uppercase" id="kode_icd10" onChange={(event) => diagnosisForm.setData('kode_icd10', event.target.value.toUpperCase())} required value={diagnosisForm.data.kode_icd10} /></Field><Field error={diagnosisForm.errors.nama_diagnosa} htmlFor="nama_diagnosa" label="Nama diagnosis" required><Input id="nama_diagnosa" onChange={(event) => diagnosisForm.setData('nama_diagnosa', event.target.value)} required value={diagnosisForm.data.nama_diagnosa} /></Field><Field error={diagnosisForm.errors.jenis} htmlFor="jenis-diagnosa" label="Jenis diagnosis"><Select id="jenis-diagnosa" onChange={(event) => diagnosisForm.setData('jenis', event.target.value)} value={diagnosisForm.data.jenis}><option value="utama">Utama</option><option value="tambahan">Tambahan</option></Select></Field></div><p className="text-xs text-neutral-600">Memilih diagnosis utama baru akan memindahkan diagnosis utama sebelumnya menjadi diagnosis tambahan.</p>
                            <div className="flex justify-end border-t border-neutral-200 pt-4"><Button disabled={diagnosisForm.processing || !diagnosisForm.data.kode_icd10 || !diagnosisForm.data.nama_diagnosa} type="submit"><Plus className="size-4" />Tambah diagnosis</Button></div>
                        </form></fieldset>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Diagnosis tercatat</h4>{visit.diagnoses.length ? visit.diagnoses.map((diagnosis) => <div className="flex flex-col justify-between gap-3 rounded-xl border border-neutral-200 p-4 sm:flex-row sm:items-center" key={diagnosis.id}><div className="flex flex-wrap items-center gap-3"><Badge variant="examination">{diagnosis.code}</Badge><span className="text-sm font-medium text-neutral-900">{diagnosis.name}</span><Badge>{diagnosis.type === 'utama' ? 'Utama' : 'Tambahan'}</Badge></div><ActionButton onClick={() => deleteItem(`/pelayanan/pemeriksaan/diagnosa/${diagnosis.id}`, 'Hapus diagnosis ini?')} type="button" action="delete" label="Hapus" /></div>) : <Empty className="min-h-0 py-6" description="Diagnosis yang dicatat selama pemeriksaan akan tampil di sini." title="Belum ada diagnosis" />}</div>
                        <fieldset className="min-w-0" disabled={differentialForm.processing}><form className="space-y-4 rounded-xl border border-neutral-200 p-4" onSubmit={(event) => submitClinicalSection(event, 'diagnosis')}>
                            <Field error={differentialForm.errors.diagnosis_banding} htmlFor="diagnosis-banding" label="Diagnosis banding"><Textarea id="diagnosis-banding" onChange={(event) => differentialForm.setData('diagnosis_banding', event.target.value)} placeholder="Diagnosis alternatif atau kemungkinan lain yang dipertimbangkan." rows={2} value={differentialForm.data.diagnosis_banding} /></Field>
                            <div className="flex justify-end"><Button disabled={differentialForm.processing} type="submit">Simpan diagnosis banding</Button></div>
                        </form></fieldset>
                        {renderTabNavigation('diagnosa', 'tindakan', 'Lanjut ke tindakan')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="tindakan">
                        <DiagnosisSummary
                            id={isDental ? 'dental-diagnosis-summary' : 'general-diagnosis-summary'}
                            diagnoses={visit.diagnoses}
                            differentialDiagnosis={visit.examination?.differentialDiagnosis}
                            codeSystems={diagnosisCodeSystems}
                            hasUnsavedChanges={diagnosisForm.isDirty || differentialForm.isDirty}
                            onReview={() => navigateSection('diagnosa')}
                        />
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-tindakan">Tindakan medis</h3><p className="mt-1 text-sm text-neutral-500">Cari kode atau uraian ICD-9-CM Volume 3, lalu catat tindakan yang sudah dilakukan. Input manual tersedia bila belum ada padanan kode.</p></div>
                        <fieldset className="min-w-0" disabled={treatmentForm.processing}><form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, treatmentForm, `/pelayanan/pemeriksaan/${visit.id}/tindakan`)}>
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
                                    {treatmentForm.data.tooth_fdis.length > 0 ? `Tindakan akan dicatat untuk ${treatmentForm.data.tooth_fdis.map((tooth) => `gigi ${tooth}`).join(', ')}. Jumlah berlaku untuk setiap gigi.` : 'Pilih temuan pada tab Pemeriksaan & odontogram untuk menautkan tindakan ke gigi terkait. Tanpa pilihan, tindakan dicatat tanpa lokasi gigi spesifik.'}
                                </p>
                            )}
                            {isDental && <Button onClick={() => navigateSection('odontogram')} size="sm" type="button" variant="secondary">Pilih atau ubah temuan odontogram</Button>}
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
                        </form></fieldset>
                        <div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Kode ICD-9-CM</TableHead><TableHead>Nama tindakan</TableHead><TableHead>Jumlah</TableHead><TableHead>Catatan</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader><TableBody>{visit.treatments.length ? visit.treatments.map((item) => <TableRow key={item.id}><TableCell><span className="font-mono font-medium">{item.code || 'Tanpa kode'}</span>{item.codeRelease && <span className="mt-1 block text-xs text-neutral-500">{item.codeRelease}</span>}</TableCell><TableCell className="whitespace-normal font-medium">{item.name}{item.toothFdi && <span className="ml-2 text-xs text-neutral-500">Gigi {item.toothFdi}</span>}</TableCell><TableCell>{item.quantity}x</TableCell><TableCell className="max-w-xs whitespace-pre-wrap text-sm text-neutral-600">{item.note || optionalAnswers.notRecorded}</TableCell><TableCell className="text-center"><ActionButton onClick={() => deleteItem(`/pelayanan/pemeriksaan/tindakan/${item.id}`, 'Hapus tindakan ini?')} type="button" action="delete" label="Hapus" /></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada tindakan medis." /></TableCell></TableRow>}</TableBody></Table></div>
                        {renderTabNavigation('tindakan', 'resep', 'Lanjut ke resep klinik')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="resep">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-resep">Resep obat klinik</h3><p className="mt-1 text-sm text-neutral-500">Pilih obat dari stok klinik; jumlah akan diperiksa sebelum disimpan.</p></div>
                        <fieldset className="min-w-0" disabled={prescriptionForm.processing}><form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, prescriptionForm, `/pelayanan/pemeriksaan/${visit.id}/resep`)}>
                            <div className="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-4"><Field error={prescriptionForm.errors.jenis} htmlFor="jenis-resep" label="Jenis resep"><Select id="jenis-resep" onChange={(event) => prescriptionForm.setData('jenis', event.target.value)} value={prescriptionForm.data.jenis}><option value="jadi">Obat jadi</option><option value="racikan">Obat racikan</option></Select></Field><Field error={prescriptionForm.errors.obat_id} htmlFor="obat_id" label="Obat dari stok klinik" required><Select id="obat_id" onChange={(event) => { const medicine = medicines.find((item) => item.id.toString() === event.target.value); prescriptionForm.setData((data) => ({ ...data, obat_id: event.target.value, satuan: medicine?.unit ?? '' })); }} required value={prescriptionForm.data.obat_id}><option value="">Pilih obat</option>{medicines.map((medicine) => <option key={medicine.id} value={medicine.id}>{medicine.name} · stok {medicine.stock} {medicine.unit}</option>)}</Select></Field><Field error={prescriptionForm.errors.jumlah} htmlFor="jumlah-obat" label="Jumlah" required><Input id="jumlah-obat" min="1" onChange={(event) => prescriptionForm.setData('jumlah', event.target.value)} required type="number" value={prescriptionForm.data.jumlah} /></Field><Field error={prescriptionForm.errors.satuan} htmlFor="satuan-obat" label="Satuan"><Input id="satuan-obat" onChange={(event) => prescriptionForm.setData('satuan', event.target.value)} placeholder="tablet, kapsul, botol" value={prescriptionForm.data.satuan} /></Field></div>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={prescriptionForm.errors.aturan_pakai} htmlFor="aturan_pakai" label="Aturan pakai"><Input id="aturan_pakai" onChange={(event) => prescriptionForm.setData('aturan_pakai', event.target.value)} placeholder="3 x 1 tablet setelah makan" value={prescriptionForm.data.aturan_pakai} /></Field><Field error={prescriptionForm.errors.catatan} htmlFor="catatan-resep" label="Catatan"><Input id="catatan-resep" onChange={(event) => prescriptionForm.setData('catatan', event.target.value)} placeholder="Habiskan, bila demam..." value={prescriptionForm.data.catatan} /></Field></div>
                            <div className="flex justify-end border-t border-neutral-200 pt-4"><Button disabled={prescriptionForm.processing || !prescriptionEditable} type="submit"><Plus className="size-4" />Tambahkan resep klinik</Button></div>
                        </form></fieldset>
                        {renderPrescriptionItems(visit.prescription.items.filter((item) => !item.external), prescriptionEditable, deleteItem)}
                        {renderTabNavigation('resep', 'resep-luar')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="resep-luar">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-resep-luar">Resep luar</h3><p className="mt-1 text-sm text-neutral-500">Catat obat yang diresepkan untuk ditebus di luar klinik. Entri ini tidak mengurangi stok klinik.</p></div>
                        <fieldset className="min-w-0" disabled={externalPrescriptionForm.processing}><form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, externalPrescriptionForm, `/pelayanan/pemeriksaan/${visit.id}/resep`)}>
                            <div className="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-4"><Field error={externalPrescriptionForm.errors.jenis} htmlFor="jenis-resep-luar" label="Jenis resep"><Select id="jenis-resep-luar" onChange={(event) => externalPrescriptionForm.setData('jenis', event.target.value)} value={externalPrescriptionForm.data.jenis}><option value="jadi">Obat jadi</option><option value="racikan">Obat racikan</option></Select></Field><Field error={externalPrescriptionForm.errors.nama_obat} htmlFor="nama-obat-luar" label="Nama obat" required><Input id="nama-obat-luar" onChange={(event) => externalPrescriptionForm.setData('nama_obat', event.target.value)} required value={externalPrescriptionForm.data.nama_obat} /></Field><Field error={externalPrescriptionForm.errors.jumlah} htmlFor="jumlah-obat-luar" label="Jumlah" required><Input id="jumlah-obat-luar" min="0.01" onChange={(event) => externalPrescriptionForm.setData('jumlah', event.target.value)} required step="0.01" type="number" value={externalPrescriptionForm.data.jumlah} /></Field><Field error={externalPrescriptionForm.errors.satuan} htmlFor="satuan-obat-luar" label="Satuan"><Input id="satuan-obat-luar" onChange={(event) => externalPrescriptionForm.setData('satuan', event.target.value)} placeholder="tablet, kapsul, botol" value={externalPrescriptionForm.data.satuan} /></Field></div>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={externalPrescriptionForm.errors.aturan_pakai} htmlFor="aturan-pakai-luar" label="Aturan pakai"><Input id="aturan-pakai-luar" onChange={(event) => externalPrescriptionForm.setData('aturan_pakai', event.target.value)} placeholder="3 x 1 tablet setelah makan" value={externalPrescriptionForm.data.aturan_pakai} /></Field><Field error={externalPrescriptionForm.errors.catatan} htmlFor="catatan-resep-luar" label="Catatan"><Input id="catatan-resep-luar" placeholder="Catatan khusus resep luar (opsional)" onChange={(event) => externalPrescriptionForm.setData('catatan', event.target.value)} value={externalPrescriptionForm.data.catatan} /></Field></div>
                            <div className="flex justify-end border-t border-neutral-200 pt-4"><Button disabled={externalPrescriptionForm.processing || !prescriptionEditable} type="submit"><Plus className="size-4" />Simpan resep luar</Button></div>
                        </form></fieldset>
                        {visit.prescription.items.some((item) => item.external) && <Button asChild variant="secondary"><a href={visit.prescription.printUrl ?? '#'} onClick={(event) => { if (!visit.prescription.printUrl) event.preventDefault(); }}><FileText className="size-4" />Cetak resep luar</a></Button>}
                        {renderPrescriptionItems(visit.prescription.items.filter((item) => item.external), prescriptionEditable, deleteItem)}
                        {renderTabNavigation('resep-luar', 'surat')}
                    </TabsContent>
                    <TabsContent className="space-y-6 p-5 sm:p-6" value="surat">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-surat">Surat medis</h3><p className="mt-1 text-sm text-neutral-500">Terbitkan surat keterangan yang berkaitan dengan kunjungan.</p></div>
                        <fieldset className="min-w-0" disabled={letterForm.processing}><form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, letterForm, `/pelayanan/pemeriksaan/${visit.id}/surat`)}><div className="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-3"><Field error={letterForm.errors.jenis} htmlFor="jenis-surat" label="Jenis surat" required><Select id="jenis-surat" onChange={(event) => { letterForm.setData((data) => ({ ...data, jenis: event.target.value, surat_template_id: '' })); }} value={letterForm.data.jenis}><option value="sakit">Surat keterangan sakit</option><option value="sehat">Surat keterangan sehat</option><option value="rujukan">Surat rujukan eksternal</option><option value="lainnya">Lainnya</option></Select></Field><Field error={letterForm.errors.surat_template_id} htmlFor="letter-template" label="Template surat"><Select id="letter-template" onChange={(event) => letterForm.setData('surat_template_id', event.target.value)} value={letterForm.data.surat_template_id}><option value="">Tanpa template</option>{correspondenceTemplates.filter((template) => template.type === letterForm.data.jenis).map((template) => <option key={template.id} value={template.id}>{template.name}</option>)}</Select></Field><Field error={letterForm.errors.tanggal} htmlFor="tanggal-surat" label="Tanggal surat" required><DatePicker id="tanggal-surat" onChange={(event) => letterForm.setData('tanggal', event.target.value)} required value={letterForm.data.tanggal} /></Field></div><p className="text-xs text-neutral-500">Nomor surat dibuat otomatis dari template yang dipilih. Kosongkan isi untuk menggunakan isi template.</p><Field error={letterForm.errors.konten} htmlFor="konten-surat" label="Isi keterangan"><Textarea id="konten-surat" placeholder="Kosongkan untuk menggunakan isi template" onChange={(event) => letterForm.setData('konten', event.target.value)} rows={4} value={letterForm.data.konten} /></Field><div className="flex justify-end"><Button disabled={letterForm.processing} type="submit"><Plus className="size-4" />Buat surat medis</Button></div></form></fieldset>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Surat yang diterbitkan</h4>{visit.letters.length ? visit.letters.map((letter) => <article className="flex flex-col justify-between gap-2 rounded-xl border border-neutral-200 p-4 sm:flex-row sm:items-center" key={letter.id}><div><Badge variant="examination">{letter.type}</Badge><p className="mt-2 text-sm font-medium text-neutral-900">No: {letter.number ?? 'Belum diisi'}</p>{letter.content && <p className="mt-1 whitespace-pre-wrap text-sm text-neutral-600">{letter.content}</p>}</div><div className="flex items-center justify-between gap-3 sm:flex-col sm:items-end"><span className="text-xs text-neutral-500">{letter.date ?? 'Belum diisi'}</span><ActionButton asChild action="print" label="Cetak"><a href={letter.printUrl} /></ActionButton></div></article>) : <Empty className="min-h-0 py-6" description="Surat medis yang diterbitkan akan tercatat di sini." title="Belum ada surat medis" />}</div>
                        {renderTabNavigation('surat', 'rujukan')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="rujukan">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-rujukan">Rujukan internal</h3><p className="mt-1 text-sm text-neutral-500">Arahkan pasien untuk konsultasi ke poliklinik lain.</p></div>
                        <fieldset className="min-w-0" disabled={referralForm.processing}><form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, referralForm, `/pelayanan/pemeriksaan/${visit.id}/rujukan`)}><div className="grid items-end gap-4 md:grid-cols-2"><Field error={referralForm.errors.ke_poli_id} htmlFor="ke_poli_id" label="Poliklinik tujuan" required><Select id="ke_poli_id" onChange={(event) => referralForm.setData('ke_poli_id', event.target.value)} required value={referralForm.data.ke_poli_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field><Field error={referralForm.errors.surat_template_id} htmlFor="referral-template" label="Template rujukan"><Select id="referral-template" onChange={(event) => referralForm.setData('surat_template_id', event.target.value)} value={referralForm.data.surat_template_id}><option value="">Tanpa template</option>{correspondenceTemplates.filter((template) => template.type === 'rujukan_internal').map((template) => <option key={template.id} value={template.id}>{template.name}</option>)}</Select></Field></div><Field error={referralForm.errors.konten_surat} htmlFor="referral-content" label="Isi surat rujukan"><Textarea id="referral-content" onChange={(event) => referralForm.setData('konten_surat', event.target.value)} placeholder="Kosongkan untuk menggunakan isi template" rows={4} value={referralForm.data.konten_surat} /></Field><Field error={referralForm.errors.catatan} htmlFor="catatan-rujukan" label="Catatan konsultasi"><Textarea id="catatan-rujukan" placeholder="Keterangan tambahan untuk konsultasi" onChange={(event) => referralForm.setData('catatan', event.target.value)} rows={3} value={referralForm.data.catatan} /></Field><div className="flex justify-end"><Button disabled={referralForm.processing} type="submit"><Plus className="size-4" />Kirim rujukan</Button></div></form></fieldset>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Riwayat rujukan</h4>{visit.referrals.length ? visit.referrals.map((referral) => <article className="flex flex-col justify-between gap-2 rounded-xl border border-neutral-200 p-4 sm:flex-row sm:items-center" key={referral.id}><div><p className="text-sm font-medium text-neutral-900">{referral.fromClinic ?? 'Belum diisi'} → {referral.toClinic ?? 'Belum diisi'}</p><p className="mt-1 text-xs text-neutral-500">No: {referral.number ?? 'Belum diisi'}</p><p className="mt-1 text-sm text-neutral-600">{referral.notes ?? 'Belum diisi'}</p></div><div className="flex items-center justify-between gap-3"><Badge variant={referral.status === 'selesai' ? 'complete' : 'waiting'}>{referral.status}</Badge><ActionButton asChild action="print" label="Cetak"><a href={referral.printUrl} /></ActionButton></div></article>) : <Empty className="min-h-0 py-6" description="Rujukan internal untuk kunjungan ini akan muncul di sini." title="Belum ada rujukan" />}</div>
                        {renderTabNavigation('rujukan')}
                        <div className="flex justify-end"><Button onClick={() => navigateSection('rencana')} type="button">Lanjut ke rencana tindak lanjut & kontrol</Button></div>
                    </TabsContent>
                    </fieldset>
                    </Tabs>
                </Card>
                <Card id="rencana-tindak-lanjut" className="scroll-mt-6">
                    <details className="group" id="rencana-details">
                    <summary aria-controls="rencana-content" className="flex cursor-pointer list-none items-start justify-between gap-4 rounded-2xl p-5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 sm:p-6 [&::-webkit-details-marker]:hidden">
                        <span className="min-w-0 space-y-1.5"><span className="block text-base font-semibold tracking-tight text-neutral-950">Rencana tindak lanjut & kontrol</span><span className="block text-sm text-neutral-500">Tentukan jadwal kontrol bila diperlukan, catatan dokter, dan edukasi pasien.</span></span>
                        <ChevronDown aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-neutral-500 transition-transform group-open:rotate-180" />
                    </summary>
                    <CardContent className="border-t border-neutral-100 pt-5 sm:pt-6" id="rencana-content"><fieldset className="min-w-0" disabled={!canEdit || followUpForm.processing}><form className="space-y-5" onSubmit={(event) => submitClinicalSection(event, 'rencana')}>
                        <div className="grid gap-4 md:grid-cols-2"><Field error={followUpForm.errors.kontrol_berikutnya} htmlFor="kontrol_berikutnya" label="Jadwal kontrol berikutnya"><DatePicker id="kontrol_berikutnya" onChange={(event) => followUpForm.setData('kontrol_berikutnya', event.target.value)}  value={followUpForm.data.kontrol_berikutnya} /></Field><Field error={followUpForm.errors.catatan} htmlFor="catatan" label="Catatan khusus dokter"><Input id="catatan" onChange={(event) => followUpForm.setData('catatan', event.target.value)} placeholder="Catatan internal" value={followUpForm.data.catatan} /></Field></div>
<Field error={followUpForm.errors.edukasi} htmlFor="edukasi" label="Edukasi pasien"><Textarea id="edukasi" onChange={(event) => followUpForm.setData('edukasi', event.target.value)} placeholder="Anjuran, cara penggunaan obat, larangan, dan jadwal kontrol" rows={3} value={followUpForm.data.edukasi} /></Field>
                        <div className="flex flex-wrap justify-end gap-2"><Button disabled={!canEdit || followUpForm.processing} type="submit" variant="secondary">Simpan rencana tindak lanjut</Button>{canEdit && <Button disabled={saving} onClick={finishExamination} type="button"><Check className="size-4" />Selesai periksa</Button>}</div>
                    </form></fieldset></CardContent>
                    </details>
                </Card>
            </div>
            <Dialog onOpenChange={setSignatureDialogOpen} open={signatureDialogOpen}>
                <DialogContent>
                    <DialogHeader><DialogTitle>Tandatangani dan finalisasi</DialogTitle><DialogDescription>Masukkan kata sandi akun dokter untuk mengunci catatan, memberi cap waktu, dan membuat versi final yang tidak dapat ditimpa.</DialogDescription></DialogHeader>
                    <fieldset className="min-w-0" disabled={signatureForm.processing}><form className="space-y-4" onSubmit={signExamination}><p className="rounded-lg bg-neutral-50 p-3 text-sm text-neutral-700">{visit.prescription.items.some((item) => !item.external) ? 'Pasien diteruskan ke farmasi untuk resep klinik.' : 'Kunjungan selesai setelah ditandatangani. Resep luar ditebus di luar klinik.'}</p>
                        <Field error={signatureForm.errors.signature_password} htmlFor="signature-password" label="Kata sandi akun" required><Input autoComplete="current-password" id="signature-password" onChange={(event) => signatureForm.setData('signature_password', event.target.value)} required type="password" value={signatureForm.data.signature_password} /></Field>
                        <div className="flex justify-end gap-2"><Button onClick={() => setSignatureDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={signatureForm.processing} type="submit"><Check className="size-4" />Tandatangani pemeriksaan</Button></div>
                    </form></fieldset>
                </DialogContent>
            </Dialog>
        </>
    );
}

function ConfirmedHistoryField({ id, kind, label, placeholder, value, onChange, error }: {
    id: string;
    kind: keyof typeof confirmedAbsentHistory;
    label: string;
    placeholder: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    const absentStatement = confirmedAbsentHistory[kind];
    const confirmedAbsent = value === absentStatement;

    function confirmAbsence(checked: boolean): void {
        if (!checked) {
            onChange('');
            return;
        }
        if (normalizeClinicalAnswer(value) && !confirmedAbsent) {
            confirmAction(`Ganti isian ${label.toLocaleLowerCase()} dengan pernyataan tidak ada yang sudah dikonfirmasi kepada pasien?`, () => onChange(absentStatement), 'Konfirmasi tidak ada');
            return;
        }
        onChange(absentStatement);
    }

    return <Field error={error} htmlFor={id} label={label}>
        <div className="space-y-3">
            <Textarea className={kind === 'allergy' && isReportedAllergy(value) ? 'border-red-300 focus-visible:ring-red-500' : undefined} id={id} maxLength={kind === 'allergy' ? 5000 : 10000} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} rows={3} value={value} />
            <label className="flex cursor-pointer items-start gap-2 text-xs leading-5 text-neutral-700" htmlFor={`${id}-none`}>
                <Checkbox aria-label={`Konfirmasi tidak ada: ${label.toLocaleLowerCase()}`} checked={confirmedAbsent} className="mt-0.5 shrink-0" id={`${id}-none`} onCheckedChange={(checked) => confirmAbsence(checked === true)} />
                <span>{kind === 'allergy' ? 'Tidak ada alergi' : 'Tidak ada riwayat'}, sudah dikonfirmasi</span>
            </label>
        </div>
    </Field>;
}

function renderPrescriptionItems(items: PrescriptionItem[], editable: boolean, onDelete: (url: string, confirmation: string) => void) {
    return <div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Nama obat</TableHead><TableHead>Jenis</TableHead><TableHead>Jumlah</TableHead><TableHead>Aturan pakai</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader><TableBody>{items.length ? items.map((item) => <TableRow key={item.id}><TableCell className="font-medium">{item.name}</TableCell><TableCell>{item.type === 'jadi' ? 'Jadi' : 'Racikan'}</TableCell><TableCell>{item.quantity} {item.unit}</TableCell><TableCell>{item.instructions ?? 'Belum ditetapkan oleh dokter'}</TableCell><TableCell className="text-center"><ActionButton disabled={!editable} onClick={() => onDelete(`/pelayanan/pemeriksaan/resep/${item.id}`, 'Hapus obat dari resep? Stok akan dikembalikan bila sebelumnya dikurangi.')} type="button" action="delete" label="Hapus" /></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada obat dalam resep." /></TableCell></TableRow>}</TableBody></Table></div>;
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
        belum_diketahui: 'Belum diketahui',
        kehamilan: 'Kehamilan',
    };

    return values.length > 0 ? values.map((value) => labels[value] ?? value).join(', ') : fallback;
}
