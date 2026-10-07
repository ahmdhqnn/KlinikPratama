import { Head, useForm } from '@inertiajs/react';
import { Building2, ImagePlus, Save } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Attachment } from '@/components/ui/attachment';

interface Props {
    setting: {
        namaKlinik: string;
        alamat: string;
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
        tagline: setting.tagline,
        telepon: setting.telepon,
        email: setting.email,
        alamat: setting.alamat,
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
        logo.post('/setting/logo', { forceFormData: true, preserveScroll: true, onSuccess: () => { logo.reset(); setLogoInputKey((key) => key + 1); } });
    }

    return (
        <>
            <Head title="Pengaturan Klinik" />
            <div className="mx-auto max-w-4xl space-y-6">
                <div><p className="text-sm font-medium text-neutral-700">Konfigurasi aplikasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Profil klinik</h2><p className="mt-1 text-sm text-neutral-500">Identitas ini digunakan di dokumen dan alur pelayanan klinik.</p></div>
                <Card><CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Building2 className="size-5" /></span><div><CardTitle>Identitas dan profil klinik</CardTitle><CardDescription className="mt-1">Informasi untuk kop surat, resep, dan kuitansi.</CardDescription></div></div></CardHeader><CardContent><form className="space-y-5" onSubmit={saveProfile}>
                    <Field error={profile.errors.nama_klinik} htmlFor="nama-klinik" label="Nama klinik" required><Input id="nama-klinik" onChange={(event) => profile.setData('nama_klinik', event.target.value)} required value={profile.data.nama_klinik} /></Field>
                    <Field error={profile.errors.tagline} htmlFor="tagline" label="Tagline / semboyan"><Input id="tagline" onChange={(event) => profile.setData('tagline', event.target.value)} placeholder="Melayani dengan sepenuh hati" value={profile.data.tagline} /></Field>
                    <div className="grid gap-5 sm:grid-cols-2"><Field error={profile.errors.telepon} htmlFor="telepon" label="Nomor telepon"><Input id="telepon" onChange={(event) => profile.setData('telepon', event.target.value)} value={profile.data.telepon} /></Field><Field error={profile.errors.email} htmlFor="email" label="Email resmi"><Input id="email" onChange={(event) => profile.setData('email', event.target.value)} type="email" value={profile.data.email} /></Field></div>
                    <Field error={profile.errors.alamat} htmlFor="alamat" label="Alamat lengkap"><Textarea id="alamat" onChange={(event) => profile.setData('alamat', event.target.value)} rows={3} value={profile.data.alamat} /></Field>
                    <div className="rounded-xl border border-neutral-100 bg-neutral-50/70 p-4"><Field error={profile.errors.pelaksana_ttv} htmlFor="pelaksana-ttv" label="Pelaksana pemeriksaan TTV" required><p className="mb-2 text-xs text-neutral-600">Pilih role yang menerima pasien setelah proses pendaftaran.</p><Select id="pelaksana-ttv" onChange={(event) => profile.setData('pelaksana_ttv', event.target.value)} value={profile.data.pelaksana_ttv}><option value="perawat">Perawat</option><option value="pendaftaran">Staf pendaftaran</option></Select></Field></div>
                    <div className="grid gap-5 sm:grid-cols-2"><Field error={profile.errors.kepala_klinik} htmlFor="kepala-klinik" label="Kepala klinik / penanggung jawab"><Input id="kepala-klinik" onChange={(event) => profile.setData('kepala_klinik', event.target.value)} value={profile.data.kepala_klinik} /></Field><Field error={profile.errors.nip_kepala} htmlFor="nip-kepala" label="NIP / SIP kepala klinik"><Input id="nip-kepala" onChange={(event) => profile.setData('nip_kepala', event.target.value)} value={profile.data.nip_kepala} /></Field></div>
                    <Field error={profile.errors.website} htmlFor="website" label="Website"><Input id="website" onChange={(event) => profile.setData('website', event.target.value)} placeholder="https://klinik.example" value={profile.data.website} /></Field>
                    <div className="flex justify-end border-t border-neutral-100 pt-5"><Button disabled={profile.processing} type="submit"><Save className="size-4" />{profile.processing ? 'Menyimpan…' : 'Simpan profil klinik'}</Button></div>
                </form></CardContent></Card>

                <Card><CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ImagePlus className="size-5" /></span><div><CardTitle>Logo klinik</CardTitle><CardDescription className="mt-1">Gunakan gambar PNG transparan dengan ukuran maksimal 2 MB.</CardDescription></div></div></CardHeader><CardContent>
                    {setting.logoUrl && <div className="mb-5 inline-flex min-h-32 min-w-40 items-center justify-center rounded-xl border border-neutral-200 bg-neutral-50 p-4"><img alt="Logo klinik aktif" className="max-h-28 max-w-48 object-contain" src={setting.logoUrl} /></div>}
                    <form className="flex flex-col gap-4 sm:flex-row sm:items-end" onSubmit={uploadLogo}><Attachment accept="image/png,.png" error={logo.errors.logo} fileName={logo.data.logo?.name} id="clinic-logo" inputKey={logoInputKey} label="Pilih file PNG" onFileChange={(file) => logo.setData('logo', file)} required /><Button disabled={logo.processing || !logo.data.logo} type="submit"><ImagePlus className="size-4" />{logo.processing ? 'Mengunggah…' : 'Unggah logo PNG'}</Button></form>
                </CardContent></Card>
            </div>
        </>
    );
}
