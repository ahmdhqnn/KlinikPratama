import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, HeartPulse, Save, ShieldAlert, UserRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';

type ScreeningValues = {
    petugas_id: string;
    keluhan: string;
    td_sistole: string;
    td_diastole: string;
    nadi: string;
    suhu: string;
    berat_badan: string;
    tinggi_badan: string;
    lingkar_perut: string;
    spo2: string;
    respirasi: string;
    riwayat_penyakit: string;
    riwayat_alergi: string;
    risiko_jatuh: string;
    risiko_nyeri: string;
    skrining_gizi: string;
    alergi_jenis: string;
    alergi_reaksi: string;
    penyakit_nama: string;
    penyakit_keterangan: string;
    nyeri_dada: string;
    kondisi_psikiatri: string;
    nadi_teraba: string;
    kejang: string;
    pola_pernapasan: string;
    kesadaran: string;
    risiko_jatuh_visual: string;
    riwayat_penyakit_keluarga: string;
    skala_nyeri: string;
    lokasi_nyeri_gigi: string;
    pemicu_nyeri_gigi: string[];
    durasi_keluhan_gigi: string;
    risiko_medis_gigi: string[];
    riwayat_infeksi_gigi: string[];
    catatan_medis_gigi: string;
};
type ScreeningScalarField = { [Key in keyof ScreeningValues]: ScreeningValues[Key] extends string ? Key : never }[keyof ScreeningValues];

interface Visit {
    id: number;
    number: string;
    status: string;
    patient: {
        name: string;
        medicalRecordNumber: string;
        gender: string | null;
        age: number | null;
        bloodType: string | null;
        allergyHistory: string | null;
    };
    clinic: string;
    clinicType: 'umum' | 'gigi';
    doctor: string;
    screening: Record<string, string | number | string[] | null>;
    triage: string | null;
    priority: string | null;
}

interface Staff {
    id: number;
    name: string;
    position: string;
}

interface Props {
    visit: Visit;
    staff: Staff[];
}

const triageFields: Array<{ name: ScreeningScalarField; label: string; options: Array<[string, string]> }> = [
    { name: 'nyeri_dada', label: 'Nyeri dada', options: [['tidak', 'Tidak'], ['ya', 'Ya']] },
    { name: 'kejang', label: 'Kejang', options: [['tidak', 'Tidak'], ['ya', 'Ya']] },
    { name: 'nadi_teraba', label: 'Keterabaan nadi', options: [['teraba', 'Teraba'], ['tidak_teraba', 'Tidak teraba']] },
    { name: 'pola_pernapasan', label: 'Pola pernapasan', options: [['normal', 'Normal'], ['tidak_normal', 'Tidak normal']] },
    { name: 'kesadaran', label: 'Kesadaran', options: [['sadar', 'Sadar'], ['menurun', 'Menurun'], ['tidak_sadar', 'Tidak sadar']] },
    { name: 'kondisi_psikiatri', label: 'Kondisi psikiatri', options: [['normal', 'Normal'], ['terganggu', 'Terganggu']] },
    { name: 'risiko_jatuh_visual', label: 'Risiko jatuh visual', options: [['rendah', 'Rendah'], ['sedang', 'Sedang'], ['tinggi', 'Tinggi']] },
];

export default function ScreeningForm({ visit, staff }: Props) {
    const [mode, setMode] = useState<'simple' | 'complete'>('complete');
    const form = useForm<ScreeningValues>({
        petugas_id: getValue(visit, 'petugas_id'),
        keluhan: getValue(visit, 'keluhan'),
        td_sistole: getValue(visit, 'td_sistole'),
        td_diastole: getValue(visit, 'td_diastole'),
        nadi: getValue(visit, 'nadi'),
        suhu: getValue(visit, 'suhu'),
        berat_badan: getValue(visit, 'berat_badan'),
        tinggi_badan: getValue(visit, 'tinggi_badan'),
        lingkar_perut: getValue(visit, 'lingkar_perut'),
        spo2: getValue(visit, 'spo2'),
        respirasi: getValue(visit, 'respirasi'),
        riwayat_penyakit: getValue(visit, 'riwayat_penyakit'),
        riwayat_alergi: getValue(visit, 'riwayat_alergi', visit.patient.allergyHistory ?? ''),
        risiko_jatuh: getValue(visit, 'risiko_jatuh', 'rendah'),
        risiko_nyeri: getValue(visit, 'risiko_nyeri', '0 - Tidak Nyeri'),
        skrining_gizi: getValue(visit, 'skrining_gizi', 'normal'),
        alergi_jenis: getValue(visit, 'alergi_jenis'),
        alergi_reaksi: getValue(visit, 'alergi_reaksi'),
        penyakit_nama: getValue(visit, 'penyakit_nama'),
        penyakit_keterangan: getValue(visit, 'penyakit_keterangan'),
        nyeri_dada: getValue(visit, 'nyeri_dada', 'tidak'),
        kondisi_psikiatri: getValue(visit, 'kondisi_psikiatri', 'normal'),
        nadi_teraba: getValue(visit, 'nadi_teraba', 'teraba'),
        kejang: getValue(visit, 'kejang', 'tidak'),
        pola_pernapasan: getValue(visit, 'pola_pernapasan', 'normal'),
        kesadaran: getValue(visit, 'kesadaran', 'sadar'),
        risiko_jatuh_visual: getValue(visit, 'risiko_jatuh_visual', 'rendah'),
        riwayat_penyakit_keluarga: getValue(visit, 'riwayat_penyakit_keluarga'),
        skala_nyeri: getValue(visit, 'skala_nyeri', '0'),
        lokasi_nyeri_gigi: getValue(visit, 'lokasi_nyeri_gigi'),
        pemicu_nyeri_gigi: getListValue(visit, 'pemicu_nyeri_gigi'),
        durasi_keluhan_gigi: getValue(visit, 'durasi_keluhan_gigi'),
        risiko_medis_gigi: getListValue(visit, 'risiko_medis_gigi'),
        riwayat_infeksi_gigi: getListValue(visit, 'riwayat_infeksi_gigi'),
        catatan_medis_gigi: getValue(visit, 'catatan_medis_gigi'),
    });

    const isClosed = ['selesai', 'batal'].includes(visit.status);
    const weight = Number(form.data.berat_badan);
    const heightInMeters = Number(form.data.tinggi_badan) / 100;
    const bmi = weight > 0 && heightInMeters > 0 ? (weight / (heightInMeters * heightInMeters)).toFixed(1) : null;

    function updateField(field: ScreeningScalarField, value: string) {
        form.setData((data) => ({ ...data, [field]: value }));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/pelayanan/screening/${visit.id}`);
    }

    return (
        <>
            <Head title={`Skrining · ${visit.patient.name}`} />
            <form className="mx-auto max-w-5xl space-y-6" onSubmit={submit}>
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-neutral-700">Skrining keperawatan</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Tanda vital dan triase awal</h2>
                        <p className="mt-1 text-sm text-neutral-500">Kunjungan {visit.number} · {visit.clinic} · Asesmen {visit.clinicType === 'gigi' ? 'gigi' : 'umum'}</p>
                    </div>
                    <Button asChild variant="secondary"><Link href="/pelayanan/screening"><ArrowLeft className="size-4" />Kembali ke antrean</Link></Button>
                </div>

                <Card>
                    <CardContent className="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center sm:p-6">
                        <div className="flex items-start gap-3">
                            <span className="flex size-11 items-center justify-center rounded-full bg-neutral-50 text-neutral-700"><UserRound className="size-5" /></span>
                            <div>
                                <h3 className="font-semibold text-neutral-950">{visit.patient.name}</h3>
                                <p className="mt-0.5 text-sm text-neutral-500">{visit.patient.medicalRecordNumber} · {visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : '—'}{visit.patient.age !== null ? ` · ${visit.patient.age} tahun` : ''} · Gol. darah {visit.patient.bloodType ?? '—'}</p>
                                <p className="mt-1 text-xs text-neutral-500">Dokter: {visit.doctor}</p>
                            </div>
                        </div>
                        {visit.triage && <div className={`rounded-xl px-4 py-2 text-sm font-semibold capitalize ${visit.triage === 'merah' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700'}`}><span className="inline-flex items-center gap-2"><ShieldAlert className="size-4" />Triase {visit.triage}{visit.priority ? ` · ${visit.priority}` : ''}</span></div>}
                    </CardContent>
                </Card>

                {isClosed && <div role="status" className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Kunjungan berstatus {visit.status}; skrining tidak dapat diubah.</div>}

                <Card>
                    <CardHeader className="flex flex-col justify-between gap-4 border-b border-neutral-100 sm:flex-row sm:items-center">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><HeartPulse className="size-5" /></span>
                            <div><CardTitle>Asesmen pasien</CardTitle><CardDescription className="mt-1">Tanda vital dan triase diperlukan untuk meneruskan pasien.</CardDescription></div>
                        </div>
                        <div aria-label="Tingkat detail asesmen" className="inline-flex rounded-lg bg-neutral-100 p-1">
                            <Button aria-pressed={mode === 'simple'} className={`h-auto rounded-md px-3 py-2 text-xs font-medium ${mode === 'simple' ? 'bg-surface text-neutral-900 shadow-sm hover:bg-surface' : 'text-neutral-500 hover:bg-transparent hover:text-neutral-800'}`} onClick={() => setMode('simple')} type="button" variant="ghost">Ringkas</Button>
                            <Button aria-pressed={mode === 'complete'} className={`h-auto rounded-md px-3 py-2 text-xs font-medium ${mode === 'complete' ? 'bg-surface text-neutral-900 shadow-sm hover:bg-surface' : 'text-neutral-500 hover:bg-transparent hover:text-neutral-800'}`} onClick={() => setMode('complete')} type="button" variant="ghost">Lengkap</Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-8 p-5 sm:p-6">
                        <section className="space-y-4">
                            <div><h3 className="text-sm font-semibold text-neutral-900">Tanda-tanda vital</h3><p className="mt-1 text-xs text-neutral-500">Isi hasil pengukuran yang tersedia.</p></div>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <NumericField form={form} name="td_sistole" label="Tekanan sistole" unit="mmHg" placeholder="120" onChange={updateField} />
                                <NumericField form={form} name="td_diastole" label="Tekanan diastole" unit="mmHg" placeholder="80" onChange={updateField} />
                                <NumericField form={form} name="nadi" label="Nadi" unit="kali/menit" placeholder="80" onChange={updateField} />
                                <NumericField form={form} name="suhu" label="Suhu tubuh" unit="°C" step="0.1" placeholder="36.5" onChange={updateField} />
                                {visit.clinicType === 'umum' && <>
                                    <NumericField form={form} name="berat_badan" label="Berat badan" unit="kg" step="0.1" placeholder="65" onChange={updateField} />
                                    <NumericField form={form} name="tinggi_badan" label="Tinggi badan" unit="cm" step="0.1" placeholder="170" onChange={updateField} />
                                    <NumericField form={form} name="lingkar_perut" label="Lingkar perut" unit="cm" step="0.1" placeholder="80" onChange={updateField} />
                                    <div aria-live="polite" className="flex min-h-10 items-center rounded-lg border border-neutral-200 bg-neutral-50 px-3 text-sm text-neutral-700">
                                        <span className="font-medium">IMT:</span><span className="ml-2">{bmi ? `${bmi} kg/m²` : 'Terisi otomatis setelah BB dan TB dicatat'}</span>
                                    </div>
                                </>}
                                <NumericField form={form} name="spo2" label="Saturasi oksigen" unit="%" placeholder="98" onChange={updateField} />
                                <NumericField form={form} name="respirasi" label="Respirasi" unit="kali/menit" placeholder="20" onChange={updateField} />
                            </div>
                        </section>
                        <section className="space-y-4">
                            <div><h3 className="text-sm font-semibold text-neutral-900">Keluhan utama</h3><p className="mt-1 text-xs text-neutral-500">Catat keluhan yang disampaikan pasien saat ini.</p></div>
                            <Field error={form.errors.keluhan} htmlFor="keluhan" label="Keluhan pasien"><Textarea id="keluhan" onChange={(event) => updateField('keluhan', event.target.value)} placeholder="Jelaskan keluhan pasien…" value={form.data.keluhan} /></Field>
                        </section>

                        {mode === 'complete' && (
                            <section className="space-y-4">
                                <div><h3 className="text-sm font-semibold text-neutral-900">Asesmen {visit.clinicType === 'gigi' ? 'poli gigi' : 'poli umum'}</h3><p className="mt-1 text-xs text-neutral-500">{visit.clinicType === 'gigi' ? 'Fokus keluhan orofasial dan faktor risiko sebelum tindakan.' : 'Riwayat kesehatan umum, faktor keluarga, alergi, dan risiko jatuh.'}</p></div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <TextField form={form} name="riwayat_penyakit" label="Riwayat penyakit kronis" placeholder="Hipertensi, diabetes, asma, dan lainnya" onChange={updateField} multiline />
                                    <TextField form={form} name="riwayat_alergi" label="Riwayat alergi" placeholder="Obat, makanan, cuaca, dan lainnya" onChange={updateField} multiline />
                                    {visit.clinicType === 'umum' && <SelectField form={form} name="risiko_jatuh" label="Skrining risiko jatuh" options={[['rendah', 'Risiko rendah'], ['sedang', 'Risiko sedang'], ['tinggi', 'Risiko tinggi']]} onChange={updateField} />}
                                    <SelectField form={form} name="skala_nyeri" label="Skala nyeri (0–10)" options={Array.from({ length: 11 }, (_, score): [string, string] => [String(score), score === 0 ? '0 · Tidak nyeri' : `${score} · ${score <= 3 ? 'Ringan' : score <= 6 ? 'Sedang' : 'Berat'}`])} onChange={updateField} />
                                    {visit.clinicType === 'umum' && <SelectField form={form} name="skrining_gizi" label="Skrining status gizi" options={[
                                        ['normal', 'Status gizi normal'], ['kurang', 'Gizi kurang'], ['lebih', 'Gizi lebih / obesitas'],
                                    ]} onChange={updateField} />}
                                    {visit.clinicType === 'umum' && <TextField form={form} name="riwayat_penyakit_keluarga" label="Riwayat penyakit keluarga" placeholder="Riwayat relevan dalam keluarga" onChange={updateField} multiline />}
                                    <TextField form={form} name="alergi_jenis" label="Jenis alergi" placeholder="Makanan, obat, atau lainnya" onChange={updateField} />
                                    <TextField form={form} name="alergi_reaksi" label="Reaksi alergi" placeholder="Gatal, sesak, dan lainnya" onChange={updateField} />
                                    <TextField form={form} name="penyakit_nama" label="Nama penyakit" placeholder="Penyakit yang diketahui" onChange={updateField} />
                                    <TextField form={form} name="penyakit_keterangan" label="Keterangan penyakit" placeholder="Durasi atau catatan lain" onChange={updateField} />
                                </div>
                                {visit.clinicType === 'gigi' && <div className="space-y-5 rounded-xl border border-neutral-200 p-4">
                                    <div><h4 className="text-sm font-semibold text-neutral-900">Keluhan gigi dan mulut</h4><p className="mt-1 text-xs text-neutral-500">Lokasi, pemicu, dan durasi berdasarkan keterangan pasien.</p></div>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <TextField form={form} name="lokasi_nyeri_gigi" label="Lokasi nyeri gigi / gusi" placeholder="Contoh: gigi geraham kiri bawah" onChange={updateField} />
                                        <TextField form={form} name="durasi_keluhan_gigi" label="Durasi keluhan" placeholder="Sejak kapan dan berapa lama" onChange={updateField} />
                                    </div>
                                    <CheckboxGroup error={form.errors.pemicu_nyeri_gigi} label="Pemicu nyeri" name="pemicu_nyeri_gigi" options={ [['dingin', 'Dingin'], ['manis', 'Manis'], ['mengunyah', 'Saat mengunyah'], ['panas', 'Panas'], ['spontan', 'Muncul spontan'], ['lainnya', 'Lainnya']] } selected={form.data.pemicu_nyeri_gigi} onChange={(values) => form.setData('pemicu_nyeri_gigi', values)} />
                                    <CheckboxGroup error={form.errors.risiko_medis_gigi} label="Riwayat medis yang memengaruhi tindakan" name="risiko_medis_gigi" options={ [['hipertensi', 'Hipertensi'], ['diabetes', 'Diabetes melitus'], ['penyakit_jantung', 'Penyakit jantung'], ['gangguan_pembekuan', 'Gangguan pembekuan darah'], ['antikoagulan', 'Mengonsumsi antikoagulan'], ['lainnya', 'Lainnya']] } selected={form.data.risiko_medis_gigi} onChange={(values) => form.setData('risiko_medis_gigi', values)} />
                                    <CheckboxGroup error={form.errors.riwayat_infeksi_gigi} label="Riwayat infeksi" name="riwayat_infeksi_gigi" options={ [['hepatitis', 'Hepatitis'], ['hiv', 'HIV'], ['infeksi_lain', 'Infeksi lain'], ['tidak_ada', 'Tidak ada riwayat yang diketahui']] } selected={form.data.riwayat_infeksi_gigi} onChange={(values) => {
                                        const wasNoneSelected = form.data.riwayat_infeksi_gigi.includes('tidak_ada');
                                        const isNoneSelected = values.includes('tidak_ada');
                                        form.setData('riwayat_infeksi_gigi', isNoneSelected && !wasNoneSelected ? ['tidak_ada'] : values.filter((value) => value !== 'tidak_ada'));
                                    }} />
                                    <Field error={form.errors.catatan_medis_gigi} htmlFor="catatan_medis_gigi" label="Catatan tambahan medis gigi"><Textarea id="catatan_medis_gigi" onChange={(event) => updateField('catatan_medis_gigi', event.target.value)} placeholder="Informasi tambahan yang disampaikan pasien" value={form.data.catatan_medis_gigi} /></Field>
                                    <p className="text-xs text-neutral-500">Informasi ini membantu dokter gigi menilai kewaspadaan klinis; keputusan tindakan tetap oleh dokter.</p>
                                </div>}
                            </section>
                        )}

                        <section className="space-y-4">
                            <div><h3 className="text-sm font-semibold text-neutral-900">Skrining visual dan triase awal</h3><p className="mt-1 text-xs text-neutral-500">Semua item perlu dijawab untuk membantu menentukan prioritas layanan.</p></div>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {triageFields.map((field) => (
                                    <SelectField form={form} key={field.name} name={field.name} label={field.label} options={field.options} onChange={updateField} required />
                                ))}
                            </div>
                        </section>

                        <Field error={form.errors.petugas_id} htmlFor="petugas_id" label="Petugas skrining">
                            <Select id="petugas_id" onChange={(event) => updateField('petugas_id', event.target.value)} value={form.data.petugas_id}>
                                <option value="">Pilih petugas (opsional)</option>{staff.map((person) => <option key={person.id} value={person.id}>{person.name} · {person.position}</option>)}
                            </Select>
                        </Field>
                    </CardContent>
                </Card>

                <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                    <Button asChild variant="secondary"><Link href="/pelayanan/screening">Batal</Link></Button>
                    <Button disabled={form.processing || isClosed} type="submit"><Save className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan dan teruskan ke dokter'}</Button>
                </div>
            </form>
        </>
    );
}

function getValue(visit: Visit, key: ScreeningScalarField, fallback = ''): string {
    const value = visit.screening[key];
    return value === null || value === undefined || Array.isArray(value) ? fallback : String(value);
}

function getListValue(visit: Visit, key: keyof ScreeningValues): string[] {
    const value = visit.screening[key];
    return Array.isArray(value) ? value.map(String) : [];
}

function CheckboxGroup({
    error,
    label,
    name,
    options,
    selected,
    onChange,
}: {
    error?: string;
    label: string;
    name: string;
    options: Array<[string, string]>;
    selected: string[];
    onChange: (values: string[]) => void;
}) {
    return <fieldset className="space-y-2">
        <legend className="text-sm font-medium text-neutral-800">{label}</legend>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{options.map(([value, optionLabel]) => {
            const id = `${name}-${value}`;
            return <label className="flex min-h-10 cursor-pointer items-center gap-3 rounded-lg border border-neutral-200 px-3 py-2 text-sm text-neutral-700" htmlFor={id} key={value}>
                <Checkbox checked={selected.includes(value)} id={id} onCheckedChange={(checked) => onChange(checked === true ? [...selected, value] : selected.filter((item) => item !== value))} />
                {optionLabel}
            </label>;
        })}</div>
        {error && <p className="text-xs font-medium text-red-600">{error}</p>}
    </fieldset>;
}

function NumericField({
    form,
    name,
    label,
    unit,
    placeholder,
    step = '1',
    onChange,
}: {
    form: ReturnType<typeof useForm<ScreeningValues>>;
    name: ScreeningScalarField;
    label: string;
    unit: string;
    placeholder: string;
    step?: string;
    onChange: (name: ScreeningScalarField, value: string) => void;
}) {
    return (
        <Field error={form.errors[name]} htmlFor={name} label={label}>
            <div className="relative">
                <Input id={name} min="0" onChange={(event) => onChange(name, event.target.value)} placeholder={placeholder} step={step} type="number" value={form.data[name]} />
                <span className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-neutral-400">{unit}</span>
            </div>
        </Field>
    );
}

function TextField({
    form,
    name,
    label,
    placeholder,
    onChange,
    multiline = false,
}: {
    form: ReturnType<typeof useForm<ScreeningValues>>;
    name: ScreeningScalarField;
    label: string;
    placeholder: string;
    onChange: (name: ScreeningScalarField, value: string) => void;
    multiline?: boolean;
}) {
    return (
        <Field error={form.errors[name]} htmlFor={name} label={label}>
            {multiline
                ? <Textarea id={name} onChange={(event) => onChange(name, event.target.value)} placeholder={placeholder} value={form.data[name]} />
                : <Input id={name} onChange={(event) => onChange(name, event.target.value)} placeholder={placeholder} value={form.data[name]} />}
        </Field>
    );
}

function SelectField({
    form,
    name,
    label,
    options,
    onChange,
    required = false,
}: {
    form: ReturnType<typeof useForm<ScreeningValues>>;
    name: ScreeningScalarField;
    label: string;
    options: Array<[string, string]>;
    onChange: (name: ScreeningScalarField, value: string) => void;
    required?: boolean;
}) {
    return (
        <Field error={form.errors[name]} htmlFor={name} label={label} required={required}>
            <Select id={name} onChange={(event) => onChange(name, event.target.value)} required={required} value={form.data[name]}>
                {options.map(([value, optionLabel]) => <option key={value} value={value}>{optionLabel}</option>)}
            </Select>
        </Field>
    );
}
