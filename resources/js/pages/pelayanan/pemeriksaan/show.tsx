import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, ClipboardPlus, FileText, HeartPulse, Lightbulb, Pill, Plus, Search, Stethoscope, Trash2, UserRound } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { DatePicker } from '@/components/ui/date-picker';
import { toast } from 'sonner';

interface Option { id: number; name: string; }
interface Diagnosis { id: number; code: string; codeSystem?: string; name: string; type: string; }
interface Treatment { id: number; name: string; quantity: number; tariff: number; toothFdi: string | null; }
interface DentalFinding { toothFdi: string; surface: string; findingCode: string; notes: string | null; }
interface PrescriptionItem { id: number; name: string; type: string; quantity: number; unit: string | null; instructions: string | null; external: boolean; }
interface Letter { id: number; type: string; number: string | null; content: string | null; date: string | null; }
interface Referral { id: number; fromClinic: string | null; toClinic: string | null; notes: string | null; status: string; }
interface Examination {
    doctorId: number | null;
    anamnesis: string | null;
    physicalExam: string | null;
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
    paymentType: string;
    doctorId: number | null;
    clinicId: number;
    clinicType: string | null;
    clinic: string;
    patient: { id: number; name: string; medicalRecordNumber: string; gender: string | null; age: number | null; bloodType: string | null; allergies: string | null; };
    screening: { systolic: number | null; diastolic: number | null; pulse: number | null; temperature: number | null; oxygenSaturation: number | null; complaint: string | null; } | null;
    examination: Examination | null;
    diagnoses: Diagnosis[];
    treatments: Treatment[];
    odontogram: DentalFinding[];
    prescription: { status: string | null; items: PrescriptionItem[] };
    letters: Letter[];
    referrals: Referral[];
}
interface Props {
    visit: Visit;
    doctors: Option[];
    treatments: (Option & { tariff: number })[];
    medicines: (Option & { stock: number; unit: string | null })[];
    clinics: Option[];
    diagnosisCodeSystems: Array<{ value: string; label: string }>;
    dentalOptions: { teeth: string[]; surfaces: Record<string, string>; findings: Record<string, string> };
    today: string;
}
interface IcdSuggestion { kode: string; nama: string; code_system: string; release: string; }
type Tab = 'anamnesis' | 'diagnosa' | 'tindakan' | 'resep' | 'surat' | 'rujukan';

const tabs: { id: Tab; label: string; icon: typeof Stethoscope }[] = [
    { id: 'anamnesis', label: 'Anamnesis & fisik', icon: Stethoscope },
    { id: 'diagnosa', label: 'Diagnosis', icon: ClipboardPlus },
    { id: 'tindakan', label: 'Tindakan', icon: HeartPulse },
    { id: 'resep', label: 'Resep obat', icon: Pill },
    { id: 'surat', label: 'Surat medis', icon: FileText },
    { id: 'rujukan', label: 'Rujukan internal', icon: ArrowLeft },
];

export default function ExaminationDetail({ visit, doctors, treatments, medicines, clinics, diagnosisCodeSystems, dentalOptions, today }: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('anamnesis');
    const [icdQuery, setIcdQuery] = useState('');
    const [diagnosisCodeSystem, setDiagnosisCodeSystem] = useState('icd10_who');
    const [icdResults, setIcdResults] = useState<IcdSuggestion[]>([]);
    const [icdLoading, setIcdLoading] = useState(false);
    const [suggestions, setSuggestions] = useState<IcdSuggestion[]>([]);
    const [suggestionsOpen, setSuggestionsOpen] = useState(false);
    const examinationForm = useForm({
        dokter_id: (visit.examination?.doctorId ?? visit.doctorId ?? '').toString(),
        anamnesis: visit.examination?.anamnesis ?? visit.screening?.complaint ?? '',
        pemeriksaan_fisik: visit.examination?.physicalExam ?? '',
        kontrol_berikutnya: visit.examination?.followUpDate ?? '',
        catatan: visit.examination?.notes ?? '',
        edukasi: visit.examination?.education ?? '',
    });
    const diagnosisForm = useForm({ kode_icd10: '', code_system: 'icd10_who', code_release: '', nama_diagnosa: '', jenis: 'utama' });
    const treatmentForm = useForm({ tindakan_id: '', jumlah: '1', tooth_fdi: '' });
    const dentalForm = useForm({ tooth_fdi: '', surface: 'W', finding_code: 'caries', notes: '', reason: '' });
    const prescriptionForm = useForm({ obat_id: '', nama_obat: '', jumlah: '1', satuan: '', aturan_pakai: '', catatan: '', jenis: 'jadi', is_resep_luar: false });
    const letterForm = useForm({ jenis: 'sakit', nomor_surat: '', tanggal: today, konten: '' });
    const referralForm = useForm({ ke_poli_id: '', catatan: '' });
    const addendumForm = useForm({
        reason: '',
        anamnesis: visit.examination?.anamnesis ?? '',
        pemeriksaan_fisik: visit.examination?.physicalExam ?? '',
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

    function submitForm(event: FormEvent<HTMLFormElement>, form: typeof examinationForm | typeof diagnosisForm | typeof treatmentForm | typeof prescriptionForm | typeof letterForm | typeof referralForm, url: string) {
        event.preventDefault();
        form.post(url);
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
        confirmAction('Selesaikan pemeriksaan dan teruskan pasien ke tahap layanan berikutnya?', () => { router.post(`/pelayanan/pemeriksaan/${visit.id}/selesai`); }, 'Lanjutkan');
    }

    const canEdit = visit.status === 'pemeriksaan' && visit.examination?.status !== 'selesai';
    const prescriptionEditable = canEdit && (!visit.prescription.status || visit.prescription.status === 'menunggu');

    return (
        <>
            <Head title={`Pemeriksaan ${visit.patient.name}`} />
            <div className="space-y-6">
                <Card><CardContent className="flex flex-col justify-between gap-5 p-5 lg:flex-row lg:items-center">
                    <div className="flex items-center gap-4"><span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-lg font-bold text-neutral-800">{visit.patient.name.slice(0, 1).toLocaleUpperCase()}</span><div><p className="text-xs font-medium text-neutral-500">{visit.number} · {visit.clinic}</p><h2 className="mt-0.5 text-lg font-semibold text-neutral-950">{visit.patient.name}</h2><p className="mt-1 text-xs text-neutral-600">{visit.patient.medicalRecordNumber} · {visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : '—'}, {visit.patient.age === null ? 'umur belum diketahui' : `${visit.patient.age} th`} · Gol. {visit.patient.bloodType ?? '—'} · {visit.paymentType.toUpperCase()}</p></div></div>
                    <div className="flex flex-wrap items-center gap-2">{visit.screening && <div className="mr-1 flex flex-wrap gap-1.5 rounded-lg bg-neutral-50 p-2 text-xs"><strong className="px-1 py-1 text-neutral-700">Tanda vital</strong><Badge>TD {visit.screening.systolic ?? '—'}/{visit.screening.diastolic ?? '—'}</Badge><Badge>N {visit.screening.pulse ?? '—'}x</Badge><Badge>S {visit.screening.temperature ?? '—'}°C</Badge><Badge>SpO₂ {visit.screening.oxygenSaturation ?? '—'}%</Badge></div>}<Button asChild size="sm" variant="secondary"><Link href={`/pelayanan/pasien/${visit.patient.id}/rekam-medis`}><UserRound className="size-4" />Riwayat RME</Link></Button><Button disabled={!visit.examination || !canEdit} onClick={finishExamination} size="sm"><Check className="size-4" />Selesai periksa</Button></div>
                {visit.patient.allergies && <Alert className="w-full lg:basis-full" variant="destructive"><AlertTriangle aria-hidden="true" /><AlertTitle>Peringatan alergi</AlertTitle><AlertDescription>{visit.patient.allergies}</AlertDescription></Alert>}
                </CardContent></Card>

                {!canEdit && <Alert><AlertTitle>Pemeriksaan terkunci</AlertTitle><AlertDescription>Data kunjungan ini sudah melewati tahap pemeriksaan. Koreksi dicatat sebagai addendum beralasan tanpa mengubah versi final.</AlertDescription></Alert>}
                {visit.examination?.integrityValid === false && <Alert variant="destructive"><AlertTriangle aria-hidden="true" /><AlertTitle>Integritas catatan perlu diperiksa</AlertTitle><AlertDescription>Rantai versi catatan tidak cocok. Addendum dihentikan sampai data diperiksa oleh pengelola rekam medis.</AlertDescription></Alert>}

                {visit.clinicType === 'gigi' && <Card>
                    <CardHeader><CardTitle>Odontogram kunjungan</CardTitle><CardDescription>Catat gigi FDI, permukaan, dan temuan. Koreksi setelah finalisasi membuat versi baru.</CardDescription></CardHeader>
                    <CardContent className="space-y-5">
                        {visit.odontogram.length > 0 ? <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">{visit.odontogram.map((finding) => <article className="rounded-xl border border-neutral-200 p-4" key={`${finding.toothFdi}-${finding.surface}`}><div className="flex items-center justify-between gap-2"><strong className="font-mono text-base text-neutral-950">Gigi {finding.toothFdi}</strong><Badge>{dentalOptions.surfaces[finding.surface] ?? finding.surface}</Badge></div><p className="mt-2 text-sm font-medium text-neutral-800">{dentalOptions.findings[finding.findingCode] ?? finding.findingCode}</p>{finding.notes && <p className="mt-1 whitespace-pre-wrap text-sm text-neutral-600">{finding.notes}</p>}</article>)}</div> : <Empty className="min-h-0 py-5" description="Temuan gigi perlu dicatat sebelum pemeriksaan gigi difinalisasi." title="Belum ada temuan odontogram" />}
                        {(canEdit || visit.examination?.signedAt) && <form className="space-y-4 border-t border-neutral-200 pt-5" onSubmit={(event) => { event.preventDefault(); dentalForm.post(`/pelayanan/pemeriksaan/${visit.id}/odontogram${canEdit ? '' : '/addendum'}`, { preserveScroll: true, onSuccess: () => dentalForm.reset('notes', 'reason') }); }}>
                            <div className="grid gap-4 md:grid-cols-3"><Field error={dentalForm.errors.tooth_fdi} htmlFor="dental-tooth" label="Nomor gigi FDI" required><Select id="dental-tooth" onChange={(event) => dentalForm.setData('tooth_fdi', event.target.value)} required value={dentalForm.data.tooth_fdi}><option value="">Pilih gigi</option>{dentalOptions.teeth.map((tooth) => <option key={tooth} value={tooth}>{tooth}</option>)}</Select></Field><Field error={dentalForm.errors.surface} htmlFor="dental-surface" label="Permukaan" required><Select id="dental-surface" onChange={(event) => dentalForm.setData('surface', event.target.value)} value={dentalForm.data.surface}>{Object.entries(dentalOptions.surfaces).map(([code, label]) => <option key={code} value={code}>{label}</option>)}</Select></Field><Field error={dentalForm.errors.finding_code} htmlFor="dental-finding" label="Temuan" required><Select id="dental-finding" onChange={(event) => dentalForm.setData('finding_code', event.target.value)} value={dentalForm.data.finding_code}>{Object.entries(dentalOptions.findings).map(([code, label]) => <option key={code} value={code}>{label}</option>)}</Select></Field></div>
                            <Field error={dentalForm.errors.notes} htmlFor="dental-notes" label="Catatan temuan"><Textarea id="dental-notes" onChange={(event) => dentalForm.setData('notes', event.target.value)} rows={2} value={dentalForm.data.notes} /></Field>
                            {!canEdit && <Field error={dentalForm.errors.reason} htmlFor="dental-reason" label="Alasan addendum" required><Textarea id="dental-reason" minLength={10} onChange={(event) => dentalForm.setData('reason', event.target.value)} required rows={2} value={dentalForm.data.reason} /></Field>}
                            <div className="flex justify-end"><Button disabled={dentalForm.processing || visit.examination?.integrityValid === false} type="submit">{canEdit ? 'Simpan temuan' : 'Simpan addendum odontogram'}</Button></div>
                        </form>}
                    </CardContent>
                </Card>}

                {visit.examination?.signedAt && <Card><CardHeader><CardTitle>Finalisasi dan riwayat koreksi</CardTitle><CardDescription>Difinalisasi oleh {visit.examination.signedBy ?? 'dokter'} pada {visit.examination.signedAt}. Setiap versi menyimpan penulis, waktu, dan alasan koreksi.</CardDescription></CardHeader><CardContent className="space-y-5">
                    <div className="space-y-3">{visit.examination.versions.map((version) => <article className="rounded-xl border border-neutral-200 p-4" key={version.version}><div className="flex flex-wrap items-center gap-2"><Badge variant={version.kind === 'addendum' ? 'waiting' : 'complete'}>Versi {version.version} · {version.kind === 'addendum' ? 'Addendum' : 'Final'}</Badge><span className="text-xs text-neutral-600">{version.actor ?? 'Dokter'} · {version.recordedAt ?? '—'}</span></div>{version.reason && <p className="mt-2 text-sm font-medium text-neutral-800">Alasan: {version.reason}</p>}<div className="mt-3 grid gap-2 text-sm text-neutral-700 md:grid-cols-2"><p className="whitespace-pre-wrap"><strong>Anamnesis:</strong> {version.anamnesis || '—'}</p><p className="whitespace-pre-wrap"><strong>Pemeriksaan fisik:</strong> {version.physicalExam || '—'}</p><p className="whitespace-pre-wrap"><strong>Catatan:</strong> {version.notes || '—'}</p><p className="whitespace-pre-wrap"><strong>Edukasi:</strong> {version.education || '—'}</p></div>{visit.clinicType === 'gigi' && version.odontogram.length > 0 && <p className="mt-3 text-sm text-neutral-700"><strong>Odontogram:</strong> {version.odontogram.map((finding) => `${finding.toothFdi} ${dentalOptions.surfaces[finding.surface] ?? finding.surface}: ${dentalOptions.findings[finding.findingCode] ?? finding.findingCode}`).join('; ')}</p>}</article>)}</div>
                    <form className="space-y-4 border-t border-neutral-200 pt-5" onSubmit={(event) => { event.preventDefault(); addendumForm.post(`/pelayanan/pemeriksaan/${visit.id}/addendum`, { preserveScroll: true, onSuccess: () => addendumForm.reset('reason') }); }}><div><h3 className="text-sm font-semibold text-neutral-950">Buat addendum</h3><p className="mt-1 text-sm text-neutral-600">Ubah bagian yang perlu dikoreksi. Nilai versi sebelumnya tetap tersimpan.</p></div><Field error={addendumForm.errors.reason} htmlFor="addendum-reason" label="Alasan koreksi" required><Textarea id="addendum-reason" minLength={10} onChange={(event) => addendumForm.setData('reason', event.target.value)} required rows={2} value={addendumForm.data.reason} /></Field><div className="grid gap-4 md:grid-cols-2"><Field error={addendumForm.errors.anamnesis} htmlFor="addendum-anamnesis" label="Anamnesis"><Textarea id="addendum-anamnesis" onChange={(event) => addendumForm.setData('anamnesis', event.target.value)} rows={3} value={addendumForm.data.anamnesis} /></Field><Field error={addendumForm.errors.pemeriksaan_fisik} htmlFor="addendum-fisik" label="Pemeriksaan fisik"><Textarea id="addendum-fisik" onChange={(event) => addendumForm.setData('pemeriksaan_fisik', event.target.value)} rows={3} value={addendumForm.data.pemeriksaan_fisik} /></Field><Field error={addendumForm.errors.catatan} htmlFor="addendum-catatan" label="Catatan dokter"><Textarea id="addendum-catatan" onChange={(event) => addendumForm.setData('catatan', event.target.value)} rows={3} value={addendumForm.data.catatan} /></Field><Field error={addendumForm.errors.edukasi} htmlFor="addendum-edukasi" label="Edukasi pasien"><Textarea id="addendum-edukasi" onChange={(event) => addendumForm.setData('edukasi', event.target.value)} rows={3} value={addendumForm.data.edukasi} /></Field></div><Field error={addendumForm.errors.kontrol_berikutnya} htmlFor="addendum-kontrol" label="Kontrol berikutnya"><DatePicker id="addendum-kontrol" onChange={(event) => addendumForm.setData('kontrol_berikutnya', event.target.value)} value={addendumForm.data.kontrol_berikutnya} /></Field><div className="flex justify-end"><Button disabled={addendumForm.processing} type="submit">Simpan addendum</Button></div></form>
                </CardContent></Card>}

                <Card className="overflow-hidden">
                    <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as Tab)}>
                    <TabsList aria-label="Bagian pemeriksaan">{tabs.map(({ id, icon: Icon, label }, index) => <TabsTrigger className="min-h-12 px-3 sm:px-4" key={id} value={id}><Icon aria-hidden="true" className="size-4" />{index + 1}. {label}{id === 'diagnosa' && <Badge>{visit.diagnoses.length}</Badge>}{id === 'tindakan' && <Badge>{visit.treatments.length}</Badge>}{id === 'resep' && <Badge>{visit.prescription.items.length}</Badge>}</TabsTrigger>)}</TabsList>

                    <fieldset className="min-w-0" disabled={!canEdit}>

                    <TabsContent className="space-y-5 p-5 sm:p-6" value="anamnesis">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-anamnesis">Anamnesis dan pemeriksaan fisik</h3><p className="mt-1 text-sm text-neutral-500">Catat kondisi klinis, rencana kontrol, dan edukasi pasien.</p></div>
                        <form className="space-y-5" onSubmit={(event) => submitForm(event, examinationForm, `/pelayanan/pemeriksaan/${visit.id}`)}>
                            <Field error={examinationForm.errors.dokter_id} htmlFor="dokter_id" label="Dokter pemeriksa"><Select id="dokter_id" onChange={(event) => examinationForm.setData('dokter_id', event.target.value)} value={examinationForm.data.dokter_id}><option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select></Field>
                            <Field error={examinationForm.errors.anamnesis} htmlFor="anamnesis" label="Anamnesis (keluhan dan riwayat penyakit)"><div className="space-y-2"><Textarea id="anamnesis" onChange={(event) => examinationForm.setData('anamnesis', event.target.value)} placeholder="Keluhan pasien, riwayat penyakit sekarang..." rows={4} value={examinationForm.data.anamnesis} /><Button onClick={suggestDiagnoses} size="sm" type="button" variant="secondary"><Lightbulb className="size-4" />Saran diagnosis berdasarkan kata kunci</Button></div></Field>
                            {suggestionsOpen && <div className="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4"><div className="flex justify-between gap-3"><div><p className="text-sm font-semibold text-neutral-950">Hasil katalog terminologi</p><p className="text-xs text-neutral-800">Kandidat berasal dari katalog yang diimpor. Dokter tetap menilai dan mengonfirmasi kode.</p></div><Button onClick={() => setSuggestionsOpen(false)} size="sm" type="button" variant="ghost">Tutup</Button></div>{suggestions.length ? <div className="flex flex-wrap gap-2">{suggestions.map((item) => <Button key={`${item.code_system}-${item.kode}-${item.nama}`} onClick={() => { chooseDiagnosis(item.kode, item.nama, item.code_system, item.release); setDiagnosisCodeSystem(item.code_system); setActiveTab('diagnosa'); }} size="sm" type="button" variant="secondary">{item.kode} · {item.nama}</Button>)}</div> : <p className="text-sm text-neutral-500">Tidak ada kecocokan pada katalog untuk kata yang dipilih.</p>}</div>}
                            <Field error={examinationForm.errors.pemeriksaan_fisik} htmlFor="pemeriksaan_fisik" label="Pemeriksaan fisik"><Textarea id="pemeriksaan_fisik" onChange={(event) => examinationForm.setData('pemeriksaan_fisik', event.target.value)} placeholder="Kepala, leher, thorax, abdomen, ekstremitas..." rows={4} value={examinationForm.data.pemeriksaan_fisik} /></Field>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={examinationForm.errors.kontrol_berikutnya} htmlFor="kontrol_berikutnya" label="Jadwal kontrol berikutnya"><DatePicker id="kontrol_berikutnya" onChange={(event) => examinationForm.setData('kontrol_berikutnya', event.target.value)}  value={examinationForm.data.kontrol_berikutnya} /></Field><Field error={examinationForm.errors.catatan} htmlFor="catatan" label="Catatan khusus dokter"><Input id="catatan" onChange={(event) => examinationForm.setData('catatan', event.target.value)} placeholder="Catatan internal" value={examinationForm.data.catatan} /></Field></div>
                            <Field error={examinationForm.errors.edukasi} htmlFor="edukasi" label="Edukasi pasien"><Textarea id="edukasi" onChange={(event) => examinationForm.setData('edukasi', event.target.value)} placeholder="Anjuran, cara penggunaan obat, larangan, dan jadwal kontrol" rows={3} value={examinationForm.data.edukasi} /></Field>
                            <div className="flex justify-end"><Button disabled={examinationForm.processing} type="submit">Simpan pemeriksaan</Button></div>
                        </form>
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="diagnosa">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-diagnosa">Diagnosis terstandar</h3><p className="mt-1 text-sm text-neutral-500">Cari dan catat kode dari sistem terminologi yang sesuai dengan katalog klinik.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, diagnosisForm, `/pelayanan/pemeriksaan/${visit.id}/diagnosa`)}>
                        <div className="grid gap-4 md:grid-cols-[16rem_minmax(0,1fr)]"><Field error={diagnosisForm.errors.code_system} htmlFor="diagnosis-code-system" label="Sistem kode"><Select id="diagnosis-code-system" onChange={(event) => { setDiagnosisCodeSystem(event.target.value); diagnosisForm.setData((data) => ({ ...data, code_system: event.target.value, code_release: '', kode_icd10: '', nama_diagnosa: '' })); }} value={diagnosisCodeSystem}>{diagnosisCodeSystems.map((system) => <option key={system.value} value={system.value}>{system.label}</option>)}</Select></Field><div className="relative"><Field htmlFor="icd-search" label="Cari kode atau uraian"><div className="relative"><Search className="absolute left-3 top-3 size-4 text-neutral-400" /><Input autoComplete="off" className="pl-9" id="icd-search" onChange={(event) => setIcdQuery(event.target.value)} placeholder="Ketik kode atau nama diagnosis" value={icdQuery} /></div></Field>{(icdResults.length > 0 || icdLoading) && <div className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-xl border border-neutral-200 bg-surface shadow-lg">{icdLoading && <p className="p-3 text-sm text-neutral-500">Mencari…</p>}{icdResults.map((result) => <Button className="h-auto w-full justify-between rounded-none border-b border-neutral-100 px-3 py-2 text-left font-normal hover:bg-neutral-50 hover:text-neutral-900" key={`${result.code_system}-${result.release}-${result.kode}`} onClick={() => chooseDiagnosis(result.kode, result.nama, result.code_system, result.release)} type="button" variant="ghost"><span className="font-mono font-semibold text-neutral-700">{result.kode}</span><span>{result.nama}</span></Button>)}</div>}</div></div>
                            <div className="grid gap-4 md:grid-cols-3"><Field error={diagnosisForm.errors.kode_icd10} htmlFor="kode_icd10" label="Kode diagnosis" required><Input className="font-mono uppercase" id="kode_icd10" onChange={(event) => diagnosisForm.setData('kode_icd10', event.target.value.toUpperCase())} required value={diagnosisForm.data.kode_icd10} /></Field><Field error={diagnosisForm.errors.nama_diagnosa} htmlFor="nama_diagnosa" label="Nama diagnosis" required><Input id="nama_diagnosa" onChange={(event) => diagnosisForm.setData('nama_diagnosa', event.target.value)} required value={diagnosisForm.data.nama_diagnosa} /></Field><Field error={diagnosisForm.errors.jenis} htmlFor="jenis-diagnosa" label="Jenis diagnosis"><Select id="jenis-diagnosa" onChange={(event) => diagnosisForm.setData('jenis', event.target.value)} value={diagnosisForm.data.jenis}><option value="utama">Utama</option><option value="tambahan">Tambahan</option></Select></Field></div>
                            <div className="flex justify-end"><Button disabled={diagnosisForm.processing} type="submit"><Plus className="size-4" />Tambah diagnosis</Button></div>
                        </form>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Diagnosis tercatat</h4>{visit.diagnoses.length ? visit.diagnoses.map((diagnosis) => <div className="flex flex-col justify-between gap-3 rounded-xl border border-neutral-200 p-4 sm:flex-row sm:items-center" key={diagnosis.id}><div className="flex flex-wrap items-center gap-3"><Badge variant="examination">{diagnosis.code}</Badge><span className="text-sm font-medium text-neutral-900">{diagnosis.name}</span><Badge>{diagnosis.type === 'utama' ? 'Utama' : 'Tambahan'}</Badge></div><Button onClick={() => deleteItem(`/pelayanan/pemeriksaan/diagnosa/${diagnosis.id}`, 'Hapus diagnosis ini?')} size="sm" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" />Hapus</Button></div>) : <Empty className="min-h-0 py-6" description="Diagnosis yang dicatat selama pemeriksaan akan tampil di sini." title="Belum ada diagnosis" />}</div>
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="tindakan">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-tindakan">Tindakan medis</h3><p className="mt-1 text-sm text-neutral-500">Tindakan yang tersedia mengikuti poliklinik kunjungan ini.</p></div>
                        <form className="grid gap-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4 md:grid-cols-[minmax(0,1fr)_9rem_auto] md:items-end" onSubmit={(event) => submitForm(event, treatmentForm, `/pelayanan/pemeriksaan/${visit.id}/tindakan`)}><Field error={treatmentForm.errors.tindakan_id} htmlFor="tindakan_id" label="Tindakan" required><Select id="tindakan_id" onChange={(event) => treatmentForm.setData('tindakan_id', event.target.value)} required value={treatmentForm.data.tindakan_id}><option value="">Pilih tindakan</option>{treatments.map((item) => <option key={item.id} value={item.id}>{item.name}</option>)}</Select></Field><Field error={treatmentForm.errors.jumlah} htmlFor="jumlah-tindakan" label="Jumlah" required><Input id="jumlah-tindakan" min="1" onChange={(event) => treatmentForm.setData('jumlah', event.target.value)} required type="number" value={treatmentForm.data.jumlah} /></Field>{visit.clinicType === 'gigi' && <Field error={treatmentForm.errors.tooth_fdi} htmlFor="treatment-tooth" label="Gigi FDI"><Select id="treatment-tooth" onChange={(event) => treatmentForm.setData('tooth_fdi', event.target.value)} value={treatmentForm.data.tooth_fdi}><option value="">Seluruh mulut / tidak spesifik</option>{dentalOptions.teeth.map((tooth) => <option key={tooth} value={tooth}>{tooth}</option>)}</Select></Field>}<Button disabled={treatmentForm.processing} type="submit"><Plus className="size-4" />Tambah</Button></form>
                        <div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Nama tindakan</TableHead><TableHead>Jumlah</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{visit.treatments.length ? visit.treatments.map((item) => <TableRow key={item.id}><TableCell className="font-medium">{item.name}{item.toothFdi && <span className="ml-2 text-xs text-neutral-500">Gigi {item.toothFdi}</span>}</TableCell><TableCell>{item.quantity}x</TableCell><TableCell className="text-right"><Button onClick={() => deleteItem(`/pelayanan/pemeriksaan/tindakan/${item.id}`, 'Hapus tindakan ini?')} size="sm" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" />Hapus</Button></TableCell></TableRow>) : <TableRow><TableCell colSpan={3}><Empty size="compact" title="Belum ada tindakan medis." /></TableCell></TableRow>}</TableBody></Table></div>
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="resep">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-resep">Resep obat</h3><p className="mt-1 text-sm text-neutral-500">Resep obat klinik akan mengurangi stok saat ditambahkan. Resep luar tidak mengurangi stok.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, prescriptionForm, `/pelayanan/pemeriksaan/${visit.id}/resep`)}>
                            <div className="grid gap-4 md:grid-cols-3"><Field error={prescriptionForm.errors.jenis} htmlFor="jenis-resep" label="Jenis resep"><Select id="jenis-resep" onChange={(event) => prescriptionForm.setData('jenis', event.target.value)} value={prescriptionForm.data.jenis}><option value="jadi">Obat jadi</option><option value="racikan">Obat racikan</option></Select></Field>{!prescriptionForm.data.is_resep_luar && <Field error={prescriptionForm.errors.obat_id} htmlFor="obat_id" label="Obat dari stok klinik" required><Select id="obat_id" onChange={(event) => prescriptionForm.setData('obat_id', event.target.value)} required value={prescriptionForm.data.obat_id}><option value="">Pilih obat</option>{medicines.map((medicine) => <option key={medicine.id} value={medicine.id}>{medicine.name} · stok {medicine.stock} {medicine.unit}</option>)}</Select></Field>}{prescriptionForm.data.is_resep_luar && <Field error={prescriptionForm.errors.nama_obat} htmlFor="nama_obat" label="Nama obat luar" required><Input id="nama_obat" onChange={(event) => prescriptionForm.setData('nama_obat', event.target.value)} required value={prescriptionForm.data.nama_obat} /></Field>}<Field error={prescriptionForm.errors.jumlah} htmlFor="jumlah-obat" label="Jumlah" required><Input id="jumlah-obat" min={prescriptionForm.data.is_resep_luar ? '0.01' : '1'} onChange={(event) => prescriptionForm.setData('jumlah', event.target.value)} required step={prescriptionForm.data.is_resep_luar ? '0.01' : '1'} type="number" value={prescriptionForm.data.jumlah} /></Field></div>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={prescriptionForm.errors.aturan_pakai} htmlFor="aturan_pakai" label="Aturan pakai"><Input id="aturan_pakai" onChange={(event) => prescriptionForm.setData('aturan_pakai', event.target.value)} placeholder="3 x 1 tablet setelah makan" value={prescriptionForm.data.aturan_pakai} /></Field><Field error={prescriptionForm.errors.catatan} htmlFor="catatan-resep" label="Catatan"><Input id="catatan-resep" onChange={(event) => prescriptionForm.setData('catatan', event.target.value)} placeholder="Habiskan, bila demam..." value={prescriptionForm.data.catatan} /></Field></div>
                            <Label className="flex items-center gap-2 text-sm font-medium text-neutral-800"><Checkbox checked={prescriptionForm.data.is_resep_luar} onCheckedChange={(checked) => prescriptionForm.setData((data) => ({ ...data, is_resep_luar: (checked === true), obat_id: '', nama_obat: '' }))} />Resep luar (ditebus di apotek luar)</Label><div className="flex justify-end"><Button disabled={prescriptionForm.processing} type="submit"><Plus className="size-4" />Tambahkan ke resep</Button></div>
                        </form>
                        <div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Nama obat</TableHead><TableHead>Jenis</TableHead><TableHead>Jumlah</TableHead><TableHead>Aturan pakai</TableHead><TableHead>Tipe</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{visit.prescription.items.length ? visit.prescription.items.map((item) => <TableRow key={item.id}><TableCell className="font-medium">{item.name}</TableCell><TableCell>{item.type === 'jadi' ? 'Jadi' : 'Racikan'}</TableCell><TableCell>{item.quantity} {item.unit}</TableCell><TableCell>{item.instructions ?? '—'}</TableCell><TableCell><Badge variant={item.external ? 'waiting' : 'complete'}>{item.external ? 'Resep luar' : 'Klinik'}</Badge></TableCell><TableCell className="text-right"><Button disabled={!prescriptionEditable} onClick={() => deleteItem(`/pelayanan/pemeriksaan/resep/${item.id}`, 'Hapus obat dari resep? Stok akan dikembalikan bila sebelumnya dikurangi.')} size="sm" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" />Hapus</Button></TableCell></TableRow>) : <TableRow><TableCell colSpan={6}><Empty size="compact" title="Belum ada obat dalam resep." /></TableCell></TableRow>}</TableBody></Table></div>
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="surat">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-surat">Surat medis</h3><p className="mt-1 text-sm text-neutral-500">Terbitkan surat keterangan yang berkaitan dengan kunjungan.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, letterForm, `/pelayanan/pemeriksaan/${visit.id}/surat`)}><div className="grid gap-4 md:grid-cols-3"><Field error={letterForm.errors.jenis} htmlFor="jenis-surat" label="Jenis surat" required><Select id="jenis-surat" onChange={(event) => letterForm.setData('jenis', event.target.value)} value={letterForm.data.jenis}><option value="sakit">Surat keterangan sakit</option><option value="sehat">Surat keterangan sehat</option><option value="rujukan">Surat rujukan eksternal</option><option value="lainnya">Lainnya</option></Select></Field><Field error={letterForm.errors.nomor_surat} htmlFor="nomor_surat" label="Nomor surat"><Input id="nomor_surat" onChange={(event) => letterForm.setData('nomor_surat', event.target.value)} value={letterForm.data.nomor_surat} /></Field><Field error={letterForm.errors.tanggal} htmlFor="tanggal-surat" label="Tanggal surat" required><DatePicker id="tanggal-surat" onChange={(event) => letterForm.setData('tanggal', event.target.value)} required  value={letterForm.data.tanggal} /></Field></div><Field error={letterForm.errors.konten} htmlFor="konten-surat" label="Isi keterangan"><Textarea id="konten-surat" onChange={(event) => letterForm.setData('konten', event.target.value)} rows={3} value={letterForm.data.konten} /></Field><div className="flex justify-end"><Button disabled={letterForm.processing} type="submit"><Plus className="size-4" />Buat surat medis</Button></div></form>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Surat yang diterbitkan</h4>{visit.letters.length ? visit.letters.map((letter) => <article className="flex flex-col justify-between gap-2 rounded-xl border border-neutral-200 p-4 sm:flex-row" key={letter.id}><div><Badge variant="examination">{letter.type}</Badge><p className="mt-2 text-sm font-medium text-neutral-900">No: {letter.number ?? '—'}</p>{letter.content && <p className="mt-1 whitespace-pre-wrap text-sm text-neutral-600">{letter.content}</p>}</div><span className="text-xs text-neutral-500">{letter.date ?? '—'}</span></article>) : <Empty className="min-h-0 py-6" description="Surat medis yang diterbitkan akan tercatat di sini." title="Belum ada surat medis" />}</div>
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="rujukan">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-rujukan">Rujukan internal</h3><p className="mt-1 text-sm text-neutral-500">Arahkan pasien untuk konsultasi ke poliklinik lain.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, referralForm, `/pelayanan/pemeriksaan/${visit.id}/rujukan`)}><Field error={referralForm.errors.ke_poli_id} htmlFor="ke_poli_id" label="Poliklinik tujuan" required><Select id="ke_poli_id" onChange={(event) => referralForm.setData('ke_poli_id', event.target.value)} required value={referralForm.data.ke_poli_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field><Field error={referralForm.errors.catatan} htmlFor="catatan-rujukan" label="Catatan konsultasi"><Textarea id="catatan-rujukan" onChange={(event) => referralForm.setData('catatan', event.target.value)} rows={3} value={referralForm.data.catatan} /></Field><div className="flex justify-end"><Button disabled={referralForm.processing} type="submit"><Plus className="size-4" />Kirim rujukan</Button></div></form>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Riwayat rujukan</h4>{visit.referrals.length ? visit.referrals.map((referral) => <article className="flex flex-col justify-between gap-2 rounded-xl border border-neutral-200 p-4 sm:flex-row" key={referral.id}><div><p className="text-sm font-medium text-neutral-900">{referral.fromClinic ?? '—'} → {referral.toClinic ?? '—'}</p><p className="mt-1 text-sm text-neutral-600">{referral.notes ?? '—'}</p></div><Badge variant={referral.status === 'selesai' ? 'complete' : 'waiting'}>{referral.status}</Badge></article>) : <Empty className="min-h-0 py-6" description="Rujukan internal untuk kunjungan ini akan muncul di sini." title="Belum ada rujukan" />}</div>
                    </TabsContent>
                    </fieldset>
                    </Tabs>
                </Card>
            </div>
        </>
    );
}

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
}
