import { Head, useForm } from '@inertiajs/react';
import {
    Activity,
    Building2,
    ClipboardCheck,
    ImagePlus,
    Lock,
    RotateCcw,
    Save,
    ShieldCheck,
    SlidersHorizontal,
    Stethoscope,
} from 'lucide-react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Attachment } from '@/components/ui/attachment';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { FormActionFooter } from '@/components/ui/form-action-footer';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import {
    defaultClinicalWorkflow,
    resolveClinicalWorkflow,
    type AnthropometryMode,
    type ClinicalWorkflowConfig,
    type NurseOptionalAssignment,
    type RoleAssignment,
} from '@/lib/clinical-workflow';

interface Props {
    initialTab?: 'profil' | 'alur-klinis';
    setting: {
        namaKlinik: string;
        kodeFasyankes?: string;
        izinOperasional?: string;
        satusehatOrgId?: string;
        alamat: string;
        kodeProvinsi?: string;
        kodeKabupaten?: string;
        kodeKecamatan?: string;
        kodeKelurahan?: string;
        kodePos?: string;
        telepon: string;
        email: string;
        kepalaKlinik: string;
        nipKepala: string;
        tagline: string;
        website: string;
        pelaksanaTtv: string;
        logoUrl: string | null;
    };
    clinicalWorkflow?: Partial<ClinicalWorkflowConfig>;
}

const roleOptions: Array<[RoleAssignment, string]> = [
    ['dokter', 'Dokter saja'],
    ['perawat', 'Perawat saja'],
    ['keduanya', 'Perawat & Dokter (verifikasi lanjutan)'],
];

const nurseOptionalOptions: Array<[NurseOptionalAssignment, string]> = [
    ['perawat', 'Aktif di pengkajian perawat'],
    ['nonaktif', 'Disembunyikan (tidak digunakan)'],
];

const anthropometryOptions: Array<[AnthropometryMode, string]> = [
    ['ringkas', 'Tampil di mode Ringkas & Lengkap'],
    ['lengkap', 'Hanya tampil di mode Lengkap'],
];

export default function ClinicSettings({ initialTab = 'profil', setting, clinicalWorkflow }: Props) {
    const [activeTab, setActiveTab] = useState<'profil' | 'alur-klinis'>(initialTab);
    const [logoInputKey, setLogoInputKey] = useState(0);

    const resolvedWorkflow = resolveClinicalWorkflow(clinicalWorkflow, defaultClinicalWorkflow);

    const profile = useForm({
        nama_klinik: setting.namaKlinik,
        kode_fasyankes: setting.kodeFasyankes ?? '',
        izin_operasional: setting.izinOperasional ?? '',
        satusehat_org_id: setting.satusehatOrgId ?? '',
        tagline: setting.tagline,
        telepon: setting.telepon,
        email: setting.email,
        alamat: setting.alamat,
        kode_provinsi: setting.kodeProvinsi ?? '',
        kode_kabupaten: setting.kodeKabupaten ?? '',
        kode_kecamatan: setting.kodeKecamatan ?? '',
        kode_kelurahan: setting.kodeKelurahan ?? '',
        kode_pos: setting.kodePos ?? '',
        kepala_klinik: setting.kepalaKlinik,
        nip_kepala: setting.nipKepala,
        website: setting.website,
    });

    const workflowForm = useForm({
        pelaksana_ttv: setting.pelaksanaTtv || 'perawat',
        clinical_workflow: { ...resolvedWorkflow },
    });

    const logo = useForm<{ logo: File | null }>({ logo: null });

    function handleTabChange(nextTab: string) {
        const validTab: 'profil' | 'alur-klinis' = nextTab === 'alur-klinis' ? 'alur-klinis' : 'profil';
        setActiveTab(validTab);
        if (typeof window !== 'undefined') {
            const url = new URL(window.location.href);
            if (validTab === 'profil') {
                url.searchParams.delete('tab');
            } else {
                url.searchParams.set('tab', validTab);
            }
            window.history.replaceState({}, '', url.toString());
        }
    }

    function saveProfile(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        profile.put('/setting', {
            preserveScroll: true,
            onSuccess: () => profile.setDefaults(profile.data),
        });
    }

    function saveWorkflow(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        workflowForm.put('/setting/alur-klinis', {
            preserveScroll: true,
            onSuccess: () => workflowForm.setDefaults(workflowForm.data),
        });
    }

    function restoreDefaultWorkflow() {
        workflowForm.setData({
            pelaksana_ttv: 'perawat',
            clinical_workflow: { ...defaultClinicalWorkflow },
        });
    }

    const isAtDefaultWorkflow = workflowForm.data.pelaksana_ttv === 'perawat'
        && JSON.stringify(workflowForm.data.clinical_workflow) === JSON.stringify(defaultClinicalWorkflow);

    function updateWorkflowKey<K extends keyof ClinicalWorkflowConfig>(key: K, value: ClinicalWorkflowConfig[K]) {
        workflowForm.setData('clinical_workflow', {
            ...workflowForm.data.clinical_workflow,
            [key]: value,
        });
    }

    function uploadLogo(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        logo.post('/setting/logo', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                logo.reset();
                setLogoInputKey((key) => key + 1);
            },
        });
    }

    const wfErrors = workflowForm.errors as Record<string, string | undefined>;

    return (
        <>
            <Head title="Pengaturan klinik" />
            <div className="space-y-6">
                <PageHeaderWithActions
                    eyebrow="Konfigurasi aplikasi"
                    subtitle="Kelola profil fasyankes, identitas persuratan resmi, serta pembagian bagian pengkajian awal dan pemeriksaan klinis antar peran."
                    title="Pengaturan klinik"
                />

                <Tabs className="space-y-6" onValueChange={handleTabChange} value={activeTab}>
                    <TabsList aria-label="Kategori pengaturan klinik" className="w-full justify-start border-b border-neutral-200 bg-transparent px-0">
                        <TabsTrigger className="gap-2 px-4 py-3" value="profil">
                            <Building2 aria-hidden="true" className="size-4" />
                            Profil klinik
                        </TabsTrigger>
                        <TabsTrigger className="gap-2 px-4 py-3" value="alur-klinis">
                            <SlidersHorizontal aria-hidden="true" className="size-4" />
                            Pengkajian & pemeriksaan
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent className="space-y-6" value="profil">
                        <Card>
                            <CardHeader className="border-b border-neutral-100">
                                <div className="flex items-start gap-3">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Building2 className="size-5" /></span>
                                    <div>
                                        <CardTitle>Identitas, perizinan, & kode wilayah</CardTitle>
                                        <CardDescription className="mt-1">Perubahan pada profil ini diterapkan pada kop surat resmi, etiket farmasi, dan pemetaan profil fasyankes SATUSEHAT.</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="p-5 sm:p-6">
                                <form className="space-y-5" onSubmit={saveProfile}>
                                    <Field error={profile.errors.nama_klinik} htmlFor="nama-klinik" label="Nama klinik" required>
                                        <Input id="nama-klinik" onChange={(event) => profile.setData('nama_klinik', event.target.value)} placeholder="Contoh: Klinik Pratama Kementerian Pariwisata" required value={profile.data.nama_klinik} />
                                    </Field>
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <Field error={profile.errors.izin_operasional} htmlFor="izin-operasional" label="Nomor izin operasional">
                                            <Input id="izin-operasional" onChange={(event) => profile.setData('izin_operasional', event.target.value)} placeholder="Contoh: 6/B.6.1/31.09.86/e/2023" value={profile.data.izin_operasional} />
                                        </Field>
                                        <Field error={profile.errors.kode_fasyankes} htmlFor="kode-fasyankes" label="Kode fasyankes Kemenkes">
                                            <Input id="kode-fasyankes" onChange={(event) => profile.setData('kode_fasyankes', event.target.value)} placeholder="Kode registrasi fasyankes" value={profile.data.kode_fasyankes} />
                                        </Field>
                                        <Field error={profile.errors.satusehat_org_id} htmlFor="satusehat-org-id" label="Organization ID SATUSEHAT">
                                            <Input id="satusehat-org-id" onChange={(event) => profile.setData('satusehat_org_id', event.target.value)} placeholder="Opsional (siap integrasi)" value={profile.data.satusehat_org_id} />
                                        </Field>
                                    </div>
                                    <Field error={profile.errors.tagline} htmlFor="tagline" label="Tagline / semboyan">
                                        <Input id="tagline" onChange={(event) => profile.setData('tagline', event.target.value)} placeholder="Contoh: Layanan Kesehatan Prima Aparatur Pariwisata" value={profile.data.tagline} />
                                    </Field>
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <Field error={profile.errors.telepon} htmlFor="telepon" label="Nomor telepon">
                                            <Input id="telepon" onChange={(event) => profile.setData('telepon', event.target.value)} placeholder="Contoh: 021 3838142" value={profile.data.telepon} />
                                        </Field>
                                        <Field error={profile.errors.email} htmlFor="email" label="Email resmi">
                                            <Input id="email" onChange={(event) => profile.setData('email', event.target.value)} placeholder="Contoh: klinik@kemenpar.go.id" type="email" value={profile.data.email} />
                                        </Field>
                                        <Field error={profile.errors.kode_pos} htmlFor="kode-pos" label="Kode pos (5 digit)">
                                            <Input id="kode-pos" inputMode="numeric" maxLength={5} onChange={(event) => profile.setData('kode_pos', event.target.value.replace(/\D/g, ''))} placeholder="10110" value={profile.data.kode_pos} />
                                        </Field>
                                    </div>
                                    <Field error={profile.errors.alamat} htmlFor="alamat" label="Alamat lengkap">
                                        <Textarea id="alamat" onChange={(event) => profile.setData('alamat', event.target.value)} placeholder="Gedung Sapta Pesona, Jl. Medan Merdeka Barat No. 17, Gambir, Jakarta Pusat" rows={3} value={profile.data.alamat} />
                                    </Field>
                                    <div className="space-y-3 rounded-xl border border-neutral-200 bg-neutral-50/70 p-4">
                                        <div>
                                            <p className="text-xs font-semibold uppercase tracking-wide text-neutral-600">Kode wilayah administratif (Kemendagri · SATUSEHAT)</p>
                                            <p className="mt-0.5 text-xs text-neutral-500">Digunakan untuk standardisasi alamat fasyankes pada pemetaan FHIR Organization dan Location.</p>
                                        </div>
                                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                            <Field error={profile.errors.kode_provinsi} htmlFor="kode-provinsi" label="Provinsi (2 digit)">
                                                <Input id="kode-provinsi" inputMode="numeric" maxLength={2} onChange={(event) => profile.setData('kode_provinsi', event.target.value.replace(/\D/g, ''))} placeholder="31" value={profile.data.kode_provinsi} />
                                            </Field>
                                            <Field error={profile.errors.kode_kabupaten} htmlFor="kode-kabupaten" label="Kab/Kota (4 digit)">
                                                <Input id="kode-kabupaten" inputMode="numeric" maxLength={4} onChange={(event) => profile.setData('kode_kabupaten', event.target.value.replace(/\D/g, ''))} placeholder="3171" value={profile.data.kode_kabupaten} />
                                            </Field>
                                            <Field error={profile.errors.kode_kecamatan} htmlFor="kode-kecamatan" label="Kecamatan (6 digit)">
                                                <Input id="kode-kecamatan" inputMode="numeric" maxLength={6} onChange={(event) => profile.setData('kode_kecamatan', event.target.value.replace(/\D/g, ''))} placeholder="317101" value={profile.data.kode_kecamatan} />
                                            </Field>
                                            <Field error={profile.errors.kode_kelurahan} htmlFor="kode-kelurahan" label="Kelurahan (10 digit)">
                                                <Input id="kode-kelurahan" inputMode="numeric" maxLength={10} onChange={(event) => profile.setData('kode_kelurahan', event.target.value.replace(/\D/g, ''))} placeholder="3171011001" value={profile.data.kode_kelurahan} />
                                            </Field>
                                        </div>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field error={profile.errors.kepala_klinik} htmlFor="kepala-klinik" label="Kepala klinik / penanggung jawab">
                                            <Input id="kepala-klinik" onChange={(event) => profile.setData('kepala_klinik', event.target.value)} placeholder="Nama lengkap beserta gelar" value={profile.data.kepala_klinik} />
                                        </Field>
                                        <Field error={profile.errors.nip_kepala} htmlFor="nip-kepala" label="NIP / SIP kepala klinik">
                                            <Input id="nip-kepala" onChange={(event) => profile.setData('nip_kepala', event.target.value)} placeholder="NIP atau nomor SIP penanggung jawab" value={profile.data.nip_kepala} />
                                        </Field>
                                    </div>
                                    <Field error={profile.errors.website} htmlFor="website" label="Website">
                                        <Input id="website" onChange={(event) => profile.setData('website', event.target.value)} placeholder="https://klinik.example" value={profile.data.website} />
                                    </Field>
                                    <FormActionFooter>
                                        <Button disabled={profile.processing} type="submit">
                                            <Save className="size-4" />
                                            {profile.processing ? 'Menyimpan…' : 'Simpan profil klinik'}
                                        </Button>
                                    </FormActionFooter>
                                </form>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="border-b border-neutral-100">
                                <div className="flex items-start gap-3">
                                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ImagePlus className="size-5" /></span>
                                    <div>
                                        <CardTitle>Logo klinik</CardTitle>
                                        <CardDescription className="mt-1">Gunakan gambar PNG, JPG, atau WebP dengan ukuran maksimal 2 MB untuk kop dokumen resmi.</CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="p-5 sm:p-6">
                                <form className="space-y-5" onSubmit={uploadLogo}>
                                    <div className={setting.logoUrl ? 'grid items-center gap-5 md:grid-cols-[minmax(12rem,16rem)_minmax(0,1fr)]' : 'space-y-4'}>
                                        {setting.logoUrl && (
                                            <div className="flex min-h-32 items-center justify-center rounded-xl border border-dashed border-neutral-300 bg-neutral-50 p-4">
                                                <img alt="Logo klinik aktif" className="max-h-28 max-w-48 object-contain" src={setting.logoUrl} />
                                            </div>
                                        )}
                                        <Attachment accept="image/png,image/jpeg,image/webp,.png,.jpg,.jpeg,.webp" error={logo.errors.logo} fileName={logo.data.logo?.name} id="clinic-logo" inputKey={logoInputKey} label="Pilih file logo" onFileChange={(file) => logo.setData('logo', file)} required />
                                    </div>
                                    <FormActionFooter>
                                        <Button disabled={logo.processing || !logo.data.logo} type="submit">
                                            <ImagePlus className="size-4" />
                                            {logo.processing ? 'Mengunggah…' : 'Unggah logo'}
                                        </Button>
                                    </FormActionFooter>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent className="space-y-6" value="alur-klinis">
                        <form className="space-y-6" onSubmit={saveWorkflow}>
                            <Card>
                                <CardHeader className="border-b border-neutral-100">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Activity className="size-5" /></span>
                                        <div>
                                            <CardTitle>Alur layanan awal & pelaksana tanda vital</CardTitle>
                                            <CardDescription className="mt-1">Tentukan peran yang menerima pasien setelah pendaftaran untuk pengukuran tanda vital dan pengkajian awal.</CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="p-5 sm:p-6">
                                    <WorkflowSettingRow
                                        description="Menentukan peran pelaksana pengukuran tanda vital (TTV) setelah kunjungan didaftarkan."
                                        error={workflowForm.errors.pelaksana_ttv}
                                        htmlFor="pelaksana-ttv"
                                        title="Pelaksana pengkajian awal & TTV"
                                    >
                                        <Select
                                            id="pelaksana-ttv"
                                            onChange={(event) => workflowForm.setData('pelaksana_ttv', event.target.value)}
                                            value={workflowForm.data.pelaksana_ttv}
                                        >
                                            <option value="perawat">Perawat</option>
                                            <option value="pendaftaran">Staf pendaftaran</option>
                                        </Select>
                                    </WorkflowSettingRow>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="border-b border-neutral-100">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Stethoscope className="size-5" /></span>
                                        <div>
                                            <CardTitle>Poli Umum — Distribusi bagian pengkajian & pemeriksaan</CardTitle>
                                            <CardDescription className="mt-1">Atur bagian pengkajian lanjutan yang diisi oleh Perawat, Dokter, atau keduanya. Bagian yang dialihkan ke Dokter otomatis tampil rapi di tab Anamnesis.</CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="divide-y divide-neutral-100 p-5 sm:p-6">
                                    <WorkflowSettingRow
                                        badge="Default: Dokter"
                                        description="Lokasi keluhan, karakteristik, pola/frekuensi, pemicu, pereda, perkembangan keluhan, dan skala nyeri."
                                        error={wfErrors['clinical_workflow.umum_complaint_details']}
                                        htmlFor="wf-umum-complaint-details"
                                        title="Rincian keluhan & skala nyeri"
                                    >
                                        <Select
                                            id="wf-umum-complaint-details"
                                            onChange={(event) => updateWorkflowKey('umum_complaint_details', event.target.value as RoleAssignment)}
                                            value={workflowForm.data.clinical_workflow.umum_complaint_details}
                                        >
                                            {roleOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </Select>
                                    </WorkflowSettingRow>

                                    <WorkflowSettingRow
                                        badge="Default: Dokter"
                                        description="Review of Systems (ROS): kardiovaskular, respirasi, pencernaan, neurologis, muskuloskeletal, genitourinaria, integumen, dan psikologis."
                                        error={wfErrors['clinical_workflow.umum_system_review']}
                                        htmlFor="wf-umum-system-review"
                                        title="Pengkajian sistem tubuh (ROS)"
                                    >
                                        <Select
                                            id="wf-umum-system-review"
                                            onChange={(event) => updateWorkflowKey('umum_system_review', event.target.value as RoleAssignment)}
                                            value={workflowForm.data.clinical_workflow.umum_system_review}
                                        >
                                            {roleOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </Select>
                                    </WorkflowSettingRow>

                                    <WorkflowSettingRow
                                        description="Penapisan status gizi, kemampuan mobilitas, dan kebutuhan khusus pasien pada pengkajian awal perawat."
                                        error={wfErrors['clinical_workflow.umum_nutrition_needs']}
                                        htmlFor="wf-umum-nutrition-needs"
                                        title="Nutrisi, mobilitas, & kebutuhan khusus"
                                    >
                                        <Select
                                            id="wf-umum-nutrition-needs"
                                            onChange={(event) => updateWorkflowKey('umum_nutrition_needs', event.target.value as NurseOptionalAssignment)}
                                            value={workflowForm.data.clinical_workflow.umum_nutrition_needs}
                                        >
                                            {nurseOptionalOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </Select>
                                    </WorkflowSettingRow>

                                    <WorkflowSettingRow
                                        description="Berat badan, tinggi badan, lingkar perut, dan perhitungan Indeks Massa Tubuh (IMT) otomatis."
                                        error={wfErrors['clinical_workflow.umum_anthropometry_mode']}
                                        htmlFor="wf-umum-anthropometry"
                                        title="Tampilan antropometri perawat"
                                    >
                                        <Select
                                            id="wf-umum-anthropometry"
                                            onChange={(event) => updateWorkflowKey('umum_anthropometry_mode', event.target.value as AnthropometryMode)}
                                            value={workflowForm.data.clinical_workflow.umum_anthropometry_mode}
                                        >
                                            {anthropometryOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </Select>
                                    </WorkflowSettingRow>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="border-b border-neutral-100">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardCheck className="size-5" /></span>
                                        <div>
                                            <CardTitle>Poli Gigi & Mulut — Distribusi bagian pengkajian & pemeriksaan</CardTitle>
                                            <CardDescription className="mt-1">Sesuaikan pembagian pengkajian nyeri gigi dan observasi mukosa mulut antara terapis/perawat gigi dan dokter gigi.</CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="divide-y divide-neutral-100 p-5 sm:p-6">
                                    <WorkflowSettingRow
                                        description="Lokasi nyeri gigi/gusi, skala nyeri, pola durasi, faktor pemicu, karakter, pereda, dan dampak aktivitas. Jika dialihkan ke Dokter, tampil di tab Anamnesis."
                                        error={wfErrors['clinical_workflow.gigi_pain_details']}
                                        htmlFor="wf-gigi-pain-details"
                                        title="Rincian nyeri gigi & perkembangan keluhan"
                                    >
                                        <Select
                                            id="wf-gigi-pain-details"
                                            onChange={(event) => updateWorkflowKey('gigi_pain_details', event.target.value as RoleAssignment)}
                                            value={workflowForm.data.clinical_workflow.gigi_pain_details}
                                        >
                                            {roleOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </Select>
                                    </WorkflowSettingRow>

                                    <WorkflowSettingRow
                                        description="Observasi lidah, pipi, bibir, palatum, gusi, dan catatan mukosa. Jika dialihkan ke Dokter, tampil di tab Pemeriksaan & Odontogram."
                                        error={wfErrors['clinical_workflow.gigi_mucosa_observation']}
                                        htmlFor="wf-gigi-mucosa"
                                        title="Observasi mukosa mulut awal"
                                    >
                                        <Select
                                            id="wf-gigi-mucosa"
                                            onChange={(event) => updateWorkflowKey('gigi_mucosa_observation', event.target.value as RoleAssignment)}
                                            value={workflowForm.data.clinical_workflow.gigi_mucosa_observation}
                                        >
                                            {roleOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </Select>
                                    </WorkflowSettingRow>

                                    <WorkflowSettingRow
                                        description="Penapisan gizi, riwayat penyakit keluarga, dan catatan pemeriksaan awal tambahan di pengkajian perawat gigi."
                                        error={wfErrors['clinical_workflow.gigi_additional_assessment']}
                                        htmlFor="wf-gigi-additional"
                                        title="Pengkajian tambahan Poli Gigi"
                                    >
                                        <Select
                                            id="wf-gigi-additional"
                                            onChange={(event) => updateWorkflowKey('gigi_additional_assessment', event.target.value as NurseOptionalAssignment)}
                                            value={workflowForm.data.clinical_workflow.gigi_additional_assessment}
                                        >
                                            {nurseOptionalOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                        </Select>
                                    </WorkflowSettingRow>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="border-b border-neutral-100">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><SlidersHorizontal className="size-5" /></span>
                                        <div>
                                            <CardTitle>Opsi ruang kerja pemeriksaan dokter</CardTitle>
                                            <CardDescription className="mt-1">Pengaturan tampilan pendukung pada layar pemeriksaan dokter untuk kedua poliklinik.</CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="divide-y divide-neutral-100 p-5 sm:p-6">
                                    <WorkflowSettingRow
                                        description="Menyediakan tab SOAP 1-layar di samping tab terstruktur (Anamnesis, Pemeriksaan fisik, Diagnosis, Tindakan, Resep)."
                                        error={wfErrors['clinical_workflow.dokter_soap_tab']}
                                        htmlFor="wf-dokter-soap-tab"
                                        title="Tab SOAP langsung"
                                    >
                                        <Select
                                            id="wf-dokter-soap-tab"
                                            onChange={(event) => updateWorkflowKey('dokter_soap_tab', event.target.value === '1')}
                                            value={workflowForm.data.clinical_workflow.dokter_soap_tab ? '1' : '0'}
                                        >
                                            <option value="1">Aktif (tampilkan tab SOAP langsung)</option>
                                            <option value="0">Nonaktif (fokus tab terstruktur)</option>
                                        </Select>
                                    </WorkflowSettingRow>

                                    <WorkflowSettingRow
                                        description="Menampilkan panel input pengukuran ulang tekanan darah, nadi, dan suhu pada tab Pemeriksaan fisik dokter."
                                        error={wfErrors['clinical_workflow.dokter_repeat_vitals']}
                                        htmlFor="wf-dokter-repeat-vitals"
                                        title="Pengukuran ulang tanda vital dokter"
                                    >
                                        <Select
                                            id="wf-dokter-repeat-vitals"
                                            onChange={(event) => updateWorkflowKey('dokter_repeat_vitals', event.target.value === '1')}
                                            value={workflowForm.data.clinical_workflow.dokter_repeat_vitals ? '1' : '0'}
                                        >
                                            <option value="1">Aktif (tampilkan panel ukur ulang TTV)</option>
                                            <option value="0">Nonaktif (gunakan TTV pengkajian awal)</option>
                                        </Select>
                                    </WorkflowSettingRow>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="border-b border-neutral-100">
                                    <div className="flex items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ShieldCheck className="size-5" /></span>
                                        <div>
                                            <CardTitle>Batasan keselamatan & kepatuhan regulasi (terkunci)</CardTitle>
                                            <CardDescription className="mt-1">Sesuai PMK No. 24 Tahun 2022, komponen inti berikut tetap wajib aktif sesuai kewenangan klinis dan tidak dapat dinonaktifkan.</CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="grid gap-4 p-5 sm:p-6 md:grid-cols-2">
                                    <div className="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50/70 p-4">
                                        <div className="flex items-center gap-2 text-sm font-semibold text-neutral-900">
                                            <Lock aria-hidden="true" className="size-4 text-neutral-600" />
                                            Inti pengkajian awal (Perawat)
                                        </div>
                                        <ul className="list-inside list-disc space-y-1 text-xs leading-5 text-neutral-600">
                                            <li>Keluhan utama, lama keluhan, dan sumber informasi</li>
                                            <li>Konfirmasi alergi, riwayat penyakit, obat, & status maternal</li>
                                            <li>Tanda vital (TD, nadi, suhu, respirasi, SpO₂)</li>
                                            <li>Kondisi awal, triase / tanda bahaya, & serah terima</li>
                                        </ul>
                                    </div>
                                    <div className="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50/70 p-4">
                                        <div className="flex items-center gap-2 text-sm font-semibold text-neutral-900">
                                            <Lock aria-hidden="true" className="size-4 text-neutral-600" />
                                            Inti pemeriksaan klinis (Dokter) & integritas RME
                                        </div>
                                        <ul className="list-inside list-disc space-y-1 text-xs leading-5 text-neutral-600">
                                            <li>Anamnesis inti, pemeriksaan fisik / intraoral, & odontogram FDI</li>
                                            <li>Diagnosis ICD-10 WHO, tindakan ICD-9-CM, & informed consent</li>
                                            <li>Resep obat, rencana tindak lanjut, tanda tangan, & addendum</li>
                                            <li>Data kunjungan terdahulu tetap tampil utuh pada RME meski pengaturan diubah</li>
                                        </ul>
                                    </div>
                                </CardContent>
                            </Card>

                            <FormActionFooter>
                                {workflowForm.isDirty && (
                                    <span className="mr-auto text-xs font-medium text-amber-700" role="status">Perubahan pengaturan belum disimpan.</span>
                                )}
                                <Button disabled={workflowForm.processing || isAtDefaultWorkflow} onClick={restoreDefaultWorkflow} type="button" variant="secondary">
                                    <RotateCcw className="size-4" />
                                    Pulihkan standar klinik
                                </Button>
                                <Button disabled={workflowForm.processing} type="submit">
                                    <Save className="size-4" />
                                    {workflowForm.processing ? 'Menyimpan…' : 'Simpan pengaturan alur klinis'}
                                </Button>
                            </FormActionFooter>
                        </form>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

function WorkflowSettingRow({
    title,
    description,
    badge,
    htmlFor,
    error,
    children,
}: {
    title: string;
    description: string;
    badge?: string;
    htmlFor: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-4 py-4 first:pt-0 last:pb-0 md:grid-cols-[minmax(0,1fr)_18rem] md:items-start lg:grid-cols-[minmax(0,1fr)_21rem]">
            <div className="min-w-0 space-y-1">
                <div className="flex flex-wrap items-center gap-2">
                    <label className="text-sm font-semibold text-neutral-900" htmlFor={htmlFor}>{title}</label>
                    {badge && <Badge variant="default">{badge}</Badge>}
                </div>
                <p className="text-xs leading-5 text-neutral-600">{description}</p>
            </div>
            <div className="min-w-0 space-y-1.5">
                {children}
                {error && <p className="text-xs font-medium text-red-600" role="alert">{error}</p>}
            </div>
        </div>
    );
}
