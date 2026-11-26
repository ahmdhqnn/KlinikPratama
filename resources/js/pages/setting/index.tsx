import { Head, useForm } from '@inertiajs/react';
import { Building2, ImagePlus, Save } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { FormActionFooter } from '@/components/ui/form-action-footer';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Attachment } from '@/components/ui/attachment';

interface Props {
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
}

export default function ClinicSettings({ setting }: Props) {
    const [logoInputKey, setLogoInputKey] = useState(0);
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
        pelaksana_ttv: setting.pelaksanaTtv,
        kepala_klinik: setting.kepalaKlinik,
        nip_kepala: setting.nipKepala,
        website: setting.website,
    });
    const logo = useForm<{ logo: File | null }>({ logo: null });

    function saveProfile(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        profile.put('/setting', { preserveScroll: true });
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

    return (
        <>
            <Head title="Pengaturan klinik" />
            <div className="space-y-6">
                <PageHeaderWithActions
                    eyebrow="Konfigurasi aplikasi"
                    subtitle="Identitas ini digunakan di kop surat resmi, etiket farmasi, dan kesiapan integrasi SATUSEHAT."
                    title="Profil klinik"
                />

                <Card>
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Building2 className="size-5" /></span>
                            <div>
                                <CardTitle>Identitas, perizinan, & kode wilayah</CardTitle>
                                <CardDescription className="mt-1">Seluruh perubahan pada profil ini otomatis diterapkan pada kop persuratan, etiket, dan profil fasyankes SATUSEHAT.</CardDescription>
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
                                    <p className="mt-0.5 text-xs text-neutral-500">Digunakan untuk standardisasi alamat fasyankes pada integrasi FHIR Organization/Location.</p>
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
                            <div className="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4">
                                <Field error={profile.errors.pelaksana_ttv} htmlFor="pelaksana-ttv" label="Pelaksana pengkajian awal & TTV" required>
                                    <Select id="pelaksana-ttv" onChange={(event) => profile.setData('pelaksana_ttv', event.target.value)} value={profile.data.pelaksana_ttv}>
                                        <option value="perawat">Perawat</option>
                                        <option value="pendaftaran">Staf pendaftaran</option>
                                    </Select>
                                    <p className="text-xs leading-5 text-neutral-600">Pilih peran yang menerima pasien setelah proses pendaftaran.</p>
                                </Field>
                            </div>
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
                                <CardDescription className="mt-1">Gunakan gambar PNG, JPG, atau WebP dengan ukuran maksimal 2 MB.</CardDescription>
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
            </div>
        </>
    );
}
