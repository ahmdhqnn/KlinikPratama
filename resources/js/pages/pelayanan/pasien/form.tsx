import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, UserRoundPlus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ManualPatientFields, type ManualPatientIdentity } from '@/components/pasien/manual-patient-fields';
import { PatientFields, type PatientFormData } from '@/components/pasien/patient-fields';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';

interface Patient {
    id: number;
    name: string;
    nik: string | null;
    memberNip: string | null;
    unit: string | null;
    birthPlace: string | null;
    birthDate: string | null;
    gender: string | null;
    bloodType: string | null;
    religion: string | null;
    phone: string | null;
    address: string | null;
    rt: string | null;
    rw: string | null;
    village: string | null;
    district: string | null;
    insuranceId: number | null;
    membershipId: number | null;
    insuranceNumber: string | null;
}

interface InsuranceProvider {
    id: number;
    name: string;
    type: string;
}

interface FormData extends PatientFormData, ManualPatientIdentity {
    registration_type: 'directory' | 'manual';
    grant_special_access: boolean;
    special_unit_kerja: string;
    special_cost_center: string;
    special_valid_from: string;
    special_valid_until: string;
    special_reference: string;
}

interface Props {
    patient?: Patient;
    insuranceProviders: InsuranceProvider[];
    today: string;
}

export default function PatientForm({ patient, insuranceProviders, today }: Props) {
    const [registrationType, setRegistrationType] = useState<'directory' | 'manual'>('directory');
    const form = useForm<FormData>({
        registration_type: 'directory', kepesertaan_id: patient?.membershipId?.toString() ?? '',
        nama: patient?.name ?? '', nik: patient?.nik ?? '', nip: patient?.memberNip ?? '', unit_kerja: patient?.unit ?? '',
        tempat_lahir: patient?.birthPlace ?? '', tanggal_lahir: patient?.birthDate ?? '', jenis_kelamin: patient?.gender ?? '',
        golongan_darah: patient?.bloodType ?? '', agama: patient?.religion ?? '', nama_ibu: '', telepon: patient?.phone ?? '',
        alamat: patient?.address ?? '', rt: patient?.rt ?? '', rw: patient?.rw ?? '', kelurahan: patient?.village ?? '',
        kecamatan: patient?.district ?? '', riwayat_alergi: '', asuransi_id: patient?.insuranceId?.toString() ?? '',
        no_asuransi: patient?.insuranceNumber ?? '', grant_special_access: false, special_unit_kerja: '',
        special_cost_center: '', special_valid_from: today, special_valid_until: '', special_reference: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (patient) {
            form.put(`/pelayanan/pasien/${patient.id}`);
        } else {
            form.setData('registration_type', registrationType);
            form.post('/pelayanan/pasien');
        }
    }

    return (
        <>
            <Head title={patient ? `Edit ${patient.name}` : 'Daftarkan pasien baru'} />
            <form className="mx-auto max-w-5xl space-y-6" onSubmit={submit}>
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div><p className="text-sm font-medium text-neutral-700">Data induk pasien</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{patient ? 'Perbarui data pasien' : 'Daftarkan pasien baru'}</h2><p className="mt-1 text-sm text-neutral-500">Gunakan format data identitas yang sama dengan pendaftaran petugas.</p></div>
                    <Button asChild variant="secondary"><Link href={patient ? `/pelayanan/pasien/${patient.id}` : '/pelayanan/pasien'}><ArrowLeft className="size-4" />Kembali</Link></Button>
                </div>
                {!patient && <Tabs className="space-y-5" onValueChange={(value) => { const next = value as 'directory' | 'manual'; setRegistrationType(next); form.setData('registration_type', next); }} value={registrationType}>
                    <TabsList aria-label="Metode pendaftaran pasien"><TabsTrigger value="directory">Dari kepesertaan</TabsTrigger><TabsTrigger value="manual">Pendaftaran manual</TabsTrigger></TabsList>
                    <TabsContent className="space-y-6" value="directory">
                        <PatientIdentityCard form={form} insuranceProviders={insuranceProviders} />
                    </TabsContent>
                    <TabsContent className="space-y-6" value="manual">
                        <Card>
                            <CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UserRoundPlus className="size-5" /></span><div><CardTitle>Data pasien manual</CardTitle><CardDescription className="mt-1">Input identitas dan kontak dengan susunan yang sama seperti formulir petugas pendaftaran.</CardDescription></div></div></CardHeader>
                            <CardContent className="space-y-6">
                                <ManualPatientFields errors={form.errors} onChange={(field, value) => form.setData(field, value)} value={form.data} />
                                <section className="space-y-4 rounded-xl border border-neutral-200 bg-neutral-50/70 p-4">
                                    <div className="flex items-start gap-3">
                                        <Checkbox checked={form.data.grant_special_access} id="grant-special-access" onCheckedChange={(checked) => form.setData('grant_special_access', checked === true)} />
                                        <div><label className="text-sm font-semibold text-neutral-900" htmlFor="grant-special-access">Verifikasi hak layanan khusus</label><p className="mt-1 text-xs text-neutral-600">Khusus admin. Pasien yang tidak tercantum dalam direktori hanya mendapat fasilitas setelah bukti diverifikasi; hak ini dibatasi masa berlaku dan dapat dicabut.</p></div>
                                    </div>
                                    {form.data.grant_special_access && <div className="grid gap-4 sm:grid-cols-2">
                                        <Field error={form.errors.special_unit_kerja} htmlFor="special-unit" label="Unit / instansi penanggung" required><Input id="special-unit" maxLength={200} onChange={(event) => form.setData('special_unit_kerja', event.target.value)} value={form.data.special_unit_kerja} /></Field>
                                        <Field error={form.errors.special_cost_center} htmlFor="special-cost-center" label="Cost center" required><Input id="special-cost-center" maxLength={100} onChange={(event) => form.setData('special_cost_center', event.target.value)} value={form.data.special_cost_center} /></Field>
                                        <Field error={form.errors.special_valid_from} htmlFor="special-valid-from" label="Hak berlaku mulai" required><DatePicker id="special-valid-from" onChange={(event) => form.setData('special_valid_from', event.target.value)} required value={form.data.special_valid_from} /></Field>
                                        <Field error={form.errors.special_valid_until} htmlFor="special-valid-until" label="Hak berlaku sampai" required><DatePicker id="special-valid-until" min={form.data.special_valid_from} onChange={(event) => form.setData('special_valid_until', event.target.value)} required value={form.data.special_valid_until} /></Field>
                                        <div className="sm:col-span-2"><Field error={form.errors.special_reference} htmlFor="special-reference" label="Referensi bukti verifikasi" required><Input id="special-reference" maxLength={255} placeholder="Nomor surat, dasar penugasan, atau dokumen persetujuan" onChange={(event) => form.setData('special_reference', event.target.value)} value={form.data.special_reference} /></Field></div>
                                        <div className="sm:col-span-2"><Badge variant="waiting">Perlu NIK 16 digit dan identitas kependudukan lengkap</Badge></div>
                                    </div>}
                                </section>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>}
                {patient && <Card><CardHeader><div className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UserRoundPlus className="size-5" /></span><div><CardTitle>Informasi pasien</CardTitle><CardDescription className="mt-1">Kolom bertanda bintang wajib diisi.</CardDescription></div></div></CardHeader><CardContent><PatientFields data={form.data} errors={form.errors} insuranceProviders={insuranceProviders} onChange={(field, value) => form.setData(field, value)} /></CardContent></Card>}
                <div className="flex flex-col-reverse justify-end gap-3 border-t border-neutral-100 pt-5 sm:flex-row">
                    <Button asChild variant="secondary"><Link href={patient ? `/pelayanan/pasien/${patient.id}` : '/pelayanan/pasien'}>Batalkan</Link></Button>
                    <Button disabled={form.processing} type="submit"><Save className="size-4" />{form.processing ? 'Menyimpan…' : patient ? 'Simpan perubahan' : 'Daftarkan pasien'}</Button>
                </div>
            </form>
        </>
    );
}

function PatientIdentityCard({ form, insuranceProviders }: { form: ReturnType<typeof useForm<FormData>>; insuranceProviders: InsuranceProvider[] }) {
    return (
        <Card>
            <CardHeader><div className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UserRoundPlus className="size-5" /></span><div><CardTitle>Data pasien terverifikasi</CardTitle><CardDescription className="mt-1">Identitas utama diambil dari direktori kepesertaan; kontak dan alergi dicatat pada rekam pasien.</CardDescription></div></div></CardHeader>
            <CardContent className="space-y-6">
                <PatientFields data={form.data} errors={form.errors} insuranceProviders={insuranceProviders} onChange={(field, value) => form.setData(field, value)} showPhone={false} />
                <section className="space-y-4">
                    <div><h3 className="text-sm font-semibold text-neutral-950">Kontak dan riwayat kesehatan</h3><p className="mt-1 text-sm text-neutral-500">Gunakan nomor telepon yang dapat dihubungi dan catat alergi yang diketahui.</p></div>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field error={form.errors.nama_ibu} htmlFor="admin-patient-mother" label="Nama ibu kandung"><Input id="admin-patient-mother" onChange={(event) => form.setData('nama_ibu', event.target.value)} value={form.data.nama_ibu} /></Field>
                        <Field error={form.errors.telepon} htmlFor="admin-patient-phone" label="Nomor telepon"><Input autoComplete="tel" id="admin-patient-phone" onChange={(event) => form.setData('telepon', event.target.value)} type="tel" value={form.data.telepon} /></Field>
                    </div>
                    <Field error={form.errors.riwayat_alergi} htmlFor="admin-patient-allergy" label="Riwayat alergi"><Textarea id="admin-patient-allergy" onChange={(event) => form.setData('riwayat_alergi', event.target.value)} value={form.data.riwayat_alergi} /></Field>
                </section>
            </CardContent>
        </Card>
    );
}
