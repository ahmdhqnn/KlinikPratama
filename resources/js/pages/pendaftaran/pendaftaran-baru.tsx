import { focusFirstInvalidField } from '@/lib/form-error-focus';
import { identityPlaceholders } from '@/lib/form-placeholders';
import { RegisteredPatientForm, type InternalReferralRegistration, type PatientResult } from '@/components/pendaftaran/registered-patient-form';
import { MembershipIdentityFields } from '@/components/pasien/membership-identity-fields';
import { ManualPatientFields } from '@/components/pasien/manual-patient-fields';
import { Head, Link, useForm } from '@inertiajs/react';
import { ClipboardPlus, UserRoundPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { VisitFields, type VisitFormValues } from '@/components/pendaftaran/visit-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useState } from 'react';

interface Option {
    id: number;
    name: string;
    type?: string;
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
    telepon: string;
    alamat: string;
    rt: string;
    rw: string;
    kelurahan: string;
    kecamatan: string;
    riwayat_alergi: string;
    general_consent_confirmed: string;
    general_consent_version: string;
    general_consent_relationship: string;
    general_consent_representative: string;
    general_consent_authority: string;
    satusehat_sharing_decision: string;
}

interface Props {
    initialPatient: PatientResult | null;
    referralBackUrl: string;
    internalReferral?: InternalReferralRegistration | null;
    clinics: Option[];
    today: string;
    doctorsUrl: string;
    initialTab: 'manual' | 'directory' | 'registered';
    generalConsent: { text: string; version: string };
}

export default function NewPatientRegistration({ clinics, today, doctorsUrl, initialTab, initialPatient, internalReferral, referralBackUrl, generalConsent }: Props) {
    const [registrationType, setRegistrationType] = useState<'directory' | 'manual' | 'registered'>(initialTab);
    const defaultData: PatientForm = {
        registration_type: 'directory', kepesertaan_id: '', nama: '', nik: '', nip: '', unit_kerja: '', tempat_lahir: '', tanggal_lahir: '', jenis_kelamin: '', golongan_darah: '',
        agama: '', telepon: '', alamat: '', rt: '', rw: '', kelurahan: '', kecamatan: '',
        riwayat_alergi: '', poliklinik_id: clinics.find((clinic) => clinic.type === 'umum')?.id.toString() ?? '', dokter_id: '', tanggal: today, catatan: '',
        general_consent_confirmed: '', general_consent_version: generalConsent?.version ?? '', general_consent_relationship: 'patient',
        general_consent_representative: '', general_consent_authority: '', satusehat_sharing_decision: 'undocumented',
    };
    const manualForm = useForm<PatientForm>({ ...defaultData, registration_type: 'manual' });
    const directoryForm = useForm<PatientForm>(defaultData);
    const form = registrationType === 'manual' ? manualForm : directoryForm;

    function updateField(field: keyof PatientForm, value: string) {
        form.setData((data) => ({ ...data, general_consent_confirmed: '', [field]: value }));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!form.data.general_consent_confirmed) return;
        if (registrationType === 'directory' && !form.data.kepesertaan_id) {
            const formElement = event.currentTarget;
            form.setError('kepesertaan_id', 'Pilih peserta dari hasil pencarian.');
            window.requestAnimationFrame(() => focusFirstInvalidField(formElement));
            return;
        }
        form.transform((data) => ({ ...data, registration_type: registrationType === 'directory' ? 'directory' : 'manual' }));
        form.post('/pendaftaran/pendaftaran-baru', { preserveScroll: 'errors' });
    }

    return (
        <>
            <Head title="Pendaftaran pasien" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Layanan pendaftaran</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{internalReferral ? 'Pendaftaran rujukan internal' : 'Pendaftaran pasien'}</h2>
                    {internalReferral ? <Link className="mt-1 inline-block text-sm font-medium text-neutral-700 underline-offset-4 hover:underline" href={referralBackUrl}>Kembali ke daftar rujukan</Link> : <p className="mt-1 text-sm text-neutral-500">Daftarkan pasien internal dan tentukan tujuan kunjungan.</p>}
                </div>

                {internalReferral ? <RegisteredPatientForm initialPatient={initialPatient} internalReferral={internalReferral} clinics={clinics} today={today} doctorsUrl={doctorsUrl} /> : <Tabs className="space-y-5" onValueChange={(value) => {
                    setRegistrationType(value as typeof registrationType);
                    manualForm.clearErrors();
                    directoryForm.clearErrors();
                }} value={registrationType}>
                    <TabsList aria-label="Pendaftaran pasien" className="h-auto flex-wrap">
                        <TabsTrigger value="manual">Pendaftaran Baru</TabsTrigger>
                        <TabsTrigger value="registered">Pasien Terdaftar</TabsTrigger>
                        <TabsTrigger value="directory">Dari Kepesertaan</TabsTrigger>
                    </TabsList>
                    {registrationType === 'registered' && <TabsContent value="registered"><RegisteredPatientForm initialPatient={initialPatient} clinics={clinics} today={today} doctorsUrl={doctorsUrl} /></TabsContent>}
                    {registrationType !== 'registered' && <form className="space-y-6" onSubmit={submit}>
                {registrationType === 'directory' && <TabsContent className="space-y-6" value="directory">
                <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><UserRoundPlus className="size-5" /></span>
                            <div><CardTitle>Data identitas pasien</CardTitle><CardDescription className="mt-1">Informasi ini digunakan untuk rekam medis dan komunikasi layanan.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <MembershipIdentityFields
                            registrationOnly
                            value={form.data.kepesertaan_id}
                            error={form.errors.kepesertaan_id}
                            onClear={() => form.reset()}
                            identity={{
                                name: form.data.nama, nik: form.data.nik || null, nip: form.data.nip || null, unit: form.data.unit_kerja,
                                tempatLahir: form.data.tempat_lahir || null, tanggalLahir: form.data.tanggal_lahir || null,
                                jenisKelamin: form.data.jenis_kelamin || null, agama: form.data.agama || null, golonganDarah: form.data.golongan_darah || null,
                                alamat: form.data.alamat || null, rt: form.data.rt || null, rw: form.data.rw || null,
                                kelurahan: form.data.kelurahan || null, kecamatan: form.data.kecamatan || null,
                            }}
                            onSelect={(member) => form.setData((data) => ({
                                ...data, general_consent_confirmed: '', telepon: '', riwayat_alergi: '', kepesertaan_id: String(member.id), nama: member.name, nik: member.nik ?? '', nip: member.nip ?? '', unit_kerja: member.unit,
                                tempat_lahir: member.tempatLahir ?? '', tanggal_lahir: member.tanggalLahir ?? '', jenis_kelamin: member.jenisKelamin ?? '',
                                golongan_darah: member.golonganDarah ?? '', agama: member.agama ?? '', alamat: member.alamat ?? '',
                                rt: member.rt ?? '', rw: member.rw ?? '', kelurahan: member.kelurahan ?? '', kecamatan: member.kecamatan ?? '',
                            }))}
                        />
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field error={form.errors.telepon} htmlFor="telepon" label="Nomor telepon">
                                <Input autoComplete="tel" id="telepon" onChange={(event) => updateField('telepon', event.target.value)} placeholder={identityPlaceholders.telepon} type="tel" value={form.data.telepon} />
                            </Field>
                        </div>

                    </CardContent>
                </Card>

                </TabsContent>}
                {registrationType === 'manual' && <TabsContent className="space-y-6" value="manual">
                    <Card>
                        <CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><UserRoundPlus className="size-5" /></span><div><CardTitle>Identitas pasien</CardTitle><CardDescription className="mt-1">Kunjungan dapat langsung didaftarkan. Admin akan memverifikasi data untuk kepesertaan.</CardDescription></div></div></CardHeader>
                        <CardContent><ManualPatientFields errors={form.errors} onChange={(field, value) => updateField(field, value)} value={form.data} /></CardContent>
                    </Card>
                </TabsContent>}
                <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardPlus className="size-5" /></span>
                            <div><CardTitle>Data kunjungan pertama</CardTitle><CardDescription className="mt-1">Pasien baru otomatis dibuatkan nomor rekam medis dan kunjungan awal.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <VisitFields
                            clinics={clinics}
                            today={today}
                            doctorsUrl={doctorsUrl}
                            errors={form.errors}
                            onChange={(fields) => form.setData((data) => ({ ...data, ...fields }))}
                            values={form.data}
                        />
                    </CardContent>
                </Card>

                {generalConsent && <Card>
                    <CardHeader>
                        <CardTitle>Persetujuan umum pasien baru</CardTitle>
                        <CardDescription>Jelaskan ketentuan ini dan konfirmasi keputusan sebelum mendaftarkan kunjungan pertama.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm leading-6 text-neutral-700">{generalConsent.text}</p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Pemberi keputusan" htmlFor="registration-consent-relationship" error={form.errors.general_consent_relationship}>
                                <Select id="registration-consent-relationship" value={form.data.general_consent_relationship} onChange={(event) => form.setData((data) => ({ ...data, general_consent_relationship: event.target.value, general_consent_confirmed: '' }))}>
                                    <option value="patient">Pasien sendiri</option><option value="parent">Orang tua</option><option value="spouse">Suami / istri</option><option value="guardian">Wali / pengampu</option><option value="other">Wakil lain yang sah</option>
                                </Select>
                            </Field>
                            <Field label="Pertukaran data SATUSEHAT Kemenkes (opsional)" htmlFor="registration-satusehat-sharing" error={form.errors.satusehat_sharing_decision}>
                                <Select id="registration-satusehat-sharing" value={form.data.satusehat_sharing_decision} onChange={(event) => form.setData('satusehat_sharing_decision', event.target.value)}>
                                    <option value="undocumented">Belum ditetapkan saat ini</option>
                                    <option value="opt_in">Setuju berbagi ke SATUSEHAT (Opt-In)</option>
                                    <option value="opt_out">Menolak berbagi ke SATUSEHAT (Opt-Out)</option>
                                </Select>
                            </Field>
                        </div>
                        {form.data.general_consent_relationship !== 'patient' && <>
                            <Field label="Nama wakil" htmlFor="registration-consent-name" error={form.errors.general_consent_representative} required>
                                <Input id="registration-consent-name" required minLength={2} maxLength={150} value={form.data.general_consent_representative} onChange={(event) => updateField('general_consent_representative', event.target.value)} />
                            </Field>
                            <Field label="Dasar kewenangan wakil" htmlFor="registration-consent-authority" error={form.errors.general_consent_authority} required>
                                <Textarea id="registration-consent-authority" required minLength={10} maxLength={1000} value={form.data.general_consent_authority} onChange={(event) => updateField('general_consent_authority', event.target.value)} />
                            </Field>
                        </>}
                        <label className="flex items-start gap-3 text-sm leading-6">
                            <input className="mt-1 size-4 shrink-0" type="checkbox" checked={Boolean(form.data.general_consent_confirmed)} onChange={(event) => updateField('general_consent_confirmed', event.target.checked ? '1' : '')} />
                            <span>Saya telah memberi penjelasan, memeriksa identitas, dan menerima persetujuan langsung dari pasien atau wakil yang sah.</span>
                        </label>
                        {(form.errors.general_consent_confirmed || form.errors.general_consent_version) && <p role="alert" className="text-sm text-red-600">{form.errors.general_consent_confirmed ?? form.errors.general_consent_version}</p>}
                    </CardContent>
                </Card>}
                <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                    <Button disabled={form.processing || !form.data.general_consent_confirmed} type="submit"><UserRoundPlus className="size-4" />{form.processing ? 'Mendaftarkan…' : 'Daftarkan dan buat kunjungan'}</Button>
                </div>
                    </form>}
                </Tabs>}
            </div>
        </>
    );
}
