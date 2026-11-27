import { InternalReferralStatus } from '@/components/kunjungan/internal-referral-status';
import { handleBackNavigation } from '@/lib/navigation';
import { ProcedureCodingReview } from '@/components/kunjungan/procedure-coding-review';
import { ConsentDialogSkeleton, SearchResultsSkeleton } from '@/components/loading-skeletons';
import { describeGeneralFinding, generalFindingSearch } from '@/lib/general-diagnosis';
import { GeneralDiagnosisSuggestions } from '@/components/kunjungan/general-diagnosis-suggestions';
import { focusFirstInvalidField, focusFormControl } from '@/lib/form-error-focus';
import { NursingSystemReview } from '@/components/kunjungan/nursing-system-review';
import { ScreeningVersionHistory, type ScreeningVersionSummary } from '@/components/kunjungan/screening-version-history';
import { SystemAssessment, type SystemAnswers } from '@/components/kunjungan/general-assessment';
import { countDoctorAssessmentFields, DoctorComplaintDetailsSection, DoctorDentalPainSection, DoctorMucosaObservationSection } from '@/components/kunjungan/doctor-clinical-sections';
import { legacyClinicalWorkflow, resolveClinicalWorkflow, type ClinicalWorkflowConfig, type DoctorClinicalAssessment } from '@/lib/clinical-workflow';
import { BodyExaminationRecord } from '@/components/anatomy/body-examination-record';
import { PhysicalExaminationWorkspace } from '@/components/anatomy/physical-examination-workspace';
import { Pagination } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { AssistanceReasonField } from '@/components/kunjungan/assistance-reason-field';
import { PatientContextBar } from '@/components/kunjungan/patient-context-bar';
import { useUnsavedChangesGuard } from '@/hooks/use-unsaved-changes-guard';
import { DentalExaminationSummary, DiagnosisSummary, GeneralExaminationSummary } from '@/components/kunjungan/clinical-workflow-summary';
import { VisitAttachments, type ClinicalAttachment } from '@/components/kunjungan/visit-attachments';
import { MedicalLetterFields, emptyLetterDetails, letterTypes } from '@/components/medical-letter-fields';
import { DentalClinicalControls } from '@/components/odontogram/dental-clinical-controls';
import { DentalDiagnosisBridge } from '@/components/odontogram/dental-diagnosis-bridge';
import { OdontogramFdi } from '@/components/odontogram/odontogram-fdi';
import { ActionButton } from '@/components/ui/action-button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertDialog, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { confirmAction, confirmReason } from '@/components/ui/confirm-dialog';
import { DatePicker } from '@/components/ui/date-picker';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { isReportedAllergy } from '@/lib/allergy-status';
import { detectClientAllergyMatch, detectClientDuplicateIngredient } from '@/lib/prescribing-safety';
import { emptyBodyFinding } from '@/lib/body-examination';
import { emptyBodyMap, hasBodyMapContent, type BodyMapData } from '@/lib/body-map';
import { confirmedAbsentHistory, normalizeClinicalAnswer } from '@/lib/clinical-answer';
import { missingCariesDetails } from '@/lib/dental-assessment';
import { dentalIntraoralNarrative, intraoralNarrativeLimit, syncIntraoralNarrative } from '@/lib/dental-intraoral-narrative';
import { candidateFindings, dentalDiagnosisDescription, dentalFindingDetails, dentalSurfaceLabel, dentalTreatmentSearches, diagnosisMatchesFindings, groupDentalDiagnoses, isDentalDiagnosisRecorded, wholeToothFindingCodes, type DentalDiagnosisCandidate, type DentalDiagnosisGroup } from '@/lib/dental-diagnosis';
import { examinationState } from '@/lib/examination-state';
import { paginateOdontogram, selectedOdontogramTeeth } from '@/lib/odontogram-selection';
import { RecordedTableNote } from '@/components/kunjungan/recorded-table-note';
import ProcedureConsent, { type ProcedureConsentProps } from './procedure-consent';
import { Head, Link, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, ChevronDown, ClipboardPlus, FileText, HeartPulse, Pill, Play, Plus, Search, Stethoscope, UserRound, X } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';
import { toast } from 'sonner';

interface Option { id: number; name: string; }
interface Diagnosis { sourceFindingCode?: string | null; codeRelease?: string | null; codingStatus?: 'verified' | 'unresolved' | 'legacy'; codingUnresolvedReason?: string | null; toothFdi?: string | null; surfaces?: string[]; id: number; code: string | null; codeSystem?: string; name: string; note?: string | null; type: string; }
interface ProcedureConsentSummary { decision: string; currentId: number | null; recordedAt: string | null; planMatches: boolean; requiredReview: boolean; signaturePending?: boolean; doctorStartRequired?: boolean; doctorStartedAt?: string | null; url: string; }
interface Treatment { codingStatus?: string; codingRevision?: string | null; executionRole: string; id: number; name: string; code: string | null; codeSystem: string | null; codeRelease: string | null; quantity: number; toothFdi: string | null; note: string | null; completed?: boolean; consent?: ProcedureConsentSummary | null; }
const consentAllowsExecution = (consent?: ProcedureConsentSummary | null) => !consent
    || (!consent.signaturePending && ((!consent.requiredReview && consent.decision === 'unreviewed') || (consent.planMatches && ['accepted', 'oral', 'not_required', 'emergency'].includes(consent.decision))));
const procedureConsentLabels: Record<string, string> = {
    accepted: 'Persetujuan tercatat', oral: 'Persetujuan lisan tercatat',
    not_required: 'Persetujuan khusus tidak diperlukan', emergency: 'Pengecualian darurat',
    refused: 'Ditolak pasien', withdrawn: 'Persetujuan dicabut',
};
interface ProcedureSuggestion { id: number; code: string; display: string; release: string; }
type DentalDetails = Record<string, string>;
interface DentalFinding { details?: DentalDetails | null; detailSummary?: string | null; toothFdi: string; surface: string; findingCode: string; notes: string | null; }
interface PrescriptionItem { id: number; medicineId?: number | null; name: string; ingredients?: string | null; isHighAlert?: boolean; isLasa?: boolean; allergyOverrideReason?: string | null; allergyMatchedTerm?: string | null; type: string; quantity: number; unit: string | null; instructions: string | null; external: boolean; }
interface Letter { id: number; type: string; number: string | null; content: string | null; date: string | null; printUrl: string; }
interface Referral { destinationVisit?: string | null; id: number; fromClinic: string | null; toClinic: string | null; notes: string | null; status: string; number: string | null; content: string | null; printUrl: string; }
interface CorrespondenceOption { id: number; name: string; type: string; content: string; }
interface Examination {
    supportingExams?: Record<string, string>;
    reviewOfSystems?: SystemAnswers;
    clinicalAssessment?: DoctorClinicalAssessment;
    socialHistory?: string | null;
    finalCondition?: string | null;
    disposition?: string | null;
    nursingReview?: { clarification?: string | null; reviewed_at?: string | null } | null;
    doctorId: number | null;
    canClaim: boolean;
    riwayat_obat?: string | null;
    riwayat_perawatan_gigi?: string | null;
    kebiasaan_gigi?: string | null;
    startedAt: string | null;
    anamnesis: string | null;
    currentHistory: string | null;
    pastHistory: string | null;
    familyHistory: string | null;
    allergyHistory: string | null;
    physicalExam: string | null;
    repeatVitals?: { systolic?: number | null; diastolic?: number | null; pulse?: number | null; temperature?: number | null } | null;
    odontogramSummary?: string | null;
    physicalSystems: Record<string, string>;
    bodyMap?: BodyMapData | null;
    extraoralExam: string | null;
    oralHygieneIndex?: number | null;
    differentialDiagnosis: string | null;
    followUpDate: string | null;
    notes: string | null;
    nonPharmacological: string | null;
    education: string | null;
    status: string;
    integrityValid: boolean | null;
    signedAt: string | null;
    after48Hours?: boolean;
    signedBy: string | null;
    versions: Array<{ version: number; kind: string; reason: string | null; actor: string | null; recordedAt: string | null; anamnesis: string | null; physicalExam: string | null; odontogramSummary?: string | null; bodyMap?: BodyMapData | null; notes: string | null; education: string | null; nonPharmacological: string | null; clinicalAssessmentSummary?: Record<string, string>; odontogram: DentalFinding[] }>;
}
interface Visit {
    isReservation?: boolean;
    recordUrl?: string | null;
    attachmentArchive?: ClinicalAttachment[]; attachments?: ClinicalAttachment[];
    id: number;
    number: string;
    status: string;
    canClaim: boolean;
    screeningSubmitted: boolean;
    screeningRevision?: string | null;
    dentalEscalationRequired?: boolean;
    doctorId: number | null;
    clinicId: number;
    clinicType: string | null;
    clinic: string;
    patient: { id: number; name: string; medicalRecordNumber: string; gender: string | null; age: number | null; bloodType: string | null; allergies: string | null; maternal?: { applicable?: boolean; hasRisk?: boolean; summaryText?: string | null; pregnancyLabel?: string | null; lactationLabel?: string | null } | null; };
    examinationFormDefaults: { reviewOfSystems?: SystemAnswers; riwayat_obat?: string | null; riwayat_perawatan_gigi?: string | null; kebiasaan_gigi?: string | null; pemeriksaan_ekstraoral?: string | null; anamnesis: string | null; currentHistory: string | null; pastHistory: string | null; familyHistory: string | null; allergyHistory: string | null; };
    screening: { systemAssessment?: SystemAnswers; versions?: ScreeningVersionSummary[]; summary?: Record<string, string | null>; systolic: number | null; diastolic: number | null; pulse: number | null; temperature: number | null; oxygenSaturation: number | null; weight: number | null; height: number | null; bmi: number | null; bmiCategory?: string | null; dentalHypertensionAlert?: boolean; waistCircumference: number | string | null; respiration: number | null; complaint: string | null; medicalHistory: string | null; familyHistory: string | null; allergyHistory: string | null; painScale: number | null; fallRisk: string | null; triage: string | null; priority: string | null; dentalPainLocation: string | null; dentalPainTriggers: string[]; dentalPainDuration: string | null; dentalMedicalRisks: string[]; dentalInfectionHistory: string[]; dentalNotes: string | null; } | null;
    examination: Examination | null;
    diagnoses: Diagnosis[];
    treatments: Treatment[];
    cancelledDraftEntries?: { id: number; kind: string; label: string; details: string | null; reason: string; at: string; actorId: number }[];
    odontogram: DentalFinding[];
    prescription: { status: string | null; items: PrescriptionItem[]; printUrl: string | null };
    letters: Letter[];
    referrals: Referral[];
    incomingReferral?: { number: string | null; fromClinic: string | null; originVisit: string | null; note: string | null; content: string | null } | null;
}
interface Props {
    assistanceReview?: { auditId: number; operator: string | null; lastEditedAt: string; draftHash: string | null } | null;
    assistance?: { isAdmin: boolean; active: boolean };
    visit: Visit;
    initialTab?: string;
    examinerName?: string | null;
    treatments: (Option & { icd9Code?: string | null; canDelegateToNurse?: boolean; consentMode?: string; consentModeLabel?: string })[];
    medicines: (Option & { stock: number; unit: string | null; ingredients?: string | null; isHighAlert?: boolean; isLasa?: boolean; nearExpiryDate?: string | null })[];
    clinics: Option[];
    diagnosisCodeSystems: Array<{ value: string; label: string }>;
    codingDowntimeActive: boolean;
    procedureCatalogAvailable: boolean;
    procedureSearchUrl: string;
    dentalOptions: { findingGroups?: Record<string, string[]>; wholeToothFindings?: string[]; diagnosisCatalog?: Array<{ code: string; name: string; codeSystem: string; release: string }>; detailOptions?: Record<string, Record<string, string>>; teeth: string[]; surfaces: Record<string, string>; findings: Record<string, string> };
    today: string;
    correspondenceTemplates: CorrespondenceOption[];
    workflowSettings?: Partial<ClinicalWorkflowConfig>;
}
interface IcdSuggestion { kode: string; nama: string; code_system: string; release: string; }
type Tab = 'pemeriksaan' | 'odontogram' | 'anamnesis' | 'diagnosa' | 'tindakan' | 'resep' | 'resep-luar' | 'surat' | 'rujukan' | 'soap';


const commonTabs: { id: Tab; label: string; icon: typeof Stethoscope; optional?: boolean }[] = [
    { id: 'anamnesis', label: 'Anamnesis', icon: Stethoscope },
    { id: 'pemeriksaan', label: 'Pemeriksaan fisik', icon: HeartPulse },
    { id: 'diagnosa', label: 'Diagnosis', icon: ClipboardPlus },
    { id: 'tindakan', label: 'Tindakan', icon: HeartPulse },
    { id: 'resep', label: 'Resep klinik', icon: Pill },
    { id: 'resep-luar', label: 'Resep luar', icon: Pill, optional: true },
    { id: 'surat', label: 'Surat medis', icon: FileText, optional: true },
    { id: 'rujukan', label: 'Rujukan internal', icon: ArrowLeft, optional: true },
];

export default function ExaminationDetail({ assistanceReview, assistance, visit, initialTab, examinerName, treatments, medicines, clinics, diagnosisCodeSystems, codingDowntimeActive, procedureCatalogAvailable, procedureSearchUrl, dentalOptions, today, correspondenceTemplates, workflowSettings }: Props) {
    const workflow = resolveClinicalWorkflow(workflowSettings, legacyClinicalWorkflow);
    const assistanceForm = useForm({ reason: '' });
    const isAdminAssistance = assistance?.isAdmin === true;
    const isDental = visit.clinicType === 'gigi';
    const showSoapTab = workflow.dokter_soap_tab;
    const showRepeatVitals = workflow.dokter_repeat_vitals || Boolean(
        visit.examination?.repeatVitals?.systolic != null
        || visit.examination?.repeatVitals?.diastolic != null
        || visit.examination?.repeatVitals?.pulse != null
        || visit.examination?.repeatVitals?.temperature != null,
    );
    const hasNurseSystemAssessment = !isDental && Object.values(visit.screening?.systemAssessment ?? {}).some(
        (entry) => (entry?.status && entry.status !== 'not_assessed') || Boolean(entry?.notes?.trim()),
    );
    const hasDoctorSystemReview = !isDental && Object.values(visit.examination?.reviewOfSystems ?? {}).some(
        (entry) => (entry?.status && entry.status !== 'not_assessed') || Boolean(entry?.notes?.trim()),
    );
    const showDoctorComplaintDetails = !isDental && (
        ['dokter', 'keduanya'].includes(workflow.umum_complaint_details)
        || Boolean(visit.examination?.clinicalAssessment?.complaint_details && Object.keys(visit.examination.clinicalAssessment.complaint_details).length > 0)
    );
    const showDoctorSystemReview = !isDental && (
        ['dokter', 'keduanya'].includes(workflow.umum_system_review)
        || hasDoctorSystemReview
    );
    const showDoctorDentalPain = isDental && (
        ['dokter', 'keduanya'].includes(workflow.gigi_pain_details)
        || Boolean(visit.examination?.clinicalAssessment?.dental_pain && Object.keys(visit.examination.clinicalAssessment.dental_pain).length > 0)
    );
    const showDoctorMucosa = isDental && (
        ['dokter', 'keduanya'].includes(workflow.gigi_mucosa_observation)
        || Boolean(visit.examination?.clinicalAssessment?.mucosa_observation && Object.keys(visit.examination.clinicalAssessment.mucosa_observation).length > 0)
    );
    const physicalTab: Tab = isDental ? 'odontogram' : 'pemeriksaan';
    const tabs = commonTabs.filter((tab) => !isAdminAssistance || !['surat', 'rujukan'].includes(tab.id)).map((tab) => tab.id === 'pemeriksaan' && isDental
        ? { ...tab, id: 'odontogram' as Tab, label: 'Pemeriksaan & odontogram' }
        : tab);
    const tabLabels = Object.fromEntries(tabs.map((tab) => [tab.id, tab.label]));
    const [activeTab, setActiveTab] = useState<Tab>(() => {
        const requestedTab = initialTab;

        return (requestedTab === 'soap' && showSoapTab) || tabs.some(({ id }) => id === requestedTab) ? requestedTab as Tab : 'anamnesis';
    });
    const [icdQuery, setIcdQuery] = useState('');
    const availableDiagnosisSystems = diagnosisCodeSystems.filter(system => system.value === 'icd10_who');
    const defaultDiagnosisSystem = availableDiagnosisSystems[0]?.value ?? 'icd10_who';
    const [diagnosisCodeSystem, setDiagnosisCodeSystem] = useState(defaultDiagnosisSystem);
    const [icdResults, setIcdResults] = useState<IcdSuggestion[]>([]);
    const [icdActiveIndex, setIcdActiveIndex] = useState(-1);
    const [icdLoading, setIcdLoading] = useState(false);
    const [icdSearchFailed, setIcdSearchFailed] = useState(false);
    const [consentUrl, setConsentUrl] = useState<string | null>(null);
    const [consentDetails, setConsentDetails] = useState<ProcedureConsentProps | null>(null);
    const [consentLoadFailed, setConsentLoadFailed] = useState(false);
    const [resumeSignature, setResumeSignature] = useState(false);
    const [startingProcedure, setStartingProcedure] = useState<Treatment | null>(null);
    const procedureStartForm = useForm({ consent_id: 0, confirmed: false });
    useEffect(() => {
        if (!consentUrl) return;
        const controller = new AbortController();
        fetch(consentUrl, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then((response) => {
                if (!response.ok) throw new Error('Persetujuan gagal dimuat');
                return response.json() as Promise<ProcedureConsentProps>;
            })
            .then((data) => { if (!controller.signal.aborted) setConsentDetails(data); })
            .catch(() => { if (!controller.signal.aborted) setConsentLoadFailed(true); });
        return () => controller.abort();
    }, [consentUrl]);

    function openProcedureConsent(url: string, fromSignature = false) {
        setConsentDetails(null);
        setConsentLoadFailed(false);
        setResumeSignature(fromSignature);
        setConsentUrl(url);
    }

    const [signatureDialogOpen, setSignatureDialogOpen] = useState(false);
    const [reviewPageStale, setReviewPageStale] = useState(false);
    const [dentition, setDentition] = useState<'adult' | 'primary'>('adult');
    const [treatmentMode, setTreatmentMode] = useState<'icd9cm' | 'clinic' | 'manual'>('icd9cm');
    const [procedureQuery, setProcedureQuery] = useState('');
    const [procedureResults, setProcedureResults] = useState<ProcedureSuggestion[]>([]);
    const [procedureActiveIndex, setProcedureActiveIndex] = useState(-1);
    const [procedureLoading, setProcedureLoading] = useState(false);
    const [procedureSearchFailed, setProcedureSearchFailed] = useState(false);
    const [selectedProcedure, setSelectedProcedure] = useState<ProcedureSuggestion | null>(null);
    const [medicineFilter, setMedicineFilter] = useState('');
    const [selectedDentalFindingKeys, setSelectedDentalFindingKeys] = useState<string[]>([]);
    const [odontogramPage, setOdontogramPage] = useState(1);
    const odontogramPagination = paginateOdontogram(visit.odontogram, odontogramPage);
    const [undoFinding, setUndoFinding] = useState<DentalFinding | null>(null);
    const [bodyFindingDraft, setBodyFindingDraft] = useState({ ...emptyBodyFinding });
    const [addendumBodyDraft, setAddendumBodyDraft] = useState({ ...emptyBodyFinding });
    const [bodyFindingPending, setBodyFindingPending] = useState(false);
    const [addendumBodyPending, setAddendumBodyPending] = useState(false);
    const physicalSystemLabels = ['Keadaan umum', 'Kesadaran', 'Kepala', 'Mata', 'Telinga, hidung, tenggorokan', 'Leher', 'Thoraks dan paru', 'Jantung', 'Abdomen', 'Ekstremitas', 'Neurologis', 'Kulit', 'Pemeriksaan lain sesuai indikasi'];
    const defaultPhysicalSystems: Record<string, string> = {
        ...Object.fromEntries(physicalSystemLabels.map(key => [key, visit.examination?.physicalSystems?.[key] ?? ''])),
        ...Object.fromEntries(Object.entries(visit.examination?.physicalSystems ?? {}).filter(([key, value]) => !physicalSystemLabels.includes(key) && Boolean(value?.trim()))),
    };
    const previousHistoryValues = useRef(new Map<string, string>());
    const examinationForm = useForm({
        section: 'anamnesis',
        review_of_systems: visit.examinationFormDefaults.reviewOfSystems ?? visit.examination?.reviewOfSystems ?? {} as SystemAnswers,
        clinical_assessment: (isDental
            ? { dental_pain: visit.examination?.clinicalAssessment?.dental_pain ?? {} }
            : { complaint_details: visit.examination?.clinicalAssessment?.complaint_details ?? {} }) as DoctorClinicalAssessment,
        riwayat_sosial: visit.examination?.socialHistory ?? '',
        riwayat_obat: visit.examinationFormDefaults.riwayat_obat ?? visit.examination?.riwayat_obat ?? '',
        riwayat_perawatan_gigi: visit.examinationFormDefaults.riwayat_perawatan_gigi ?? visit.examination?.riwayat_perawatan_gigi ?? '',
        kebiasaan_gigi: visit.examinationFormDefaults.kebiasaan_gigi ?? visit.examination?.kebiasaan_gigi ?? '',
        anamnesis: normalizeClinicalAnswer(visit.examinationFormDefaults.anamnesis) ?? '',
        riwayat_penyakit_sekarang: normalizeClinicalAnswer(visit.examinationFormDefaults.currentHistory) ?? '',
        riwayat_penyakit_dahulu: normalizeClinicalAnswer(visit.examinationFormDefaults.pastHistory) ?? '',
        riwayat_penyakit_keluarga: normalizeClinicalAnswer(visit.examinationFormDefaults.familyHistory) ?? '',
        riwayat_alergi: normalizeClinicalAnswer(visit.examinationFormDefaults.allergyHistory) ?? '',
    });
    const [physicalNurseNotesDismissed, setPhysicalNurseNotesDismissed] = useState(false);
    const [handoverNotesDismissed, setHandoverNotesDismissed] = useState(false);
    const oralObservations = isDental ? Object.entries(visit.screening?.summary ?? {}).filter(([label, value]) =>
        (label.startsWith('Observasi mukosa - ') && Boolean(value) && value !== 'Normal')
        || (['Kelainan mukosa yang ditemukan', 'Observasi mukosa - Catatan tambahan'].includes(label) && Boolean(normalizeClinicalAnswer(value))),
    ) : [];
    const nurseNotes = Object.entries({
        'Catatan / hal yang perlu dikonfirmasi dokter gigi': isDental ? visit.screening?.dentalNotes : null,
        'Catatan penapisan': visit.screening?.summary?.['Catatan penapisan'],
        'Catatan pengkajian': visit.screening?.summary?.['Catatan Pengkajian'],
        'Catatan verifikasi alergi': visit.screening?.summary?.['Catatan verifikasi alergi'],
        'Catatan visual ekstraoral awal perawat': isDental ? visit.screening?.summary?.['Catatan visual ekstraoral awal perawat'] : null,
    }).filter(([, value]) => normalizeClinicalAnswer(value));
    const physicalForm = useForm({
        section: 'pemeriksaan',
        supporting_exams: visit.examination?.supportingExams ?? {} as Record<string, string>,
        ...(isDental ? { clinical_assessment: { mucosa_observation: visit.examination?.clinicalAssessment?.mucosa_observation ?? {} } as DoctorClinicalAssessment } : {}),
        pemeriksaan_fisik: normalizeClinicalAnswer(visit.examination?.physicalExam) ?? '',
        pemeriksaan_fisik_terstruktur: { ...defaultPhysicalSystems },
        body_map: visit.examination?.bodyMap ?? emptyBodyMap(),
        pemeriksaan_ekstraoral: normalizeClinicalAnswer(visit.examinationFormDefaults.pemeriksaan_ekstraoral ?? visit.examination?.extraoralExam) ?? '',
        oral_hygiene_index: visit.examination?.oralHygieneIndex != null ? String(visit.examination.oralHygieneIndex) : '',
        td_sistole_ulang: visit.examination?.repeatVitals?.systolic != null ? String(visit.examination.repeatVitals.systolic) : '',
        td_diastole_ulang: visit.examination?.repeatVitals?.diastolic != null ? String(visit.examination.repeatVitals.diastolic) : '',
        nadi_ulang: visit.examination?.repeatVitals?.pulse != null ? String(visit.examination.repeatVitals.pulse) : '',
        suhu_ulang: visit.examination?.repeatVitals?.temperature != null ? String(visit.examination.repeatVitals.temperature) : '',
    });
    const differentialForm = useForm({ section: 'diagnosis', diagnosis_banding: normalizeClinicalAnswer(visit.examination?.differentialDiagnosis) ?? '' });
    const followUpForm = useForm({
        section: 'rencana',
        kondisi_akhir: visit.examination?.finalCondition ?? '',
        disposisi: visit.examination?.disposition ?? '',
        kontrol_berikutnya: visit.examination?.followUpDate ?? '',
        catatan: normalizeClinicalAnswer(visit.examination?.notes) ?? '',
        edukasi: normalizeClinicalAnswer(visit.examination?.education) ?? '',
        non_farmakologis: normalizeClinicalAnswer(visit.examination?.nonPharmacological) ?? '',
    });
    const primaryDiagnosisForm = useForm({});
    const dentalDiagnosisGroups = groupDentalDiagnoses(visit.odontogram);
    const diagnosisForm = useForm({ tooth_fdi: '', source_finding_code: '', surfaces: [] as string[], is_manual: false, kode_icd10: '', code_system: defaultDiagnosisSystem, code_release: '', nama_diagnosa: '', keterangan: '', jenis: visit.diagnoses.some((diagnosis) => diagnosis.type === 'utama') ? 'tambahan' : 'utama' });
    const treatmentForm = useForm({ execution_role: isDental ? 'dokter' : 'perawat', terminology_id: '', tindakan_id: '', nama_tindakan_manual: '', jumlah: '1', tooth_fdis: [] as string[], catatan: '' });
    const dentalForm = useForm({ tooth_fdi: '', surface: 'W', surfaces: [] as string[], finding_code: '', notes: '', reason: '', late_correction_acknowledged: false, details: {} as DentalDetails });
    const intraoralNarrative = isDental
        ? dentalIntraoralNarrative(visit.odontogram, visit.examination?.odontogramSummary, dentalOptions)
        : '';
    const lastIntraoralNarrative = useRef('');
    const intraoralEdited = useRef(false);
    const odontogramSummaryForm = useForm({ section: 'pemeriksaan', ringkasan_odontogram: normalizeClinicalAnswer(visit.examination?.odontogramSummary) ?? '' });
    const soundForm = useForm({ teeth: [] as string[], confirmed: true, mixed_dentition_verified: false });
    const odontogramUndoForm = useForm({ tooth_fdi: '', surface: '', reason: '', findings: [] as Array<{ tooth_fdi: string; surface: string }>, confirmed: true });
    const prescriptionForm = useForm({ obat_id: '', nama_obat: '', jumlah: '1', satuan: '', aturan_pakai: '', catatan: '', allergy_override_reason: '', jenis: 'jadi', is_resep_luar: false });
    const externalPrescriptionForm = useForm({ obat_id: '', nama_obat: '', jumlah: '1', satuan: '', aturan_pakai: '', catatan: '', allergy_override_reason: '', jenis: 'jadi', is_resep_luar: true });
    const letterForm = useForm({ jenis: 'sakit', surat_template_id: '', tanggal: today, konten: '', details: { ...emptyLetterDetails } });
    const referralForm = useForm({ ke_poli_id: '', surat_template_id: '', catatan: '', konten_surat: '' });
    const signatureForm = useForm({ signature_password: '', review_nursing_assessment: false, nursing_clarification: '', dental_scope: '', dental_scope_reason: '', dental_focus: '', dental_follow_up_plan: '', dental_prior_chart: '', dental_escalation_decision: '', dental_escalation_note: '', coding_downtime_acknowledged: false, coding_downtime_reason: '', screening_revision: undefined as string | undefined, review_dental_assessment: false, review_final_plan: false, review_performed_procedures: false, review_admin_assistance: false, assistance_review_id: assistanceReview?.auditId ?? null, assistance_review_hash: assistanceReview?.draftHash ?? null });
    const addendumDefaults = {
        supporting_exams: visit.examination?.supportingExams ?? {} as Record<string, string>,
        review_of_systems: visit.examination?.reviewOfSystems ?? {} as SystemAnswers,
        clinical_assessment: (visit.examination?.clinicalAssessment ?? {}) as DoctorClinicalAssessment,
        riwayat_sosial: visit.examination?.socialHistory ?? '',
        kondisi_akhir: visit.examination?.finalCondition ?? '',
        disposisi: visit.examination?.disposition ?? '',
        reason: '',
        late_correction_acknowledged: false,
        riwayat_obat: visit.examination?.riwayat_obat ?? '',
        riwayat_perawatan_gigi: visit.examination?.riwayat_perawatan_gigi ?? '',
        kebiasaan_gigi: visit.examination?.kebiasaan_gigi ?? '',
        anamnesis: normalizeClinicalAnswer(visit.examination?.anamnesis) ?? '',
        riwayat_penyakit_sekarang: normalizeClinicalAnswer(visit.examination?.currentHistory) ?? '',
        riwayat_penyakit_dahulu: normalizeClinicalAnswer(visit.examination?.pastHistory) ?? '',
        riwayat_penyakit_keluarga: normalizeClinicalAnswer(visit.examination?.familyHistory) ?? '',
        riwayat_alergi: normalizeClinicalAnswer(visit.examination?.allergyHistory) ?? '',
        pemeriksaan_fisik: normalizeClinicalAnswer(visit.examination?.physicalExam) ?? '',
        ringkasan_odontogram: normalizeClinicalAnswer(visit.examination?.odontogramSummary) ?? '',
        pemeriksaan_fisik_terstruktur: { ...defaultPhysicalSystems },
        body_map: visit.examination?.bodyMap ?? emptyBodyMap(),
        pemeriksaan_ekstraoral: normalizeClinicalAnswer(visit.examination?.extraoralExam) ?? '',
        diagnosis_banding: normalizeClinicalAnswer(visit.examination?.differentialDiagnosis) ?? '',
        catatan: normalizeClinicalAnswer(visit.examination?.notes) ?? '',
        edukasi: normalizeClinicalAnswer(visit.examination?.education) ?? '',
        non_farmakologis: normalizeClinicalAnswer(visit.examination?.nonPharmacological) ?? '',
        kontrol_berikutnya: visit.examination?.followUpDate ?? '',
    };
    const addendumForm = useForm(addendumDefaults);

    useEffect(() => {
        const query = icdQuery.trim();
        setIcdResults([]);
        setIcdSearchFailed(false);
        if (query.length < 2) {
            setIcdLoading(false);
            return;
        }

        const controller = new AbortController();
        setIcdLoading(true);
        const timeout = window.setTimeout(() => {
            fetch(`/pelayanan/icd10/search?q=${encodeURIComponent(query.slice(0, 100))}&code_system=${encodeURIComponent(diagnosisCodeSystem)}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
                .then((response) => response.ok ? response.json() as Promise<IcdSuggestion[]> : Promise.reject(new Error('Pencarian ICD-10 gagal.')))
                .then((results) => { if (!controller.signal.aborted) setIcdResults(results); })
                .catch(() => { if (!controller.signal.aborted) { setIcdResults([]); setIcdSearchFailed(true); } })
                .finally(() => { if (!controller.signal.aborted) setIcdLoading(false); });
        }, 250);

        return () => { controller.abort(); window.clearTimeout(timeout); };
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
        const apply = () => {
            setTreatmentMode(mode);
            setSelectedProcedure(null);
            setProcedureQuery('');
            setProcedureResults([]);
            treatmentForm.clearErrors();
            treatmentForm.setData((data) => ({ ...data, terminology_id: '', tindakan_id: '', nama_tindakan_manual: '' }));
        };
        if (treatmentForm.data.terminology_id || treatmentForm.data.tindakan_id || treatmentForm.data.nama_tindakan_manual.trim()) confirmAction('Ganti cara pencatatan dan kosongkan pilihan atau nama tindakan yang belum ditambahkan?', apply, 'Ganti cara pencatatan'); else apply();
    }

    function navigateSection(section: Tab | 'rencana', fieldId?: string): void {
        if (section === 'rencana') {
            if (activeTab === 'soap') {
                document.getElementById('soap-plan')?.focus();
                return;
            }
            const details = document.getElementById('rencana-details') as HTMLDetailsElement | null;
            if (details) details.open = true;
            document.getElementById('rencana-tindak-lanjut')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            document.getElementById('kontrol_berikutnya')?.focus({ preventScroll: true });
            return;
        }
        setActiveTab(section);
        window.requestAnimationFrame(() => {
            const field = fieldId && document.getElementById(fieldId);
            if (field) focusFormControl(field);
            else {
                const workspace = document.getElementById('pemeriksaan-sections');
                workspace?.querySelector<HTMLElement>('[role="tabpanel"]:not([hidden])')?.focus({ preventScroll: true });
                workspace?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    }

    function confirmSave(label: string, save: () => void, critical = false): void {
        if (!critical) {
            save();
            return;
        }
        confirmAction('Simpan data ini? Setelah tersimpan, Anda tetap berada di bagian ini.', save, label, label);
    }

    function submitClinicalSection(event: FormEvent<HTMLFormElement>, section: 'anamnesis' | 'pemeriksaan' | 'diagnosis' | 'rencana'): void {
        event.preventDefault();
        if (section === 'pemeriksaan' && bodyFindingPending) {
            toast.error('Tambahkan temuan lokasi ke pemeriksaan atau kosongkan isiannya sebelum menyimpan.');
            return;
        }
        const sections = {
            anamnesis: { form: examinationForm, label: 'Simpan anamnesis', saved: () => examinationForm.setDefaults(examinationForm.data) },
            pemeriksaan: { form: physicalForm, label: 'Simpan pemeriksaan fisik', saved: () => physicalForm.setDefaults(physicalForm.data) },
            diagnosis: { form: differentialForm, label: 'Simpan diagnosis banding', saved: () => differentialForm.setDefaults(differentialForm.data) },
            rencana: { form: followUpForm, label: 'Simpan rencana tindak lanjut', saved: () => followUpForm.setDefaults(followUpForm.data) },
        };
        const formElement = event.currentTarget;
        const selected = sections[section];
        if (!sectionSaveAllowed[section]) { toast.error('Belum ada perubahan yang dapat disimpan atau proses penyimpanan masih berjalan.'); return; }

        confirmSave(selected.label, () => selected.form.post(`/pelayanan/pemeriksaan/${visit.id}`, {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) => focusExaminationErrors(formElement, errors),
            onSuccess: selected.saved,
        }));
    }

    function submitForm(event: FormEvent<HTMLFormElement> | null, form: typeof diagnosisForm | typeof treatmentForm | typeof prescriptionForm | typeof externalPrescriptionForm | typeof letterForm | typeof referralForm, url: string, afterSave?: () => void): void {
        event?.preventDefault();
        if (!canSubmitEntry(form)) return;
        const label = form === diagnosisForm ? 'Tambah diagnosis'
            : form === treatmentForm ? 'Tambah tindakan'
                : form === prescriptionForm ? 'Tambah obat resep klinik'
                    : form === externalPrescriptionForm ? 'Simpan resep luar'
                        : form === letterForm ? 'Simpan surat medis' : 'Simpan rujukan internal';

        const formElement = event ? event.currentTarget : document.querySelector<HTMLElement>('#pemeriksaan-sections [role="tabpanel"]:not([hidden]) form[data-entry-form]');
        const save = () => form.post(url, { preserveScroll: true, preserveState: true, onError: (errors) => {
            if (formElement) focusExaminationErrors(formElement, errors);
            else toast.error(Object.values(errors)[0] ?? 'Periksa kembali isian.');
        }, onSuccess: () => {
            if (form === diagnosisForm) {
                const data = { tooth_fdi: '', source_finding_code: '', surfaces: [] as string[], is_manual: diagnosisForm.data.is_manual, kode_icd10: '', code_system: diagnosisForm.data.is_manual ? 'manual' : diagnosisCodeSystem, code_release: '', nama_diagnosa: '', keterangan: '', jenis: 'tambahan' };
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
            afterSave?.();
        } });
        const isCriticalDocument = form === letterForm || form === referralForm;
        if (afterSave) save();
        else confirmSave(label, save, isCriticalDocument);
    }

    function canSubmitEntry(form: typeof diagnosisForm | typeof treatmentForm | typeof prescriptionForm | typeof externalPrescriptionForm | typeof letterForm | typeof referralForm): boolean {
        if (!(canEdit || (form === letterForm && canCorrect)) || saving || form.processing || !form.isDirty) return false;
        if (form === diagnosisForm) return Boolean(normalizeClinicalAnswer(diagnosisForm.data.nama_diagnosa) && (diagnosisForm.data.is_manual || diagnosisForm.data.kode_icd10.trim()));
        if (form === treatmentForm) return Number.isInteger(Number(treatmentForm.data.jumlah)) && Number(treatmentForm.data.jumlah) > 0 && Boolean(
            treatmentMode === 'icd9cm' ? treatmentForm.data.terminology_id
                : treatmentMode === 'clinic' ? treatmentForm.data.tindakan_id
                    : normalizeClinicalAnswer(treatmentForm.data.nama_tindakan_manual) && treatmentForm.data.nama_tindakan_manual.trim().length >= 3,
        );
        if (form === prescriptionForm) return prescriptionEditable && Boolean(prescriptionForm.data.obat_id) && Number.isInteger(Number(prescriptionForm.data.jumlah)) && Number(prescriptionForm.data.jumlah) > 0;
        if (form === externalPrescriptionForm) return prescriptionEditable && Boolean(normalizeClinicalAnswer(externalPrescriptionForm.data.nama_obat)) && Number(externalPrescriptionForm.data.jumlah) >= 0.01;
        if (form === letterForm) return Boolean(letterForm.data.tanggal);
        return Boolean(referralForm.data.ke_poli_id && referralForm.data.ke_poli_id !== String(visit.clinicId));
    }

    function submitAddendum(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        if (!canSaveAddendum) return;
        confirmSave('Simpan addendum', () => addendumForm.post(`/pelayanan/pemeriksaan/${visit.id}/addendum`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                const saved = { ...addendumForm.data, reason: '' };
                addendumForm.setData(saved);
                addendumForm.setDefaults(saved);
            },
        }), true);
    }

    const { endodontic, pulpal } = dentalForm.data.details;
    const pulpalConflictsWithPsa = ['treated', 'in_progress'].includes(endodontic)
        && Boolean(pulpal) && !['previously_treated', 'uncertain'].includes(pulpal);

    const dentalAssessmentSummary = (() => {
        const data = dentalForm.data;
        if (!data.tooth_fdi || !data.finding_code) return '';
        const details = data.details;
        const label = (key: string) => dentalOptions.detailOptions?.[key]?.[details[key] ?? ''];
        const surfaces = (data.surfaces.length ? data.surfaces : [data.surface])
            .map(surface => dentalOptions.surfaces[surface]).filter(Boolean).join('–');
        const findings = [
            data.finding_code === 'caries' ? 'karies' : dentalOptions.findings[data.finding_code],
            ...(['activity', 'site', 'depth', 'root_severity', 'pulpal', 'periapical', 'eruption', 'position', 'morphology', 'endodontic', 'prosthesis', 'material', 'restoration_state'] as const)
                .map(label).filter(Boolean),
        ];
        const tests = [
            ['vitality', 'Tes termal'], ['percussion', 'perkusi'], ['palpation', 'palpasi'], ['mobility', 'mobilitas'],
        ].flatMap(([key, name]) => details[key] && details[key] !== 'unknown'
            ? [`${name} ${key === 'mobility' ? details[key] : details[key] === 'positive' ? '(+)' : '(-)'}`] : []);
        return `Pemeriksaan klinis gigi ${data.tooth_fdi} menunjukkan ${findings.join(', ')}${surfaces ? ` pada permukaan ${surfaces}` : ''}${details.icdas && details.icdas !== 'na' ? ` (ICDAS ${details.icdas})` : ''}. ${tests.length ? `${tests.join(', ')}.` : ''}`.trim();
    })();
    const savedAssessment = dentalForm.data.details.assessment ?? '';
    const assessmentManual = savedAssessment.split('\n').filter(line => !line.startsWith('Pemeriksaan klinis gigi ') && !line.startsWith('Tes klinis: ')).join('\n').trimStart();
    const assessmentLead = dentalAssessmentSummary ? `${dentalAssessmentSummary}\n` : '';

    function focusExaminationErrors(form: HTMLElement, errors: Record<string, string>): void {
        requestAnimationFrame(() => requestAnimationFrame(() => {
            if (!focusFirstInvalidField(form)) {
                toast.error(Object.values(errors)[0] ?? 'Periksa kembali isian pemeriksaan.');
                form.tabIndex = -1;
                focusFormControl(form);
            }
        }));
    }

    function focusDentalErrors(errors: Record<string, string>): void {
        const panel = document.getElementById('dental-action-panel');
        if (panel) focusExaminationErrors(panel, errors);
    }

    function submitDentalFinding(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        if (!canSaveDentalFinding) return;
        const details = {
            ...(dentalFindingUsesNaturalTooth ? dentalForm.data.details : dentalFindingDetails(dentalForm.data.finding_code, dentalForm.data.details)),
            assessment: [dentalAssessmentSummary, assessmentManual].filter(Boolean).join('\n'),
        };
        dentalForm.transform((data) => ({ ...data, details, surfaces: data.surfaces.length ? data.surfaces : [data.surface] }));
        confirmSave(canEdit ? 'Simpan temuan odontogram' : 'Simpan addendum odontogram', () => dentalForm.post(`/pelayanan/pemeriksaan/${visit.id}/odontogram${canEdit ? '' : '/addendum'}`, {
            preserveScroll: true,
            preserveState: true,
            onError: focusDentalErrors,
            onSuccess: () => {
                const saved = { ...dentalForm.data, details, reason: '', late_correction_acknowledged: false };
                dentalForm.setData(saved);
                dentalForm.setDefaults(saved);
            },
        }), !canEdit);
    }

    function selectDentalRecord(tooth: string, surface = dentalForm.data.surface): void {
        const applySelection = () => {
            const existing = visit.odontogram.find((finding) => finding.toothFdi === tooth && finding.surface === surface);
            const data = { tooth_fdi: tooth, surface: tooth ? surface : 'W', surfaces: tooth ? [surface] : [], finding_code: existing?.findingCode ?? '', notes: existing?.notes ?? '', reason: '', late_correction_acknowledged: false, details: existing?.details ?? {} as DentalDetails };
            dentalForm.setData(data);
            dentalForm.setDefaults(data);
            dentalForm.clearErrors();
            if (tooth) setDentition(Number(tooth[0]) > 4 ? 'primary' : 'adult');
            requestAnimationFrame(() => { const field = document.getElementById('dental-tooth'); if (field) focusFormControl(field); });
        };
        if (dentalForm.isDirty) {
            confirmAction('Temuan yang sedang diisi belum disimpan. Ganti pilihan dan buang perubahan ini?', applySelection, 'Ganti pilihan');
            return;
        }
        applySelection();
    }

    function toggleDentalSurface(surface: string): void {
        dentalForm.setData((data) => {
            const current = data.surfaces.length ? data.surfaces : [data.surface];
            const surfaces = surface === 'W' ? ['W'] : current.includes(surface) ? current.filter((value) => value !== surface && value !== 'W') : [...current.filter((value) => value !== 'W'), surface];
            return { ...data, surfaces, surface: surfaces[0] ?? '' };
        });
    }

    function selectDentalTooth(tooth: string): void {
        selectDentalRecord(tooth === dentalForm.data.tooth_fdi ? '' : tooth);
    }

    function changeDentition(next: 'adult' | 'primary'): void {
        const apply = () => {
            const data = { tooth_fdi: '', surface: 'W', surfaces: [] as string[], finding_code: '', notes: '', reason: '', late_correction_acknowledged: false, details: {} as DentalDetails };
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
        if ((!undoFinding && odontogramUndoForm.data.findings.length === 0) || saving || (!canEdit && !canCorrect) || (odontogramUndoForm.data.reason.trim().length < 10)) return;

        odontogramUndoForm.transform((data) => data.findings.length > 0 ? { findings: data.findings, confirmed: data.confirmed, reason: data.reason } : { tooth_fdi: data.tooth_fdi, surface: data.surface, reason: data.reason });
        odontogramUndoForm.delete(`/pelayanan/pemeriksaan/${visit.id}/odontogram`, {
            preserveScroll: true,
            onSuccess: () => {
                if (odontogramUndoForm.data.findings.length > 0) setOdontogramPage(1);
                const removedKeys = odontogramUndoForm.data.findings.length > 0 ? odontogramUndoForm.data.findings.map((finding) => `${finding.tooth_fdi}-${finding.surface}`) : [`${undoFinding?.toothFdi}-${undoFinding?.surface}`];
                const nextKeys = selectedDentalFindingKeys.filter((key) => !removedKeys.includes(key));
                setSelectedDentalFindingKeys(nextKeys);
                treatmentForm.setData('tooth_fdis', selectedOdontogramTeeth(visit.odontogram, nextKeys));
                if (removedKeys.includes(`${dentalForm.data.tooth_fdi}-${dentalForm.data.surface}`)) {
                    const cleared = { tooth_fdi: '', surface: 'W', surfaces: [] as string[], finding_code: '', notes: '', reason: '', late_correction_acknowledged: false, details: {} as DentalDetails };
                    dentalForm.setData(cleared);
                    dentalForm.setDefaults(cleared);
                }
                setUndoFinding(null);
                odontogramUndoForm.reset();
            },
        });
    }

    function renderTabNavigation(tab: Tab, nextTab?: Tab, nextLabel?: string) {
        const mainTabs = tabs.filter((item) => !item.optional);
        const tabIndex = mainTabs.findIndex((item) => item.id === tab);
        const previousTab = tabIndex > 0 ? mainTabs[tabIndex - 1].id : tabIndex === -1 ? 'resep' : null;
        const showFinish = ['tindakan', 'resep', 'resep-luar', 'surat', 'rujukan'].includes(tab);

        if (!previousTab && !nextTab && !showFinish) return null;

        return <div className="flex flex-col justify-between gap-3 border-t border-neutral-200 pt-4 sm:flex-row sm:items-center" aria-label="Navigasi pemeriksaan">
            {previousTab ? <Button onClick={() => navigateSection(previousTab)} size="sm" type="button" variant="secondary"><ArrowLeft className="size-4" />Kembali ke {tabLabels[previousTab]}</Button> : <span />}
            <div className="flex flex-wrap justify-end gap-2 sm:ml-auto">
                {showFinish && <Button disabled={!canEdit || saving || isAdminAssistance} onClick={requestExaminationFinish} size="sm" type="button" variant={nextTab ? 'secondary' : 'default'}><Check className="size-4" />Selesai periksa</Button>}
                {nextTab && <Button disabled={saving} onClick={() => advanceSection(tab, nextTab)} size="sm" type="button">{nextLabel ?? `Lanjut ke ${tabLabels[nextTab]}`}<ArrowLeft className="size-4 rotate-180" /></Button>}
            </div>
        </div>;
    }

    function renderTabTrigger({ id, icon: Icon, label }: (typeof tabs)[number]) {
        return <TabsTrigger className="min-h-12 px-3 sm:px-4" key={id} value={id}>
            <Icon aria-hidden="true" className="size-4" />{label}
            {id === 'diagnosa' && <Badge>{visit.diagnoses.length}</Badge>}
            {id === 'tindakan' && <Badge>{visit.treatments.length}</Badge>}
            {(id === 'resep' || id === 'resep-luar') && <Badge>{visit.prescription.items.filter((item) => item.external === (id === 'resep-luar')).length}</Badge>}
        </TabsTrigger>;
    }

    function searchDiagnosisFromFinding(term: string, source: string): void {
        if (!canEdit || saving) return;
        const apply = () => {
            diagnosisForm.setData(data => ({
                ...data,
                is_manual: false,
                code_system: diagnosisCodeSystem,
                code_release: '',
                kode_icd10: '',
                nama_diagnosa: '',
                keterangan: source.slice(0, 2000),
            }));
            if (source.length > 2000) toast.info('Keterangan diagnosis dibatasi 2.000 karakter. Detail lengkap tersedia di pemeriksaan fisik.');
            diagnosisForm.clearErrors();
            setIcdQuery(term);
            navigateSection('diagnosa', 'icd-search');
        };
        if (hasDiagnosisDraft) confirmAction('Ganti kode, nama, dan keterangan pada draf diagnosis dengan temuan ini?', apply, 'Gunakan temuan');
        else apply();
    }

    function chooseDiagnosis(code: string, name: string, codeSystem = diagnosisCodeSystem, release = '') {
        if (!isDental && hasDiagnosisDraft) {
            confirmAction('Ganti kode dan nama diagnosis pada isian ini?', applyDiagnosisCode, 'Gunakan kode');
            return;
        }
        applyDiagnosisCode();

        function applyDiagnosisCode() {
            diagnosisForm.setData((data) => ({ ...data, kode_icd10: code, code_system: codeSystem, code_release: release, nama_diagnosa: name }));
            setIcdQuery('');
            setIcdResults([]);
        }
    }

    function prepareOralDiagnosis(): void {
        if (!canEdit || saving || dentalForm.isDirty) return;
        const apply = () => {
            const note = [
                physicalForm.data.pemeriksaan_fisik.trim() ? `Intraoral: ${physicalForm.data.pemeriksaan_fisik.trim()}` : '',
                physicalForm.data.pemeriksaan_ekstraoral.trim() ? `Ekstraoral: ${physicalForm.data.pemeriksaan_ekstraoral.trim()}` : '',
            ].filter(Boolean).join('\n').slice(0, 2000);
            diagnosisForm.setData({ tooth_fdi: '', source_finding_code: '', surfaces: [], is_manual: false, kode_icd10: '', nama_diagnosa: '', code_system: diagnosisCodeSystem, code_release: '', jenis: visit.diagnoses.some(diagnosis => diagnosis.type === 'utama') ? 'tambahan' : 'utama', keterangan: note });
            diagnosisForm.clearErrors(); setIcdQuery(''); setIcdResults([]); navigateSection('diagnosa');
        };
        const proceed = () => saveClinicalDraft(false, ['pemeriksaan', 'ringkasan-odontogram'], apply);
        if (hasDiagnosisDraft) confirmAction('Ganti draf diagnosis dengan konteks pemeriksaan mulut?', proceed, 'Gunakan pemeriksaan'); else proceed();
    }

    function startSuggestedProcedureSearch(query: string): void {
        if (!canEdit || saving || !procedureCatalogAvailable) return;
        const apply = () => {
            setTreatmentMode('icd9cm'); setSelectedProcedure(null); setProcedureQuery(query); setProcedureResults([]);
            treatmentForm.setData(data => ({ ...data, terminology_id: '', tindakan_id: '', nama_tindakan_manual: '' }));
            treatmentForm.clearErrors();
        };
        if (treatmentForm.data.terminology_id || treatmentForm.data.tindakan_id || treatmentForm.data.nama_tindakan_manual.trim()) confirmAction('Ganti pilihan tindakan yang belum disimpan dan cari prosedur lain? Lokasi gigi, jumlah, dan catatan tetap tersedia.', apply, 'Cari prosedur lain'); else apply();
    }

    function useGeneralDiagnosisForTreatment(diagnosis: Diagnosis): void {
        if (isDental || !canEdit || saving) return;
        const diagnosisLine = `Diagnosis terkait: ${diagnosis.name}${diagnosis.code ? ` (${diagnosis.code})` : ''}`;
        const context = [diagnosisLine, diagnosis.note?.trim()].filter(Boolean).join('\n');
        const previous = treatmentForm.data.catatan.trimEnd();
        if (previous === context || previous.startsWith(context + '\n\n')) {
            navigateSection('tindakan', 'catatan-tindakan');
            return;
        }

        const available = 2000 - previous.length - (previous ? 2 : 0);
        const imported = available >= diagnosisLine.length ? context.slice(0, available).trimEnd() : '';
        const apply = () => {
            if (imported) treatmentForm.setData('catatan', [imported, previous].filter(Boolean).join('\n\n'));
            if (imported !== context) toast.info('Ruang catatan terbatas. Tinjau konteks diagnosis sebelum menyimpan tindakan.');
            navigateSection('tindakan', 'catatan-tindakan');
        };
        if (previous) confirmAction('Tambahkan konteks diagnosis ke catatan tindakan yang sedang diisi?', apply, 'Gunakan diagnosis');
        else apply();
    }

    function useDentalDiagnosisForTreatment(diagnosis: Diagnosis): void {
        if (!canEdit || saving || !diagnosis.toothFdi) return;
        if (!diagnosisMatchesFindings(diagnosis, visit.odontogram)) { toast.error('Temuan asal sudah berubah. Tinjau pemeriksaan dan diagnosis sebelum menyiapkan tindakan.'); navigateSection(physicalTab); return; }
        const apply = () => {
            treatmentForm.setData('tooth_fdis', [diagnosis.toothFdi!]);
            setSelectedDentalFindingKeys(visit.odontogram.filter(finding => finding.toothFdi === diagnosis.toothFdi).map(finding => `${finding.toothFdi}-${finding.surface}`));
            navigateSection('tindakan');
        };
        if (treatmentForm.isDirty) confirmAction(`Ganti lokasi tindakan yang sedang diisi menjadi gigi ${diagnosis.toothFdi}? Jenis tindakan dan catatan tetap tersimpan.`, apply, 'Ganti lokasi tindakan'); else apply();
    }

    function importDentalDiagnosis(group: DentalDiagnosisGroup, candidate: DentalDiagnosisCandidate): void {
        if (!canEdit || saving || isDentalDiagnosisRecorded(group, candidate, visit.diagnoses)) return;
        if (physicalChanged || dentalForm.isDirty) {
            toast.error('Simpan pemeriksaan dan temuan gigi sebelum mengimpornya ke diagnosis.');
            return;
        }
        const apply = () => {
            const records = candidateFindings(group, candidate);
            const surfaces = candidate.findingCode && !wholeToothFindingCodes.includes(candidate.findingCode) ? [...new Set(records.map(finding => finding.surface))] : ['W'];
            const catalog = (dentalOptions.diagnosisCatalog ?? []).filter(term => term.codeSystem === 'icd10_who');
            const terms = candidate.exact ? catalog.filter(term => term.code === candidate.code) : [];
            const term = terms.find(term => term.codeSystem === diagnosisCodeSystem) ?? terms[0];
            const system = term?.codeSystem ?? (catalog.some(entry => entry.codeSystem === diagnosisCodeSystem) ? diagnosisCodeSystem : catalog[0]?.codeSystem ?? diagnosisCodeSystem);
            const note = [...new Set(records.map(finding => finding.detailSummary || finding.notes).filter(Boolean))].join('; ');
            diagnosisForm.setData({ tooth_fdi: group.toothFdi, source_finding_code: candidate.findingCode ?? (candidate.label.includes('apikal') ? 'periapical' : 'pulpal'), surfaces, is_manual: false, code_system: system, code_release: term?.release ?? '', kode_icd10: term?.code ?? '', nama_diagnosa: term?.name ?? '', jenis: visit.diagnoses.some(diagnosis => diagnosis.type === 'utama') ? 'tambahan' : 'utama', keterangan: `Gigi ${group.toothFdi} (${dentalSurfaceLabel(surfaces)})${note ? ` — ${note}` : ''}` });
            diagnosisForm.clearErrors();
            setDiagnosisCodeSystem(system);
            setIcdResults([]);
            setIcdQuery(term ? '' : candidate.code);
            navigateSection('diagnosa');
            if (!term) toast.info('Pilih kode yang sesuai dengan hasil pemeriksaan. Usulan ini belum menjadi diagnosis.');
        };
        if (hasDiagnosisDraft) confirmAction('Ganti isian diagnosis yang belum disimpan dengan temuan gigi ini?', apply, 'Gunakan temuan'); else apply();
    }

    function deleteItem(url: string, confirmation: string) {
        if (!canEdit || saving) return;
        confirmReason(confirmation + ' Catatan asli tetap tersimpan.', (reason) => { deleteForm.transform(() => ({ reason })); deleteForm.delete(url, { preserveScroll: true }); }, 'Batalkan entri');
    }

    function finishExamination() {
        if (unsavedSections.length > 0 || saving) {
            toast.error('Simpan atau kosongkan isian yang belum disimpan sebelum menyelesaikan pemeriksaan.');
            if (unsavedSections[0]) navigateSection(unsavedSections[0].tab, unsavedSections[0].fieldId);
            return;
        }
        const missing = readiness.find((item) => !item.complete && !item.resolveInSignature);
        if (missing) {
            navigateSection(missing.tab, missing.fieldId);
            if (missing.tab === 'odontogram' && incompleteDentalFindings[0]) {
                const finding = incompleteDentalFindings[0];
                selectDentalRecord(finding.toothFdi, finding.surface);
                toast.error(`Gigi ${finding.toothFdi}: lengkapi ${cariesDetailsByFinding.get(finding)?.join(', ')}.`);
                return;
            }
            toast.error(`Lengkapi ${missing.label.toLocaleLowerCase()} sebelum menyelesaikan pemeriksaan.`);
            return;
        }
        setSignatureDialogOpen(true);
    }

    function signExamination(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!canEdit || reviewPageStale || signatureForm.processing) return;
        if (unsavedSections.length > 0 || saving || readiness.some((item) => !item.complete && !item.resolveInSignature)) {
            if (isDental && visit.odontogram.length === 0 && !signatureForm.data.dental_scope) {
                signatureForm.setError('dental_scope', 'Catat odontogram atau pilih cakupan terbatas sesuai pemeriksaan.');
                return;
            }
            setSignatureDialogOpen(false);
            finishExamination();
            return;
        }
        signatureForm.transform((data) => ({ ...data, assistance_review_id: assistanceReview?.auditId ?? null, assistance_review_hash: assistanceReview?.draftHash ?? null, screening_revision: visit.screeningRevision ?? undefined }));
        signatureForm.post(`/pelayanan/pemeriksaan/${visit.id}/selesai`, {
            preserveScroll: true,
            onError: (errors) => {
                toast.error(Object.values(errors)[0] ?? 'Pemeriksaan belum dapat diselesaikan. Periksa isian wajib.');
                if (errors.assistance_review_id || errors.assistance_review_hash || errors.screening_revision) {
                    setReviewPageStale(true);
                    signatureForm.setData((data) => ({ ...data, review_admin_assistance: false, review_dental_assessment: false }));
                }
            },
            onSuccess: () => {
                setSignatureDialogOpen(false);
                signatureForm.reset();
            },
        });
    }

    const state = examinationState(visit);
    const canStart = state.canStart && (!isAdminAssistance || assistance?.active === true);
    const canEdit = state.canEdit && (!isAdminAssistance || assistance?.active === true);
    const { setData: setPhysicalData } = physicalForm;
    const intraoralValue = physicalForm.data.pemeriksaan_fisik;
    useEffect(() => {
        if (!isDental || !canEdit || intraoralEdited.current) return;

        const next = syncIntraoralNarrative(intraoralValue, lastIntraoralNarrative.current, intraoralNarrative);
        lastIntraoralNarrative.current = intraoralNarrative;
        if (next !== intraoralValue) setPhysicalData('pemeriksaan_fisik', next);
    }, [canEdit, intraoralNarrative, intraoralValue, isDental, setPhysicalData]);
    const canCorrect = state.canCorrect && !isAdminAssistance;
    const examinationNotice = state.notice;
    const medicalRecordUrl = visit.recordUrl ?? (!visit.canClaim ? `/pelayanan/pasien/${visit.patient.id}/rekam-medis` : null);
    const startForm = useForm({});
    const deleteForm = useForm({});
    const prescriptionEditable = canEdit && (!visit.prescription.status || visit.prescription.status === 'menunggu');
    const saving = [examinationForm, physicalForm, differentialForm, followUpForm, diagnosisForm, treatmentForm, dentalForm, prescriptionForm, externalPrescriptionForm, letterForm, referralForm, odontogramUndoForm, signatureForm, addendumForm, startForm, deleteForm, primaryDiagnosisForm, odontogramSummaryForm, soundForm, procedureStartForm].some((form) => form.processing);
    const savedAnamnesis = {
        riwayat_obat: visit.examination?.riwayat_obat ?? '',
        riwayat_perawatan_gigi: visit.examination?.riwayat_perawatan_gigi ?? '',
        kebiasaan_gigi: visit.examination?.kebiasaan_gigi ?? '',
        anamnesis: normalizeClinicalAnswer(visit.examination?.anamnesis) ?? '',
        riwayat_penyakit_sekarang: normalizeClinicalAnswer(visit.examination?.currentHistory) ?? '',
        riwayat_penyakit_dahulu: normalizeClinicalAnswer(visit.examination?.pastHistory) ?? '',
        riwayat_penyakit_keluarga: normalizeClinicalAnswer(visit.examination?.familyHistory) ?? '',
        riwayat_alergi: normalizeClinicalAnswer(visit.examination?.allergyHistory) ?? '',
    };
    const anamnesisChanged = examinationForm.isDirty || Object.entries(savedAnamnesis).some(([key, value]) => (normalizeClinicalAnswer(examinationForm.data[key as keyof typeof savedAnamnesis]) ?? '') !== value);
    const physicalChanged = physicalForm.isDirty
        || (normalizeClinicalAnswer(physicalForm.data.pemeriksaan_ekstraoral) ?? '') !== (normalizeClinicalAnswer(visit.examination?.extraoralExam) ?? '');
    const sectionSaveAllowed = {
        anamnesis: canEdit && !saving && anamnesisChanged,
        pemeriksaan: canEdit && !saving && physicalChanged && !bodyFindingPending,
        diagnosis: canEdit && !saving && differentialForm.isDirty,
        rencana: canEdit && !saving && followUpForm.isDirty,
    };
    const [draftSaveStatus, setDraftSaveStatus] = useState('');
    const [finishAfterDraft, setFinishAfterDraft] = useState(false);
    const draftSaveBusy = useRef(false);
    const lastDraftAttempt = useRef<Record<string, string>>({});
    const draftTasks = [
        { key: 'anamnesis', form: examinationForm, eligible: examinationForm.isDirty || anamnesisChanged },
        { key: 'pemeriksaan', form: physicalForm, eligible: physicalChanged && !bodyFindingPending },
        { key: 'ringkasan-odontogram', form: odontogramSummaryForm, eligible: isDental && odontogramSummaryForm.isDirty },
        { key: 'diagnosis', form: differentialForm, eligible: differentialForm.isDirty },
        { key: 'rencana', form: followUpForm, eligible: followUpForm.isDirty },
    ];
    function advanceSection(tab: Tab, nextTab: Tab): void {
        const proceed = () => navigateSection(nextTab);
        if (!canEdit) { proceed(); return; }
        if (((tab === physicalTab || tab === 'soap') && bodyFindingPending) || (tab === 'odontogram' && dentalForm.isDirty)) {
            toast.error('Simpan atau kosongkan temuan yang masih terbuka sebelum melanjutkan.');
            return;
        }
        const entry = tab === 'diagnosa' && hasDiagnosisDraft ? diagnosisForm
            : tab === 'tindakan' && unsavedSections.some((section) => section.tab === 'tindakan') ? treatmentForm : null;
        if (entry && !canSubmitEntry(entry)) {
            toast.error(tab === 'diagnosa' ? 'Lengkapi diagnosis yang sedang diisi sebelum melanjutkan.' : 'Lengkapi tindakan yang sedang diisi sebelum melanjutkan.');
            return;
        }
        const saveEntry = () => {
            if (!entry) { proceed(); return; }
            submitForm(null, entry, `/pelayanan/pemeriksaan/${visit.id}/${tab}`, proceed);
        };
        const keys = tab === 'soap' ? undefined : tab === 'anamnesis' ? ['anamnesis'] : tab === physicalTab
            ? ['pemeriksaan', 'ringkasan-odontogram'] : tab === 'diagnosa' ? ['diagnosis'] : [];
        if (tab === 'anamnesis' && !normalizeClinicalAnswer(examinationForm.data.anamnesis)) {
            navigateSection(tab, 'anamnesis');
            toast.error('Catat keluhan utama sebelum melanjutkan.');
            return;
        }
        saveClinicalDraft(false, keys, saveEntry);
    }

    function requestExaminationFinish(): void {
        const pendingEntry = bodyFindingPending || dentalForm.isDirty || hasDiagnosisDraft
            || treatmentForm.isDirty || prescriptionForm.isDirty || externalPrescriptionForm.isDirty
            || letterForm.isDirty || referralForm.isDirty;
        if (!pendingEntry && draftTasks.some((task) => task.eligible)) {
            saveClinicalDraft(false, undefined, () => setFinishAfterDraft(true));
            return;
        }
        finishExamination();
    }

    function saveClinicalDraft(automatic = false, keys?: string[], afterSave?: () => void): void {
        if (!canEdit || saving || draftSaveBusy.current) return;
        const tasks = draftTasks.filter(task => (!keys || keys.includes(task.key)) && task.eligible && (!automatic || task.form.isDirty) && (!automatic || lastDraftAttempt.current[task.key] !== JSON.stringify(task.form.data)));
        if (!tasks.length) { afterSave?.(); return; }
        draftSaveBusy.current = true;
        setDraftSaveStatus('Menyimpan draf…');
        function next(index: number): void {
            const task = tasks[index];
            if (!task) { draftSaveBusy.current = false; setDraftSaveStatus(draftTasks.some(item => item.form.isDirty && !tasks.includes(item)) ? 'Sebagian draf tersimpan. Periksa isian yang masih belum tersimpan.' : 'Draf pemeriksaan tersimpan'); afterSave?.(); return; }
            const fingerprint = JSON.stringify(task.form.data);
            lastDraftAttempt.current[task.key] = fingerprint;
            let completed = false;
            task.form.post(`/pelayanan/pemeriksaan/${visit.id}`, {
                preserveScroll: true, preserveState: true,
                onSuccess: () => { completed = true; if (JSON.stringify(task.form.data) === fingerprint) task.form.setDefaults(); next(index + 1); },
                onError: (errors) => { completed = true; draftSaveBusy.current = false; setDraftSaveStatus('Draf belum tersimpan. Periksa isian yang ditandai lalu simpan kembali.'); if (afterSave) toast.error(Object.values(errors)[0] ?? 'Periksa kembali isian pemeriksaan.'); },
                onCancel: () => { completed = true; draftSaveBusy.current = false; setDraftSaveStatus('Penyimpanan draf belum selesai'); },
                onFinish: () => { if (!completed) { draftSaveBusy.current = false; setDraftSaveStatus('Draf belum tersimpan. Coba simpan kembali.'); } },
            });
        }
        next(0);
    }


    const isAfter48Hours = Boolean(visit.examination?.after48Hours);
    const addendumChanged = Object.entries(addendumForm.data).some(([key, value]) => !['reason', 'late_correction_acknowledged'].includes(key) && JSON.stringify(value) !== JSON.stringify(addendumDefaults[key as keyof typeof addendumDefaults]));
    const canSaveAddendum = canCorrect && !saving && addendumForm.isDirty && addendumChanged && addendumForm.data.reason.trim().length >= 10 && (!isAfter48Hours || addendumForm.data.late_correction_acknowledged) && !addendumBodyPending;
    const dentalFindingUsesNaturalTooth = !['implant', 'bridge_pontic', 'removable_denture', 'missing', 'unerupted'].includes(dentalForm.data.finding_code);
    const selectedDentalRecord = visit.odontogram.find((finding) => finding.toothFdi === dentalForm.data.tooth_fdi && finding.surface === dentalForm.data.surface);
    const dentalFindingChanged = !selectedDentalRecord || selectedDentalRecord.findingCode !== dentalForm.data.finding_code || (selectedDentalRecord.notes ?? '').trim() !== dentalForm.data.notes.trim() || JSON.stringify(selectedDentalRecord?.details ?? {}) !== JSON.stringify(dentalForm.data.details ?? {}) || (dentalForm.data.surfaces?.length ?? 0) > 1;
    const canSaveDentalFinding = (canEdit || canCorrect) && !saving && dentalForm.isDirty && dentalFindingChanged && Boolean(dentalForm.data.tooth_fdi && dentalForm.data.surface && dentalForm.data.finding_code) && (!canCorrect || (dentalForm.data.reason.trim().length >= 10 && (!isAfter48Hours || dentalForm.data.late_correction_acknowledged)));
    const selectedClinicMedicine = medicines.find((item) => item.id.toString() === prescriptionForm.data.obat_id) ?? null;
    const effectiveAllergyText = examinationForm.data.riwayat_alergi || visit.patient.allergies || visit.screening?.allergyHistory || '';
    const matchedClinicAllergy = detectClientAllergyMatch(effectiveAllergyText, selectedClinicMedicine);
    const matchedExternalAllergy = detectClientAllergyMatch(effectiveAllergyText, null, externalPrescriptionForm.data.nama_obat);
    const duplicateClinicMedicine = detectClientDuplicateIngredient(selectedClinicMedicine, visit.prescription.items);
    const hasDiagnosisDraft = Boolean(diagnosisForm.data.tooth_fdi || diagnosisForm.data.kode_icd10.trim() || diagnosisForm.data.nama_diagnosa.trim() || diagnosisForm.data.keterangan.trim());
    const unsavedSections: { tab: Tab | 'rencana'; label: string; fieldId?: string }[] = [
        ...(anamnesisChanged ? [{ tab: 'anamnesis' as Tab, label: 'Anamnesis', fieldId: 'anamnesis' }] : []),
        ...(!isDental && bodyFindingPending ? [{ tab: physicalTab, label: 'Temuan lokasi tubuh' }] : []),
        ...(physicalChanged ? [{ tab: physicalTab, label: isDental ? 'Pemeriksaan fisik & intraoral dokter' : 'Pemeriksaan fisik' }] : []),
        ...(followUpForm.isDirty ? [{ tab: 'rencana' as const, label: 'Nonfarmakologis, edukasi & kontrol' }] : []),
        ...(isDental && dentalForm.isDirty ? [{ tab: 'odontogram' as Tab, label: 'Odontogram', fieldId: 'dental-action-panel' }] : []),
        ...(isDental && odontogramSummaryForm.isDirty ? [{ tab: 'odontogram' as Tab, label: 'Ringkasan odontogram' }] : []),
        ...((hasDiagnosisDraft || differentialForm.isDirty) ? [{ tab: 'diagnosa' as Tab, label: 'Diagnosis' }] : []),
        ...((treatmentForm.data.terminology_id || treatmentForm.data.tindakan_id || treatmentForm.data.nama_tindakan_manual.trim() || treatmentForm.data.catatan.trim()) ? [{ tab: 'tindakan' as Tab, label: 'Tindakan' }] : []),
        ...(prescriptionForm.isDirty ? [{ tab: 'resep' as Tab, label: 'Resep klinik' }] : []),
        ...(externalPrescriptionForm.isDirty ? [{ tab: 'resep-luar' as Tab, label: 'Resep luar' }] : []),
        ...(letterForm.isDirty ? [{ tab: 'surat' as Tab, label: 'Surat medis' }] : []),
        ...(referralForm.isDirty ? [{ tab: 'rujukan' as Tab, label: 'Rujukan internal' }] : []),
    ];
    const cariesDetailsByFinding = new Map(visit.odontogram.map((finding) => [
        finding,
        missingCariesDetails(finding, dentalOptions.detailOptions),
    ]));
    const incompleteDentalFindings = visit.odontogram.filter((finding) => cariesDetailsByFinding.get(finding)?.length);
    const unresolvedDiagnoses = visit.diagnoses.filter((diagnosis) => diagnosis.codingStatus === 'unresolved');
    const unresolvedProcedures = visit.treatments.filter((item) => item.codingStatus === 'unresolved');
    const codingExceptionAvailable = codingDowntimeActive || visit.screening?.priority === 'darurat';
    const readiness: { tab: Tab; label: string; complete: boolean; fieldId?: string; resolveInSignature?: boolean }[] = [
        { tab: 'anamnesis', label: 'Anamnesis', fieldId: 'anamnesis', complete: Boolean(visit.examination?.anamnesis?.trim()) },
        { tab: physicalTab, label: isDental ? 'Pemeriksaan intraoral dan ekstraoral' : 'Pemeriksaan fisik', fieldId: isDental && normalizeClinicalAnswer(visit.examination?.physicalExam) ? 'extraoral' : 'pemeriksaan_fisik', complete: isDental
            ? Boolean(normalizeClinicalAnswer(visit.examination?.physicalExam) && normalizeClinicalAnswer(visit.examination?.extraoralExam))
            : Boolean(normalizeClinicalAnswer(visit.examination?.physicalExam) || hasBodyMapContent(visit.examination?.bodyMap) || Object.values(visit.examination?.physicalSystems ?? {}).some(normalizeClinicalAnswer)) },
        ...(isDental ? [{
            tab: 'odontogram' as Tab,
            label: 'Odontogram dan rincian lesi',
            complete: (Boolean(signatureForm.data.dental_scope) || visit.odontogram.length > 0) && incompleteDentalFindings.length === 0,
            resolveInSignature: visit.odontogram.length === 0,
        }] : []),
        ...(isDental ? [{ tab: 'diagnosa' as Tab, label: 'Konteks gigi pada diagnosis', complete: visit.diagnoses.every(diagnosis => diagnosisMatchesFindings(diagnosis, visit.odontogram)) }] : []),
        { tab: 'diagnosa', label: 'Satu diagnosis utama', fieldId: 'jenis-diagnosa', complete: visit.diagnoses.filter((diagnosis) => diagnosis.type === 'utama').length === 1 },
        { tab: 'diagnosa', label: 'Verifikasi ICD-10 WHO', fieldId: 'recorded-diagnoses-title', complete: unresolvedDiagnoses.length === 0 || (codingExceptionAvailable && signatureForm.data.coding_downtime_acknowledged && signatureForm.data.coding_downtime_reason.trim().length >= 10), resolveInSignature: unresolvedDiagnoses.length > 0 && codingExceptionAvailable },
        { tab: 'tindakan', label: 'Verifikasi ICD-9-CM tindakan', complete: unresolvedProcedures.length === 0 || (codingExceptionAvailable && signatureForm.data.coding_downtime_acknowledged && signatureForm.data.coding_downtime_reason.trim().length >= 10), resolveInSignature: unresolvedProcedures.length > 0 && codingExceptionAvailable },
    ];
    const allergyWarning = [examinationForm.data.riwayat_alergi, visit.patient.allergies, visit.screening?.allergyHistory].find(isReportedAllergy);
    useUnsavedChangesGuard(canEdit && unsavedSections.length > 0 && !saving);
    useEffect(() => {
        if (!finishAfterDraft || saving) return;
        setFinishAfterDraft(false);
        finishExamination();
    });
    const screeningMeasures = visit.screening ? [
        { label: 'Tekanan darah', value: visit.screening.systolic !== null || visit.screening.diastolic !== null ? formatBloodPressure(visit.screening.systolic, visit.screening.diastolic) : null },
        { label: 'Nadi', value: formatMeasurement(visit.screening.pulse, 'x/menit') },
        { label: 'Suhu', value: formatMeasurement(visit.screening.temperature, '°C') },
        { label: 'SpO₂', value: formatMeasurement(visit.screening.oxygenSaturation, '%') },
        { label: 'Respirasi', value: formatMeasurement(visit.screening.respiration, 'x/menit') },
        { label: 'Berat badan', value: formatMeasurement(visit.screening.weight, 'kg') },
        { label: 'Tinggi badan', value: formatMeasurement(visit.screening.height, 'cm') },
        { label: 'IMT', value: visit.screening.bmi === null ? null : `${visit.screening.bmi} kg/m²${visit.screening.bmiCategory ? ` (${visit.screening.bmiCategory})` : ''}` },
        { label: 'Lingkar perut', value: formatMeasurement(visit.screening.waistCircumference, 'cm') },
    ].filter((item): item is { label: string; value: string } => item.value !== null) : [];
    const screeningMedicalHistory = visit.screening ? [
        visit.screening.medicalHistory,
        visit.screening.summary?.['Nama penyakit'],
        visit.screening.summary?.['Keterangan riwayat penyakit'],
    ].map(normalizeClinicalAnswer).filter((answer): answer is string => answer !== null).join(' — ') || null : null;
    const screeningSummaryGroups: { title: string; rows: [string, string | null | undefined][] }[] = visit.screening ? [
        ...(!isDental ? [{ title: 'Pengkajian umum perawat', rows: Object.entries(visit.screening.summary ?? {}).filter(([label, value]) => value && (label.startsWith('Pengkajian sistem - ') || ['Kondisi kedatangan', 'Pendamping pasien', 'Keadaan umum', 'Kondisi umum', 'Jalan napas', 'Sirkulasi', 'Catatan rincian keluhan', 'Catatan pengkajian sistem tubuh', 'Lokasi keluhan', 'Karakteristik keluhan', 'Pola dan frekuensi', 'Faktor pemicu', 'Faktor pereda', 'Perkembangan keluhan', 'Rincian perkembangan keluhan', 'Riwayat rawat inap', 'Riwayat operasi', 'Status mobilitas', 'Status kebutuhan khusus', 'Kebutuhan khusus pasien', 'Tujuan kunjungan (catatan sebelumnya)', 'Awitan keluhan (catatan sebelumnya)', 'Keparahan (catatan sebelumnya)', 'Gejala penyerta (catatan sebelumnya)', 'Penyakit kronis (catatan sebelumnya)', 'Rincian rawat inap (catatan sebelumnya)', 'Rincian operasi (catatan sebelumnya)', 'Ringkasan temuan (catatan sebelumnya)', 'Catatan untuk dokter (catatan sebelumnya)', 'Tanda bahaya / hasil penilaian', 'Waktu pencatatan TTV', 'Waktu pencatatan triase'].includes(label))) as [string, string | null | undefined][] }] : []),
        { title: 'Ringkasan dan serah terima perawat', rows: Object.entries(visit.screening.summary ?? {}).filter(([label, value]) => value && ['Status masalah perawat', 'Masalah / kebutuhan teridentifikasi', 'Status tindakan awal', 'Tindakan awal sesuai kewenangan', 'Tindak lanjut serah terima', ...(isDental ? ['Kondisi umum', 'Perkembangan keluhan', 'Rincian perkembangan keluhan'] : [])].includes(label)) as [string, string][] },
        {
            title: 'Keluhan & riwayat kesehatan',
            rows: [
                ['Keluhan awal', visit.screening.complaint], ['Keluhan tambahan', visit.screening.summary?.['Keluhan tambahan']],
                ['Lama keluhan', visit.screening.summary?.['Lama keluhan']], ['Sumber informasi', visit.screening.summary?.['Sumber informasi']],
                ['Status riwayat penyakit', visit.screening.summary?.['Status riwayat penyakit']], ['Riwayat penyakit', screeningMedicalHistory],
                ['Obat yang digunakan', visit.screening.summary?.['Obat yang digunakan']], ['Riwayat penyakit keluarga', visit.screening.familyHistory],
                ['Status alergi saat ini', visit.screening.summary?.['Status alergi saat ini']], ['Riwayat alergi', visit.screening.allergyHistory],
                ['Jenis / reaksi alergi', visit.screening.summary?.['Jenis / reaksi alergi']],
            ] as [string, string | null | undefined][],
        },
        {
            title: 'Kondisi awal & respons',
            rows: [
                ['Nyeri dada', visit.screening.summary?.['Nyeri dada']], ['Kejang', visit.screening.summary?.['Kejang']],
                ['Nadi teraba', visit.screening.summary?.['Nadi teraba']], ['Pola pernapasan', visit.screening.summary?.['Pola pernapasan']],
                ['Kesadaran', visit.screening.summary?.['Kesadaran']], ['Kondisi psikiatri', visit.screening.summary?.['Kondisi psikiatri']],
                ['Risiko jatuh', visit.screening.summary?.['Risiko jatuh']], ['Skala nyeri', visit.screening.summary?.['Skala nyeri']],
                ['Kebutuhan respons awal', visit.screening.summary?.['Kebutuhan respons awal']], ['Catatan penapisan', visit.screening.summary?.['Catatan penapisan']],
            ] as [string, string | null | undefined][],
        },
        {
            title: 'Pengkajian tambahan',
            rows: [
                ['Pengkajian gizi', visit.screening.summary?.['Pengkajian gizi']], ['Observasi awal', visit.screening.summary?.['Observasi awal']],
                ['Catatan verifikasi alergi', visit.screening.summary?.['Catatan verifikasi alergi']], ['Tingkat keparahan alergi jika diketahui', visit.screening.summary?.['Tingkat keparahan alergi jika diketahui']],
                ['Status pengukuran TTV', visit.screening.summary?.['Status pengukuran TTV']],
                ['Catatan pengkajian', visit.screening.summary?.['Catatan Pengkajian']],
            ] as [string, string | null | undefined][],
        },
        ...(isDental ? [{
            title: 'Pengkajian gigi dan mulut',
            rows: [
                ...['Visual ekstraoral awal perawat - simetri wajah', 'Visual ekstraoral awal perawat - pembengkakan', 'Catatan visual ekstraoral awal perawat', 'Tanda bahaya gigi / mulut'].map((label) => [label, visit.screening?.summary?.[label]] as [string, string | null | undefined]),
                ['Kategori obat yang dilaporkan', visit.screening.summary?.['Kategori obat yang dilaporkan']],
                ...Object.entries(visit.screening.summary ?? {}).filter(([label]) => /Keterangan .*lainnya|Karakter dan|mengurangi nyeri|Dampak nyeri|Kondisi khusus|Observasi awal mulut|Perawatan mulut|Observasi mukosa|Kelainan mukosa|Kebiasaan gigi dan mulut yang|Status penggunaan obat/.test(label)),
                ['Riwayat perawatan gigi', visit.screening.summary?.['Riwayat perawatan gigi']],
                ['Kebiasaan terkait gigi dan mulut', visit.screening.summary?.['Kebiasaan terkait gigi dan mulut']],
                ['Lokasi keluhan / nyeri', visit.screening.dentalPainLocation], ['Pemicu keluhan', displayDentalValues(visit.screening.dentalPainTriggers)],
                ['Pola / lama keluhan', visit.screening.dentalPainDuration], ['Kondisi kesehatan yang perlu diketahui dokter gigi', displayDentalValues(visit.screening.dentalMedicalRisks)],
                ['Riwayat infeksi atau bengkak', displayDentalValues(visit.screening.dentalInfectionHistory)], ['Catatan untuk dokter gigi', visit.screening.dentalNotes],
            ] as [string, string | null | undefined][],
        }] : []),
    ].map((group) => ({ ...group, rows: group.rows.filter(([, value]) => normalizeClinicalAnswer(value)) }))
        .filter((group) => group.rows.length > 0) : [];
    const disclosureToggle = <span className={buttonVariants({ variant: 'secondary', size: 'sm', className: 'shrink-0' })}><span className="group-open/disclosure:hidden">Buka</span><span className="hidden group-open/disclosure:inline">Tutup</span><ChevronDown aria-hidden="true" className="size-4 transition-transform group-open/disclosure:rotate-180" /></span>;
    return (
        <>
            <Head title={`Pemeriksaan ${visit.patient.name}`} />
            <div className="space-y-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium text-neutral-700">{visit.clinic}</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pemeriksaan {isDental ? 'poli gigi dan mulut' : 'poli umum'}</h2>
                        <p className="mt-1 max-w-3xl text-sm text-neutral-500">{visit.number} · {visit.patient.name} · No. RM {visit.patient.medicalRecordNumber}</p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild size="sm" variant="secondary">
                            <Link href="/pelayanan/pemeriksaan" onClick={(event) => handleBackNavigation(event, '/pelayanan/pemeriksaan')}><ArrowLeft className="size-4" />Kembali</Link>
                        </Button>
                    </div>
                </header>
                {activeTab === 'diagnosa' && unresolvedDiagnoses.length > 0 && <Alert dismissible={false} variant="default"><AlertTriangle className="size-4" /><AlertTitle>Diagnosis perlu pemetaan ICD-10 WHO</AlertTitle><AlertDescription>{visit.examination?.signedAt ? 'Catatan ini ditandatangani dengan diagnosis unresolved pada kondisi darurat/downtime. Tindak lanjuti rekonsiliasi coding sesuai penugasan klinik.' : 'Diagnosis manual atau kode tanpa verifikasi katalog masih tersimpan sebagai draf. Tinjau pada bagian Diagnosis sebelum finalisasi normal.'}</AlertDescription></Alert>}
                {!!visit.cancelledDraftEntries?.length && <details className="rounded-xl border border-neutral-200 bg-neutral-50 p-4"><summary className="cursor-pointer text-sm font-semibold text-neutral-900">Riwayat pembatalan entri draf ({visit.cancelledDraftEntries.length})</summary><ul className="mt-4 divide-y divide-neutral-200">{visit.cancelledDraftEntries.map((entry) => <li key={`${entry.kind}-${entry.id}`} className="space-y-1 py-3 text-sm"><p className="font-medium text-neutral-900">{entry.kind}: {entry.label}</p>{entry.details && <p className="whitespace-pre-wrap text-neutral-600">{entry.details}</p>}<p className="text-neutral-600">Alasan: {entry.reason}</p><p className="text-xs text-neutral-500">Dibatalkan {entry.at} · Operator #{entry.actorId}</p></li>)}</ul></details>}

                <Card><CardContent className="space-y-4 p-5 sm:p-6">
                    <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                        <div className="flex min-w-0 items-center gap-3"><span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-lg font-bold text-neutral-800">{visit.patient.name.slice(0, 1).toLocaleUpperCase()}</span><div className="min-w-0 text-left"><p className="text-xs font-medium text-neutral-500">{visit.number} · {visit.clinic}</p><h2 className="mt-0.5 text-lg font-semibold text-neutral-950">{visit.patient.name}</h2><p className="mt-1 text-sm text-neutral-600">{visit.patient.medicalRecordNumber} · {visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : 'Belum diketahui'} · {visit.patient.age === null ? 'umur belum diketahui' : `${visit.patient.age} th`} · Gol. {visit.patient.bloodType ?? 'Belum diketahui'}</p><div className="mt-2"><StatusBadge examinationStarted={Boolean(visit.examination)} reservation={visit.isReservation} status={visit.status} /></div>{visit.examination?.startedAt && <p className="mt-1 text-xs text-neutral-500">Pemeriksaan dimulai {visit.examination.startedAt}</p>}</div></div>
                        <div className="flex shrink-0 flex-wrap justify-start gap-2 sm:justify-end">{medicalRecordUrl ? <Button asChild size="sm" variant="secondary"><Link href={medicalRecordUrl}><UserRound className="size-4" />Riwayat RME</Link></Button> : <Button size="sm" variant="secondary" disabled title="Mulai pemeriksaan terlebih dahulu untuk membuka rekam medis"><UserRound className="size-4" />Riwayat RME</Button>}{medicalRecordUrl && <Button asChild size="sm" variant="secondary"><Link href={`${medicalRecordUrl}${medicalRecordUrl.includes('?') ? '&' : '?'}kunjungan_id=${visit.id}`}>Cetak RME kunjungan</Link></Button>}{canStart && <Button disabled={startForm.processing || saving} onClick={() => confirmAction('Mulai pemeriksaan pasien ini?', () => startForm.post(`/pelayanan/pemeriksaan/${visit.id}/mulai`, { preserveScroll: true }), 'Mulai pemeriksaan', 'Mulai pemeriksaan')} size="sm"><Play className="size-4" />{startForm.processing ? 'Memulai…' : 'Mulai pemeriksaan'}</Button>}<Button disabled={!canEdit || saving || isAdminAssistance} onClick={requestExaminationFinish} size="sm"><Check className="size-4" />Selesai periksa</Button></div>
                    </div>
                    {allergyWarning && <Alert className="text-left" dismissible={false} variant="destructive"><AlertTriangle aria-hidden="true" /><AlertTitle>Peringatan alergi</AlertTitle><AlertDescription>{allergyWarning}</AlertDescription></Alert>}
                </CardContent></Card>

                {visit.incomingReferral && <Card>
                    <CardHeader>
                        <CardTitle>Rujukan masuk</CardTitle>
                        <CardDescription>{[visit.incomingReferral.fromClinic, visit.incomingReferral.number, visit.incomingReferral.originVisit].filter(Boolean).join(' · ')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-3 break-words text-sm">
                        {visit.incomingReferral.note && <div><p className="font-medium">Catatan konsultasi</p><p className="mt-1 whitespace-pre-wrap">{visit.incomingReferral.note}</p></div>}
                        {visit.incomingReferral.content && <div><p className="font-medium">Isi surat rujukan</p><p className="mt-1 whitespace-pre-wrap">{visit.incomingReferral.content}</p></div>}
                    </CardContent>
                </Card>}
                {assistanceReview && !isAdminAssistance && <Card><CardHeader><CardTitle>Draf dibantu admin</CardTitle><CardDescription>Input terakhir oleh {assistanceReview.operator ?? 'operator admin'} pada {assistanceReview.lastEditedAt}. Tinjau seluruh catatan, diagnosis, tindakan dan resep sebelum menandatangani.</CardDescription></CardHeader></Card>}
                {isAdminAssistance && <Card><CardHeader><CardTitle>Bantuan pemeriksaan admin</CardTitle><CardDescription>Anda mencatat sebagai operator bantuan. Dokter penanggung jawab tetap meninjau dan menandatangani hasil pemeriksaan.</CardDescription></CardHeader><CardContent className="space-y-3">
                    {assistance?.active ? <p className="text-sm text-neutral-600">Bantuan draf aktif untuk kunjungan ini. Masa bantuan dua jam; jika kedaluwarsa, mulai kembali dengan alasan.</p> : <form className="space-y-4" onSubmit={(event) => { event.preventDefault(); assistanceForm.post(`/pelayanan/pemeriksaan/${visit.id}/bantuan`, { preserveScroll: true }); }}>
                        <AssistanceReasonField error={assistanceForm.errors.reason} id="assistance-reason" value={assistanceForm.data.reason} onChange={(value) => assistanceForm.setData('reason', value)} disabled={assistanceForm.processing} />
                        <div className="flex justify-end"><Button disabled={assistanceForm.processing || assistanceForm.data.reason.trim().length < 10} type="submit">Mulai bantuan draf</Button></div>
                    </form>}
                </CardContent></Card>}


                {visit.screeningSubmitted && visit.screening?.dentalHypertensionAlert && <Alert dismissible={false} variant="destructive">
                    <AlertTriangle aria-hidden="true" /><AlertTitle>Peringatan hipertensi kritis Poli Gigi ({formatBloodPressure(visit.screening.systolic, visit.screening.diastolic)})</AlertTitle>
                    <AlertDescription>Tekanan darah ≥ 180/110 mmHg: tunda ekstraksi atau tindakan invasif berisiko perdarahan dan hindari anestesi lokal ber-adrenalin sebelum stabilisasi medis.</AlertDescription>
                </Alert>}
                {visit.screeningSubmitted && ['darurat', 'segera'].includes(visit.screening?.priority ?? '') && <Alert dismissible={false} variant={visit.screening?.priority === 'darurat' ? 'destructive' : 'default'}>
                    <AlertTriangle aria-hidden="true" /><AlertTitle>Prioritas pengkajian perawat · {visit.screening?.priority}</AlertTitle>
                    <AlertDescription>{visit.screening?.summary?.['Tanda bahaya gigi / mulut'] ?? 'Tinjau hasil pengkajian sebelum melanjutkan.'}</AlertDescription>
                </Alert>}
                <details open={!isDental} id="nurse-assessment-summary" className="group/disclosure rounded-xl bg-surface"><summary className="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] items-start gap-3 p-4 [&::-webkit-details-marker]:hidden"><div className="min-w-0 text-sm leading-6"><p className="font-semibold">Ringkasan pengkajian awal perawat</p><p className="line-clamp-2 text-neutral-600">TD: {visit.screening ? formatBloodPressure(visit.screening.systolic, visit.screening.diastolic) : 'Belum diukur'} | Nadi: {visit.screening?.pulse ?? 'Belum diukur'} | Alergi: {visit.screening?.allergyHistory || 'Belum diketahui'} | Keluhan: {visit.screening?.complaint || 'Belum dicatat'}</p></div>{disclosureToggle}</summary><Card className="rounded-t-none border-t border-neutral-200"><CardHeader><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600"><Stethoscope className="size-5" /></span><div className="min-w-0 space-y-1"><CardTitle>Ringkasan pengkajian awal perawat</CardTitle><CardDescription>Isian tersimpan dari pengkajian {isDental ? 'Poli Gigi dan Mulut' : 'Poli Umum'} untuk konteks pemeriksaan dokter.</CardDescription></div></div></CardHeader><CardContent className="space-y-5">
                    {visit.screening ? <>
                        {screeningMeasures.length > 0 && <section aria-label="Hasil pengukuran perawat" className="space-y-3"><h3 className="text-sm font-semibold text-neutral-800">Hasil pengukuran</h3><dl className="grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4">{screeningMeasures.map(({ label, value }) => <div className="min-w-0 rounded-xl border border-neutral-200 bg-neutral-50 p-3 sm:p-4" key={label}><dt className="text-xs font-medium text-neutral-500">{label}</dt><dd className="mt-1 break-words text-sm font-semibold text-neutral-900">{value}</dd></div>)}</dl></section>}
                        {screeningSummaryGroups.length > 0 && <div className="grid min-w-0 gap-4 md:grid-cols-2">{screeningSummaryGroups.map((group) => <section className={`min-w-0 rounded-xl border border-neutral-200 bg-surface p-4 sm:p-5 ${['Pengkajian tambahan', 'Pengkajian gigi dan mulut'].includes(group.title) ? 'md:col-span-2' : ''}`} key={group.title}><h3 className="mb-4 text-sm font-semibold text-neutral-900">{group.title}</h3><dl className="grid min-w-0 gap-x-5 gap-y-4 sm:grid-cols-2">{group.rows.map(([label, value]) => <AssessmentNote key={label} label={label} value={value} />)}</dl></section>)}</div>}
                        {screeningMeasures.length === 0 && screeningSummaryGroups.length === 0 && <Empty className="min-h-0 py-5" title="Belum ada isian pengkajian untuk ditampilkan" />}
                    </> : <Empty className="min-h-0 py-5" title="Pengkajian awal perawat belum tercatat" />}
                </CardContent></Card></details>

                {examinationNotice && <Alert key={examinationNotice.title} dismissLabel={examinationNotice.destructive ? undefined : 'Tutup informasi pemeriksaan'} variant={examinationNotice.destructive ? 'destructive' : 'default'}>{examinationNotice.destructive && <AlertTriangle aria-hidden="true" />}<AlertTitle>{examinationNotice.title}</AlertTitle><AlertDescription>{examinationNotice.description}</AlertDescription></Alert>}

                <Dialog open={Boolean(consentUrl)} onOpenChange={(open) => { if (!open) setConsentUrl(null); }}>
                    <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-4xl" onInteractOutside={(event) => event.preventDefault()}>
                        <DialogHeader><DialogTitle>Persetujuan tindakan</DialogTitle><DialogDescription>Tinjau keputusan untuk tindakan pada kunjungan ini.</DialogDescription></DialogHeader>
                        {consentDetails ? <ProcedureConsent {...consentDetails} onSaved={() => {
                            setConsentUrl(null);
                            if (resumeSignature) setFinishAfterDraft(true);
                        }} />
                            : consentLoadFailed ? <p role="alert" className="text-sm text-red-700">Persetujuan gagal dimuat. <a className="underline" href={consentUrl ?? undefined}>Buka halaman persetujuan</a></p>
                            : <ConsentDialogSkeleton label="Memuat persetujuan…" />}
                    </DialogContent>
                </Dialog>

                <Dialog open={startingProcedure !== null} onOpenChange={(open) => { if (!open && !procedureStartForm.processing) setStartingProcedure(null); }}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Mulai tindakan</DialogTitle>
                            <DialogDescription>{startingProcedure?.name}</DialogDescription>
                        </DialogHeader>
                        <form className="space-y-4" onSubmit={(event) => {
                            event.preventDefault();
                            if (!startingProcedure || !procedureStartForm.data.confirmed) return;
                            procedureStartForm.post(`/pelayanan/pemeriksaan/${visit.id}/tindakan/${startingProcedure.id}/mulai`, {
                                preserveScroll: true,
                                onSuccess: () => setStartingProcedure(null),
                            });
                        }}>
                            <label className="flex items-start gap-2 text-sm">
                                <Checkbox checked={procedureStartForm.data.confirmed} disabled={procedureStartForm.processing} onCheckedChange={(checked) => procedureStartForm.setData('confirmed', checked === true)} />
                                <span>Saya telah memeriksa persetujuan tertulis pasien atau wakil untuk tindakan ini.</span>
                            </label>
                            {Object.values(procedureStartForm.errors).map((error, index) => <p key={index} role="alert" className="text-sm text-red-700">{error}</p>)}
                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="secondary" disabled={procedureStartForm.processing} onClick={() => setStartingProcedure(null)}>Batal</Button>
                                <Button type="submit" disabled={!procedureStartForm.data.confirmed || procedureStartForm.processing}>Konfirmasi mulai</Button>
                            </div>
                        </form>
                    </DialogContent>
                </Dialog>

                <AlertDialog onOpenChange={(open) => { if (!open) { setUndoFinding(null); odontogramUndoForm.reset(); } }} open={undoFinding !== null || odontogramUndoForm.data.findings.length > 0}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{odontogramUndoForm.data.findings.length > 0 ? 'Batalkan temuan terpilih?' : 'Batalkan temuan odontogram?'}</AlertDialogTitle>
                            <AlertDialogDescription>{odontogramUndoForm.data.findings.length > 0 ? `Sebanyak ${odontogramUndoForm.data.findings.length} temuan yang dipilih akan dibatalkan. Temuan lainnya tetap tersimpan. Tindakan dan diagnosis yang sudah tersimpan tetap tercatat. Pada pemeriksaan final, pembatalan dicatat sebagai addendum dan versi sebelumnya tetap tersimpan.` : undoFinding ? `Gigi ${undoFinding.toothFdi} · ${dentalOptions.surfaces[undoFinding.surface] ?? undoFinding.surface} · ${dentalOptions.findings[undoFinding.findingCode] ?? undoFinding.findingCode}` : ''}{odontogramUndoForm.data.findings.length === 0 && '. Temuan dibatalkan dari daftar aktif; catatan asli tetap tersimpan.'}</AlertDialogDescription>
                        </AlertDialogHeader>
                        <Field error={odontogramUndoForm.errors.reason} htmlFor="odontogram-undo-reason" label="Alasan pembatalan temuan" required><Textarea id="odontogram-undo-reason" minLength={10} onChange={(event) => odontogramUndoForm.setData('reason', event.target.value)} placeholder="Jelaskan alasan pembatalan temuan" required rows={3} value={odontogramUndoForm.data.reason} /></Field>
                        {(['findings', 'confirmed', 'tooth_fdi', 'surface'] as const).map((key) => odontogramUndoForm.errors[key] ? <p key={key} role="alert" className="text-sm text-red-700">{odontogramUndoForm.errors[key]}</p> : null)}
                        <AlertDialogFooter className="border-t border-neutral-200 pt-4">
                            <AlertDialogCancel asChild>
                                <Button type="button" variant="secondary">Kembali</Button>
                            </AlertDialogCancel>
                            <Button disabled={saving || (odontogramUndoForm.data.reason.trim().length < 10)} onClick={undoDentalFinding} type="button" variant="destructiveOutline">{odontogramUndoForm.data.findings.length > 0 ? 'Batalkan temuan terpilih' : 'Batalkan temuan'}</Button>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                {canEdit && <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-surface p-4"><div className="text-xs text-neutral-600" role="status">Simpan perubahan pemeriksaan sebagai draf sebelum meninggalkan halaman.{bodyFindingPending && <p className="mt-1 text-amber-700">Tambahkan atau kosongkan temuan lokasi yang masih tertunda sebelum menyimpan pemeriksaan fisik.</p>}</div><Button type="button" size="sm" variant="secondary" disabled={saving || draftSaveBusy.current || !draftTasks.some(task => task.eligible)} onClick={() => saveClinicalDraft()}>Simpan draf pemeriksaan</Button></div>}
                {draftSaveStatus && draftSaveStatus !== 'Menyimpan draf…' && <Alert key={draftSaveStatus} autoDismissMs={9000} variant={draftSaveStatus === 'Draf pemeriksaan tersimpan' ? 'success' : 'warning'}><AlertDescription>{draftSaveStatus}</AlertDescription></Alert>}
                {!handoverNotesDismissed && nurseNotes.length > 0 && <section aria-label="Catatan penting dari perawat" className="relative space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4 pr-12 text-amber-950"><h3 className="text-sm font-semibold">Catatan penting dari perawat</h3><Button type="button" variant="ghost" size="icon" className="absolute right-2 top-2" aria-label="Tutup catatan penting dari perawat" onClick={() => setHandoverNotesDismissed(true)}><X className="size-4" /></Button><dl className="grid gap-3 sm:grid-cols-2">{nurseNotes.map(([label, value]) => <div key={label}><dt className="text-sm font-medium">{label}</dt><dd className="mt-1 whitespace-pre-wrap break-words text-sm">{value}</dd></div>)}</dl></section>}
{activeTab === physicalTab && visit.screening && !physicalNurseNotesDismissed && <section className="relative space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-4 pr-12 text-amber-950" aria-label="Acuan pemeriksaan dari perawat"><Button type="button" variant="ghost" size="icon" className="absolute right-2 top-2" aria-label="Tutup acuan pemeriksaan dari perawat" onClick={() => setPhysicalNurseNotesDismissed(true)}><X className="size-4" /></Button><p className="whitespace-pre-wrap text-sm"><strong>Keluhan:</strong> {visit.screening.complaint || 'Belum dicatat'}</p><h4 className="text-sm font-semibold">Temuan awal dan tindak lanjut dari perawat</h4><dl className="grid gap-3 sm:grid-cols-2">{Object.entries(visit.screening.summary ?? {}).filter(([label, value]) => value && (label.startsWith('Pengkajian sistem - ') && value.startsWith('Positif') || ['Observasi awal', 'Kondisi umum', 'Kesadaran', 'Catatan rincian keluhan', 'Catatan pengkajian sistem tubuh', 'Masalah / kebutuhan teridentifikasi', 'Tindakan awal sesuai kewenangan', 'Tindak lanjut serah terima', ...(isDental ? ['Visual ekstraoral awal perawat - simetri wajah', 'Visual ekstraoral awal perawat - pembengkakan', 'Tanda bahaya gigi / mulut', 'Kondisi kesehatan yang perlu diketahui dokter gigi'] : [])].includes(label))).map(([label, value]) => <div key={label}><dt className="text-xs text-neutral-500">{label}</dt><dd className="whitespace-pre-wrap text-sm">{value}</dd></div>)}</dl><p className="text-xs text-neutral-500">Gunakan temuan awal sebagai acuan. Catat hasil pemeriksaan objektif dokter {isDental ? 'pada pemeriksaan ekstraoral, intraoral, dan odontogram.' : 'pada diagram dan lokasi keluhan.'}</p>{oralObservations.length > 0 && <section aria-label="Observasi mukosa dari perawat" className="space-y-2 border-t border-amber-200 pt-3"><h4 className="text-sm font-semibold">Observasi mukosa dari perawat</h4><dl className="grid gap-3 sm:grid-cols-2">{oralObservations.map(([label, value]) => <div key={label}><dt className="text-xs">{label}</dt><dd className="whitespace-pre-wrap text-sm">{value}</dd></div>)}</dl></section>}</section>}
                {visit.examination?.signedAt && <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600"><ClipboardPlus aria-hidden="true" className="size-5" /></span>
                            <div className="min-w-0 space-y-1">
                                <CardTitle>Riwayat pemeriksaan & koreksi</CardTitle>
                                <CardDescription>Ditandatangani oleh {visit.examination.signedBy ?? 'dokter'} pada {visit.examination.signedAt}. Buka versi untuk melihat hasil pemeriksaan dan alasan koreksi.</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-5">
                    <div className="space-y-3">
                        {[...visit.examination.versions].reverse().map((version, index) => <details key={version.version} open={index === 0} className="group/disclosure min-w-0 rounded-xl bg-neutral-50">
                            <summary className="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] items-start gap-3 p-4 [&::-webkit-details-marker]:hidden">
                                <div className="min-w-0 space-y-2">
                                    <Badge variant={version.kind === 'addendum' ? 'waiting' : 'complete'}>Versi {version.version} · {version.kind === 'addendum' ? 'Addendum' : 'Final'}</Badge>
                                    <p className="text-sm text-neutral-600 [overflow-wrap:anywhere]">{version.actor ?? 'Dokter'}{version.recordedAt && ` · ${version.recordedAt}`}</p>
                                </div>
                                {disclosureToggle}
                            </summary>
                            <div className="space-y-4 border-t border-neutral-200 p-4">
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    {[
                                        ['Alasan koreksi', version.reason],
                                        ['Keluhan utama', version.anamnesis],
                                        [isDental ? 'Pemeriksaan intraoral' : 'Ringkasan pemeriksaan fisik', version.physicalExam],
                                        ...(isDental ? [['Ringkasan odontogram', version.odontogramSummary]] : []),
                                        ['Perawatan nonfarmakologis', version.nonPharmacological],
                                        ['Edukasi pasien', version.education],
                                        ['Catatan dokter', version.notes],
                                        ...Object.entries(version.clinicalAssessmentSummary ?? {}),
                                    ].filter(([, value]) => value?.trim()).map(([label, value]) => <div key={label} className="min-w-0">
                                        <dt className="text-xs font-medium text-neutral-500">{label}</dt>
                                        <dd className="mt-1 whitespace-pre-wrap text-sm leading-6 text-neutral-900 [overflow-wrap:anywhere]">{value}</dd>
                                    </div>)}
                                </dl>
                                {!isDental && <BodyExaminationRecord data={version.bodyMap} diagnosisSummary />}
                                {isDental && version.odontogram.length > 0 && <div className="overflow-x-auto rounded-xl bg-surface">
                                    <Table aria-label={`Temuan odontogram versi ${version.version}`} className="min-w-[36rem] table-fixed">
                                        <TableHeader><TableRow><TableHead className="w-20">Gigi FDI</TableHead><TableHead className="w-28">Permukaan</TableHead><TableHead className="w-36 text-left">Temuan</TableHead><TableHead className="text-left">Catatan</TableHead></TableRow></TableHeader>
                                        <TableBody>{version.odontogram.map(finding => <TableRow key={`${finding.toothFdi}-${finding.surface}`}>
                                            <TableCell className="font-semibold tabular-nums">{finding.toothFdi}</TableCell>
                                            <TableCell>{dentalOptions.surfaces[finding.surface] ?? finding.surface}</TableCell>
                                            <TableCell className="whitespace-normal text-left [overflow-wrap:anywhere]">{dentalOptions.findings[finding.findingCode] ?? finding.findingCode}</TableCell>
                                            <TableCell className="align-top text-left"><RecordedTableNote parts={[finding.detailSummary, finding.notes]} /></TableCell>
                                        </TableRow>)}</TableBody>
                                    </Table>
                                </div>}
                            </div>
                        </details>)}
                    </div>
                    {canCorrect && <fieldset className="min-w-0" disabled={saving}><form className="space-y-6 border-t border-neutral-200 pt-5" onSubmit={submitAddendum}>
                        <div><h3 className="text-sm font-semibold text-neutral-950">Buat addendum</h3><p className="mt-1 text-sm text-neutral-600">Ubah bagian yang perlu dikoreksi. Nilai versi sebelumnya tetap tersimpan.</p></div>
                        <Field error={addendumForm.errors.reason} htmlFor="addendum-reason" label="Alasan koreksi" required><Textarea id="addendum-reason" placeholder="Contoh: koreksi setelah verifikasi ulang" minLength={10} onChange={(event) => addendumForm.setData('reason', event.target.value)} required rows={2} value={addendumForm.data.reason} /></Field>
                        {isAfter48Hours && <div className="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950"><p className="font-semibold">Koreksi melewati batas 2×24 jam (PMK No. 24 Tahun 2022 Pasal 37)</p><label className="flex cursor-pointer items-start gap-2"><Checkbox checked={addendumForm.data.late_correction_acknowledged} onCheckedChange={(checked) => addendumForm.setData('late_correction_acknowledged', checked === true)} /><span>Saya mengonfirmasi bahwa koreksi rekam medis setelah 48 jam ini dilakukan atas dasar verifikasi klinis sah dan tercatat dalam audit tata kelola klinik.</span></label>{addendumForm.errors.late_correction_acknowledged && <p role="alert" className="text-xs text-red-700">{addendumForm.errors.late_correction_acknowledged}</p>}</div>}
                        <section className="space-y-4" aria-labelledby="addendum-history-title"><h4 className="font-semibold text-neutral-900" id="addendum-history-title">Anamnesis</h4><div className="grid gap-4 md:grid-cols-2"><Field error={addendumForm.errors.anamnesis} htmlFor="addendum-anamnesis" label="Keluhan utama"><Textarea id="addendum-anamnesis" placeholder="Keluhan utama yang disampaikan pasien" onChange={(event) => addendumForm.setData('anamnesis', event.target.value)} rows={3} value={addendumForm.data.anamnesis} /></Field><Field error={addendumForm.errors.riwayat_penyakit_sekarang} htmlFor="addendum-rps" label="Riwayat penyakit sekarang"><Textarea id="addendum-rps" placeholder="Awal keluhan, durasi, dan perkembangannya" onChange={(event) => addendumForm.setData('riwayat_penyakit_sekarang', event.target.value)} rows={3} value={addendumForm.data.riwayat_penyakit_sekarang} /></Field><ConfirmedHistoryField previousValues={previousHistoryValues.current} error={addendumForm.errors.riwayat_penyakit_dahulu} id="addendum-rpd" kind="past" label="Riwayat penyakit dahulu" onChange={(value) => addendumForm.setData('riwayat_penyakit_dahulu', value)} placeholder="Riwayat penyakit, operasi, dan pengobatan rutin" value={addendumForm.data.riwayat_penyakit_dahulu} /><ConfirmedHistoryField previousValues={previousHistoryValues.current} error={addendumForm.errors.riwayat_penyakit_keluarga} id="addendum-riwayat-keluarga" kind="family" label="Riwayat penyakit keluarga" onChange={(value) => addendumForm.setData('riwayat_penyakit_keluarga', value)} placeholder="Riwayat penyakit keluarga yang relevan" value={addendumForm.data.riwayat_penyakit_keluarga} /><ConfirmedHistoryField previousValues={previousHistoryValues.current} error={addendumForm.errors.riwayat_alergi} id="addendum-alergi" kind="allergy" label="Riwayat alergi" onChange={(value) => addendumForm.setData('riwayat_alergi', value)} placeholder="Obat, makanan, dan reaksi alergi" value={addendumForm.data.riwayat_alergi} /></div>
                            {showDoctorComplaintDetails && <DoctorComplaintDetailsSection disabled={saving} errors={addendumForm.errors as Record<string, string>} idPrefix="addendum-" onChange={(complaint_details) => addendumForm.setData('clinical_assessment', { ...addendumForm.data.clinical_assessment, complaint_details })} value={addendumForm.data.clinical_assessment?.complaint_details ?? {}} />}
                            {showDoctorDentalPain && <DoctorDentalPainSection disabled={saving} errors={addendumForm.errors as Record<string, string>} idPrefix="addendum-" onChange={(dental_pain) => addendumForm.setData('clinical_assessment', { ...addendumForm.data.clinical_assessment, dental_pain })} value={addendumForm.data.clinical_assessment?.dental_pain ?? {}} />}
                        </section>
                        <div className="grid gap-4 md:grid-cols-2">
                            <ConfirmedHistoryField previousValues={previousHistoryValues.current} error={addendumForm.errors.riwayat_obat} id="addendum-riwayat_obat" kind="medication" label="Obat yang sedang digunakan" placeholder="Nama obat, dosis, dan frekuensi yang diketahui" value={addendumForm.data.riwayat_obat} onChange={value => addendumForm.setData('riwayat_obat', value)} />
                            {isDental && <><ConfirmedHistoryField previousValues={previousHistoryValues.current} error={addendumForm.errors.riwayat_perawatan_gigi} id="addendum-riwayat_perawatan_gigi" kind="dentalTreatment" label="Riwayat perawatan gigi" placeholder="Jenis perawatan dan keluhan atau komplikasi" value={addendumForm.data.riwayat_perawatan_gigi} onChange={value => addendumForm.setData('riwayat_perawatan_gigi', value)} /><ConfirmedHistoryField previousValues={previousHistoryValues.current} error={addendumForm.errors.kebiasaan_gigi} id="addendum-kebiasaan_gigi" kind="dentalHabits" label="Kebiasaan terkait gigi dan mulut" placeholder="Kebiasaan yang relevan" value={addendumForm.data.kebiasaan_gigi} onChange={value => addendumForm.setData('kebiasaan_gigi', value)} /></>}
                        </div>
                        <section className="space-y-4 border-t border-neutral-200 pt-5" aria-labelledby="addendum-exam-title"><h4 className="font-semibold text-neutral-900" id="addendum-exam-title">{isDental ? 'Pemeriksaan fisik & intraoral dokter' : 'Pemeriksaan fisik'}</h4><div className={isDental ? "grid gap-4 md:grid-cols-2" : "space-y-4"}><Field error={addendumForm.errors.pemeriksaan_fisik} htmlFor="addendum-fisik" label={visit.clinicType === 'gigi' ? 'Ringkasan pemeriksaan intraoral' : 'Ringkasan pemeriksaan fisik'}><Textarea id="addendum-fisik" placeholder="Ringkasan temuan pemeriksaan fisik" onChange={(event) => addendumForm.setData('pemeriksaan_fisik', event.target.value)} rows={3} value={addendumForm.data.pemeriksaan_fisik} /></Field>{isDental && <Field error={addendumForm.errors.ringkasan_odontogram} htmlFor="addendum-ringkasan-odontogram" label="Ringkasan odontogram kunjungan (opsional)"><Textarea id="addendum-ringkasan-odontogram" rows={3} maxLength={5000} value={addendumForm.data.ringkasan_odontogram} onChange={event => addendumForm.setData('ringkasan_odontogram', event.target.value)} /></Field>}{isDental && <Field error={addendumForm.errors.pemeriksaan_ekstraoral} htmlFor="addendum-extraoral" label="Pemeriksaan ekstraoral"><Textarea id="addendum-extraoral" placeholder="Catat temuan ekstraoral yang diperiksa" onChange={(event) => addendumForm.setData('pemeriksaan_ekstraoral', event.target.value)} rows={3} value={addendumForm.data.pemeriksaan_ekstraoral} /></Field>}</div>{showDoctorMucosa && <DoctorMucosaObservationSection disabled={saving} errors={addendumForm.errors as Record<string, string>} idPrefix="addendum-" onChange={(mucosa_observation) => addendumForm.setData('clinical_assessment', { ...addendumForm.data.clinical_assessment, mucosa_observation })} value={addendumForm.data.clinical_assessment?.mucosa_observation ?? {}} />}{visit.clinicType === 'umum' && <details className="group/disclosure rounded-xl bg-neutral-50 p-4"><summary className="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] items-center gap-3 text-sm font-semibold text-neutral-900 [&::-webkit-details-marker]:hidden"><span>Koreksi peta tubuh, anotasi & temuan per sistem</span>{disclosureToggle}</summary><div className="mt-4"><PhysicalExaminationWorkspace idPrefix="addendum-" systems={addendumForm.data.pemeriksaan_fisik_terstruktur} onChange={(systems) => addendumForm.setData('pemeriksaan_fisik_terstruktur', systems)} bodyMap={addendumForm.data.body_map} onBodyMapChange={(data) => addendumForm.setData('body_map', data)} draft={addendumBodyDraft} onDraftChange={setAddendumBodyDraft} onPendingChange={setAddendumBodyPending} errors={addendumForm.errors} disabled={saving} gender={visit.patient.gender} /></div></details>}</section>
                        <section className="space-y-4 border-t border-neutral-200 pt-5" aria-labelledby="addendum-diagnosis-title"><h4 className="font-semibold text-neutral-900" id="addendum-diagnosis-title">Diagnosis</h4><ConfirmedHistoryField previousValues={previousHistoryValues.current} error={addendumForm.errors.diagnosis_banding} id="addendum-diagnosis-banding" label="Diagnosis banding" kind="differential" placeholder="Diagnosis alternatif yang dipertimbangkan" onChange={value => addendumForm.setData('diagnosis_banding', value)} value={addendumForm.data.diagnosis_banding} /></section>
                        <section className="space-y-4 border-t border-neutral-200 pt-5" aria-labelledby="addendum-plan-title">
                            <div><h4 className="font-semibold text-neutral-900" id="addendum-plan-title">Perbarui rencana tindak lanjut</h4><p className="mt-1 text-sm text-neutral-500">Koreksi setiap bagian secara terpisah; isian baru disimpan sebagai addendum.</p></div>
                            <div className="grid items-start gap-4 xl:grid-cols-2">
                                <section aria-labelledby="addendum-non-pharmacological-title" className="min-w-0 space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4"><h5 className="text-sm font-semibold text-neutral-900" id="addendum-non-pharmacological-title">Nonfarmakologis & edukasi pasien</h5><div className="grid gap-4 md:grid-cols-2"><Field error={addendumForm.errors.non_farmakologis} htmlFor="addendum-non-farmakologis" label="Koreksi terapi nonfarmakologis"><Textarea id="addendum-non-farmakologis" onChange={(event) => addendumForm.setData('non_farmakologis', event.target.value)} rows={4} value={addendumForm.data.non_farmakologis} /></Field><Field error={addendumForm.errors.edukasi} htmlFor="addendum-edukasi" label="Edukasi / anjuran"><Textarea id="addendum-edukasi" placeholder="Anjuran atau edukasi yang diberikan" onChange={(event) => addendumForm.setData('edukasi', event.target.value)} rows={4} value={addendumForm.data.edukasi} /></Field></div></section>
                                <section aria-labelledby="addendum-follow-up-title" className="min-w-0 space-y-3 rounded-xl border border-neutral-200 bg-neutral-50 p-4"><h5 className="text-sm font-semibold text-neutral-900" id="addendum-follow-up-title">Kontrol & catatan dokter</h5><Field error={addendumForm.errors.kontrol_berikutnya} htmlFor="addendum-kontrol" label="Kontrol berikutnya"><DatePicker id="addendum-kontrol" onChange={(event) => addendumForm.setData('kontrol_berikutnya', event.target.value)} value={addendumForm.data.kontrol_berikutnya} /></Field><Field error={addendumForm.errors.catatan} htmlFor="addendum-catatan" label="Catatan dokter"><Textarea id="addendum-catatan" placeholder="Catatan klinis tambahan" onChange={(event) => addendumForm.setData('catatan', event.target.value)} rows={3} value={addendumForm.data.catatan} /></Field></section>
                            </div>
                        </section>
                        <section className="space-y-4 border-t border-neutral-200 pt-4">
                            <h4 className="text-sm font-semibold">Koreksi pengkajian sistem dan evaluasi akhir</h4>
                            {!isDental && (hasNurseSystemAssessment
                                ? <NursingSystemReview prefix="addendum-review_of_systems" source={visit.screening?.systemAssessment ?? {}} value={addendumForm.data.review_of_systems} errors={addendumForm.errors} onChange={value => addendumForm.setData('review_of_systems', value)} />
                                : showDoctorSystemReview ? <SystemAssessment doctor prefix="addendum-review_of_systems" value={addendumForm.data.review_of_systems} errors={addendumForm.errors} onChange={value => addendumForm.setData('review_of_systems', value)} /> : null)}
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field htmlFor="addendum-general-kondisi_akhir" label="Kondisi akhir" error={addendumForm.errors.kondisi_akhir}><Textarea id="addendum-general-kondisi_akhir" value={addendumForm.data.kondisi_akhir} onChange={event => addendumForm.setData('kondisi_akhir', event.target.value)} /></Field>
                                <Field htmlFor="addendum-disposition" label="Status akhir pelayanan" error={addendumForm.errors.disposisi}><Select id="addendum-disposition" value={addendumForm.data.disposisi} onChange={event => addendumForm.setData('disposisi', event.target.value)}><option value="">Belum ditetapkan</option><option value="pulang">Pulang</option><option value="observasi">Observasi</option><option value="rujukan">Rujukan</option></Select></Field>
                            </div>
                        </section>
                        <div className="flex justify-end"><Button disabled={!canSaveAddendum} type="submit">Simpan addendum</Button></div>
                    </form></fieldset>}
                </CardContent></Card>}

                <ScreeningVersionHistory versions={visit.screening?.versions} />
                {!!(visit.attachments?.length || visit.attachmentArchive?.length) && <Card><CardHeader><CardTitle>Lampiran pasien</CardTitle><CardDescription>Dokumen pendukung yang dibawa pasien pada kunjungan ini.</CardDescription></CardHeader><CardContent><VisitAttachments archive={visit.attachmentArchive} attachments={visit.attachments} /></CardContent></Card>}
                <PatientContextBar
                    age={visit.patient.age}
                    allergyText={allergyWarning ?? examinationForm.data.riwayat_alergi ?? visit.patient.allergies ?? visit.screening?.allergyHistory}
                    bloodPressure={visit.screening && (visit.screening.systolic !== null || visit.screening.diastolic !== null) ? formatBloodPressure(visit.screening.systolic, visit.screening.diastolic) : null}
                    draftStatus={draftSaveStatus || null}
                    gender={visit.patient.gender}
                    maternalRisk={Boolean(visit.patient.maternal?.hasRisk)}
                    maternalSummary={visit.patient.maternal?.summaryText}
                    medicalRecordNumber={visit.patient.medicalRecordNumber}
                    oxygenSaturation={visit.screening?.oxygenSaturation}
                    patientName={visit.patient.name}
                    pulse={visit.screening?.pulse}
                    temperature={visit.screening?.temperature}
                    triage={visit.screening?.triage}
                    unsavedCount={unsavedSections.length}
                    visitNumber={visit.number}
                    weight={visit.screening?.weight}
                />
                <Card className="scroll-mt-6 overflow-hidden" id="pemeriksaan-sections">
                    <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as Tab)}>
                    <TabsList aria-label="Bagian pemeriksaan">
                        {showSoapTab && <TabsTrigger className="min-h-12 px-3 sm:px-4" value="soap"><FileText aria-hidden="true" className="size-4" />SOAP langsung</TabsTrigger>}
                        {tabs.map(renderTabTrigger)}
                    </TabsList>

                    {showSoapTab && <TabsContent className="space-y-5 p-5 sm:p-6" value="soap">
                        <div>
                            <h3 className="text-base font-semibold text-neutral-950">Catatan SOAP</h3>
                            <p className="mt-1 text-sm text-neutral-600">Isi narasi dalam satu layar. Diagnosis berkode, tindakan, dan resep tercatat melalui bagian terkait.</p>
                        </div>
                        <fieldset className="grid min-w-0 gap-4 lg:grid-cols-2" disabled={!canEdit || saving}>
                            <section className="space-y-4 rounded-xl border border-neutral-200 p-4">
                                <h4 className="font-semibold">S · Subjektif</h4>
                                <Field htmlFor="soap-complaint" label="Keluhan utama" required error={examinationForm.errors.anamnesis}>
                                    <Textarea id="soap-complaint" maxLength={10000} rows={2} value={examinationForm.data.anamnesis} onChange={(event) => examinationForm.setData('anamnesis', event.target.value)} />
                                </Field>
                                <Field htmlFor="soap-rps" label="Riwayat penyakit sekarang" error={examinationForm.errors.riwayat_penyakit_sekarang}>
                                    <Textarea id="soap-rps" maxLength={10000} rows={3} placeholder="Catat riwayat yang ditanyakan langsung kepada pasien" value={examinationForm.data.riwayat_penyakit_sekarang} onChange={(event) => examinationForm.setData('riwayat_penyakit_sekarang', event.target.value)} />
                                </Field>
                                <ConfirmedHistoryField previousValues={previousHistoryValues.current} id="soap-allergy" kind="allergy" label="Riwayat alergi" placeholder="Obat, makanan, dan reaksi alergi" value={examinationForm.data.riwayat_alergi} onChange={(value) => examinationForm.setData('riwayat_alergi', value)} error={examinationForm.errors.riwayat_alergi} />
                                {(showDoctorComplaintDetails || showDoctorSystemReview || showDoctorDentalPain) && (
                                    <div className="rounded-lg border border-neutral-200 bg-neutral-50/70 p-3 text-xs text-neutral-600">
                                        <p className="font-medium text-neutral-800">Anamnesis terstruktur (sesuai pengaturan klinik)</p>
                                        <p className="mt-1">
                                            {[
                                                showDoctorComplaintDetails ? `Rincian keluhan: ${countDoctorAssessmentFields(examinationForm.data.clinical_assessment?.complaint_details as Record<string, unknown>)} terisi` : null,
                                                showDoctorSystemReview ? `Pengkajian sistem (ROS): ${Object.values(examinationForm.data.review_of_systems ?? {}).filter((entry) => entry?.status && entry.status !== 'not_assessed').length} sistem dikaji` : null,
                                                showDoctorDentalPain ? `Rincian nyeri gigi: ${countDoctorAssessmentFields(examinationForm.data.clinical_assessment?.dental_pain as Record<string, unknown>)} terisi` : null,
                                            ].filter(Boolean).join(' · ')}
                                        </p>
                                    </div>
                                )}
                                <Button type="button" size="sm" variant="secondary" onClick={() => navigateSection('anamnesis')}>{(showDoctorComplaintDetails || showDoctorSystemReview || showDoctorDentalPain) ? 'Lengkapi anamnesis & ROS' : 'Riwayat lain'}</Button>
                            </section>
                            <section className="space-y-4 rounded-xl border border-neutral-200 p-4">
                                <h4 className="font-semibold">O · Objektif</h4>
                                <Field htmlFor="soap-physical" label={isDental ? 'Temuan intraoral' : 'Temuan pemeriksaan fisik'} error={physicalForm.errors.pemeriksaan_fisik}>
                                    <Textarea id="soap-physical" maxLength={10000} rows={4} value={physicalForm.data.pemeriksaan_fisik} onChange={(event) => {
                                        if (isDental) intraoralEdited.current = true;
                                        setPhysicalData('pemeriksaan_fisik', event.target.value);
                                    }} />
                                </Field>
                                {isDental && <Field htmlFor="soap-extraoral" label="Temuan ekstraoral" error={physicalForm.errors.pemeriksaan_ekstraoral}>
                                    <Textarea id="soap-extraoral" maxLength={5000} rows={3} value={physicalForm.data.pemeriksaan_ekstraoral} onChange={(event) => physicalForm.setData('pemeriksaan_ekstraoral', event.target.value)} />
                                </Field>}
                                {isDental && visit.examination?.odontogramSummary && <p className="whitespace-pre-wrap text-sm">{visit.examination.odontogramSummary}</p>}
                                {showDoctorMucosa && countDoctorAssessmentFields(physicalForm.data.clinical_assessment?.mucosa_observation as Record<string, unknown>) > 0 && (
                                    <p className="text-xs text-neutral-600">Observasi mukosa mulut (dokter): {countDoctorAssessmentFields(physicalForm.data.clinical_assessment?.mucosa_observation as Record<string, unknown>)} bagian tercatat.</p>
                                )}
                                {!isDental && Object.values(visit.examination?.physicalSystems ?? {}).some(Boolean) && <SoapEntries entries={Object.values(visit.examination?.physicalSystems ?? {}).filter((item): item is string => Boolean(item))} empty="" />}
                                {!isDental && Boolean(visit.examination?.bodyMap?.findings.length) && <SoapEntries entries={visit.examination?.bodyMap?.findings.map(describeGeneralFinding) ?? []} empty="" />}
                                <Button type="button" size="sm" variant="secondary" onClick={() => navigateSection(physicalTab)}>{isDental ? 'Buka odontogram' : 'Buka pemeriksaan interaktif'}</Button>
                            </section>
                            <section className="space-y-4 rounded-xl border border-neutral-200 p-4">
                                <h4 className="font-semibold">A · Asesmen</h4>
                                <SoapEntries entries={visit.diagnoses.map((item) => [item.code, item.name].filter(Boolean).join(' · '))} empty="Diagnosis belum tercatat." />
                                <Field htmlFor="soap-differential" label="Diagnosis banding" error={differentialForm.errors.diagnosis_banding}>
                                    <Textarea id="soap-differential" maxLength={5000} rows={3} value={differentialForm.data.diagnosis_banding} onChange={(event) => differentialForm.setData('diagnosis_banding', event.target.value)} />
                                </Field>
                                <Button type="button" size="sm" variant="secondary" onClick={() => advanceSection('soap', 'diagnosa')}>Lanjut ke Diagnosis</Button>
                            </section>
                            <section className="space-y-4 rounded-xl border border-neutral-200 p-4">
                                <h4 className="font-semibold">P · Rencana</h4>
                                <SoapEntries entries={visit.treatments.map((item) => item.name)} empty="Tindakan belum tercatat." />
                                {visit.prescription.items.length > 0 && <SoapEntries entries={visit.prescription.items.map((item) => [item.name, `${item.quantity}${item.unit ? ` ${item.unit}` : ''}`, item.instructions, item.external ? 'Resep luar' : null].filter(Boolean).join(' · '))} empty="" />}
                                <Field htmlFor="soap-plan" label="Rencana dan tindak lanjut" error={followUpForm.errors.catatan}>
                                    <Textarea id="soap-plan" maxLength={5000} rows={3} value={followUpForm.data.catatan} onChange={(event) => followUpForm.setData('catatan', event.target.value)} />
                                </Field>
                                <Field htmlFor="soap-education" label="Edukasi / anjuran" error={followUpForm.errors.edukasi}>
                                    <Textarea id="soap-education" maxLength={5000} rows={2} value={followUpForm.data.edukasi} onChange={(event) => followUpForm.setData('edukasi', event.target.value)} />
                                </Field>
                                <Field htmlFor="soap-nonpharmacological" label="Perawatan tanpa obat" error={followUpForm.errors.non_farmakologis}>
                                    <Textarea id="soap-nonpharmacological" maxLength={5000} rows={2} value={followUpForm.data.non_farmakologis} onChange={(event) => followUpForm.setData('non_farmakologis', event.target.value)} />
                                </Field>
                                <Field htmlFor="soap-control" label="Jadwal kontrol berikutnya" error={followUpForm.errors.kontrol_berikutnya}>
                                    <DatePicker id="soap-control" value={followUpForm.data.kontrol_berikutnya} onChange={(event) => followUpForm.setData('kontrol_berikutnya', event.target.value)} />
                                </Field>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Field htmlFor="soap-final-condition" label="Kondisi akhir kunjungan" error={followUpForm.errors.kondisi_akhir}>
                                        <Input id="soap-final-condition" placeholder="Stabil / membaik / perlu observasi" value={followUpForm.data.kondisi_akhir} onChange={(event) => followUpForm.setData('kondisi_akhir', event.target.value)} />
                                    </Field>
                                    <Field htmlFor="soap-disposition" label="Status akhir pelayanan" error={followUpForm.errors.disposisi}>
                                        <Select id="soap-disposition" value={followUpForm.data.disposisi} onChange={(event) => followUpForm.setData('disposisi', event.target.value)}>
                                            <option value="">Belum ditetapkan</option>
                                            <option value="pulang">Pulang</option>
                                            <option value="observasi">Observasi</option>
                                            <option value="rujukan">Rujukan</option>
                                        </Select>
                                    </Field>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button type="button" size="sm" variant="secondary" onClick={() => advanceSection('soap', 'tindakan')}>Tindakan</Button>
                                    <Button type="button" size="sm" variant="secondary" onClick={() => advanceSection('soap', 'resep')}>Resep klinik</Button>
                                </div>
                            </section>
                        </fieldset>
                        {canEdit && <div className="flex flex-wrap justify-end gap-2">
                            <Button disabled={saving || !draftTasks.some((task) => task.eligible)} type="button" variant="secondary" onClick={() => saveClinicalDraft()}>Simpan draf SOAP</Button>
                            <Button disabled={saving || isAdminAssistance} type="button" onClick={requestExaminationFinish}><Check className="size-4" />Selesai periksa</Button>
                        </div>}
                    </TabsContent>}

                    <TabsContent className="space-y-5 p-5 sm:p-6" value={physicalTab}>
                        {physicalNurseNotesDismissed && <Button type="button" size="sm" variant="ghost" onClick={() => setPhysicalNurseNotesDismissed(false)}>Tampilkan acuan perawat</Button>}

                    <div><h3 className="flex items-center gap-2 text-base font-semibold text-neutral-950"><Stethoscope aria-hidden="true" className="size-5 shrink-0 text-neutral-600" />{isDental ? 'Pemeriksaan fisik & intraoral dokter' : 'Pemeriksaan fisik'}</h3><p className="mt-1 text-sm text-neutral-500">Dokumentasikan temuan objektif yang diperiksa, lalu simpan hasil pemeriksaan.</p></div>
                    <fieldset className="min-w-0" disabled={saving}><form className="space-y-5" onSubmit={(event) => submitClinicalSection(event, 'pemeriksaan')}>
                        {showRepeatVitals && <section className="space-y-3 rounded-xl border border-neutral-200 bg-neutral-50/70 p-4" aria-label="Pengukuran ulang tanda vital dokter">
                            <div>
                                <h4 className="text-sm font-semibold text-neutral-900">Pengukuran ulang tanda vital (opsional)</h4>
                                <p className="mt-0.5 text-xs text-neutral-500">Catat bila dokter melakukan pemeriksaan ulang tekanan darah, nadi, atau suhu setelah pengkajian awal perawat.</p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                <Field error={physicalForm.errors.td_sistole_ulang} htmlFor="td-sistole-ulang" label="TD sistole ulang (mmHg)">
                                    <Input disabled={!canEdit} id="td-sistole-ulang" inputMode="numeric" max={300} min={40} onChange={(event) => physicalForm.setData('td_sistole_ulang', event.target.value)} placeholder="Contoh: 120" type="number" value={physicalForm.data.td_sistole_ulang} />
                                </Field>
                                <Field error={physicalForm.errors.td_diastole_ulang} htmlFor="td-diastole-ulang" label="TD diastole ulang (mmHg)">
                                    <Input disabled={!canEdit} id="td-diastole-ulang" inputMode="numeric" max={200} min={20} onChange={(event) => physicalForm.setData('td_diastole_ulang', event.target.value)} placeholder="Contoh: 80" type="number" value={physicalForm.data.td_diastole_ulang} />
                                </Field>
                                <Field error={physicalForm.errors.nadi_ulang} htmlFor="nadi-ulang" label="Nadi ulang (x/menit)">
                                    <Input disabled={!canEdit} id="nadi-ulang" inputMode="numeric" max={250} min={20} onChange={(event) => physicalForm.setData('nadi_ulang', event.target.value)} placeholder="Contoh: 78" type="number" value={physicalForm.data.nadi_ulang} />
                                </Field>
                                <Field error={physicalForm.errors.suhu_ulang} htmlFor="suhu-ulang" label="Suhu ulang (°C)">
                                    <Input disabled={!canEdit} id="suhu-ulang" max={45} min={30} onChange={(event) => physicalForm.setData('suhu_ulang', event.target.value)} placeholder="Contoh: 36.8" step="0.1" type="number" value={physicalForm.data.suhu_ulang} />
                                </Field>
                            </div>
                        </section>}
                        {!isDental && <section className="space-y-4"><PhysicalExaminationWorkspace
                            onPrepareDiagnosis={(finding) => {
                                if (bodyFindingPending) {
                                    toast.info('Tambahkan atau kosongkan temuan yang masih terbuka sebelum melanjutkan.');
                                    return;
                                }
                                saveClinicalDraft(false, ['pemeriksaan'], () => searchDiagnosisFromFinding(generalFindingSearch(finding.finding), describeGeneralFinding(finding)));
                            }}
                            bodyMap={physicalForm.data.body_map}
                            onBodyMapChange={(data) => physicalForm.setData('body_map', data)}
                            systems={physicalForm.data.pemeriksaan_fisik_terstruktur}
                            onChange={(systems) => physicalForm.setData('pemeriksaan_fisik_terstruktur', systems)}
                            draft={bodyFindingDraft}
                            onDraftChange={setBodyFindingDraft}
                            onPendingChange={setBodyFindingPending}
                            errors={physicalForm.errors}
                            disabled={!canEdit || saving}
                            gender={visit.patient.gender}
                        /></section>}

                        <Field error={physicalForm.errors.pemeriksaan_fisik} htmlFor="pemeriksaan_fisik" label={isDental ? 'Pemeriksaan intraoral' : 'Ringkasan pemeriksaan fisik (opsional)'}>
                            <Textarea aria-describedby={isDental && canEdit ? 'intraoral-source' : undefined} disabled={!canEdit} id="pemeriksaan_fisik" maxLength={intraoralNarrativeLimit} onChange={(event) => {
                                if (isDental) intraoralEdited.current = true;
                                setPhysicalData('pemeriksaan_fisik', event.target.value);
                            }} placeholder={isDental ? 'Temuan mukosa, gingiva, jaringan lunak, dan oklusi yang diperiksa.' : 'Ringkasan temuan pemeriksaan fisik.'} rows={isDental ? 6 : 4} value={intraoralValue} />
                            {isDental && canEdit && <p id="intraoral-source" className="mt-2 text-sm text-neutral-600">
                                {intraoralNarrative.length > intraoralNarrativeLimit
                                    ? 'Naratif dari odontogram melebihi batas 10.000 karakter. Ringkas temuan yang relevan di kolom ini.'
                                    : 'Kolom kosong diisi dari odontogram yang sudah disimpan. Tinjau naratif dan lengkapi hanya dengan hasil yang Anda periksa.'}
                            </p>}
                        </Field>

                        {isDental && (
                            <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_14rem]">
                                <Field error={physicalForm.errors.pemeriksaan_ekstraoral} htmlFor="extraoral" label="Hasil pemeriksaan ekstraoral"><Textarea disabled={!canEdit} id="extraoral" onChange={(event) => physicalForm.setData('pemeriksaan_ekstraoral', event.target.value)} placeholder="Asimetri/pembengkakan wajah, bibir, kelenjar getah bening, bukaan mulut" rows={3} value={physicalForm.data.pemeriksaan_ekstraoral} /></Field>
                                <Field error={physicalForm.errors.oral_hygiene_index} htmlFor="oral-hygiene-index" label="Skor OHI-S (0,0 – 6,0)">
                                    <Input disabled={!canEdit} id="oral-hygiene-index" max={6} min={0} onChange={(event) => physicalForm.setData('oral_hygiene_index', event.target.value)} placeholder="Contoh: 1.5" step="0.1" type="number" value={physicalForm.data.oral_hygiene_index} />
                                    <p className="mt-1 text-xs text-neutral-500">Baik: 0–1,2 · Sedang: 1,3–3,0 · Buruk: 3,1–6,0</p>
                                </Field>
                            </div>
                        )}
                        {showDoctorMucosa && (
                            <DoctorMucosaObservationSection
                                defaultOpen={workflow.gigi_mucosa_observation === 'dokter'}
                                disabled={!canEdit || saving}
                                errors={physicalForm.errors as Record<string, string>}
                                nurseReference={workflow.gigi_mucosa_observation === 'keduanya'
                                    ? oralObservations.flatMap(([label, val]): Array<[string, string]> => (val ? [[label, val]] : []))
                                    : undefined}
                                nurseShared={workflow.gigi_mucosa_observation === 'keduanya'}
                                onChange={(mucosa_observation) => physicalForm.setData('clinical_assessment', { ...(physicalForm.data.clinical_assessment ?? {}), mucosa_observation })}
                                value={physicalForm.data.clinical_assessment?.mucosa_observation ?? {}}
                            />
                        )}


                        <div className="flex flex-wrap justify-end gap-2">{isDental && <Button type="button" variant="secondary" disabled={!canEdit || saving || dentalForm.isDirty || !physicalForm.data.pemeriksaan_fisik.trim()} onClick={prepareOralDiagnosis}>Susun diagnosis dari pemeriksaan mulut</Button>}<Button disabled={!sectionSaveAllowed.pemeriksaan} type="submit">Simpan pemeriksaan fisik</Button></div>
                    </form></fieldset>
                    {isDental && (
                <Card className="space-y-5 rounded-none border-0 border-t border-neutral-200 pt-5 shadow-none">
                    <CardHeader className="space-y-1.5 p-0 sm:p-0">
                        <CardTitle>Odontogram kunjungan (peta gigi FDI)</CardTitle>
                        <CardDescription>{canEdit ? 'Pilih gigi dan permukaan untuk mencatat temuan pemeriksaan.' : canCorrect ? 'Tinjau temuan gigi. Koreksi disimpan sebagai addendum dengan alasan perubahan.' : 'Tinjau temuan gigi yang sudah tercatat pada kunjungan ini.'}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5 p-0 sm:p-0">
                        <div className="flex flex-wrap items-center gap-2">
                                {canEdit && <Button type="button" variant="secondary" size="sm" disabled={saving || soundForm.processing || dentalForm.isDirty} onClick={() => {
                                    const isMixedDentition = visit.patient.age !== null && visit.patient.age < 14;
                                    const excludedUnerupted = isMixedDentition && dentition === 'adult' ? (visit.patient.age! < 12 ? ['17', '18', '27', '28', '37', '38', '47', '48'] : ['18', '28', '38', '48']) : [];
                                    const teeth = dentalOptions.teeth.filter((tooth) => (dentition === 'adult' ? Number(tooth[0]) <= 4 : Number(tooth[0]) > 4) && !excludedUnerupted.includes(tooth) && tooth !== dentalForm.data.tooth_fdi && !visit.odontogram.some((finding) => finding.toothFdi === tooth));
                                    if (!teeth.length) { toast.info('Tidak ada gigi lain yang belum dicatat.'); return; }
                                    const prompt = isMixedDentition
                                        ? `Pasien usia ${visit.patient.age} tahun (fase geligi bercampur). Molar belum erupsi (${excludedUnerupted.join(', ')}) telah dikecualikan otomatis. Saya mengonfirmasi ${teeth.length} gigi berikut benar-benar telah erupsi dan sehat: ${teeth.join(', ')}. Lanjutkan?`
                                        : `Saya telah memeriksa ${teeth.length} gigi berikut dan memastikan semuanya sehat: ${teeth.join(', ')}. Gigi tidak ada atau belum erupsi harus dicatat terpisah. Lanjutkan?`;
                                    confirmAction(prompt, () => { soundForm.transform(() => ({ teeth, confirmed: true, mixed_dentition_verified: isMixedDentition })); soundForm.post(`/pelayanan/pemeriksaan/${visit.id}/odontogram/sound`, { preserveScroll: true }); }, 'Tandai sehat', 'Konfirmasi hasil pemeriksaan');
                                }}>Tandai gigi lain sehat</Button>}
                                <Button onClick={() => changeDentition('adult')} size="sm" type="button" variant={dentition === 'adult' ? 'default' : 'secondary'}>Permanen · 32</Button>
                                <Button onClick={() => changeDentition('primary')} size="sm" type="button" variant={dentition === 'primary' ? 'default' : 'secondary'}>Desidui · 20</Button>
                            </div>
                        <div className="grid min-w-0 items-start gap-5 2xl:grid-cols-2"><section aria-label="Diagram odontogram" className="min-w-0 space-y-4 rounded-2xl border border-neutral-200/90 bg-neutral-50/50 p-4 sm:p-5">
                            <OdontogramFdi
                                dentition={dentition}
                                disabled={(!canEdit && !canCorrect) || saving}
                                findingLabels={dentalOptions.findings}
                                surfaceLabels={dentalOptions.surfaces}
                                findings={visit.odontogram}
                                onSelect={selectDentalTooth}
                                selectedTooth={dentalForm.data.tooth_fdi}
                            />

                        </section>
                        {(canEdit || canCorrect) && (
                            <fieldset tabIndex={-1} id="dental-action-panel" className="min-w-0 rounded-xl border border-neutral-200 p-4 sm:p-5" disabled={saving}><form className="space-y-5" onSubmit={submitDentalFinding}><div className="flex flex-wrap items-center justify-between gap-2"><h3 className="text-base font-semibold" aria-live="polite">{dentalForm.data.tooth_fdi ? `Gigi aktif ${dentalForm.data.tooth_fdi}` : 'Pilih gigi pada peta'}</h3><span className="text-xs text-neutral-500">1 layar ringkas · pilih gigi, permukaan, rincian & simpan</span></div>
                                <div className="grid min-w-0 items-start gap-4 sm:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
                                    <div className="min-w-0 sm:col-start-1 sm:row-start-1"><div className="flex items-center gap-3 rounded-xl bg-neutral-50 p-3"><span className="flex size-14 shrink-0 items-center justify-center rounded-lg bg-neutral-900 text-neutral-50 font-sans tabular-nums text-xl font-semibold" aria-label="Gigi terpilih">{dentalForm.data.tooth_fdi || <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" className="size-6"><path d="M12 5C9.5 3 6 3 4.5 5.5 2.5 9 5 13 5.5 16c.3 2 1.2 5 2.5 5 1.5 0 1-7 4-7s2.5 7 4 7c1.3 0 2.2-3 2.5-5 .5-3 3-7 1-10.5C18 3 14.5 3 12 5Z" /></svg>}</span><div className="min-w-0"><p className="text-sm font-semibold">{dentalForm.data.tooth_fdi ? `Gigi ${dentalForm.data.tooth_fdi}` : 'Belum ada gigi dipilih'}</p><p className="mt-1 text-xs leading-5 text-neutral-500">Pilih gigi pada peta atau nomor FDI di bawah.</p></div></div></div>
                                    <div className="order-4 flex min-w-0 items-stretch sm:order-none sm:col-start-2 sm:row-span-2 sm:row-start-1"><DentalClinicalControls mode="surface" tooth={dentalForm.data.tooth_fdi} surface={dentalForm.data.surface} selectedSurfaces={dentalForm.data.surfaces} finding={dentalForm.data.finding_code} details={dentalForm.data.details} disabled={!dentalForm.data.tooth_fdi || saving || (dentalOptions.wholeToothFindings ?? ['missing', 'unerupted']).includes(dentalForm.data.finding_code)} onSurface={toggleDentalSurface} onChange={(details) => dentalForm.setData('details', details)} /></div>
                                    <div className="order-2 min-w-0 sm:order-none sm:col-start-1 sm:row-start-2"><Field error={dentalForm.errors.tooth_fdi} htmlFor="dental-tooth" label="Nomor gigi FDI" required>
                                        <Select id="dental-tooth" onChange={(event) => selectDentalTooth(event.target.value)} required value={dentalForm.data.tooth_fdi}>
                                            <option value="">Pilih gigi</option>
                                            {dentalOptions.teeth.filter((tooth) => dentition === 'adult' ? Number(tooth[0]) <= 4 : Number(tooth[0]) > 4).map((tooth) => <option key={tooth} value={tooth}>{tooth}</option>)}
                                        </Select>
                                    </Field></div>
                                    <div className="order-3 min-w-0 sm:order-none sm:col-start-1 sm:row-start-3"><Field error={dentalForm.errors.finding_code} htmlFor="dental-finding" label="Temuan" required>
                                        <Select required id="dental-finding" onChange={(event) => dentalForm.setData((data) => ({ ...data, finding_code: event.target.value, details: dentalFindingDetails(event.target.value, data.details), surfaces: (dentalOptions.wholeToothFindings ?? ['missing', 'unerupted']).includes(event.target.value) ? ['W'] : data.surfaces, surface: (dentalOptions.wholeToothFindings ?? ['missing', 'unerupted']).includes(event.target.value) ? 'W' : data.surface }))} value={dentalForm.data.finding_code}>
                                            <option value="">Pilih temuan</option>{Object.entries(dentalOptions.findingGroups ?? { Temuan: Object.keys(dentalOptions.findings) }).flatMap(([group, codes]) => [<option key={group} value={`group-${group}`} disabled>── {group} ──</option>, ...codes.map(code => <option key={code} value={code}>{dentalOptions.findings[code]}</option>)])}
                                        </Select>
                                    </Field>
                                    </div>
                                    <div className="min-w-0 space-y-4"></div>
                                    <div className="order-5 min-w-0 sm:order-none sm:col-start-2 sm:row-start-3"><Field error={dentalForm.errors.surface} htmlFor="dental-surface" label="Permukaan" required>
                                        <div id="dental-surface" className="flex flex-wrap gap-2">{Object.entries(dentalOptions.surfaces).map(([code, label]) => <Button key={code} type="button" size="sm" variant={(dentalForm.data.surfaces.length ? dentalForm.data.surfaces : [dentalForm.data.surface]).includes(code) ? 'default' : 'secondary'} aria-pressed={(dentalForm.data.surfaces.length ? dentalForm.data.surfaces : [dentalForm.data.surface]).includes(code)} disabled={(dentalOptions.wholeToothFindings ?? ['missing', 'unerupted']).includes(dentalForm.data.finding_code)} title={label} aria-label={`Permukaan ${label}`} onClick={() => toggleDentalSurface(code)}>{code === 'W' ? 'Seluruh gigi' : code}</Button>)}</div>
                                    </Field></div>
                                </div>

                                <details className="group/disclosure rounded-lg border border-neutral-200 p-3" open={Boolean(Object.keys(dentalForm.data.details).some(key => ['eruption', 'position', 'morphology', 'endodontic', 'prosthesis', 'material', 'restoration_state'].includes(key)) || ['malposition', 'anomaly', 'removable_denture'].includes(dentalForm.data.finding_code))} aria-label="Kondisi dan riwayat perawatan penyerta"><summary className="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold [&::-webkit-details-marker]:hidden"><span>Kondisi & riwayat perawatan penyerta</span>{disclosureToggle}</summary>{pulpalConflictsWithPsa && <div className="mt-3 space-y-2 text-sm" role="status">
                                    <p>Diagnosis pulpa yang masih terpilih: {dentalOptions.detailOptions?.pulpal?.[pulpal] ?? pulpal}. Tinjau pilihan tersebut. Jika hanya mencatat riwayat PSA, kosongkan diagnosis pulpa pada temuan ini.</p>
                                    <Button type="button" size="sm" variant="secondary" onClick={() => {
                                        dentalForm.setData('details', { ...dentalForm.data.details, pulpal: '' });
                                        dentalForm.clearErrors('details.pulpal' as keyof typeof dentalForm.data);
                                    }}>Kosongkan diagnosis pulpa</Button>
                                </div>}<p className="my-3 text-sm text-neutral-600">Catat kondisi yang dapat menyertai temuan utama, misalnya karies pada gigi yang memakai crown atau sudah PSA. Riwayat perawatan bukan tindakan yang dilakukan pada kunjungan ini. Gunakan regio dan daftar gigi terkait untuk prostesis; tambahkan temuan khusus pada catatan.</p><div className="grid gap-4 sm:grid-cols-2">{['eruption', 'position', 'morphology', 'endodontic', 'prosthesis', 'prosthesis_region', 'material', 'restoration_state'].filter(key => dentalOptions.detailOptions?.[key]).map(key => <Field key={key} htmlFor={`dental-context-${key}`} error={(dentalForm.errors as Record<string, string>)[`details.${key}`]} label={({ eruption: 'Erupsi', position: 'Posisi gigi', morphology: 'Bentuk / ukuran', endodontic: 'Pulpa / riwayat PSA', prosthesis: 'Prostesis / implan', prosthesis_region: 'Regio prostesis (bila relevan)', material: 'Material restorasi / prostesis', restoration_state: 'Jenis restorasi' } as Record<string, string>)[key]}><Select id={`dental-context-${key}`} value={dentalForm.data.details[key] ?? ''} disabled={!dentalForm.data.tooth_fdi} onChange={event => dentalForm.setData('details', { ...dentalForm.data.details, [key]: event.target.value })}><option value="">Belum dicatat / tidak diterapkan</option>{Object.entries(dentalOptions.detailOptions?.[key] ?? {}).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>)}</div>{Boolean(dentalForm.data.details.prosthesis || ['crown', 'bridge_pontic', 'bridge_abutment', 'implant', 'removable_denture'].includes(dentalForm.data.finding_code)) && <Field className="mt-4" htmlFor="dental-prosthesis-span" label="Gigi terkait prostesis (opsional)" error={(dentalForm.errors as Record<string, string>)['details.prosthesis_span']}><Input id="dental-prosthesis-span" placeholder="Nomor FDI dipisahkan koma, misalnya 14, 15, 16" maxLength={200} value={dentalForm.data.details.prosthesis_span ?? ''} onChange={event => dentalForm.setData('details', { ...dentalForm.data.details, prosthesis_span: event.target.value })} /></Field>}</details>
                                {dentalFindingUsesNaturalTooth && <DentalClinicalControls mode="tests" tooth={dentalForm.data.tooth_fdi} surface={dentalForm.data.surface} selectedSurfaces={dentalForm.data.surfaces} finding={dentalForm.data.finding_code} details={dentalForm.data.details} disabled={!dentalForm.data.tooth_fdi || saving} onSurface={toggleDentalSurface} onChange={(details) => dentalForm.setData('details', details)} />}
                                <section className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50/50 p-4" aria-label="Rincian klinis temuan gigi">
                                    <div><h4 className="text-sm font-semibold text-neutral-900">Rincian klinis {dentalForm.data.finding_code === 'caries' ? 'karies' : 'temuan gigi'}</h4><p className="mt-1 text-sm text-neutral-600">Catat temuan dan hasil tes pada gigi yang dipilih. Bila hasilnya belum jelas, pilih belum dapat ditentukan dan tambahkan keterangan.</p></div>
                                    {dentalForm.errors.details && <p role="alert" className="text-sm text-red-600">{dentalForm.errors.details}</p>}
                                    <div className="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-3">
                                        {Object.entries(dentalOptions.detailOptions ?? {}).filter(([key]) => (dentalFindingUsesNaturalTooth && ['pulpal', 'periapical'].includes(key)) || (dentalForm.data.finding_code === 'caries' && ['activity', 'site', 'depth', 'root_severity'].includes(key))).filter(([key]) => key !== 'root_severity' || dentalForm.data.details.site === 'root').map(([key, options]) => <Field key={key} className={['pulpal', 'periapical'].includes(key) ? 'md:col-span-2 xl:col-span-3' : undefined} error={(dentalForm.errors as Record<string, string>)[`details.${key}`]} htmlFor={`dental-detail-${key}`} label={({ icdas: 'Stadium visual ICDAS', activity: 'Aktivitas lesi', site: 'Jenis / lokasi lesi', depth: 'Kedalaman hasil evaluasi', root_severity: 'Kavitas lesi akar', black_class: 'Klasifikasi Black (opsional)', pulpal: 'Diagnosis pulpa', periapical: 'Diagnosis apikal' } as Record<string, string>)[key] ?? key}>
                                            {['pulpal', 'periapical'].includes(key) ? <div id={`dental-detail-${key}`} className="flex flex-wrap gap-2">{Object.entries(options).map(([value, label]) => <Button type="button" key={value} size="sm" className="h-auto min-h-9 whitespace-normal text-left" variant={dentalForm.data.details[key] === value ? 'default' : 'secondary'} aria-pressed={dentalForm.data.details[key] === value} onClick={() => dentalForm.setData('details', { ...dentalForm.data.details, [key]: dentalForm.data.details[key] === value ? '' : value })}>{label}</Button>)}</div> : <Select id={`dental-detail-${key}`} onChange={(event) => dentalForm.setData('details', { ...dentalForm.data.details, [key]: event.target.value })} value={dentalForm.data.details[key] ?? ''}><option value="">Belum dicatat</option>{Object.entries(options).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select>}
                                        </Field>)}
                                    </div>
                                    {dentalForm.data.finding_code === 'caries' && <details className="group/disclosure rounded-lg border border-neutral-200 p-3"><summary className="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold [&::-webkit-details-marker]:hidden"><span>ICDAS dan klasifikasi Black (opsional) · {dentalForm.data.details.icdas ? `Skor ${dentalForm.data.details.icdas}` : 'Tidak diisi'}</span>{disclosureToggle}</summary><p className="my-3 text-sm text-neutral-600">Isi bila dinilai dalam pemeriksaan. Skor ICDAS dan klasifikasi Black dapat dilewati; rincian lesi di atas tetap dicatat sesuai temuan.</p><div className="grid gap-4 sm:grid-cols-2">{Object.entries(dentalOptions.detailOptions ?? {}).filter(([key]) => ['icdas', 'black_class'].includes(key)).filter(([key]) => key !== 'root_severity' || dentalForm.data.details.site === 'root').map(([key, options]) => <Field key={key} className={['pulpal', 'periapical'].includes(key) ? 'md:col-span-2 xl:col-span-3' : undefined} error={(dentalForm.errors as Record<string, string>)[`details.${key}`]} htmlFor={`dental-detail-${key}`} label={({ icdas: 'Stadium visual ICDAS', activity: 'Aktivitas lesi', site: 'Jenis / lokasi lesi', depth: 'Kedalaman hasil evaluasi', root_severity: 'Kavitas lesi akar', black_class: 'Klasifikasi Black (opsional)', pulpal: 'Diagnosis pulpa', periapical: 'Diagnosis apikal' } as Record<string, string>)[key] ?? key}>
                                        {['pulpal', 'periapical'].includes(key) ? <div id={`dental-detail-${key}`} className="flex flex-wrap gap-2">{Object.entries(options).map(([value, label]) => <Button type="button" key={value} size="sm" className="h-auto min-h-9 whitespace-normal text-left" variant={dentalForm.data.details[key] === value ? 'default' : 'secondary'} aria-pressed={dentalForm.data.details[key] === value} onClick={() => dentalForm.setData('details', { ...dentalForm.data.details, [key]: dentalForm.data.details[key] === value ? '' : value })}>{label}</Button>)}</div> : <Select id={`dental-detail-${key}`} onChange={(event) => dentalForm.setData('details', { ...dentalForm.data.details, [key]: event.target.value })} value={dentalForm.data.details[key] ?? ''}><option value="">Belum dicatat</option>{Object.entries(options).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select>}
                                    </Field>)}</div></details>}
                                    <Field error={(dentalForm.errors as Record<string, string>)['details.assessment']} htmlFor="dental-detail-assessment" label="Hasil pemeriksaan dan rencana perawatan">
                                        <Textarea id="dental-detail-assessment" maxLength={3000} rows={3} placeholder="Catat hasil tes, kondisi gigi dan jaringan sekitar, serta rencana perawatan. Tambahkan hasil radiograf bila tersedia." onChange={(event) => dentalForm.setData('details', { ...dentalForm.data.details, assessment: event.target.value.startsWith(assessmentLead) ? event.target.value.slice(assessmentLead.length) : event.target.value })} value={assessmentLead + assessmentManual} />
                                    </Field>
                                    {dentalForm.data.finding_code === 'caries' && <p className="text-sm text-neutral-600">Lengkapi rincian karies sesuai temuan, lalu catat diagnosis pada tab Diagnosis.</p>}
                                </section>
                                <Field error={dentalForm.errors.notes} htmlFor="dental-notes" label="Catatan temuan" className="border-t border-neutral-200 pt-4">
                                    <Textarea id="dental-notes" placeholder="Tambahkan keterangan bila diperlukan" onChange={(event) => dentalForm.setData('notes', event.target.value)} rows={2} value={dentalForm.data.notes} />
                                </Field>
                                {!canEdit && (
                                    <>
                                        <Field error={dentalForm.errors.reason} htmlFor="dental-reason" label="Alasan addendum" required>
                                            <Textarea placeholder="Jelaskan alasan perubahan catatan gigi" id="dental-reason" minLength={10} onChange={(event) => dentalForm.setData('reason', event.target.value)} required rows={2} value={dentalForm.data.reason} />
                                        </Field>
                                        {isAfter48Hours && <div className="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950"><label className="flex cursor-pointer items-start gap-2"><Checkbox checked={dentalForm.data.late_correction_acknowledged} onCheckedChange={(checked) => dentalForm.setData('late_correction_acknowledged', checked === true)} /><span>Konfirmasi koreksi odontogram melewati batas 2×24 jam (PMK 24/2022 Pasal 37).</span></label>{dentalForm.errors.late_correction_acknowledged && <p role="alert" className="text-red-700">{dentalForm.errors.late_correction_acknowledged}</p>}</div>}
                                    </>
                                )}
                                <div className="flex flex-wrap justify-end gap-2">
                                    <Button disabled={dentalForm.processing} onClick={() => changeDentition(dentition)} type="button" variant="secondary">Kosongkan isian</Button>
                                    <Button disabled={!canSaveDentalFinding} type="submit">{dentalForm.processing ? 'Menyimpan…' : canEdit ? visit.odontogram.some((finding) => finding.toothFdi === dentalForm.data.tooth_fdi && finding.surface === dentalForm.data.surface) ? 'Perbarui temuan' : 'Simpan temuan' : 'Simpan addendum odontogram'}</Button>
                                </div>
                            </form></fieldset>
                        )}

                        </div>
                        <form className="space-y-3 border-t border-neutral-200 pt-5" onSubmit={event => {
                            event.preventDefault();
                            if (!canEdit || saving || !odontogramSummaryForm.isDirty) return;
                            confirmSave('Simpan ringkasan odontogram', () => odontogramSummaryForm.post(`/pelayanan/pemeriksaan/${visit.id}`, {
                                preserveScroll: true, preserveState: true,
                                onSuccess: () => odontogramSummaryForm.setDefaults(odontogramSummaryForm.data),
                            }));
                        }}>
                            <Field error={odontogramSummaryForm.errors.ringkasan_odontogram} htmlFor="ringkasan-odontogram" label="Ringkasan odontogram kunjungan (opsional)">
                                <Textarea id="ringkasan-odontogram" rows={3} maxLength={5000} disabled={!canEdit || saving} value={odontogramSummaryForm.data.ringkasan_odontogram} onChange={event => odontogramSummaryForm.setData('ringkasan_odontogram', event.target.value)} placeholder="Ringkas temuan odontogram yang benar-benar diperiksa pada kunjungan ini" />
                            </Field>
                            {canEdit && <div className="flex justify-end"><Button type="submit" disabled={saving || !odontogramSummaryForm.isDirty}>Simpan ringkasan odontogram</Button></div>}
                        </form>
                        <section aria-labelledby="odontogram-findings-title" className="space-y-3 border-t border-neutral-200 pt-5">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div><h3 className="flex items-center gap-2 text-sm font-semibold text-neutral-900" id="odontogram-findings-title"><ClipboardPlus aria-hidden="true" className="size-4 shrink-0" />Tabel temuan odontogram</h3><p className="mt-1 text-sm text-neutral-600">Pilih temuan pada gigi yang akan ditangani, lalu lanjutkan ke diagnosis sebelum mencatat tindakan. Setiap gigi dihitung satu kali meskipun beberapa permukaan dipilih. Temuan bermasalah ditampilkan lebih dahulu; gigi sehat paling bawah.</p></div>
                                <div className="flex flex-wrap gap-2">{(canEdit || canCorrect) && visit.odontogram.length > 0 && <Button onClick={() => {
                                    const nextFindings = selectedDentalFindingKeys.length === visit.odontogram.length ? [] : visit.odontogram;
                                    setSelectedDentalFindingKeys(nextFindings.map((finding) => `${finding.toothFdi}-${finding.surface}`));
                                    treatmentForm.setData('tooth_fdis', selectedOdontogramTeeth(nextFindings, nextFindings.map((finding) => `${finding.toothFdi}-${finding.surface}`)));
                                }} size="sm" type="button" variant="secondary">{selectedDentalFindingKeys.length === visit.odontogram.length ? 'Batal pilih semua' : 'Pilih semua temuan'}</Button>}{(canEdit || canCorrect) && visit.odontogram.length > 0 && <Button disabled={saving || selectedDentalFindingKeys.length === 0} onClick={() => { odontogramUndoForm.setData({ tooth_fdi: '', surface: '', reason: '', findings: visit.odontogram.filter((finding) => selectedDentalFindingKeys.includes(`${finding.toothFdi}-${finding.surface}`)).map((finding) => ({ tooth_fdi: finding.toothFdi, surface: finding.surface })), confirmed: true }); setUndoFinding(null); }} size="sm" type="button" variant="destructiveOutline">Hapus temuan terpilih{selectedDentalFindingKeys.length > 0 ? ` (${selectedDentalFindingKeys.length})` : ''}</Button>}</div>
                            </div>
                            <div className="overflow-x-auto"><Table aria-label="Tabel temuan odontogram" className="min-w-[48rem] table-fixed"><TableHeader><TableRow><TableHead className="w-16 px-2">Pilih</TableHead><TableHead className="w-20 px-2">Gigi FDI</TableHead><TableHead className="w-28 px-2">Permukaan</TableHead><TableHead className="w-32 text-left">Temuan</TableHead><TableHead className="text-left">Catatan</TableHead><TableHead className="w-48">Aksi</TableHead></TableRow></TableHeader><TableBody>
                                {visit.odontogram.length ? odontogramPagination.items.map((finding) => <TableRow key={`${finding.toothFdi}-${finding.surface}`}>
                                    <TableCell><Checkbox aria-label={`Pilih temuan gigi ${finding.toothFdi} permukaan ${finding.surface}`} checked={selectedDentalFindingKeys.includes(`${finding.toothFdi}-${finding.surface}`)} disabled={(!canEdit && !canCorrect) || saving} onCheckedChange={(checked) => setDentalFindingSelection(finding, checked === true)} /></TableCell>
                                    <TableCell className="font-sans tabular-nums font-semibold">{finding.toothFdi}</TableCell>
                                    <TableCell>{dentalOptions.surfaces[finding.surface] ?? finding.surface}</TableCell>
                                    <TableCell className="whitespace-normal text-left [overflow-wrap:anywhere]">{dentalOptions.findings[finding.findingCode] ?? finding.findingCode}</TableCell>
                                    <TableCell className="whitespace-normal text-left [overflow-wrap:anywhere]">
                                        {canEdit && Boolean(cariesDetailsByFinding.get(finding)?.length) && <p className="mb-2 whitespace-normal text-xs leading-5 text-amber-800">
                                            <strong>Rincian karies belum lengkap</strong>
                                            <span className="block">{cariesDetailsByFinding.get(finding)?.join(', ')}</span>
                                            {finding.surface === 'W' && <span className="block">Simpan permukaan spesifik untuk mengganti catatan karies seluruh gigi.</span>}
                                        </p>}
                                        <RecordedTableNote parts={[finding.detailSummary, finding.notes]} />
                                    </TableCell>
                                    <TableCell><div className="flex flex-wrap items-center justify-center gap-2">
                                        {canEdit && dentalDiagnosisGroups.find(group => group.toothFdi === finding.toothFdi)?.findings[0] === finding && <Button size="sm" type="button" variant="secondary" disabled={saving || isDentalDiagnosisRecorded(dentalDiagnosisGroups.find(group => group.toothFdi === finding.toothFdi)!, dentalDiagnosisGroups.find(group => group.toothFdi === finding.toothFdi)!.candidates[0], visit.diagnoses)} onClick={() => {
                                            const group = dentalDiagnosisGroups.find(group => group.toothFdi === finding.toothFdi);
                                            if (group) importDentalDiagnosis(group, group.candidates[0]);
                                        }}>{isDentalDiagnosisRecorded(dentalDiagnosisGroups.find(group => group.toothFdi === finding.toothFdi)!, dentalDiagnosisGroups.find(group => group.toothFdi === finding.toothFdi)!.candidates[0], visit.diagnoses) ? 'Diagnosis sudah ditambahkan' : 'Susun diagnosis'}</Button>}
                                        <ActionButton action="edit" disabled={(!canEdit && !canCorrect) || saving} label={`Ubah temuan gigi ${finding.toothFdi} permukaan ${finding.surface}`} onClick={() => selectDentalRecord(finding.toothFdi, finding.surface)} type="button" />
                                        <ActionButton action="delete" disabled={(!canEdit && !canCorrect) || saving} label={`Batalkan temuan gigi ${finding.toothFdi} permukaan ${finding.surface}`} onClick={() => { odontogramUndoForm.setData({ tooth_fdi: finding.toothFdi, surface: finding.surface, reason: '', findings: [] as Array<{ tooth_fdi: string; surface: string }>, confirmed: true }); setUndoFinding(finding); }} type="button" />
                                    </div></TableCell>
                                </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={6}><Empty size="compact" title="Belum ada temuan odontogram" description="Catat temuan melalui input FDI di atas sebelum menyelesaikan pemeriksaan." /></TableCell></TableRow>}
                            </TableBody></Table></div>
                            <Pagination pagination={odontogramPagination} onPageChange={setOdontogramPage} />
                            {selectedDentalFindingKeys.length > 0 && <div className="space-y-1"><p className="text-sm font-medium text-neutral-700">{selectedDentalFindingKeys.length} temuan dari {treatmentForm.data.tooth_fdis.length} gigi dipilih.</p><p className="text-sm text-neutral-600">Pilihan gigi tetap tersimpan untuk pencatatan tindakan setelah diagnosis.</p></div>}
                        </section>
                    </CardContent>
                </Card>
                )}
                {renderTabNavigation(physicalTab, 'diagnosa')}
                </TabsContent>

                    <fieldset className="min-w-0" disabled={!canEdit || saving}>

                    <TabsContent className="space-y-5 p-5 sm:p-6" value="anamnesis">

                        <div><h3 className="flex items-center gap-2 text-base font-semibold text-neutral-950" id="tab-anamnesis"><Stethoscope aria-hidden="true" className="size-5 shrink-0 text-neutral-600" />Anamnesis</h3><p className="mt-1 text-sm text-neutral-500">Tinjau keluhan dan riwayat dari pengkajian perawat pada kunjungan ini, lalu lengkapi atau koreksi setelah dikonfirmasi kepada pasien. Pilih tidak ada riwayat hanya setelah dikonfirmasi.</p></div>
                        <fieldset className="min-w-0" disabled={saving}><form className="space-y-5" onSubmit={(event) => submitClinicalSection(event, 'anamnesis')}>
                            <dl className="rounded-lg border border-neutral-200 p-3"><dt className="text-sm text-neutral-600">Dokter pemeriksa</dt><dd className="mt-1 font-semibold">{examinerName ?? 'Belum ditugaskan'}</dd></dl>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={examinationForm.errors.anamnesis} htmlFor="anamnesis" label="Keluhan utama" required><Textarea id="anamnesis" onChange={(event) => examinationForm.setData('anamnesis', event.target.value)} placeholder="Catat keluhan utama yang disampaikan pasien" required rows={3} value={examinationForm.data.anamnesis} /></Field><Field error={examinationForm.errors.riwayat_penyakit_sekarang} htmlFor="rps" label="Riwayat penyakit sekarang"><Textarea id="rps" onChange={(event) => examinationForm.setData('riwayat_penyakit_sekarang', event.target.value)} placeholder="Catat langsung dari pasien: awal keluhan, durasi, perkembangan, dan faktor pemicu/pereda" rows={3} value={examinationForm.data.riwayat_penyakit_sekarang} /></Field></div>
                            {showDoctorComplaintDetails && (
                                <DoctorComplaintDetailsSection
                                    defaultOpen={workflow.umum_complaint_details === 'dokter'}
                                    disabled={!canEdit || saving}
                                    errors={examinationForm.errors as Record<string, string>}
                                    nurseReference={workflow.umum_complaint_details === 'keduanya'
                                        ? Object.entries(visit.screening?.summary ?? {})
                                            .flatMap(([label, value]): Array<[string, string]> => (
                                                value && ['Lokasi keluhan', 'Karakteristik keluhan', 'Pola dan frekuensi', 'Faktor pemicu', 'Faktor pereda', 'Perkembangan keluhan', 'Rincian perkembangan keluhan', 'Skala nyeri', 'Catatan rincian keluhan'].includes(label)
                                                    ? [[label, value]]
                                                    : []
                                            ))
                                        : undefined}
                                    nurseShared={workflow.umum_complaint_details === 'keduanya'}
                                    onChange={(complaint_details) => examinationForm.setData('clinical_assessment', { ...examinationForm.data.clinical_assessment, complaint_details })}
                                    value={examinationForm.data.clinical_assessment?.complaint_details ?? {}}
                                />
                            )}
                            {showDoctorDentalPain && (
                                <DoctorDentalPainSection
                                    defaultOpen={workflow.gigi_pain_details === 'dokter'}
                                    disabled={!canEdit || saving}
                                    errors={examinationForm.errors as Record<string, string>}
                                    nurseReference={workflow.gigi_pain_details === 'keduanya'
                                        ? ([
                                            ['Lokasi nyeri', visit.screening?.dentalPainLocation],
                                            ['Pola / durasi', visit.screening?.dentalPainDuration],
                                            ['Skala nyeri', visit.screening?.painScale != null ? `${visit.screening.painScale} / 10` : null],
                                        ] as Array<[string, string | null | undefined]>).flatMap(([label, value]): Array<[string, string]> => (value ? [[label, value]] : []))
                                        : undefined}
                                    nurseShared={workflow.gigi_pain_details === 'keduanya'}
                                    onChange={(dental_pain) => examinationForm.setData('clinical_assessment', { ...examinationForm.data.clinical_assessment, dental_pain })}
                                    value={examinationForm.data.clinical_assessment?.dental_pain ?? {}}
                                />
                            )}
                            <div className="grid gap-4 md:grid-cols-3"><ConfirmedHistoryField previousValues={previousHistoryValues.current} error={examinationForm.errors.riwayat_penyakit_dahulu} id="rpd" kind="past" label="Riwayat penyakit dahulu" onChange={(value) => examinationForm.setData('riwayat_penyakit_dahulu', value)} placeholder="Riwayat penyakit, operasi, dan pengobatan rutin" value={examinationForm.data.riwayat_penyakit_dahulu} /><ConfirmedHistoryField previousValues={previousHistoryValues.current} error={examinationForm.errors.riwayat_penyakit_keluarga} id="riwayat-keluarga" kind="family" label="Riwayat penyakit keluarga" onChange={(value) => examinationForm.setData('riwayat_penyakit_keluarga', value)} placeholder="Riwayat penyakit keluarga yang relevan" value={examinationForm.data.riwayat_penyakit_keluarga} /><ConfirmedHistoryField previousValues={previousHistoryValues.current} error={examinationForm.errors.riwayat_alergi} id="riwayat-alergi-dokter" kind="allergy" label="Riwayat alergi" onChange={(value) => examinationForm.setData('riwayat_alergi', value)} placeholder="Obat, makanan, dan reaksi alergi" value={examinationForm.data.riwayat_alergi} /></div>
                            {isDental && <section className="grid gap-4 border-t border-neutral-200 pt-4 md:grid-cols-2" aria-label="Riwayat khusus gigi">
                                {([
                                    ['riwayat_obat', 'Obat yang sedang digunakan'],
                                    ['riwayat_perawatan_gigi', 'Riwayat perawatan gigi'],
                                    ['kebiasaan_gigi', 'Kebiasaan terkait gigi dan mulut'],
                                ] as const).map(([name, label]) => <div className={name === 'riwayat_obat' ? 'md:col-span-2' : undefined} key={name}>
                                    <ConfirmedHistoryField previousValues={previousHistoryValues.current} error={examinationForm.errors[name]} id={name} label={label} kind={name === 'riwayat_obat' ? 'medication' : name === 'riwayat_perawatan_gigi' ? 'dentalTreatment' : 'dentalHabits'} placeholder={name === 'riwayat_obat' ? 'Nama obat, dosis, dan frekuensi yang diketahui' : name === 'riwayat_perawatan_gigi' ? 'Jenis perawatan, waktu, dan keluhan atau komplikasi yang pernah dialami' : 'Kebiasaan perawatan mulut, merokok, atau kebiasaan lain yang relevan'} value={examinationForm.data[name]} onChange={value => examinationForm.setData(name, value)} />
                                </div>)}
                            </section>}
                            {!isDental && <section className="space-y-4 border-t border-neutral-200 pt-4" aria-label="Riwayat pengobatan dan pengkajian sistem tubuh">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <ConfirmedHistoryField previousValues={previousHistoryValues.current} id="general-medication" placeholder="Nama obat, dosis, dan frekuensi yang diketahui" label="Obat yang sedang digunakan" kind="medication" value={examinationForm.data.riwayat_obat} onChange={value => examinationForm.setData('riwayat_obat', value)} error={examinationForm.errors.riwayat_obat} />
                                    <Field error={examinationForm.errors.riwayat_sosial} htmlFor="riwayat-sosial" label="Riwayat sosial & kebiasaan (opsional)">
                                        <Textarea id="riwayat-sosial" maxLength={3000} onChange={(event) => examinationForm.setData('riwayat_sosial', event.target.value)} placeholder="Pekerjaan, pola makan, merokok, aktivitas fisik, atau faktor lingkungan relevan" rows={3} value={examinationForm.data.riwayat_sosial} />
                                    </Field>
                                </div>
                                {showDoctorSystemReview && (
                                    <details
                                        className="group/disclosure min-w-0 overflow-hidden rounded-xl border border-neutral-200 bg-neutral-50/60"
                                        open={hasNurseSystemAssessment || hasDoctorSystemReview || Object.keys(examinationForm.errors).some((key) => key.startsWith('review_of_systems'))}
                                    >
                                        <summary className="grid cursor-pointer list-none grid-cols-[minmax(0,1fr)_auto] items-start gap-3 p-4 [&::-webkit-details-marker]:hidden">
                                            <div className="min-w-0 space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h4 className="text-sm font-semibold text-neutral-900">Pengkajian sistem tubuh (ROS)</h4>
                                                    {(() => {
                                                        const assessedCount = Object.values(examinationForm.data.review_of_systems ?? {}).filter((entry) => entry?.status && entry.status !== 'not_assessed').length;
                                                        return assessedCount > 0
                                                            ? <Badge variant="complete">{assessedCount} sistem dikaji</Badge>
                                                            : <Badge variant="default">{hasNurseSystemAssessment ? 'Verifikasi perawat' : 'Diisi dokter'}</Badge>;
                                                    })()}
                                                </div>
                                                <p className="text-xs leading-5 text-neutral-500">
                                                    {hasNurseSystemAssessment
                                                        ? 'Tinjau hasil pengkajian sistem tubuh dari perawat dan lengkapi klarifikasi evaluasi dokter.'
                                                        : 'Evaluasi sistem tubuh yang relevan dengan keluhan pasien. Sistem yang belum dikaji tetap tercatat sebagai belum dikaji.'}
                                                </p>
                                            </div>
                                            {disclosureToggle}
                                        </summary>
                                        <div className="border-t border-neutral-200 bg-surface p-4 sm:p-5">
                                            {hasNurseSystemAssessment ? (
                                                <NursingSystemReview
                                                    errors={examinationForm.errors}
                                                    onChange={(value) => examinationForm.setData('review_of_systems', value)}
                                                    prefix="review_of_systems"
                                                    source={visit.screening?.systemAssessment ?? {}}
                                                    value={examinationForm.data.review_of_systems}
                                                />
                                            ) : (
                                                <SystemAssessment
                                                    doctor
                                                    errors={examinationForm.errors}
                                                    onChange={(value) => examinationForm.setData('review_of_systems', value)}
                                                    prefix="review_of_systems"
                                                    value={examinationForm.data.review_of_systems}
                                                />
                                            )}
                                        </div>
                                    </details>
                                )}
                            </section>}
                            <div className="flex justify-end"><Button disabled={!sectionSaveAllowed.anamnesis} type="submit">Simpan anamnesis</Button></div>
                        </form></fieldset>
                        {renderTabNavigation('anamnesis', physicalTab)}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="diagnosa">
                        {isDental ? <DentalExaminationSummary
                            anamnesis={visit.examination?.anamnesis}
                            intraoralExam={visit.examination?.physicalExam} odontogramSummary={visit.examination?.odontogramSummary}
                            extraoralExam={visit.examination?.extraoralExam}
                            findings={visit.odontogram}
                            options={dentalOptions}
                            hasUnsavedChanges={examinationForm.isDirty || physicalChanged || dentalForm.isDirty || odontogramSummaryForm.isDirty}
                            onReview={() => navigateSection('odontogram')}
                        /> : <GeneralExaminationSummary
                            anamnesis={visit.examination?.anamnesis}
                            physicalExam={visit.examination?.physicalExam}
                            physicalSystems={visit.examination?.physicalSystems ?? {}}
                            bodyMap={visit.examination?.bodyMap}
                            hasUnsavedChanges={anamnesisChanged || physicalChanged || bodyFindingPending}
                            onReview={() => navigateSection('pemeriksaan')}
                        />}
                        <div><h3 className="flex items-center gap-2 text-base font-semibold text-neutral-950" id="tab-diagnosa"><Stethoscope aria-hidden="true" className="size-5 shrink-0 text-neutral-600" />Diagnosis</h3><p className="mt-1 text-sm text-neutral-500">Tetapkan diagnosis berdasarkan seluruh hasil pemeriksaan. Gunakan usulan kode sebagai bantuan, lalu tinjau uraian dan pilih kategori kunjungan.</p></div>
                        {isDental && <DentalDiagnosisBridge groups={dentalDiagnosisGroups} diagnoses={visit.diagnoses} findingLabels={dentalOptions.findings} disabled={!canEdit || saving} onImport={importDentalDiagnosis} />}
                        {!isDental && <GeneralDiagnosisSuggestions
                            bodyMap={visit.examination?.bodyMap}
                            physicalExam={visit.examination?.physicalExam}
                            physicalSystems={visit.examination?.physicalSystems}
                            disabled={!canEdit || saving}
                            adult={(visit.patient.age ?? 0) >= 18}
                            codeSystem={diagnosisCodeSystem}
                            onSearch={searchDiagnosisFromFinding}
                        />}
                        <fieldset className="min-w-0" disabled={saving}><form data-entry-form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, diagnosisForm, `/pelayanan/pemeriksaan/${visit.id}/diagnosa`)}>
                            {isDental && <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-neutral-200 bg-surface p-3" aria-label="Konteks diagnosis dari pemeriksaan"><div><p className="text-sm font-semibold">{diagnosisForm.data.tooth_fdi ? `Gigi ${diagnosisForm.data.tooth_fdi} · ${dentalSurfaceLabel(diagnosisForm.data.surfaces) || 'Tanpa permukaan spesifik'}` : 'Kondisi mulut atau pemeriksaan tanpa gigi spesifik'}</p><p className="mt-1 text-xs text-neutral-600">Gigi dan permukaan mengikuti temuan asal. Tinjau atau koreksi melalui Pemeriksaan & odontogram.</p></div><Button size="sm" type="button" variant="secondary" onClick={() => navigateSection(physicalTab)}>Tinjau pemeriksaan</Button>{diagnosisForm.errors.tooth_fdi && <p role="alert" className="w-full text-sm text-red-700">{diagnosisForm.errors.tooth_fdi}</p>}{diagnosisForm.errors.source_finding_code && <p role="alert" className="w-full text-sm text-red-700">{diagnosisForm.errors.source_finding_code}</p>}{diagnosisForm.errors.surfaces && <p role="alert" className="w-full text-sm text-red-700">{diagnosisForm.errors.surfaces}</p>}</div>}
                            {!diagnosisForm.data.is_manual && <>
                                <p className="text-xs text-neutral-600">Diagnosis normal menggunakan ICD-10 WHO dari katalog aktif. Gunakan tombol panah atas/bawah dan Enter untuk memilih cepat.</p>
                                <div className="grid gap-4 md:grid-cols-[16rem_minmax(0,1fr)]"><Field error={diagnosisForm.errors.code_system} htmlFor="diagnosis-code-system" label="Sistem kode"><Select id="diagnosis-code-system" onChange={(event) => { setDiagnosisCodeSystem(event.target.value); diagnosisForm.setData((data) => ({ ...data, code_system: event.target.value, code_release: '', kode_icd10: '', nama_diagnosa: '' })); }} value={diagnosisForm.data.code_system}>{availableDiagnosisSystems.map((system) => <option key={system.value} value={system.value}>{system.label}</option>)}</Select></Field><div className="relative"><Field htmlFor="icd-search" label="Cari kode atau uraian"><div className="relative"><Search className="absolute left-3 top-3 size-4 text-neutral-500" /><Input autoComplete="off" className="pl-9" id="icd-search" onChange={(event) => { setIcdQuery(event.target.value); setIcdActiveIndex(-1); }} onKeyDown={(event) => {
                                    if (!icdResults.length) return;
                                    if (event.key === 'ArrowDown') { event.preventDefault(); setIcdActiveIndex((prev) => (prev + 1) % icdResults.length); }
                                    else if (event.key === 'ArrowUp') { event.preventDefault(); setIcdActiveIndex((prev) => (prev <= 0 ? icdResults.length - 1 : prev - 1)); }
                                    else if (event.key === 'Enter' && icdActiveIndex >= 0 && icdResults[icdActiveIndex]) {
                                        event.preventDefault();
                                        const picked = icdResults[icdActiveIndex];
                                        chooseDiagnosis(picked.kode, picked.nama, picked.code_system, picked.release);
                                        setIcdActiveIndex(-1);
                                    } else if (event.key === 'Escape') {
                                        setIcdResults([]);
                                        setIcdActiveIndex(-1);
                                    }
                                }} placeholder="Cari kode atau nama diagnosis" value={icdQuery} /></div></Field>{(icdResults.length > 0 || icdLoading) && <div className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-xl border border-neutral-200 bg-surface">{icdLoading ? <SearchResultsSkeleton label="Mencari diagnosis…" /> : icdResults.map((result, idx) => <Button className={`h-auto w-full justify-between rounded-none border-b border-neutral-100 px-3 py-2 text-left font-normal hover:bg-neutral-50 hover:text-neutral-900 ${idx === icdActiveIndex ? 'bg-neutral-100 text-neutral-950' : ''}`} key={result.code_system + '-' + result.release + '-' + result.kode} onClick={() => { chooseDiagnosis(result.kode, result.nama, result.code_system, result.release); setIcdActiveIndex(-1); }} type="button" variant="ghost"><span className="font-sans tabular-nums font-semibold text-neutral-700">{result.kode}</span><span>{result.nama}</span></Button>)}</div>}</div></div>
                                {icdSearchFailed && <p className="text-sm text-red-600" role="alert">Pencarian diagnosis gagal. Periksa koneksi dan coba kembali.</p>}
                                {!icdLoading && !icdSearchFailed && icdQuery.trim().length >= 2 && icdResults.length === 0 && <p className="text-sm text-neutral-600" role="status">Tidak ada diagnosis yang cocok pada katalog terpilih.</p>}
                            </>}
                            <div className={'grid gap-4 ' + (diagnosisForm.data.is_manual ? 'md:grid-cols-[minmax(0,1fr)_14rem]' : 'md:grid-cols-3')}>
                                {!diagnosisForm.data.is_manual && <Field error={diagnosisForm.errors.kode_icd10} htmlFor="kode_icd10" label="Kode diagnosis" required><Input placeholder="Kode sesuai katalog terpilih" className="font-sans tabular-nums uppercase" id="kode_icd10" maxLength={100} onChange={(event) => diagnosisForm.setData('kode_icd10', event.target.value.toUpperCase())} required value={diagnosisForm.data.kode_icd10} /></Field>}
                                <Field error={diagnosisForm.errors.nama_diagnosa} htmlFor="nama_diagnosa" label={diagnosisForm.data.is_manual ? 'Jenis diagnosis' : 'Nama diagnosis'} required><Input id="nama_diagnosa" maxLength={255} onChange={(event) => diagnosisForm.setData('nama_diagnosa', event.target.value)} placeholder={diagnosisForm.data.is_manual ? 'Tuliskan diagnosis yang ditetapkan' : 'Nama diagnosis'} required value={diagnosisForm.data.nama_diagnosa} /></Field>
                                <Field error={diagnosisForm.errors.jenis} htmlFor="jenis-diagnosa" label="Kategori diagnosis"><Select id="jenis-diagnosa" onChange={(event) => diagnosisForm.setData('jenis', event.target.value)} value={diagnosisForm.data.jenis}><option value="utama">Utama</option><option value="tambahan">Tambahan</option></Select></Field>
                            </div>
                            <Field error={diagnosisForm.errors.keterangan} htmlFor="keterangan-diagnosis" label="Keterangan diagnosis"><Textarea id="keterangan-diagnosis" maxLength={2000} rows={3} value={diagnosisForm.data.keterangan} onChange={(event) => diagnosisForm.setData('keterangan', event.target.value)} placeholder="Rincian atau keterangan diagnosis yang ditetapkan" /></Field>
                            {diagnosisForm.data.is_manual && <p className="text-xs text-amber-800">Diagnosis manual disimpan sebagai unresolved. Pemeriksaan normal belum dapat ditandatangani sampai dipetakan ke ICD-10 WHO; pengecualian hanya untuk darurat atau downtime yang tercatat.</p>}
                            <p className="text-xs text-neutral-600">Memilih diagnosis utama baru akan memindahkan diagnosis utama sebelumnya menjadi diagnosis tambahan.</p>
                            <div className="flex flex-wrap items-center justify-end gap-x-4 gap-y-3 border-t border-neutral-200 pt-4"><label className="flex w-fit cursor-pointer items-center gap-2 text-sm font-medium text-neutral-800" htmlFor="diagnosis-manual"><Checkbox id="diagnosis-manual" checked={diagnosisForm.data.is_manual} onCheckedChange={(checked) => { const manual = checked === true; diagnosisForm.setData((data) => ({ ...data, is_manual: manual, kode_icd10: '', code_release: '', code_system: manual ? 'manual' : diagnosisCodeSystem })); diagnosisForm.clearErrors(); setIcdQuery(''); setIcdResults([]); }} />Input diagnosis manual</label><Button disabled={!canSubmitEntry(diagnosisForm)} type="submit"><Plus className="size-4" />Tambah diagnosis</Button></div>
                        </form></fieldset>
                        <section className="space-y-3" aria-labelledby="recorded-diagnoses-title">
                            <h4 className="flex items-center gap-2 text-sm font-semibold text-neutral-900" id="recorded-diagnoses-title"><ClipboardPlus className="size-4" />Diagnosis tercatat</h4>
                            {canCorrect && <Button asChild size="sm" variant="secondary"><Link href={`/pelayanan/pemeriksaan/${visit.id}/diagnosa/koreksi`}>Koreksi coding diagnosis</Link></Button>}
                            <div className="overflow-x-auto rounded-xl border border-neutral-200">
                                <Table aria-label="Tabel diagnosis tercatat" className={`table-fixed ${isDental ? 'min-w-[59rem]' : 'min-w-[50rem]'}`}>
                                    <TableHeader><TableRow>
                                        <TableHead className="w-32">Kode / sistem</TableHead>
                                        <TableHead className="w-48 text-left">Diagnosis</TableHead>
                                        {isDental && <TableHead className="w-32">Konteks pemeriksaan</TableHead>}
                                        <TableHead className="w-28">Kategori</TableHead>
                                        <TableHead className="text-left">Catatan</TableHead>
                                        <TableHead className="w-32 text-center">Aksi</TableHead>
                                    </TableRow></TableHeader>
                                    <TableBody>{visit.diagnoses.length ? visit.diagnoses.map(diagnosis => <TableRow key={diagnosis.id}>
                                        <TableCell className="align-top whitespace-normal [overflow-wrap:anywhere]">
                                            <span className="font-sans font-semibold tabular-nums">{diagnosis.code || 'Manual'}</span>
                                            <span className="mt-1 block text-xs text-neutral-500">{diagnosis.code ? diagnosisCodeSystems.find(system => system.value === diagnosis.codeSystem)?.label ?? diagnosis.codeSystem ?? 'Sistem belum dicatat' : 'Manual'}{diagnosis.codeRelease ? ` · ${diagnosis.codeRelease}` : ''}</span>
                                            {diagnosis.codingStatus === 'verified' && <Badge className="mt-2" variant="complete">Terverifikasi</Badge>}
                                            {diagnosis.codingStatus === 'unresolved' && <Badge className="mt-2" variant="warning">Perlu pemetaan</Badge>}
                                        </TableCell>
                                        <TableCell className="align-top whitespace-normal text-left font-medium [overflow-wrap:anywhere]">
                                            {diagnosis.name}
                                            {isDental && !diagnosisMatchesFindings(diagnosis, visit.odontogram) && <p role="status" className="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-2 text-xs font-normal text-amber-950">Temuan asal berubah. Tinjau pemeriksaan, lalu hapus dan susun ulang diagnosis ini bila perlu.</p>}
                                        </TableCell>
                                        {isDental && <TableCell className="align-top">
                                            {diagnosis.toothFdi ? <><span className="block font-semibold">Gigi {diagnosis.toothFdi}</span><span className="mt-1 block text-xs text-neutral-600">{dentalSurfaceLabel(diagnosis.surfaces ?? []) || 'Tanpa permukaan spesifik'}</span></> : <span className="text-neutral-600">Tanpa gigi spesifik</span>}
                                        </TableCell>}
                                        <TableCell className="align-top"><Badge variant={diagnosis.type === 'utama' ? 'examination' : 'default'}>{diagnosis.type === 'utama' ? 'Utama' : 'Tambahan'}</Badge></TableCell>
                                        <TableCell className="align-top text-left"><RecordedTableNote parts={[dentalDiagnosisDescription(diagnosis)]} /></TableCell>
                                        <TableCell className="align-top"><div className="flex flex-wrap items-center justify-center gap-1.5">{!isDental && <ActionButton disabled={!canEdit || saving} onClick={() => useGeneralDiagnosisForTreatment(diagnosis)} type="button" action="procedure" label={`Siapkan tindakan dari diagnosis ${diagnosis.name}`} />}{isDental && diagnosis.toothFdi && <ActionButton disabled={!canEdit || saving} onClick={() => useDentalDiagnosisForTreatment(diagnosis)} type="button" action="procedure" label={`Siapkan tindakan dari diagnosis ${diagnosis.name} gigi ${diagnosis.toothFdi}`} />}{diagnosis.type !== 'utama' && <ActionButton disabled={!canEdit || saving} onClick={() => confirmAction('Jadikan diagnosis ini sebagai diagnosis utama kunjungan? Diagnosis utama sebelumnya menjadi tambahan.', () => primaryDiagnosisForm.patch(`/pelayanan/pemeriksaan/diagnosa/${diagnosis.id}/utama`, { preserveScroll: true }), 'Jadikan utama')} type="button" action="primary" label={`Jadikan diagnosis ${diagnosis.name} sebagai utama`} />}<ActionButton disabled={!canEdit || saving} onClick={() => deleteItem(`/pelayanan/pemeriksaan/diagnosa/${diagnosis.id}`, 'Hapus diagnosis ini?')} type="button" action="delete" label={`Hapus diagnosis ${diagnosis.name}${diagnosis.toothFdi ? ` gigi ${diagnosis.toothFdi}` : ''}`} /></div></TableCell>
                                    </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={isDental ? 6 : 5}><Empty size="compact" description="Diagnosis yang sudah disimpan akan tampil di sini." title="Belum ada diagnosis" /></TableCell></TableRow>}</TableBody>
                                </Table>
                            </div>
                        </section>
                        <fieldset className="min-w-0" disabled={saving}><form className="space-y-4 rounded-xl border border-neutral-200 p-4" onSubmit={(event) => submitClinicalSection(event, 'diagnosis')}>
                            <ConfirmedHistoryField previousValues={previousHistoryValues.current} error={differentialForm.errors.diagnosis_banding} id="diagnosis-banding" label="Diagnosis banding" kind="differential" placeholder="Diagnosis alternatif yang dipertimbangkan berdasarkan pemeriksaan" value={differentialForm.data.diagnosis_banding} onChange={value => differentialForm.setData('diagnosis_banding', value)} />
                            <div className="flex justify-end"><Button disabled={!sectionSaveAllowed.diagnosis} type="submit">Simpan diagnosis banding</Button></div>
                        </form></fieldset>
                        {renderTabNavigation('diagnosa', 'tindakan', 'Lanjut ke tindakan')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="tindakan">
                        {isDental && <DentalExaminationSummary id="dental-treatment-examination-summary" anamnesis={visit.examination?.anamnesis} intraoralExam={visit.examination?.physicalExam} odontogramSummary={visit.examination?.odontogramSummary} extraoralExam={visit.examination?.extraoralExam} findings={visit.odontogram} options={dentalOptions} hasUnsavedChanges={anamnesisChanged || physicalChanged || dentalForm.isDirty || odontogramSummaryForm.isDirty} onReview={() => navigateSection('odontogram')} />}
                        {!isDental && <GeneralExaminationSummary id="general-treatment-examination-summary" anamnesis={visit.examination?.anamnesis} physicalExam={visit.examination?.physicalExam} physicalSystems={visit.examination?.physicalSystems ?? {}} bodyMap={visit.examination?.bodyMap} hasUnsavedChanges={anamnesisChanged || physicalChanged || bodyFindingPending} onReview={() => navigateSection('pemeriksaan')} />}
                        <DiagnosisSummary
                            id={isDental ? 'dental-diagnosis-summary' : 'general-diagnosis-summary'}
                            diagnoses={visit.diagnoses}
                            differentialDiagnosis={visit.examination?.differentialDiagnosis}
                            codeSystems={diagnosisCodeSystems}
                            hasUnsavedChanges={hasDiagnosisDraft || differentialForm.isDirty}
                            onReview={() => navigateSection('diagnosa')}
                        />
                        {!isDental && visit.screening && <section className="space-y-3 rounded-xl border border-neutral-200 p-4" aria-label="Konteks klinis untuk tindakan"><h4 className="text-sm font-semibold">Keluhan dan temuan yang perlu ditindaklanjuti</h4><p className="whitespace-pre-wrap text-sm">{visit.examination?.anamnesis || visit.screening.complaint || 'Keluhan belum dicatat'}</p><dl className="grid gap-3 sm:grid-cols-2">{Object.entries(visit.screening.summary ?? {}).filter(([label, value]) => value && ((label.startsWith('Pengkajian sistem - ') && value.startsWith('Positif')) || ['Masalah / kebutuhan teridentifikasi', 'Tindakan awal sesuai kewenangan', 'Tindak lanjut serah terima', 'Catatan penapisan'].includes(label))).map(([label, value]) => <div key={label}><dt className="text-xs text-neutral-500">{label}</dt><dd className="whitespace-pre-wrap text-sm">{value}</dd></div>)}</dl><p className="text-xs text-neutral-500">Tentukan tindakan dari diagnosis dan hasil evaluasi dokter. Temuan perawat tetap tersedia sebagai acuan tindak lanjut.</p></section>}
                        <div><h3 className="flex items-center gap-2 text-base font-semibold text-neutral-950" id="tab-tindakan"><Stethoscope aria-hidden="true" className="size-5 shrink-0 text-neutral-600" />Tindakan medis</h3><p className="mt-1 text-sm text-neutral-500">Pilih tindakan dan pelaksananya. Instruksi untuk perawat dikerjakan setelah pemeriksaan ditandatangani; tindakan oleh dokter dicatat jika sudah dilakukan.</p></div>
                        <fieldset className="min-w-0" disabled={saving}><form data-entry-form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, treatmentForm, `/pelayanan/pemeriksaan/${visit.id}/tindakan`)}>
                            <div className={"grid gap-4 " + (treatmentMode === 'manual' ? 'md:grid-cols-[minmax(0,1fr)_10rem]' : 'md:grid-cols-3')}>
                                {treatmentMode !== 'manual' && <Field htmlFor="treatment-entry-mode" label="Sumber tindakan">
                                    <Select id="treatment-entry-mode" onChange={(event) => changeTreatmentMode(event.target.value as 'icd9cm' | 'clinic' | 'manual')} value={treatmentMode}>
                                        <option value="icd9cm">Terminologi ICD-9-CM</option>
                                        <option value="clinic">Daftar tindakan klinik</option>
                                    </Select>
                                </Field>}
                                {treatmentMode === 'manual' ? (
                                    <Field error={treatmentForm.errors.nama_tindakan_manual} htmlFor="nama-tindakan-manual" label="Jenis tindakan" required>
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
                                            <Search aria-hidden="true" className="absolute left-3 top-3 size-4 text-neutral-500" />
                                            <Input autoComplete="off" className="pl-9" disabled={!procedureCatalogAvailable} id="procedure-search" maxLength={100} onChange={(event) => {
                                                setProcedureQuery(event.target.value);
                                                setProcedureActiveIndex(-1);
                                                setSelectedProcedure(null);
                                                treatmentForm.setData((data) => ({ ...data, terminology_id: '', tindakan_id: '' }));
                                            }} onKeyDown={(event) => {
                                                if (!procedureResults.length) return;
                                                if (event.key === 'ArrowDown') { event.preventDefault(); setProcedureActiveIndex((prev) => (prev + 1) % procedureResults.length); }
                                                else if (event.key === 'ArrowUp') { event.preventDefault(); setProcedureActiveIndex((prev) => (prev <= 0 ? procedureResults.length - 1 : prev - 1)); }
                                                else if (event.key === 'Enter' && procedureActiveIndex >= 0 && procedureResults[procedureActiveIndex]) {
                                                    event.preventDefault();
                                                    const picked = procedureResults[procedureActiveIndex];
                                                    setSelectedProcedure(picked);
                                                    setProcedureQuery(`${picked.code} · ${picked.display}`);
                                                    setProcedureResults([]);
                                                    setProcedureActiveIndex(-1);
                                                    treatmentForm.setData('terminology_id', picked.id.toString());
                                                    treatmentForm.clearErrors('terminology_id', 'tindakan_id');
                                                } else if (event.key === 'Escape') {
                                                    setProcedureResults([]);
                                                    setProcedureActiveIndex(-1);
                                                }
                                            }} placeholder="Cari kode atau uraian tindakan" value={procedureQuery} />
                                        </div>
                                    </Field>
                                    {procedureCatalogAvailable && procedureQuery.trim().length >= 2 && !selectedProcedure && (
                                        <div aria-label="Hasil pencarian tindakan ICD-9-CM" className="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-xl border border-neutral-200 bg-surface">
                                            {procedureLoading ? <SearchResultsSkeleton label="Mencari tindakan…" /> : procedureSearchFailed ? <p className="p-3 text-sm text-red-600" role="alert">Pencarian gagal. Ubah kata pencarian untuk mencoba kembali.</p> : procedureResults.length > 0 ? procedureResults.map((result, idx) => (
                                                <Button className={`h-auto w-full items-start justify-start gap-3 whitespace-normal rounded-none border-b border-neutral-100 px-3 py-3 text-left font-normal ${idx === procedureActiveIndex ? 'bg-neutral-100 text-neutral-950' : ''}`} key={result.id} onClick={() => {
                                                    setSelectedProcedure(result);
                                                    setProcedureQuery(`${result.code} · ${result.display}`);
                                                    setProcedureResults([]);
                                                    setProcedureActiveIndex(-1);
                                                    treatmentForm.setData('terminology_id', result.id.toString());
                                                    treatmentForm.clearErrors('terminology_id', 'tindakan_id');
                                                }} type="button" variant="ghost">
                                                    <span className="shrink-0 font-sans tabular-nums font-semibold">{result.code}</span>
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
                            {isDental && <section className="space-y-3 rounded-xl border border-neutral-200 bg-surface p-4" aria-label="Panduan pemilihan tindakan"><h4 className="flex items-center gap-2 text-sm font-semibold"><ClipboardPlus className="size-4" />Pertimbangan tindakan sesuai diagnosis</h4><p className="text-sm text-neutral-600">Pilihan berikut membantu pencarian, bukan menetapkan tindakan. Tinjau indikasi, prognosis, dan rencana perawatan pasien. Tidak setiap kunjungan memerlukan prosedur atau resep.</p>{dentalTreatmentSearches(visit.diagnoses, treatmentForm.data.tooth_fdis).map(suggestion => <div key={suggestion.key} className="flex flex-wrap items-center justify-between gap-3 border-t border-neutral-200 pt-3"><div className="min-w-0 flex-1"><p className="text-sm font-medium">{suggestion.title}</p><p className="mt-1 text-sm text-neutral-600">{suggestion.guidance}</p></div><Button type="button" variant="secondary" size="sm" disabled={!canEdit || saving || !procedureCatalogAvailable} onClick={() => startSuggestedProcedureSearch(suggestion.query)}>{suggestion.label}</Button></div>)}{dentalTreatmentSearches(visit.diagnoses, treatmentForm.data.tooth_fdis).length === 0 && <p className="text-sm text-neutral-600">Pilih diagnosis dan konteks gigi yang akan ditangani, atau cari tindakan sesuai pemeriksaan kondisi mulut.</p>}<p className="text-xs text-neutral-600">Bila belum memerlukan tindakan, lengkapi perawatan tanpa obat, edukasi, jadwal evaluasi, atau rujukan yang sesuai. Tindakan lain dapat dicari di katalog atau dicatat melalui input manual.</p></section>}
                            {treatmentMode === 'icd9cm' && !procedureCatalogAvailable && <p className="text-sm text-neutral-600" role="status">Katalog ICD-9-CM Volume 3 belum tersedia. Admin perlu mengimpor katalog prosedur di Terminologi klinis. Sementara itu, pilih input manual atau tindakan klinik.</p>}
                            {treatmentMode === 'manual' && <p className="text-xs text-neutral-500">Tindakan disimpan sebagai uraian manual tanpa kode ICD-9-CM.</p>}
                            {selectedProcedure && treatmentMode === 'icd9cm' && <div className="space-y-3 rounded-lg border border-neutral-200 bg-surface p-3">
                                <p className="text-sm font-medium"><span className="font-sans tabular-nums">{selectedProcedure.code}</span> · {selectedProcedure.display}</p>
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
                            <Field error={treatmentForm.errors.execution_role} htmlFor="execution-role" label="Pelaksana tindakan" required>
                                {(() => {
                                    const availableTreatments = typeof treatments !== 'undefined' && Array.isArray(treatments) ? treatments : [];
                                    const selectedClinicTreatment = availableTreatments.find((item) => item.id.toString() === treatmentForm.data.tindakan_id);
                                    const nurseDelegationBlocked = selectedClinicTreatment?.canDelegateToNurse === false;

                                    return (
                                        <div className="space-y-1.5">
                                            <Select
                                                id="execution-role"
                                                value={nurseDelegationBlocked ? 'dokter' : treatmentForm.data.execution_role}
                                                onChange={(event) => treatmentForm.setData('execution_role', event.target.value)}
                                            >
                                                <option disabled={nurseDelegationBlocked} value="perawat">Perawat — setelah pemeriksaan dokter{nurseDelegationBlocked ? ' (hanya kewenangan dokter)' : ''}</option>
                                                <option value="dokter">Sudah dilakukan oleh dokter pemeriksa</option>
                                            </Select>
                                            {nurseDelegationBlocked && <p className="text-xs text-amber-800">Tindakan ini hanya boleh dilaksanakan langsung oleh dokter (tidak dapat didelegasikan ke perawat).</p>}
                                            {selectedClinicTreatment?.consentModeLabel && (
                                                <p className="text-xs text-neutral-600">
                                                    Persetujuan master tindakan: <span className="font-medium text-neutral-800">{selectedClinicTreatment.consentModeLabel}</span>.
                                                </p>
                                            )}
                                        </div>
                                    );
                                })()}
                            </Field>
                            <Field error={treatmentForm.errors.catatan} htmlFor="catatan-tindakan" label="Keterangan tindakan">
                                <Textarea id="catatan-tindakan" maxLength={2000} onChange={(event) => treatmentForm.setData('catatan', event.target.value)} placeholder="Instruksi, lokasi, dan kebutuhan tindakan; hasil bila sudah dilakukan dokter" rows={3} value={treatmentForm.data.catatan} />
                            </Field>
                            <div className="flex flex-wrap items-center justify-end gap-x-4 gap-y-3 border-t border-neutral-200 pt-4">
                                <label className="flex w-fit cursor-pointer items-center gap-2 text-sm font-medium text-neutral-800" htmlFor="treatment-manual"><Checkbox id="treatment-manual" checked={treatmentMode === 'manual'} onCheckedChange={(checked) => changeTreatmentMode(checked === true ? 'manual' : 'icd9cm')} />Input tindakan manual</label>
                                <Button disabled={!canSubmitEntry(treatmentForm)} type="submit">
                                    <Plus className="size-4" />
                                    Tambah tindakan
                                </Button>
                            </div>
                        </form></fieldset>
                        <div className="overflow-x-auto rounded-xl border border-neutral-200">
                            <Table aria-label="Tabel tindakan tercatat" className="min-w-[46rem] table-fixed">
                                <TableHeader><TableRow>
                                    <TableHead className="w-32">Kode ICD-9-CM</TableHead>
                                    <TableHead className="w-52 text-left">Nama tindakan</TableHead>
                                    <TableHead className="w-20 px-2">Jumlah</TableHead>
                                    <TableHead className="text-left">Catatan</TableHead>
                                    <TableHead className="w-20 px-2">Aksi</TableHead>
                                </TableRow></TableHeader>
                                <TableBody>{visit.treatments.length ? visit.treatments.map(item => <TableRow key={item.id}>
                                    <TableCell className="align-top whitespace-normal [overflow-wrap:anywhere]">
                                        <span className="font-sans font-semibold tabular-nums">{item.code || 'Tanpa kode'}</span>
                                        {item.codeRelease && <span className="mt-1 block text-xs text-neutral-500">{item.codeRelease}</span>}
                                        {item.codingStatus === 'unresolved' && <Badge className="mt-2" variant="warning">Perlu pemetaan</Badge>}
                                        {item.codingStatus === 'not_applicable' && <span className="mt-1 block text-xs text-neutral-500">Tidak memerlukan kode prosedur</span>}
                                    </TableCell>
                                    <TableCell className="align-top whitespace-normal text-left font-medium [overflow-wrap:anywhere]">
                                        {item.name}
                                        <span className="mt-1 block text-xs text-neutral-500">{item.executionRole === 'perawat' ? 'Ditugaskan ke perawat' : 'Dilakukan dokter'}{item.toothFdi && ` · Gigi ${item.toothFdi}`}</span>
                                        {canEdit && !item.completed && item.codingRevision && <div className="mt-2"><ProcedureCodingReview visitId={visit.id} procedureId={item.id} name={item.name} revision={item.codingRevision} searchUrl={procedureSearchUrl} disabled={saving || item.consent?.signaturePending === true} /></div>}
                                        {item.consent && <div className="mt-2 flex flex-wrap items-center gap-2">
                                            <Badge variant={item.consent.signaturePending ? 'warning' : ['refused', 'withdrawn'].includes(item.consent.decision) ? 'cancelled' : !item.consent.planMatches ? 'warning' : 'complete'}>
                                                {item.consent.signaturePending ? 'Menunggu tanda tangan atau saksi' : (procedureConsentLabels[item.consent.decision] ?? 'Persetujuan belum ditinjau')}
                                            </Badge>
                                            <Button type="button" size="sm" variant="secondary" onClick={() => openProcedureConsent(item.consent!.url)}>
                                                {item.consent.signaturePending ? 'Lihat sesi tanda tangan' : consentAllowsExecution(item.consent) ? 'Lihat persetujuan' : 'Tinjau persetujuan'}
                                            </Button>
                                        </div>}
                                        {item.consent?.doctorStartedAt && <Badge className="mt-2" variant="examination">Mulai tercatat · {item.consent.doctorStartedAt}</Badge>}
                                        {canEdit && !isAdminAssistance && item.consent?.doctorStartRequired && !item.consent.doctorStartedAt && consentAllowsExecution(item.consent) && <Button
                                            className="mt-2" disabled={saving} size="sm" type="button"
                                            onClick={() => {
                                                procedureStartForm.clearErrors();
                                                procedureStartForm.setData({ consent_id: item.consent?.currentId ?? 0, confirmed: false });
                                                setStartingProcedure(item);
                                            }}
                                        >Mulai tindakan</Button>}
                                    </TableCell>
                                    <TableCell className="px-2 align-top tabular-nums">{item.quantity}x</TableCell>
                                    <TableCell className="align-top text-left"><RecordedTableNote parts={[item.note]} /></TableCell>
                                    <TableCell className="px-2 align-top">
                                        <ActionButton disabled={!canEdit || saving || item.completed || item.consent?.signaturePending} onClick={() => deleteItem(`/pelayanan/pemeriksaan/tindakan/${item.id}`, 'Hapus tindakan ini?')} type="button" action="delete" label={`Hapus tindakan ${item.name}`} />
                                    </TableCell>
                                </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Belum ada tindakan medis." /></TableCell></TableRow>}</TableBody>
                            </Table>
                        </div>
                        {renderTabNavigation('tindakan', 'resep', 'Lanjut ke resep klinik')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="resep">
                        <div><h3 className="flex items-center gap-2 text-base font-semibold text-neutral-950" id="tab-resep"><Stethoscope aria-hidden="true" className="size-5 shrink-0 text-neutral-600" />Resep obat klinik</h3><p className="mt-1 text-sm text-neutral-500">Pilih obat dari stok klinik; sistem memeriksa kecocokan alergi, stok layak pakai, kedaluwarsa batch, dan duplikasi zat aktif.</p></div>
                        {visit.patient.maternal?.hasRisk && <Alert dismissible={false} variant="warning"><AlertTriangle className="size-4" /><AlertTitle>Perhatian keamanan peresepan maternal / laktasi</AlertTitle><AlertDescription>{visit.patient.maternal.summaryText}. Pastikan obat aman sesuai kategori kehamilan dan laktasi.</AlertDescription></Alert>}
                        <fieldset className="min-w-0" disabled={saving}><form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, prescriptionForm, `/pelayanan/pemeriksaan/${visit.id}/resep`)}>
                            {medicines.length > 8 && (
                                <Field htmlFor="medicine-quick-filter" label="Filter cepat daftar obat klinik">
                                    <div className="relative">
                                        <Search aria-hidden="true" className="absolute left-3 top-3 size-4 text-neutral-500" />
                                        <Input id="medicine-quick-filter" className="pl-9" placeholder="Ketik nama obat atau zat aktif untuk menyaring daftar…" value={medicineFilter} onChange={(event) => setMedicineFilter(event.target.value)} />
                                    </div>
                                </Field>
                            )}
                            <div className="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-4"><Field error={prescriptionForm.errors.jenis} htmlFor="jenis-resep" label="Jenis resep"><Select id="jenis-resep" onChange={(event) => prescriptionForm.setData('jenis', event.target.value)} value={prescriptionForm.data.jenis}><option value="jadi">Obat jadi</option></Select></Field><Field error={prescriptionForm.errors.obat_id} htmlFor="obat_id" label="Obat dari stok klinik" required><Select id="obat_id" onChange={(event) => { const medicine = medicines.find((item) => item.id.toString() === event.target.value); prescriptionForm.setData((data) => ({ ...data, obat_id: event.target.value, satuan: medicine?.unit ?? '', allergy_override_reason: '' })); }} required value={prescriptionForm.data.obat_id}><option value="">Pilih obat</option>{medicines.filter((medicine) => !medicineFilter.trim() || String(medicine.id) === prescriptionForm.data.obat_id || `${medicine.name} ${medicine.ingredients ?? ''}`.toLocaleLowerCase('id').includes(medicineFilter.trim().toLocaleLowerCase('id'))).map((medicine) => <option key={medicine.id} value={medicine.id}>{medicine.name} · stok {medicine.stock} {medicine.unit}{medicine.isHighAlert ? ' [HIGH ALERT]' : ''}{medicine.isLasa ? ' [LASA]' : ''}</option>)}</Select></Field><Field error={prescriptionForm.errors.jumlah} htmlFor="jumlah-obat" label="Jumlah" required><Input id="jumlah-obat" min="1" onChange={(event) => prescriptionForm.setData('jumlah', event.target.value)} required type="number" value={prescriptionForm.data.jumlah} /></Field><Field error={prescriptionForm.errors.satuan} htmlFor="satuan-obat" label="Satuan"><Input id="satuan-obat" onChange={(event) => prescriptionForm.setData('satuan', event.target.value)} placeholder="Contoh: tablet, kapsul, atau botol" value={prescriptionForm.data.satuan} /></Field></div>
                            {selectedClinicMedicine && (selectedClinicMedicine.isHighAlert || selectedClinicMedicine.isLasa || selectedClinicMedicine.nearExpiryDate || Number(prescriptionForm.data.jumlah) > selectedClinicMedicine.stock || duplicateClinicMedicine) && (
                                <div className="space-y-1 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950" role="status">
                                    {selectedClinicMedicine.isHighAlert && <p><strong>HIGH ALERT:</strong> Obat kewaspadaan tinggi — verifikasi ulang dosis dan indikasi.</p>}
                                    {selectedClinicMedicine.isLasa && <p><strong>LASA (Look-Alike Sound-Alike):</strong> Periksa kembali kekuatan sediaan dan penulisan aturan pakai.</p>}
                                    {selectedClinicMedicine.nearExpiryDate && <p><strong>Mendekati kedaluwarsa:</strong> Batch FEFO terdekat kedaluwarsa pada {selectedClinicMedicine.nearExpiryDate} (≤ 30 hari).</p>}
                                    {Number(prescriptionForm.data.jumlah) > selectedClinicMedicine.stock && <p><strong>Stok layak pakai tidak mencukupi ({selectedClinicMedicine.stock} {selectedClinicMedicine.unit ?? ''}):</strong> Farmasi dapat mengalihkan kekurangan ke resep luar setelah pemeriksaan ditandatangani.</p>}
                                    {duplicateClinicMedicine && <p><strong>Potensi duplikasi terapi:</strong> Zat aktif/obat beririsan dengan {duplicateClinicMedicine} yang sudah ada pada resep kunjungan ini.</p>}
                                </div>
                            )}
                            {(matchedClinicAllergy || Boolean(prescriptionForm.errors.allergy_override_reason)) && (
                                <div className="space-y-3 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-950" role="alert">
                                    <p className="font-semibold">Peringatan Alergi Obat: terdeteksi kecocokan dengan riwayat alergi pasien ({matchedClinicAllergy ?? 'riwayat tercatat'}).</p>
                                    <Field error={prescriptionForm.errors.allergy_override_reason} htmlFor="allergy-override-reason" label="Alasan pertimbangan klinis (wajib diisi minimal 10 karakter jika tetap diresepkan)" required>
                                        <Textarea id="allergy-override-reason" minLength={10} rows={2} placeholder="Jelaskan pertimbangan klinis, riwayat toleransi, atau rencana pemantauan" value={prescriptionForm.data.allergy_override_reason} onChange={(event) => prescriptionForm.setData('allergy_override_reason', event.target.value)} required />
                                    </Field>
                                </div>
                            )}
                            <div className="grid gap-4 md:grid-cols-2"><Field error={prescriptionForm.errors.aturan_pakai} htmlFor="aturan_pakai" label="Aturan pakai" required><Input id="aturan_pakai" onChange={(event) => prescriptionForm.setData('aturan_pakai', event.target.value)} placeholder="Contoh: 3 x 1 tablet sesudah makan" required value={prescriptionForm.data.aturan_pakai} /></Field><Field error={prescriptionForm.errors.catatan} htmlFor="catatan-resep" label="Catatan"><Input id="catatan-resep" onChange={(event) => prescriptionForm.setData('catatan', event.target.value)} placeholder="Instruksi tambahan sesuai resep" value={prescriptionForm.data.catatan} /></Field></div>
                            <div className="flex justify-end border-t border-neutral-200 pt-4"><Button disabled={!canSubmitEntry(prescriptionForm) || Boolean(matchedClinicAllergy && prescriptionForm.data.allergy_override_reason.trim().length < 10)} type="submit"><Plus className="size-4" />Tambahkan resep klinik</Button></div>
                        </form></fieldset>
                        {renderPrescriptionItems(visit.prescription.items.filter((item) => !item.external), prescriptionEditable && !saving, deleteItem)}
                        {renderTabNavigation('resep')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="resep-luar">
                        <div><h3 className="flex items-center gap-2 text-base font-semibold text-neutral-950" id="tab-resep-luar"><Stethoscope aria-hidden="true" className="size-5 shrink-0 text-neutral-600" />Resep luar</h3><p className="mt-1 text-sm text-neutral-500">Catat obat yang diresepkan untuk ditebus di luar klinik. Entri ini tidak mengurangi stok klinik.</p></div>
                        {visit.patient.maternal?.hasRisk && <Alert dismissible={false} variant="warning"><AlertTriangle className="size-4" /><AlertTitle>Perhatian keamanan peresepan maternal / laktasi</AlertTitle><AlertDescription>{visit.patient.maternal.summaryText}. Pastikan obat aman sesuai kategori kehamilan dan laktasi.</AlertDescription></Alert>}
                        <fieldset className="min-w-0" disabled={saving}><form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, externalPrescriptionForm, `/pelayanan/pemeriksaan/${visit.id}/resep`)}>
                            <div className="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-4"><Field error={externalPrescriptionForm.errors.jenis} htmlFor="jenis-resep-luar" label="Jenis resep"><Select id="jenis-resep-luar" onChange={(event) => externalPrescriptionForm.setData('jenis', event.target.value)} value={externalPrescriptionForm.data.jenis}><option value="jadi">Obat jadi</option></Select></Field><Field error={externalPrescriptionForm.errors.nama_obat} htmlFor="nama-obat-luar" label="Nama obat" required><Input placeholder="Nama obat dan kekuatan sediaan" id="nama-obat-luar" onChange={(event) => externalPrescriptionForm.setData('nama_obat', event.target.value)} required value={externalPrescriptionForm.data.nama_obat} /></Field><Field error={externalPrescriptionForm.errors.jumlah} htmlFor="jumlah-obat-luar" label="Jumlah" required><Input id="jumlah-obat-luar" min="0.01" onChange={(event) => externalPrescriptionForm.setData('jumlah', event.target.value)} required step="0.01" type="number" value={externalPrescriptionForm.data.jumlah} /></Field><Field error={externalPrescriptionForm.errors.satuan} htmlFor="satuan-obat-luar" label="Satuan"><Input id="satuan-obat-luar" onChange={(event) => externalPrescriptionForm.setData('satuan', event.target.value)} placeholder="Contoh: tablet, kapsul, atau botol" value={externalPrescriptionForm.data.satuan} /></Field></div>
                            {(matchedExternalAllergy || Boolean(externalPrescriptionForm.errors.allergy_override_reason)) && (
                                <div className="space-y-3 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-950" role="alert">
                                    <p className="font-semibold">Peringatan Alergi Obat: terdeteksi kecocokan dengan riwayat alergi pasien ({matchedExternalAllergy ?? 'riwayat tercatat'}).</p>
                                    <Field error={externalPrescriptionForm.errors.allergy_override_reason} htmlFor="allergy-override-reason-luar" label="Alasan pertimbangan klinis (wajib diisi minimal 10 karakter jika tetap diresepkan)" required>
                                        <Textarea id="allergy-override-reason-luar" minLength={10} rows={2} placeholder="Jelaskan pertimbangan klinis, riwayat toleransi, atau rencana pemantauan" value={externalPrescriptionForm.data.allergy_override_reason} onChange={(event) => externalPrescriptionForm.setData('allergy_override_reason', event.target.value)} required />
                                    </Field>
                                </div>
                            )}
                            <div className="grid gap-4 md:grid-cols-2"><Field error={externalPrescriptionForm.errors.aturan_pakai} htmlFor="aturan-pakai-luar" label="Aturan pakai" required><Input id="aturan-pakai-luar" onChange={(event) => externalPrescriptionForm.setData('aturan_pakai', event.target.value)} placeholder="Contoh: 3 x 1 tablet sesudah makan" required value={externalPrescriptionForm.data.aturan_pakai} /></Field><Field error={externalPrescriptionForm.errors.catatan} htmlFor="catatan-resep-luar" label="Catatan"><Input id="catatan-resep-luar" placeholder="Instruksi tambahan sesuai resep" onChange={(event) => externalPrescriptionForm.setData('catatan', event.target.value)} value={externalPrescriptionForm.data.catatan} /></Field></div>
                            <div className="flex justify-end border-t border-neutral-200 pt-4"><Button disabled={!canSubmitEntry(externalPrescriptionForm) || Boolean(matchedExternalAllergy && externalPrescriptionForm.data.allergy_override_reason.trim().length < 10)} type="submit"><Plus className="size-4" />Simpan resep luar</Button></div>
                        </form></fieldset>
                        {visit.prescription.items.some((item) => item.external) && (visit.examination?.signedAt && visit.prescription.printUrl ? <Button asChild variant="secondary"><a href={visit.prescription.printUrl}><FileText className="size-4" />Cetak resep luar</a></Button> : <Alert dismissible={false} variant="info"><AlertDescription>Cetak resep luar tersedia setelah pemeriksaan dokter selesai dan ditandatangani.</AlertDescription></Alert>)}
                        {renderPrescriptionItems(visit.prescription.items.filter((item) => item.external), prescriptionEditable && !saving, deleteItem)}
                        {renderTabNavigation('resep-luar')}
                    </TabsContent>
                    <TabsContent className="space-y-6 p-5 sm:p-6" value="surat">
                        <div><h3 className="flex items-center gap-2 text-base font-semibold text-neutral-950" id="tab-surat"><Stethoscope aria-hidden="true" className="size-5 shrink-0 text-neutral-600" />Surat medis</h3><p className="mt-1 text-sm text-neutral-500">Terbitkan surat keterangan resmi setelah pemeriksaan dokter difinalisasi dan ditandatangani.</p></div>
                        {!visit.examination?.signedAt && <Alert dismissible={false} variant="info"><AlertDescription>Selesaikan dan tandatangani pemeriksaan terlebih dahulu sebelum menerbitkan surat medis resmi agar diagnosis dan data klinis tervalidasi.</AlertDescription></Alert>}
                        <fieldset className="min-w-0" disabled={saving || !visit.examination?.signedAt}><form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, letterForm, `/pelayanan/pemeriksaan/${visit.id}/surat`)}><div className="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-3"><Field error={letterForm.errors.jenis} htmlFor="jenis-surat" label="Jenis surat" required><Select id="jenis-surat" onChange={(event) => { letterForm.setData((data) => ({ ...data, jenis: event.target.value, surat_template_id: '', details: { ...emptyLetterDetails } })); }} value={letterForm.data.jenis}>{letterTypes.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field><Field error={letterForm.errors.surat_template_id} htmlFor="letter-template" label="Template surat"><Select id="letter-template" onChange={(event) => letterForm.setData('surat_template_id', event.target.value)} value={letterForm.data.surat_template_id}><option value="">Format standar klinik</option>{correspondenceTemplates.filter((template) => template.type === letterForm.data.jenis).map((template) => <option key={template.id} value={template.id}>{template.name}</option>)}</Select></Field><Field error={letterForm.errors.tanggal} htmlFor="tanggal-surat" label="Tanggal surat" required><DatePicker id="tanggal-surat" onChange={(event) => letterForm.setData('tanggal', event.target.value)} required value={letterForm.data.tanggal} /></Field></div><p className="text-xs text-neutral-500">Nomor dibuat otomatis untuk semua surat; urutan terus berjalan tanpa reset.</p><MedicalLetterFields type={letterForm.data.jenis} value={letterForm.data.details} onChange={(details) => letterForm.setData('details', details)} errors={letterForm.errors} /><Field error={letterForm.errors.konten} htmlFor="konten-surat" label="Isi keterangan"><Textarea id="konten-surat" placeholder="Kosongkan untuk menggunakan isi template" onChange={(event) => letterForm.setData('konten', event.target.value)} rows={4} value={letterForm.data.konten} /></Field><div className="flex justify-end"><Button disabled={!canSubmitEntry(letterForm) || !visit.examination?.signedAt} type="submit"><Plus className="size-4" />Buat surat medis</Button></div></form></fieldset>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Surat yang diterbitkan</h4>{visit.letters.length ? visit.letters.map((letter) => <article className="flex flex-col justify-between gap-2 rounded-xl border border-neutral-200 p-4 sm:flex-row sm:items-center" key={letter.id}><div><Badge variant="examination">{letter.type}</Badge><p className="mt-2 text-sm font-medium text-neutral-900">No: {letter.number ?? 'Belum dicatat'}</p>{letter.content && <p className="mt-1 whitespace-pre-wrap text-sm text-neutral-600">{letter.content}</p>}</div><div className="flex items-center justify-between gap-3 sm:flex-col sm:items-end"><span className="text-xs text-neutral-500">{letter.date ?? 'Belum dicatat'}</span><ActionButton asChild action="print" label="Cetak"><a href={letter.printUrl} /></ActionButton></div></article>) : <Empty className="min-h-0 py-6" description="Surat medis yang diterbitkan akan tercatat di sini." title="Belum ada surat medis" />}</div>
                        {renderTabNavigation('surat')}
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="rujukan">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div><h3 className="flex items-center gap-2 text-base font-semibold text-neutral-950" id="tab-rujukan"><ArrowLeft aria-hidden="true" className="size-5 shrink-0 text-neutral-600" />Rujukan internal</h3><p className="mt-1 text-sm text-neutral-500">Pilih poli tujuan dan catat kebutuhan konsultasi. Setelah rujukan diterbitkan, admin mendaftarkan kunjungan tujuan dari Persuratan.</p></div>
                            <Button asChild size="sm" variant="secondary"><Link href="/persuratan#rujukan-internal">Lihat daftar rujukan</Link></Button>
                        </div>
                        <fieldset className="min-w-0" disabled={!canEdit || saving}><form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, referralForm, `/pelayanan/pemeriksaan/${visit.id}/rujukan`)}><div className="grid items-end gap-4 md:grid-cols-2"><Field error={referralForm.errors.ke_poli_id} htmlFor="ke_poli_id" label="Poliklinik tujuan" required><Select id="ke_poli_id" onChange={(event) => referralForm.setData('ke_poli_id', event.target.value)} required value={referralForm.data.ke_poli_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field><Field error={referralForm.errors.surat_template_id} htmlFor="referral-template" label="Template rujukan"><Select id="referral-template" onChange={(event) => referralForm.setData('surat_template_id', event.target.value)} value={referralForm.data.surat_template_id}><option value="">Format standar klinik</option>{correspondenceTemplates.filter((template) => template.type === 'rujukan_internal').map((template) => <option key={template.id} value={template.id}>{template.name}</option>)}</Select></Field></div><Field error={referralForm.errors.konten_surat} htmlFor="referral-content" label="Isi surat rujukan"><Textarea id="referral-content" onChange={(event) => referralForm.setData('konten_surat', event.target.value)} placeholder="Kosongkan untuk menggunakan isi template" rows={4} value={referralForm.data.konten_surat} /></Field><Field error={referralForm.errors.catatan} htmlFor="catatan-rujukan" label="Catatan konsultasi"><Textarea id="catatan-rujukan" placeholder="Keterangan tambahan untuk konsultasi" onChange={(event) => referralForm.setData('catatan', event.target.value)} rows={3} value={referralForm.data.catatan} /></Field><div className="flex justify-end"><Button disabled={!canSubmitEntry(referralForm)} type="submit"><Plus className="size-4" />Kirim rujukan</Button></div></form></fieldset>
                        <section className="space-y-3" aria-labelledby="referral-history-title">
                            <h4 className="text-sm font-semibold text-neutral-900" id="referral-history-title">Riwayat rujukan</h4>
                            <div className="overflow-x-auto rounded-xl border border-neutral-200">
                                <Table aria-label="Rujukan dari kunjungan ini" className="min-w-[46rem] table-fixed">
                                    <TableHeader><TableRow><TableHead className="w-36">Nomor</TableHead><TableHead className="w-40 text-left">Poli tujuan</TableHead><TableHead className="text-left">Catatan konsultasi</TableHead><TableHead className="w-36">Status</TableHead><TableHead className="w-20">Aksi</TableHead></TableRow></TableHeader>
                                    <TableBody>{visit.referrals.length ? visit.referrals.map(referral => <TableRow key={referral.id}>
                                        <TableCell className="align-top font-sans tabular-nums [overflow-wrap:anywhere]">{referral.number ?? 'Nomor belum tercatat'}</TableCell>
                                        <TableCell className="align-top text-left [overflow-wrap:anywhere]">{referral.toClinic ?? 'Belum tercatat'}</TableCell>
                                        <TableCell className="align-top text-left"><RecordedTableNote parts={[referral.notes]} /></TableCell>
                                        <TableCell className="align-top"><InternalReferralStatus status={referral.status} />{referral.destinationVisit && <p className="mt-1 break-words text-xs text-neutral-600">{referral.destinationVisit}</p>}</TableCell>
                                        <TableCell className="px-2 align-top"><ActionButton asChild action="print" label="Cetak rujukan internal"><a href={referral.printUrl} /></ActionButton></TableCell>
                                    </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Belum ada rujukan internal." /></TableCell></TableRow>}</TableBody>
                                </Table>
                            </div>
                        </section>
                        {renderTabNavigation('rujukan')}
                    </TabsContent>
                    </fieldset>
                    </Tabs>
                </Card>


                <form id="rencana-tindak-lanjut" className="space-y-4 scroll-mt-6" hidden={showSoapTab && activeTab === 'soap'} onSubmit={(event) => submitClinicalSection(event, 'rencana')}>
                    <Card><details className="group/disclosure" id="education-details" open>
                        <summary className="flex cursor-pointer list-none items-start justify-between gap-4 p-5 sm:p-6 [&::-webkit-details-marker]:hidden"><span className="min-w-0"><span className="flex items-center gap-2 text-base font-semibold"><HeartPulse aria-hidden="true" className="size-5 shrink-0" />Perawatan tanpa obat & edukasi pasien</span><span className="mt-1 block text-sm text-neutral-500">Perawatan yang dianjurkan dan informasi yang disampaikan kepada pasien.</span></span>{disclosureToggle}</summary>
                        <CardContent className="border-t border-neutral-100 pt-5 sm:pt-6"><fieldset disabled={!canEdit || saving} className="grid min-w-0 gap-4 md:grid-cols-2" aria-labelledby="non-pharmacological-title"><h3 id="non-pharmacological-title" className="sr-only">Nonfarmakologis & edukasi pasien</h3>
                                    <Field error={followUpForm.errors.non_farmakologis} htmlFor="non-farmakologis" label="Terapi nonfarmakologis"><Textarea id="non-farmakologis" maxLength={5000} onChange={(event) => followUpForm.setData('non_farmakologis', event.target.value)} placeholder="Contoh: kompres hangat, istirahat, atau tindakan non-obat lain" rows={4} value={followUpForm.data.non_farmakologis} /></Field>
                                    <Field error={followUpForm.errors.edukasi} htmlFor="edukasi" label="Edukasi / anjuran"><Textarea id="edukasi" onChange={(event) => followUpForm.setData('edukasi', event.target.value)} placeholder="Catat anjuran, cara penggunaan obat, dan hal yang perlu dihindari" rows={4} value={followUpForm.data.edukasi} /></Field>
                        </fieldset></CardContent>
                    </details></Card>
                    <Card><details className="group/disclosure" id="rencana-details" open>
                        <summary className="flex cursor-pointer list-none items-start justify-between gap-4 p-5 sm:p-6 [&::-webkit-details-marker]:hidden"><span className="min-w-0"><span className="flex items-center gap-2 text-base font-semibold"><ClipboardPlus aria-hidden="true" className="size-5 shrink-0" />Jadwal kontrol & catatan dokter</span><span className="mt-1 block text-sm text-neutral-500">Tanggal yang disimpan otomatis masuk ke daftar reservasi untuk dikonfirmasi pendaftaran.</span></span>{disclosureToggle}</summary>
                        <CardContent className="border-t border-neutral-100 pt-5 sm:pt-6"><fieldset disabled={!canEdit || saving} className="grid min-w-0 gap-4 md:grid-cols-2">

                        <Field error={followUpForm.errors.kontrol_berikutnya} htmlFor="kontrol_berikutnya" label="Jadwal kontrol berikutnya"><DatePicker id="kontrol_berikutnya" onChange={(event) => followUpForm.setData('kontrol_berikutnya', event.target.value)} value={followUpForm.data.kontrol_berikutnya} /></Field>
                                <Field error={followUpForm.errors.catatan} htmlFor="catatan" label="Catatan dokter"><Textarea id="catatan" rows={3} maxLength={5000} onChange={(event) => followUpForm.setData('catatan', event.target.value)} placeholder={isDental ? "Rencana perawatan lanjutan, kondisi gigi yang dipantau, atau instruksi kontrol" : "Rencana evaluasi keluhan, respons terapi, atau instruksi kontrol"} value={followUpForm.data.catatan} /></Field>
                                <div className="grid min-w-0 gap-4 border-t border-neutral-200 pt-4 md:col-span-2 md:grid-cols-2"><Field htmlFor="final-condition" label="Kondisi akhir kunjungan" error={followUpForm.errors.kondisi_akhir}><Textarea rows={3} placeholder="Kondisi pasien setelah pemeriksaan dan penanganan pada kunjungan ini" id="final-condition" value={followUpForm.data.kondisi_akhir} onChange={event => followUpForm.setData('kondisi_akhir', event.target.value)} /></Field><Field htmlFor="disposition" label="Status akhir pelayanan" error={followUpForm.errors.disposisi}><Select id="disposition" value={followUpForm.data.disposisi} onChange={event => followUpForm.setData('disposisi', event.target.value)}><option value="">Belum ditetapkan</option><option value="pulang">Pulang</option><option value="observasi">Observasi</option><option value="rujukan">Rujukan</option></Select></Field></div>
                        </fieldset></CardContent>
                    </details></Card>
                    <div className="flex flex-wrap justify-end gap-2"><Button disabled={!sectionSaveAllowed.rencana} type="submit" variant="secondary">Simpan perawatan & tindak lanjut</Button>{canEdit && <Button disabled={saving} onClick={requestExaminationFinish} type="button"><Check className="size-4" />Selesai periksa</Button>}</div>
                </form>
            </div>
            <Dialog onOpenChange={setSignatureDialogOpen} open={signatureDialogOpen}>
                <DialogContent className="max-h-[90dvh] overflow-y-auto">
                    <DialogHeader><DialogTitle>Selesaikan pemeriksaan</DialogTitle><DialogDescription>Tinjau kembali hasil pemeriksaan, lalu masukkan kata sandi untuk menandatangani. Perubahan setelah ditandatangani dicatat melalui addendum.</DialogDescription></DialogHeader>
                    <fieldset className="min-w-0" disabled={saving}><form className="space-y-4" onSubmit={signExamination}><p className="rounded-lg bg-neutral-50 p-3 text-sm text-neutral-700">{visit.treatments.some((item) => item.executionRole === 'perawat') ? (visit.prescription.items.some((item) => !item.external) ? 'Pasien ke perawat untuk tindakan, kemudian ke farmasi, lalu selesai.' : 'Pasien ke perawat untuk tindakan, kemudian kunjungan selesai tanpa farmasi.') : visit.prescription.items.some((item) => !item.external) ? 'Pasien diteruskan ke farmasi untuk resep klinik.' : visit.prescription.items.some((item) => item.external) ? 'Kunjungan selesai setelah ditandatangani. Resep luar ditebus di luar klinik.' : 'Kunjungan selesai setelah ditandatangani tanpa resep obat.'}</p>
                        {visit.treatments.some((item) => !consentAllowsExecution(item.consent)) && <Alert dismissible={false} variant="default"><AlertTriangle className="size-4" /><AlertTitle>Tindakan perlu ditinjau</AlertTitle><AlertDescription className="space-y-2"><p>Buka tindakan yang tercantum untuk mencatat keputusan pasien atau mengonfirmasi dasar klasifikasi dokter.</p><div className="flex flex-wrap gap-2">{visit.treatments.filter((item) => !consentAllowsExecution(item.consent)).map((item) => <Button key={item.id} type="button" size="sm" variant="secondary" onClick={() => { if (item.consent) { setSignatureDialogOpen(false); signatureForm.setData('signature_password', ''); openProcedureConsent(item.consent.url, true); } }}>{item.name}</Button>)}</div>{(signatureForm.errors as Record<string, string>).procedure_consent && <p role="alert" className="text-sm font-medium text-red-700">{(signatureForm.errors as Record<string, string>).procedure_consent}</p>}</AlertDescription></Alert>}
                        {(signatureForm.errors as Record<string, string>).procedure_consent && visit.treatments.every((item) => consentAllowsExecution(item.consent)) && <p role="alert" className="text-sm font-medium text-red-700">{(signatureForm.errors as Record<string, string>).procedure_consent}</p>}
                        {(visit.treatments.some((item) => item.consent?.doctorStartRequired && !item.consent.doctorStartedAt) || (signatureForm.errors as Record<string, string>).procedure_start) && <Alert
                            action={<Button size="sm" type="button" variant="secondary" onClick={() => {
                                setSignatureDialogOpen(false);
                                signatureForm.setData('signature_password', '');
                                navigateSection('tindakan');
                            }}>Buka Tindakan medis</Button>}
                            dismissible={false}
                            variant="default"
                        >
                            <AlertTriangle className="size-4" />
                            <AlertTitle>Mulai tindakan belum dicatat</AlertTitle>
                            <AlertDescription>
                                {(signatureForm.errors as Record<string, string>).procedure_start ?? 'Konfirmasi mulai tindakan di tab Tindakan medis sebelum menandatangani pemeriksaan.'}
                            </AlertDescription>
                        </Alert>}
                        {(unresolvedDiagnoses.length > 0 || unresolvedProcedures.length > 0) && <Alert dismissible={false} variant="default"><AlertTriangle className="size-4" /><AlertTitle>Coding diagnosis atau tindakan belum lengkap</AlertTitle><AlertDescription className="space-y-3"><p>{codingExceptionAvailable ? 'Kondisi darurat/downtime dapat ditandatangani sebagai unresolved, tetapi wajib direkonsiliasi.' : 'Tinjau pemetaan ICD-10 WHO dan ICD-9-CM sebelum menandatangani pemeriksaan normal.'}</p>{codingExceptionAvailable && <><label className="flex items-start gap-2 text-sm"><Checkbox checked={signatureForm.data.coding_downtime_acknowledged} onCheckedChange={(checked) => signatureForm.setData('coding_downtime_acknowledged', checked === true)} /><span>Saya mengonfirmasi coding unresolved untuk kondisi darurat/downtime dan akan menindaklanjuti rekonsiliasi.</span></label><Field error={signatureForm.errors.coding_downtime_reason} htmlFor="coding-downtime-reason" label="Alasan dan rencana rekonsiliasi" required><Textarea id="coding-downtime-reason" maxLength={2000} required rows={3} value={signatureForm.data.coding_downtime_reason} onChange={(event) => signatureForm.setData('coding_downtime_reason', event.target.value)} /></Field></>}{(signatureForm.errors as Record<string, string>).coding_review && <p role="alert" className="text-sm font-medium text-red-700">{(signatureForm.errors as Record<string, string>).coding_review}</p>}</AlertDescription></Alert>}
                        {assistanceReview && <div className="space-y-2"><label className="flex items-start gap-2 text-sm"><Checkbox aria-invalid={Boolean(signatureForm.errors.review_admin_assistance)} required checked={signatureForm.data.review_admin_assistance} onCheckedChange={(checked) => signatureForm.setData('review_admin_assistance', checked === true)} />Saya telah meninjau draf bantuan admin dan menyetujui isinya sebagai dokter penanggung jawab.</label>{signatureForm.errors.review_admin_assistance && <p className="text-sm text-red-600">{signatureForm.errors.review_admin_assistance}</p>}{signatureForm.errors.assistance_review_hash && <p className="text-sm text-red-600">{signatureForm.errors.assistance_review_hash}</p>}{reviewPageStale && <Button onClick={() => window.location.reload()} type="button" variant="secondary">Muat ulang draf terbaru</Button>}{signatureForm.errors.assistance_review_id && <p className="text-sm text-red-600">{signatureForm.errors.assistance_review_id}</p>}</div>}
                        {isDental && dentalDiagnosisGroups.some(group => group.warnings.length > 0) && <Alert dismissible={false} variant="default"><AlertTriangle className="size-4" /><AlertTitle>Tinjau kembali temuan berikut</AlertTitle><div className="space-y-2 text-sm leading-5">{dentalDiagnosisGroups.flatMap(group => group.warnings.map(warning => <p key={`${group.toothFdi}-${warning}`}>Gigi {group.toothFdi}: {warning}</p>))}</div><p className="mt-2 text-xs">Lanjutkan setelah hasil pemeriksaan dan penetapan diagnosis Anda sudah sesuai.</p></Alert>}
                        <label className="flex items-start gap-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm leading-6"><Checkbox aria-invalid={Boolean(signatureForm.errors.review_final_plan)} required checked={signatureForm.data.review_final_plan} onCheckedChange={checked => signatureForm.setData('review_final_plan', checked === true)} /><span>Saya telah meninjau diagnosis banding, perawatan nonfarmakologis, edukasi, dan tindak lanjut. Bagian yang kosong tetap belum dicatat dan tidak diubah menjadi pernyataan klinis.</span></label>
                        {visit.treatments.some((item) => item.executionRole === 'dokter') && <div className="space-y-2"><label className="flex items-start gap-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm leading-6"><Checkbox required checked={signatureForm.data.review_performed_procedures} onCheckedChange={(checked) => signatureForm.setData('review_performed_procedures', checked === true)} /><span>Saya mengonfirmasi seluruh tindakan dengan pelaksana dokter yang tercantum telah benar-benar dilakukan. Hapus rencana yang belum dilakukan sebelum menandatangani.</span></label>{signatureForm.errors.review_performed_procedures && <p role="alert" className="text-sm text-red-600">{signatureForm.errors.review_performed_procedures}</p>}</div>}
                        {signatureForm.errors.review_final_plan && <p role="alert" className="text-sm text-red-600">{signatureForm.errors.review_final_plan}</p>}
                        {!isDental && <div className="space-y-3"><label className="flex items-start gap-2 text-sm leading-6"><Checkbox aria-invalid={Boolean(signatureForm.errors.review_nursing_assessment)} required checked={signatureForm.data.review_nursing_assessment} onCheckedChange={checked => signatureForm.setData('review_nursing_assessment', checked === true)} /><span>{hasNurseSystemAssessment || workflow.umum_system_review !== 'dokter' ? 'Saya telah memverifikasi keluhan, tanda vital, alergi, riwayat obat dan penyakit, temuan abnormal, serta catatan serah terima perawat.' : 'Saya telah memverifikasi keluhan utama, tanda vital, alergi, riwayat obat dan penyakit, triase awal, serta catatan serah terima perawat.'}</span></label>{signatureForm.errors.review_nursing_assessment && <p role="alert" className="text-sm text-red-600">{signatureForm.errors.review_nursing_assessment}</p>}<Field htmlFor="nursing-clarification" label="Klarifikasi dokter / tindak lanjut serah terima" error={signatureForm.errors.nursing_clarification}><Textarea id="nursing-clarification" maxLength={3000} value={signatureForm.data.nursing_clarification} onChange={event => signatureForm.setData('nursing_clarification', event.target.value)} /></Field></div>}
                        {isDental && <div className="space-y-2"><label className="flex items-start gap-2 text-sm leading-6"><Checkbox aria-invalid={Boolean(signatureForm.errors.review_dental_assessment)} required checked={signatureForm.data.review_dental_assessment} onCheckedChange={(checked) => signatureForm.setData('review_dental_assessment', checked === true)} /><span>Saya telah meninjau pengkajian / observasi awal dan catatan serah terima perawat, melakukan pemeriksaan ekstraoral dan intraoral sesuai cakupan, serta memvalidasi temuan, diagnosis dan rencana perawatan.</span></label>{signatureForm.errors.review_dental_assessment && <p role="alert" className="text-sm text-red-600">{signatureForm.errors.review_dental_assessment}</p>}{(signatureForm.errors as Record<string, string>).dental_diagnoses && <p role="alert" className="text-sm text-red-700">{(signatureForm.errors as Record<string, string>).dental_diagnoses}</p>}{(signatureForm.errors as Record<string, string>).dental_findings && <p role="alert" className="text-sm text-red-600">{(signatureForm.errors as Record<string, string>).dental_findings}</p>}</div>}
                        {signatureForm.errors.screening_revision && <Alert dismissible={false} variant="destructive"><AlertTriangle aria-hidden="true" className="size-4" /><AlertDescription>{signatureForm.errors.screening_revision}</AlertDescription></Alert>}
                        {isDental && <Field htmlFor="dental-exam-scope" label="Cakupan saat finalisasi" error={signatureForm.errors.dental_scope}>
                            <Select id="dental-exam-scope" value={signatureForm.data.dental_scope} onChange={(event) => {
                                signatureForm.clearErrors('dental_scope');
                                signatureForm.setData((data) => ({ ...data, dental_scope: event.target.value, review_dental_assessment: false }));
                            }}>
                                <option value="">Komprehensif</option>
                                <option value="follow_up">Terbatas · kontrol lanjutan</option>
                                <option value="emergency">Terbatas · kegawatdaruratan</option>
                                <option value="single_focus">Terbatas · fokus tunggal</option>
                                <option value="special_condition">Terbatas · kondisi khusus</option>
                            </Select>
                        </Field>}
                        {isDental && signatureForm.data.dental_scope && <div className="grid gap-3">
                            {([
                                ['dental_scope_reason', 'Alasan pembatasan'],
                                ['dental_focus', 'Fokus gigi atau area dan temuan klinis'],
                                ['dental_follow_up_plan', 'Rencana tindak lanjut atau odontogram lengkap'],
                                ...(signatureForm.data.dental_scope === 'follow_up' ? [['dental_prior_chart', 'Rujukan odontogram awal lengkap']] as const : []),
                            ] as const).map(([name, label]) => <Field key={name} label={label} htmlFor={name} error={signatureForm.errors[name]} required>
                                <Textarea id={name} rows={2} required maxLength={name === 'dental_prior_chart' ? 1000 : 2000} value={signatureForm.data[name]} onChange={(event) => signatureForm.setData(name, event.target.value)} />
                            </Field>)}
                        </div>}
                        {isDental && visit.dentalEscalationRequired && <div className="grid gap-3">
                            <Field htmlFor="dental-escalation-decision" label="Keputusan dokter gigi penanggung jawab" error={signatureForm.errors.dental_escalation_decision} required>
                                <Select id="dental-escalation-decision" required value={signatureForm.data.dental_escalation_decision} onChange={(event) => signatureForm.setData('dental_escalation_decision', event.target.value)}>
                                    <option value="">Pilih keputusan</option><option value="deferred">Tindakan ditunda</option><option value="stabilized">Penanganan awal sesuai kemampuan klinik</option><option value="referred">Koordinasi penilaian lanjutan atau rujukan</option><option value="reassessed">Dinilai ulang dan dapat melanjutkan</option>
                                </Select>
                            </Field>
                            <Field htmlFor="dental-escalation-note" label="Temuan, tindakan awal, dan tindak lanjut" error={signatureForm.errors.dental_escalation_note} required>
                                <Textarea id="dental-escalation-note" required rows={2} maxLength={2000} placeholder="Catat hasil penilaian ulang, pengukuran yang dapat dilakukan, dan tujuan serta hasil koordinasi bila dirujuk." value={signatureForm.data.dental_escalation_note} onChange={(event) => signatureForm.setData('dental_escalation_note', event.target.value)} />
                            </Field>
                        </div>}
                        <Field error={signatureForm.errors.signature_password} htmlFor="signature-password" label="Kata sandi akun" required><Input placeholder="Masukkan kata sandi akun Anda" autoComplete="current-password" id="signature-password" onChange={(event) => signatureForm.setData('signature_password', event.target.value)} required type="password" value={signatureForm.data.signature_password} /></Field>
                        <div className="flex justify-end gap-2"><Button onClick={() => setSignatureDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={reviewPageStale || saving || !canEdit} type="submit"><Check className="size-4" />Tandatangani pemeriksaan</Button></div>
                    </form></fieldset>
                </DialogContent>
            </Dialog>
        </>
    );
}

function SoapEntries({ entries, empty }: { entries: string[]; empty: string }) {
    return entries.length
        ? <ul className="list-disc space-y-1 pl-5 text-sm">{entries.map((entry, index) => <li className="break-words whitespace-pre-line" key={`${index}-${entry}`}>{entry}</li>)}</ul>
        : <p className="text-sm text-neutral-600">{empty}</p>;
}

function ConfirmedHistoryField({ id, kind, label, placeholder, value, onChange, error, previousValues }: {
    id: string;
    kind: keyof typeof confirmedAbsentHistory;
    label: string;
    placeholder: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    previousValues: Map<string, string>;
}) {
    const absentStatement = confirmedAbsentHistory[kind];
    const confirmedAbsent = value === absentStatement;
    const checklistLabels = {
        allergy: 'Tidak ada alergi',
        differential: 'Tidak ada diagnosis banding lain',
        medication: 'Tidak menggunakan obat',
        dentalTreatment: 'Tidak ada riwayat perawatan gigi',
        dentalHabits: 'Tidak ada kebiasaan terkait gigi dan mulut',
        past: 'Tidak ada riwayat penyakit dahulu',
        family: 'Tidak ada riwayat penyakit keluarga',
    };

    function confirmAbsence(checked: boolean): void {
        if (!checked) {
            onChange(previousValues.get(id) ?? '');
            previousValues.delete(id);
            return;
        }

        const applyAbsence = () => {
            previousValues.set(id, value);
            onChange(absentStatement);
        };

        if (normalizeClinicalAnswer(value)) {
            confirmAction(`Ganti isian ${label.toLocaleLowerCase()} dengan pernyataan tidak ada yang sudah dikonfirmasi kepada pasien? Lepas centang untuk mengembalikan isian sebelumnya.`, applyAbsence, 'Konfirmasi tidak ada');
            return;
        }

        applyAbsence();
    }

    return <Field error={error} htmlFor={id} label={label}>
        <div className="space-y-3">
            <Textarea className={kind === 'allergy' && isReportedAllergy(value) ? 'border-red-300 focus-visible:ring-red-500' : undefined} id={id} maxLength={kind === 'allergy' ? 5000 : 10000} onChange={(event) => onChange(event.target.value)} placeholder={placeholder} readOnly={confirmedAbsent} rows={3} value={value} />
            <label className="flex cursor-pointer items-start gap-2 text-xs leading-5 text-neutral-700" htmlFor={`${id}-none`}>
                <Checkbox aria-label={`Konfirmasi tidak ada: ${label.toLocaleLowerCase()}`} checked={confirmedAbsent} className="mt-0.5 shrink-0" id={`${id}-none`} onCheckedChange={(checked) => confirmAbsence(checked === true)} />
                <span>{checklistLabels[kind]}, sudah dikonfirmasi</span>
            </label>
        </div>
    </Field>;
}

function renderPrescriptionItems(items: PrescriptionItem[], editable: boolean, onDelete: (url: string, confirmation: string) => void) {
    return <div className="overflow-x-auto rounded-xl border border-neutral-200">
        <Table aria-label="Tabel resep tercatat" className="min-w-[40rem] table-fixed">
            <TableHeader><TableRow>
                <TableHead className="w-48 text-left">Nama obat</TableHead>
                <TableHead className="w-24 px-2">Jenis</TableHead>
                <TableHead className="w-24 px-2">Jumlah</TableHead>
                <TableHead className="text-left">Aturan pakai</TableHead>
                <TableHead className="w-20 px-2">Aksi</TableHead>
            </TableRow></TableHeader>
            <TableBody>{items.length ? items.map(item => <TableRow key={item.id}>
                <TableCell className="align-top whitespace-normal text-left font-medium [overflow-wrap:anywhere]">{item.name}<div className="mt-1 flex flex-wrap gap-1">{item.isHighAlert && <span className="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-800">HIGH ALERT</span>}{item.isLasa && <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900">LASA</span>}</div>{item.allergyOverrideReason && <p className="mt-1 text-xs font-normal text-red-700">Override alergi ({item.allergyMatchedTerm}): {item.allergyOverrideReason}</p>}</TableCell>
                <TableCell className="px-2 align-top">{item.type === 'jadi' ? 'Jadi' : 'Racikan'}</TableCell>
                <TableCell className="px-2 align-top tabular-nums [overflow-wrap:anywhere]">{item.quantity} {item.unit}</TableCell>
                <TableCell className="align-top text-left"><RecordedTableNote parts={[item.instructions]} emptyLabel="Belum ditetapkan oleh dokter" /></TableCell>
                <TableCell className="px-2 align-top">
                    <ActionButton disabled={!editable} onClick={() => onDelete(`/pelayanan/pemeriksaan/resep/${item.id}`, 'Hapus obat dari resep? Stok akan dikembalikan bila sebelumnya dikurangi.')} type="button" action="delete" label={`Hapus obat ${item.name}`} />
                </TableCell>
            </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Belum ada obat dalam resep." /></TableCell></TableRow>}</TableBody>
        </Table>
    </div>;
}

function formatMeasurement(value: number | string | null, unit: string): string | null {
    return value === null || value === '' ? null : `${value} ${unit}`;
}

function formatBloodPressure(systolic: number | null, diastolic: number | null): string {
    return `${systolic ?? '—'} / ${diastolic ?? '—'} mmHg`;
}

function displayDentalValues(values: string[]): string | null {
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

    return values.length > 0 ? values.map((value) => labels[value] ?? value).join(', ') : null;
}

function AssessmentNote({ label, value }: { label: string; value?: string | null }) {
    const answer = normalizeClinicalAnswer(value);

    return answer ? <div className="min-w-0"><dt className="text-xs font-medium text-neutral-500">{label}</dt><dd className="mt-1 whitespace-pre-wrap break-words text-sm text-neutral-800 [overflow-wrap:anywhere]">{answer}</dd></div> : null;
}
