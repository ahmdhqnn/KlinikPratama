import { MembershipIdentityFields } from '@/components/pasien/membership-identity-fields';
import { ManualPatientFields } from '@/components/pasien/manual-patient-fields';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardPlus, UserRoundPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { VisitFields, type VisitFormValues } from '@/components/pendaftaran/visit-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useState } from 'react';

interface Option {
    id: number;
    name: string;
}
interface PatientForm extends VisitFormValues {
    registration_type: 'directory' | 'manual';
    kepesertaan_id: string;
    nama: string;
    nik: string;
    nip: string;
    unit_kerja: string;
    tempat_lahir: string;
    tanggal_lahir: string;
    jenis_kelamin: string;
    golongan_darah: string;
    agama: string;
    nama_ibu: string;
    telepon: string;
    alamat: string;
    rt: string;
    rw: string;
    kelurahan: string;
    kecamatan: string;
    riwayat_alergi: string;
}

interface Props {
    clinics: Option[];
    insuranceProviders: Option[];
    today: string;
}

export default function NewPatientRegistration({ clinics, insuranceProviders, today }: Props) {
    const [registrationType, setRegistrationType] = useState<'directory' | 'manual'>('directory');
    const form = useForm<PatientForm>({
        registration_type: 'directory', kepesertaan_id: '', nama: '', nik: '', nip: '', unit_kerja: '', tempat_lahir: '', tanggal_lahir: '', jenis_kelamin: '', golongan_darah: '',
        agama: '', nama_ibu: '', telepon: '', alamat: '', rt: '', rw: '', kelurahan: '', kecamatan: '',
        riwayat_alergi: '', poliklinik_id: '', dokter_id: '', asuransi_id: '', no_asuransi: '', jenis_bayar: 'internal', catatan: '',
    });

    function updateField(field: keyof PatientForm, value: string) {
        form.setData((data) => ({ ...data, [field]: value }));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.setData('registration_type', registrationType);
        form.post('/pendaftaran/pendaftaran-baru');
    }

    return (
        <>
            <Head title="Pendaftaran Pasien Baru" />
            <form className="mx-auto max-w-5xl space-y-6" onSubmit={submit}>
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-neutral-700">Pendaftaran pasien</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Daftarkan pasien</h2>
                        <p className="mt-1 text-sm text-neutral-500">Pilih direktori kepesertaan atau input manual jika data belum tersedia.</p>
                    </div>
                    <Button asChild variant="secondary"><Link href="/pendaftaran"><ArrowLeft className="size-4" />Kembali ke dashboard</Link></Button>
                </div>

                <Tabs className="space-y-5" onValueChange={(value) => { const next = value as 'directory' | 'manual'; setRegistrationType(next); form.setData('registration_type', next); }} value={registrationType}>
                <TabsList aria-label="Metode pendaftaran pasien">
                    <TabsTrigger value="directory">Dari kepesertaan</TabsTrigger>
                    <TabsTrigger value="manual">Pendaftaran manual</TabsTrigger>
                </TabsList>
                <TabsContent className="space-y-6" value="directory">
                <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UserRoundPlus className="size-5" /></span>
                            <div><CardTitle>Data identitas pasien</CardTitle><CardDescription className="mt-1">Informasi ini digunakan untuk rekam medis dan komunikasi layanan.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <MembershipIdentityFields
                            value={form.data.kepesertaan_id}
                            error={form.errors.kepesertaan_id}
                            identity={{
                                name: form.data.nama, nik: form.data.nik || null, nip: form.data.nip || null, unit: form.data.unit_kerja,
                                tempatLahir: form.data.tempat_lahir || null, tanggalLahir: form.data.tanggal_lahir || null,
                                jenisKelamin: form.data.jenis_kelamin || null, agama: form.data.agama || null, golonganDarah: form.data.golongan_darah || null,
                                alamat: form.data.alamat || null, rt: form.data.rt || null, rw: form.data.rw || null,
                                kelurahan: form.data.kelurahan || null, kecamatan: form.data.kecamatan || null,
                            }}
                            onSelect={(member) => form.setData((data) => ({
                                ...data, kepesertaan_id: String(member.id), nama: member.name, nik: member.nik ?? '', nip: member.nip ?? '', unit_kerja: member.unit,
                                tempat_lahir: member.tempatLahir ?? '', tanggal_lahir: member.tanggalLahir ?? '', jenis_kelamin: member.jenisKelamin ?? '',
                                golongan_darah: member.golonganDarah ?? '', agama: member.agama ?? '', alamat: member.alamat ?? '',
                                rt: member.rt ?? '', rw: member.rw ?? '', kelurahan: member.kelurahan ?? '', kecamatan: member.kecamatan ?? '',
                            }))}
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field error={form.errors.nama_ibu} htmlFor="nama_ibu" label="Nama ibu kandung">
                                <Input id="nama_ibu" onChange={(event) => updateField('nama_ibu', event.target.value)} value={form.data.nama_ibu} />
                            </Field>
                            <Field error={form.errors.telepon} htmlFor="telepon" label="Nomor telepon">
                                <Input autoComplete="tel" id="telepon" onChange={(event) => updateField('telepon', event.target.value)} placeholder="08xxxxxxxxxx" type="tel" value={form.data.telepon} />
                            </Field>
                        </div>
                        <Field error={form.errors.riwayat_alergi} htmlFor="riwayat_alergi" label="Riwayat alergi">
                            <Textarea id="riwayat_alergi" onChange={(event) => updateField('riwayat_alergi', event.target.value)} placeholder="Obat, makanan, atau alergi lainnya (opsional)" value={form.data.riwayat_alergi} />
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ClipboardPlus className="size-5" /></span>
                            <div><CardTitle>Data kunjungan pertama</CardTitle><CardDescription className="mt-1">Pasien baru otomatis dibuatkan nomor rekam medis dan kunjungan awal.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <VisitFields
                            clinics={clinics}
                            today={today}
                            errors={form.errors}
                            insuranceProviders={insuranceProviders}
                            onChange={(field, value) => updateField(field, value)}
                            showInsuranceNumber
                            values={form.data}
                        />
                    </CardContent>
                </Card>
                </TabsContent>
                <TabsContent className="space-y-6" value="manual">
                    <Card>
                        <CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UserRoundPlus className="size-5" /></span><div><CardTitle>Data pasien manual</CardTitle><CardDescription className="mt-1">Pasien mendapat nomor rekam medis. Pendaftaran ini tidak mengaktifkan hak layanan internal; tautkan ke kepesertaan yang sudah diverifikasi sebelum membuka kunjungan.</CardDescription></div></div></CardHeader>
                        <CardContent><ManualPatientFields errors={form.errors} onChange={(field, value) => updateField(field, value)} value={form.data} /></CardContent>
                    </Card>
                </TabsContent>
                </Tabs>

                <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                    <Button asChild variant="secondary"><Link href="/pendaftaran">Batal</Link></Button>
                    <Button disabled={form.processing} type="submit"><UserRoundPlus className="size-4" />{form.processing ? 'Mendaftarkan…' : registrationType === 'manual' ? 'Simpan data pasien' : 'Daftarkan dan buat kunjungan'}</Button>
                </div>
            </form>
        </>
    );
}
