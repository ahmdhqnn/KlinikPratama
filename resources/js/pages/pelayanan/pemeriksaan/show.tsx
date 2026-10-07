import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Check, ClipboardPlus, FileText, HeartPulse, Lightbulb, Pill, Plus, Search, Stethoscope, Trash2, UserRound } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Empty } from '@/components/ui/empty';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { DatePicker } from '@/components/ui/date-picker';
import { toast } from 'sonner';

interface Option { id: number; name: string; }
interface Diagnosis { id: number; code: string; name: string; type: string; }
interface Treatment { id: number; name: string; quantity: number; tariff: number; }
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
}
interface Visit {
    id: number;
    number: string;
    status: string;
    paymentType: string;
    doctorId: number | null;
    clinicId: number;
    clinic: string;
    patient: { id: number; name: string; medicalRecordNumber: string; gender: string | null; age: number; bloodType: string | null; allergies: string | null; };
    screening: { systolic: number | null; diastolic: number | null; pulse: number | null; temperature: number | null; oxygenSaturation: number | null; complaint: string | null; } | null;
    examination: Examination | null;
    diagnoses: Diagnosis[];
    treatments: Treatment[];
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
    today: string;
}
interface IcdSuggestion { kode: string; nama: string; }
type Tab = 'anamnesis' | 'diagnosa' | 'tindakan' | 'resep' | 'surat' | 'rujukan';

const tabs: { id: Tab; label: string; icon: typeof Stethoscope }[] = [
    { id: 'anamnesis', label: 'Anamnesis & fisik', icon: Stethoscope },
    { id: 'diagnosa', label: 'Diagnosis ICD-10', icon: ClipboardPlus },
    { id: 'tindakan', label: 'Tindakan', icon: HeartPulse },
    { id: 'resep', label: 'Resep obat', icon: Pill },
    { id: 'surat', label: 'Surat medis', icon: FileText },
    { id: 'rujukan', label: 'Rujukan internal', icon: ArrowLeft },
];

export default function ExaminationDetail({ visit, doctors, treatments, medicines, clinics, today }: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('anamnesis');
    const [icdQuery, setIcdQuery] = useState('');
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
    const diagnosisForm = useForm({ kode_icd10: '', nama_diagnosa: '', jenis: 'utama' });
    const treatmentForm = useForm({ tindakan_id: '', jumlah: '1' });
    const prescriptionForm = useForm({ obat_id: '', nama_obat: '', jumlah: '1', satuan: '', aturan_pakai: '', catatan: '', jenis: 'jadi', is_resep_luar: false });
    const letterForm = useForm({ jenis: 'sakit', nomor_surat: '', tanggal: today, konten: '' });
    const referralForm = useForm({ ke_poli_id: '', catatan: '' });

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
            fetch(`/pelayanan/icd10/search?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() as Promise<IcdSuggestion[]> : Promise.reject(new Error('Pencarian ICD-10 gagal.')))
                .then((results) => { if (active) setIcdResults(results); })
                .catch(() => { if (active) setIcdResults([]); })
                .finally(() => { if (active) setIcdLoading(false); });
        }, 250);

        return () => { active = false; window.clearTimeout(timeout); };
    }, [icdQuery]);

    function submitForm(event: FormEvent<HTMLFormElement>, form: typeof examinationForm | typeof diagnosisForm | typeof treatmentForm | typeof prescriptionForm | typeof letterForm | typeof referralForm, url: string) {
        event.preventDefault();
        form.post(url);
    }

    function chooseDiagnosis(code: string, name: string) {
        diagnosisForm.setData((data) => ({ ...data, kode_icd10: code, nama_diagnosa: name }));
        setIcdQuery('');
        setIcdResults([]);
    }

    function suggestDiagnoses() {
        const text = examinationForm.data.anamnesis.toLocaleLowerCase('id');
        if (!text.trim()) {
            toast.error('Tuliskan keluhan atau anamnesis terlebih dahulu.');
            return;
        }
        const next: IcdSuggestion[] = [];
        if (text.includes('demam') || text.includes('panas')) next.push({ kode: 'R50.9', nama: 'Demam, tidak spesifik' });
        if (text.includes('batuk') || text.includes('dahak')) next.push({ kode: 'R05', nama: 'Batuk' }, { kode: 'J06.9', nama: 'Infeksi saluran napas atas akut' });
        if (text.includes('pilek') || text.includes('flu') || text.includes('hidung')) next.push({ kode: 'J00', nama: 'Nasofaringitis akut (common cold)' });
        if (text.includes('diare') || text.includes('mencret') || text.includes('mual') || text.includes('muntah')) next.push({ kode: 'A09', nama: 'Diare dan gastroenteritis' });
        if (text.includes('lambung') || text.includes('maag') || text.includes('nyeri perut')) next.push({ kode: 'K30', nama: 'Dispepsia fungsional' }, { kode: 'K21', nama: 'Penyakit refluks gastroesofagus' });
        if (text.includes('tensi') || text.includes('darah tinggi') || text.includes('pusing')) next.push({ kode: 'I10', nama: 'Hipertensi esensial' }, { kode: 'R51', nama: 'Sakit kepala' });
        if (text.includes('gula') || text.includes('kencing manis') || text.includes('diabetes')) next.push({ kode: 'E11', nama: 'Diabetes melitus tipe 2' });
        setSuggestions(next.length ? next : [{ kode: 'Z00.0', nama: 'Pemeriksaan umum / check-up' }, { kode: 'B34.9', nama: 'Infeksi virus, tidak spesifik' }]);
        setSuggestionsOpen(true);
    }

    function deleteItem(url: string, confirmation: string) {
        confirmAction(confirmation, () => { router.delete(url, { preserveScroll: true }); }, 'Lanjutkan');
    }

    function finishExamination() {
        confirmAction('Selesaikan pemeriksaan dan teruskan pasien ke tahap layanan berikutnya?', () => { router.post(`/pelayanan/pemeriksaan/${visit.id}/selesai`); }, 'Lanjutkan');
    }

    const prescriptionEditable = !visit.prescription.status || visit.prescription.status === 'menunggu';

    return (
        <>
            <Head title={`Pemeriksaan ${visit.patient.name}`} />
            <div className="space-y-6">
                <Card><CardContent className="flex flex-col justify-between gap-5 p-5 lg:flex-row lg:items-center">
                    <div className="flex items-center gap-4"><span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-lg font-bold text-neutral-800">{visit.patient.name.slice(0, 1).toLocaleUpperCase()}</span><div><p className="text-xs font-medium text-neutral-500">{visit.number} · {visit.clinic}</p><h2 className="mt-0.5 text-lg font-semibold text-neutral-950">{visit.patient.name}</h2><p className="mt-1 text-xs text-neutral-600">{visit.patient.medicalRecordNumber} · {visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : '—'}, {visit.patient.age} th · Gol. {visit.patient.bloodType ?? '—'} · {visit.paymentType.toUpperCase()}</p></div></div>
                    <div className="flex flex-wrap items-center gap-2">{visit.screening && <div className="mr-1 flex flex-wrap gap-1.5 rounded-lg bg-neutral-50 p-2 text-xs"><strong className="px-1 py-1 text-neutral-700">Tanda vital</strong><Badge>TD {visit.screening.systolic ?? '—'}/{visit.screening.diastolic ?? '—'}</Badge><Badge>N {visit.screening.pulse ?? '—'}x</Badge><Badge>S {visit.screening.temperature ?? '—'}°C</Badge><Badge>SpO₂ {visit.screening.oxygenSaturation ?? '—'}%</Badge></div>}<Button asChild size="sm" variant="secondary"><Link href={`/pelayanan/pasien/${visit.patient.id}/rekam-medis`}><UserRound className="size-4" />Riwayat RME</Link></Button><Button disabled={!visit.examination || visit.status !== 'pemeriksaan'} onClick={finishExamination} size="sm"><Check className="size-4" />Selesai periksa</Button></div>
                {visit.patient.allergies && <Alert className="w-full lg:basis-full" variant="destructive"><AlertTriangle aria-hidden="true" /><AlertTitle>Peringatan alergi</AlertTitle><AlertDescription>{visit.patient.allergies}</AlertDescription></Alert>}
                </CardContent></Card>

                <Card className="overflow-hidden">
                    <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as Tab)}>
                    <TabsList aria-label="Bagian pemeriksaan">{tabs.map(({ id, icon: Icon, label }, index) => <TabsTrigger className="min-h-12 px-3 sm:px-4" key={id} value={id}><Icon aria-hidden="true" className="size-4" />{index + 1}. {label}{id === 'diagnosa' && <Badge>{visit.diagnoses.length}</Badge>}{id === 'tindakan' && <Badge>{visit.treatments.length}</Badge>}{id === 'resep' && <Badge>{visit.prescription.items.length}</Badge>}</TabsTrigger>)}</TabsList>

                    <TabsContent className="space-y-5 p-5 sm:p-6" value="anamnesis">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-anamnesis">Anamnesis dan pemeriksaan fisik</h3><p className="mt-1 text-sm text-neutral-500">Catat kondisi klinis, rencana kontrol, dan edukasi pasien.</p></div>
                        <form className="space-y-5" onSubmit={(event) => submitForm(event, examinationForm, `/pelayanan/pemeriksaan/${visit.id}`)}>
                            <Field error={examinationForm.errors.dokter_id} htmlFor="dokter_id" label="Dokter pemeriksa"><Select id="dokter_id" onChange={(event) => examinationForm.setData('dokter_id', event.target.value)} value={examinationForm.data.dokter_id}><option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select></Field>
                            <Field error={examinationForm.errors.anamnesis} htmlFor="anamnesis" label="Anamnesis (keluhan dan riwayat penyakit)"><div className="space-y-2"><Textarea id="anamnesis" onChange={(event) => examinationForm.setData('anamnesis', event.target.value)} placeholder="Keluhan pasien, riwayat penyakit sekarang..." rows={4} value={examinationForm.data.anamnesis} /><Button onClick={suggestDiagnoses} size="sm" type="button" variant="secondary"><Lightbulb className="size-4" />Saran diagnosis berdasarkan kata kunci</Button></div></Field>
                            {suggestionsOpen && <div className="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50 p-4"><div className="flex justify-between gap-3"><div><p className="text-sm font-semibold text-neutral-950">Kandidat ICD-10</p><p className="text-xs text-neutral-800">Saran berbasis kata kunci; dokter tetap perlu menilai dan mengonfirmasi.</p></div><Button onClick={() => setSuggestionsOpen(false)} size="sm" type="button" variant="ghost">Tutup</Button></div><div className="flex flex-wrap gap-2">{suggestions.map((item) => <Button key={`${item.kode}-${item.nama}`} onClick={() => { chooseDiagnosis(item.kode, item.nama); setActiveTab('diagnosa'); }} size="sm" type="button" variant="secondary">{item.kode} · {item.nama}</Button>)}</div></div>}
                            <Field error={examinationForm.errors.pemeriksaan_fisik} htmlFor="pemeriksaan_fisik" label="Pemeriksaan fisik"><Textarea id="pemeriksaan_fisik" onChange={(event) => examinationForm.setData('pemeriksaan_fisik', event.target.value)} placeholder="Kepala, leher, thorax, abdomen, ekstremitas..." rows={4} value={examinationForm.data.pemeriksaan_fisik} /></Field>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={examinationForm.errors.kontrol_berikutnya} htmlFor="kontrol_berikutnya" label="Jadwal kontrol berikutnya"><DatePicker id="kontrol_berikutnya" onChange={(event) => examinationForm.setData('kontrol_berikutnya', event.target.value)}  value={examinationForm.data.kontrol_berikutnya} /></Field><Field error={examinationForm.errors.catatan} htmlFor="catatan" label="Catatan khusus dokter"><Input id="catatan" onChange={(event) => examinationForm.setData('catatan', event.target.value)} placeholder="Catatan internal" value={examinationForm.data.catatan} /></Field></div>
                            <Field error={examinationForm.errors.edukasi} htmlFor="edukasi" label="Edukasi pasien"><Textarea id="edukasi" onChange={(event) => examinationForm.setData('edukasi', event.target.value)} placeholder="Anjuran, cara penggunaan obat, larangan, dan jadwal kontrol" rows={3} value={examinationForm.data.edukasi} /></Field>
                            <div className="flex justify-end"><Button disabled={examinationForm.processing} type="submit">Simpan pemeriksaan</Button></div>
                        </form>
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="diagnosa">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-diagnosa">Diagnosis ICD-10</h3><p className="mt-1 text-sm text-neutral-500">Cari referensi kode ICD-10 lalu catat diagnosis utama atau tambahan.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, diagnosisForm, `/pelayanan/pemeriksaan/${visit.id}/diagnosa`)}>
                            <div className="relative"><Field htmlFor="icd-search" label="Cari referensi ICD-10"><div className="relative"><Search className="absolute left-3 top-3 size-4 text-neutral-400" /><Input autoComplete="off" className="pl-9" id="icd-search" onChange={(event) => setIcdQuery(event.target.value)} placeholder="Ketik kode atau nama diagnosis" value={icdQuery} /></div></Field>{(icdResults.length > 0 || icdLoading) && <div className="absolute z-20 mt-1 max-h-52 w-full overflow-auto rounded-xl border border-neutral-200 bg-surface shadow-lg">{icdLoading && <p className="p-3 text-sm text-neutral-500">Mencari…</p>}{icdResults.map((result) => <Button className="h-auto w-full justify-between rounded-none border-b border-neutral-100 px-3 py-2 text-left font-normal hover:bg-neutral-50 hover:text-neutral-900" key={result.kode} onClick={() => chooseDiagnosis(result.kode, result.nama)} type="button" variant="ghost"><span className="font-mono font-semibold text-neutral-700">{result.kode}</span><span>{result.nama}</span></Button>)}</div>}</div>
                            <div className="grid gap-4 md:grid-cols-3"><Field error={diagnosisForm.errors.kode_icd10} htmlFor="kode_icd10" label="Kode ICD-10" required><Input className="font-mono uppercase" id="kode_icd10" onChange={(event) => diagnosisForm.setData('kode_icd10', event.target.value.toUpperCase())} required value={diagnosisForm.data.kode_icd10} /></Field><Field error={diagnosisForm.errors.nama_diagnosa} htmlFor="nama_diagnosa" label="Nama diagnosis" required><Input id="nama_diagnosa" onChange={(event) => diagnosisForm.setData('nama_diagnosa', event.target.value)} required value={diagnosisForm.data.nama_diagnosa} /></Field><Field error={diagnosisForm.errors.jenis} htmlFor="jenis-diagnosa" label="Jenis diagnosis"><Select id="jenis-diagnosa" onChange={(event) => diagnosisForm.setData('jenis', event.target.value)} value={diagnosisForm.data.jenis}><option value="utama">Utama</option><option value="tambahan">Tambahan</option></Select></Field></div>
                            <div className="flex justify-end"><Button disabled={diagnosisForm.processing} type="submit"><Plus className="size-4" />Tambah diagnosis</Button></div>
                        </form>
                        <div className="space-y-3"><h4 className="text-sm font-semibold text-neutral-900">Diagnosis tercatat</h4>{visit.diagnoses.length ? visit.diagnoses.map((diagnosis) => <div className="flex flex-col justify-between gap-3 rounded-xl border border-neutral-200 p-4 sm:flex-row sm:items-center" key={diagnosis.id}><div className="flex flex-wrap items-center gap-3"><Badge variant="examination">{diagnosis.code}</Badge><span className="text-sm font-medium text-neutral-900">{diagnosis.name}</span><Badge>{diagnosis.type === 'utama' ? 'Utama' : 'Tambahan'}</Badge></div><Button onClick={() => deleteItem(`/pelayanan/pemeriksaan/diagnosa/${diagnosis.id}`, 'Hapus diagnosis ini?')} size="sm" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" />Hapus</Button></div>) : <Empty className="min-h-0 py-6" description="Diagnosis yang dicatat selama pemeriksaan akan tampil di sini." title="Belum ada diagnosis" />}</div>
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="tindakan">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-tindakan">Tindakan medis</h3><p className="mt-1 text-sm text-neutral-500">Tindakan yang tersedia mengikuti poliklinik kunjungan ini.</p></div>
                        <form className="grid gap-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4 md:grid-cols-[minmax(0,1fr)_9rem_auto] md:items-end" onSubmit={(event) => submitForm(event, treatmentForm, `/pelayanan/pemeriksaan/${visit.id}/tindakan`)}><Field error={treatmentForm.errors.tindakan_id} htmlFor="tindakan_id" label="Tindakan" required><Select id="tindakan_id" onChange={(event) => treatmentForm.setData('tindakan_id', event.target.value)} required value={treatmentForm.data.tindakan_id}><option value="">Pilih tindakan</option>{treatments.map((item) => <option key={item.id} value={item.id}>{item.name} · {formatCurrency(item.tariff)}</option>)}</Select></Field><Field error={treatmentForm.errors.jumlah} htmlFor="jumlah-tindakan" label="Jumlah" required><Input id="jumlah-tindakan" min="1" onChange={(event) => treatmentForm.setData('jumlah', event.target.value)} required type="number" value={treatmentForm.data.jumlah} /></Field><Button disabled={treatmentForm.processing} type="submit"><Plus className="size-4" />Tambah</Button></form>
                        <div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Nama tindakan</TableHead><TableHead>Jumlah</TableHead><TableHead>Tarif</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{visit.treatments.length ? visit.treatments.map((item) => <TableRow key={item.id}><TableCell className="font-medium">{item.name}</TableCell><TableCell>{item.quantity}x</TableCell><TableCell>{formatCurrency(item.tariff)}</TableCell><TableCell className="text-right"><Button onClick={() => deleteItem(`/pelayanan/pemeriksaan/tindakan/${item.id}`, 'Hapus tindakan ini?')} size="sm" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" />Hapus</Button></TableCell></TableRow>) : <TableRow><TableCell className="py-8 text-center text-neutral-500" colSpan={4}>Belum ada tindakan medis.</TableCell></TableRow>}</TableBody></Table></div>
                    </TabsContent>

                    <TabsContent className="space-y-6 p-5 sm:p-6" value="resep">
                        <div><h3 className="text-base font-semibold text-neutral-950" id="tab-resep">Resep obat</h3><p className="mt-1 text-sm text-neutral-500">Resep obat klinik akan mengurangi stok saat ditambahkan. Resep luar tidak mengurangi stok.</p></div>
                        <form className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50 p-4" onSubmit={(event) => submitForm(event, prescriptionForm, `/pelayanan/pemeriksaan/${visit.id}/resep`)}>
                            <div className="grid gap-4 md:grid-cols-3"><Field error={prescriptionForm.errors.jenis} htmlFor="jenis-resep" label="Jenis resep"><Select id="jenis-resep" onChange={(event) => prescriptionForm.setData('jenis', event.target.value)} value={prescriptionForm.data.jenis}><option value="jadi">Obat jadi</option><option value="racikan">Obat racikan</option></Select></Field>{!prescriptionForm.data.is_resep_luar && <Field error={prescriptionForm.errors.obat_id} htmlFor="obat_id" label="Obat dari stok klinik" required><Select id="obat_id" onChange={(event) => prescriptionForm.setData('obat_id', event.target.value)} required value={prescriptionForm.data.obat_id}><option value="">Pilih obat</option>{medicines.map((medicine) => <option key={medicine.id} value={medicine.id}>{medicine.name} · stok {medicine.stock} {medicine.unit}</option>)}</Select></Field>}{prescriptionForm.data.is_resep_luar && <Field error={prescriptionForm.errors.nama_obat} htmlFor="nama_obat" label="Nama obat luar" required><Input id="nama_obat" onChange={(event) => prescriptionForm.setData('nama_obat', event.target.value)} required value={prescriptionForm.data.nama_obat} /></Field>}<Field error={prescriptionForm.errors.jumlah} htmlFor="jumlah-obat" label="Jumlah" required><Input id="jumlah-obat" min="0.01" onChange={(event) => prescriptionForm.setData('jumlah', event.target.value)} required step="0.01" type="number" value={prescriptionForm.data.jumlah} /></Field></div>
                            <div className="grid gap-4 md:grid-cols-2"><Field error={prescriptionForm.errors.aturan_pakai} htmlFor="aturan_pakai" label="Aturan pakai"><Input id="aturan_pakai" onChange={(event) => prescriptionForm.setData('aturan_pakai', event.target.value)} placeholder="3 x 1 tablet setelah makan" value={prescriptionForm.data.aturan_pakai} /></Field><Field error={prescriptionForm.errors.catatan} htmlFor="catatan-resep" label="Catatan"><Input id="catatan-resep" onChange={(event) => prescriptionForm.setData('catatan', event.target.value)} placeholder="Habiskan, bila demam..." value={prescriptionForm.data.catatan} /></Field></div>
                            <Label className="flex items-center gap-2 text-sm font-medium text-neutral-800"><Checkbox checked={prescriptionForm.data.is_resep_luar} onCheckedChange={(checked) => prescriptionForm.setData((data) => ({ ...data, is_resep_luar: (checked === true), obat_id: '', nama_obat: '' }))} />Resep luar (ditebus di apotek luar)</Label><div className="flex justify-end"><Button disabled={prescriptionForm.processing} type="submit"><Plus className="size-4" />Tambahkan ke resep</Button></div>
                        </form>
                        <div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Nama obat</TableHead><TableHead>Jenis</TableHead><TableHead>Jumlah</TableHead><TableHead>Aturan pakai</TableHead><TableHead>Tipe</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{visit.prescription.items.length ? visit.prescription.items.map((item) => <TableRow key={item.id}><TableCell className="font-medium">{item.name}</TableCell><TableCell>{item.type === 'jadi' ? 'Jadi' : 'Racikan'}</TableCell><TableCell>{item.quantity} {item.unit}</TableCell><TableCell>{item.instructions ?? '—'}</TableCell><TableCell><Badge variant={item.external ? 'waiting' : 'complete'}>{item.external ? 'Resep luar' : 'Klinik'}</Badge></TableCell><TableCell className="text-right"><Button disabled={!prescriptionEditable} onClick={() => deleteItem(`/pelayanan/pemeriksaan/resep/${item.id}`, 'Hapus obat dari resep? Stok akan dikembalikan bila sebelumnya dikurangi.')} size="sm" type="button" variant="ghost"><Trash2 className="size-4 text-red-600" />Hapus</Button></TableCell></TableRow>) : <TableRow><TableCell className="py-8 text-center text-neutral-500" colSpan={6}>Belum ada obat dalam resep.</TableCell></TableRow>}</TableBody></Table></div>
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
                    </Tabs>
                </Card>
            </div>
        </>
    );
}

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(amount);
}
