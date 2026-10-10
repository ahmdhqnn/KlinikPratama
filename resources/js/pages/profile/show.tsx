import { Head, router, useForm } from '@inertiajs/react';
import { Camera, IdCard, KeyRound, PenTool, Save, Trash2, UserRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Attachment } from '@/components/ui/attachment';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

interface ProfessionalProfile {
    name: string;
    code: string;
    position: string;
    category: string;
    gender: string | null;
    sip: string | null;
    str: string | null;
    phone: string | null;
}

interface Props {
    profile: {
        name: string;
        email: string;
        phone: string;
        address: string;
        role: string;
        roleLabel: string;
        active: boolean;
        joinedAt: string | null;
        photoUrl: string | null;
        signatureUrl: string | null;
        professional: ProfessionalProfile | null;
    };
}

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="min-w-0 space-y-1">
            <dt className="text-xs font-medium text-neutral-500">{label}</dt>
            <dd className="break-words text-sm font-medium text-neutral-900">{value || 'Belum tercatat'}</dd>
        </div>
    );
}

export default function Profile({ profile }: Props) {
    const roleDescriptions: Record<string, string> = {
        admin: 'Kelola informasi akun administrator dan data kontak kerja.',
        dokter: 'Kelola informasi akun dokter, kontak, dan identitas profesi yang tercatat.',
        perawat: 'Kelola informasi akun perawat, kontak, dan identitas profesi yang tercatat.',
        farmasi: 'Kelola informasi akun farmasi, kontak, dan identitas profesi yang tercatat.',
        pendaftaran: 'Kelola informasi akun petugas pendaftaran dan data kontak kerja.',
        manajemen: 'Kelola informasi akun manajemen dan data kontak kerja.',
    };
    const [photoInputKey, setPhotoInputKey] = useState(0);
    const [signatureInputKey, setSignatureInputKey] = useState(0);
    const details = useForm({ name: profile.name, email: profile.email, phone: profile.phone, address: profile.address });
    const photo = useForm<{ photo: File | null }>({ photo: null });
    const signature = useForm<{ signature: File | null }>({ signature: null });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    function saveDetails(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        details.put('/profile', { preserveScroll: true });
    }

    function uploadPhoto(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        photo.post('/profile/photo', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                photo.reset();
                setPhotoInputKey((key) => key + 1);
            },
        });
    }

    function uploadSignature(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        signature.post('/profile/signature', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                signature.reset();
                setSignatureInputKey((key) => key + 1);
            },
        });
    }

    function savePassword(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        password.put('/profile/password', {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    }

    return (
        <>
            <Head title="Profil Saya" />
            <div className="mx-auto max-w-5xl space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Akun pribadi</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Profil Saya</h2>
                    <p className="mt-1 text-sm text-neutral-500">{roleDescriptions[profile.role] ?? 'Kelola identitas akun, foto, informasi kontak, dan keamanan masuk Anda.'}</p>
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:p-6">
                        <Avatar className="size-20 border border-neutral-200 sm:size-24">
                            {profile.photoUrl && <AvatarImage alt={`Foto ${profile.name}`} src={profile.photoUrl} />}
                            <AvatarFallback className="text-2xl">{profile.name.slice(0, 1).toLocaleUpperCase()}</AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <h3 className="truncate text-xl font-semibold text-neutral-950">{profile.name}</h3>
                            <p className="mt-1 break-all text-sm text-neutral-500">{profile.email}</p>
                            <div className="mt-3 flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-700">{profile.roleLabel}</span>
                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${profile.active ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600'}`}>{profile.active ? 'Akun aktif' : 'Akun nonaktif'}</span>
                            </div>
                        </div>
                        {profile.joinedAt && <p className="text-xs text-neutral-500">Terdaftar sejak {profile.joinedAt}</p>}
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.7fr)]">
                    <div className="space-y-6">
                        <Card>
                            <CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UserRound className="size-5" /></span><div><CardTitle>Informasi akun</CardTitle><CardDescription className="mt-1">Data ini dapat Anda perbarui sendiri.</CardDescription></div></div></CardHeader>
                            <CardContent>
                                <form className="space-y-5" onSubmit={saveDetails}>
                                    <Field error={details.errors.name} htmlFor="profile-name" label="Nama tampilan" required><Input autoComplete="name" id="profile-name" onChange={(event) => details.setData('name', event.target.value)} required value={details.data.name} /></Field>
                                    <Field error={details.errors.email} htmlFor="profile-email" label="Email untuk masuk" required><Input autoComplete="email" id="profile-email" onChange={(event) => details.setData('email', event.target.value)} required type="email" value={details.data.email} /></Field>
                                    <Field error={details.errors.phone} htmlFor="profile-phone" label="Nomor telepon pribadi"><Input autoComplete="tel" id="profile-phone" onChange={(event) => details.setData('phone', event.target.value)} type="tel" value={details.data.phone} /></Field>
                                    <Field error={details.errors.address} htmlFor="profile-address" label="Alamat kontak"><Textarea autoComplete="street-address" id="profile-address" onChange={(event) => details.setData('address', event.target.value)} rows={3} value={details.data.address} /></Field>
                                    <div className="flex justify-end border-t border-neutral-100 pt-5"><Button disabled={details.processing} type="submit"><Save className="size-4" />{details.processing ? 'Menyimpan…' : 'Simpan informasi'}</Button></div>
                                </form>
                            </CardContent>
                        </Card>

                        {profile.professional && <Card>
                            <CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><IdCard className="size-5" /></span><div><CardTitle>Data kepegawaian</CardTitle><CardDescription className="mt-1">Tercatat di data tenaga kesehatan. Perubahan identitas profesi dan izin praktik dikelola administrator.</CardDescription></div></div></CardHeader>
                            <CardContent><dl className="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                                <Detail label="Nama tercatat" value={profile.professional.name} />
                                <Detail label="Kode pegawai" value={profile.professional.code} />
                                <Detail label="Jabatan" value={profile.professional.position} />
                                <Detail label="Kategori" value={profile.professional.category === 'medis' ? 'Tenaga medis' : 'Non-medis'} />
                                <Detail label="Jenis kelamin" value={profile.professional.gender === 'L' ? 'Laki-laki' : profile.professional.gender === 'P' ? 'Perempuan' : null} />
                                <Detail label="Telepon dinas" value={profile.professional.phone} />
                                {profile.professional.sip && <Detail label="Nomor SIP" value={profile.professional.sip} />}
                                {profile.professional.str && <Detail label="Nomor STR" value={profile.professional.str} />}
                            </dl></CardContent>
                        </Card>}
                    </div>

                    <div className="space-y-6">
                        <Card>
                            <CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Camera className="size-5" /></span><div><CardTitle>Foto profil</CardTitle><CardDescription className="mt-1">JPG, PNG, atau WebP, maksimal 2 MB.</CardDescription></div></div></CardHeader>
                            <CardContent className="space-y-4">
                                <form className="space-y-4" onSubmit={uploadPhoto}>
                                    <Attachment accept="image/jpeg,image/png,image/webp" error={photo.errors.photo} fileName={photo.data.photo?.name} id="profile-photo" inputKey={photoInputKey} label="Pilih foto baru" onFileChange={(file) => photo.setData('photo', file)} required />
                                    <Button className="w-full" disabled={photo.processing || !photo.data.photo} type="submit"><Camera className="size-4" />{photo.processing ? 'Mengunggah…' : 'Unggah foto'}</Button>
                                </form>
                                {profile.photoUrl && <Button className="w-full" onClick={() => router.delete('/profile/photo', { preserveScroll: true })} type="button" variant="secondary"><Trash2 className="size-4" />Hapus foto</Button>}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><PenTool className="size-5" /></span><div><CardTitle>Tanda tangan</CardTitle><CardDescription className="mt-1">Tersimpan privat dan digunakan pada resep luar, surat medis, serta rujukan yang Anda terbitkan.</CardDescription></div></div></CardHeader>
                            <CardContent className="space-y-4">
                                {profile.signatureUrl && <div className="flex min-h-24 items-center justify-center rounded-xl border border-dashed border-neutral-300 bg-white p-4"><img alt={`Tanda tangan ${profile.name}`} className="max-h-20 max-w-full object-contain" src={profile.signatureUrl} /></div>}
                                <form className="space-y-4" onSubmit={uploadSignature}>
                                    <Attachment accept="image/png,image/jpeg" error={signature.errors.signature} fileName={signature.data.signature?.name} id="profile-signature" inputKey={signatureInputKey} label="Pilih gambar tanda tangan" onFileChange={(file) => signature.setData('signature', file)} required />
                                    <Button className="w-full" disabled={signature.processing || !signature.data.signature} type="submit"><PenTool className="size-4" />{signature.processing ? 'Menyimpan…' : 'Simpan tanda tangan'}</Button>
                                </form>
                                {profile.signatureUrl && <Button className="w-full" onClick={() => router.delete('/profile/signature', { preserveScroll: true })} type="button" variant="secondary"><Trash2 className="size-4" />Hapus tanda tangan</Button>}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><KeyRound className="size-5" /></span><div><CardTitle>Ganti kata sandi</CardTitle><CardDescription className="mt-1">Gunakan minimal 8 karakter dan konfirmasi kata sandi baru.</CardDescription></div></div></CardHeader>
                            <CardContent><form className="space-y-4" onSubmit={savePassword}>
                                <Field error={password.errors.current_password} htmlFor="current-password" label="Kata sandi saat ini" required><Input autoComplete="current-password" id="current-password" onChange={(event) => password.setData('current_password', event.target.value)} required type="password" value={password.data.current_password} /></Field>
                                <Field error={password.errors.password} htmlFor="new-password" label="Kata sandi baru" required><Input autoComplete="new-password" id="new-password" minLength={8} onChange={(event) => password.setData('password', event.target.value)} required type="password" value={password.data.password} /></Field>
                                <Field error={password.errors.password_confirmation} htmlFor="confirm-password" label="Ulangi kata sandi baru" required><Input autoComplete="new-password" id="confirm-password" minLength={8} onChange={(event) => password.setData('password_confirmation', event.target.value)} required type="password" value={password.data.password_confirmation} /></Field>
                                <Button className="w-full" disabled={password.processing} type="submit"><KeyRound className="size-4" />{password.processing ? 'Menyimpan…' : 'Perbarui kata sandi'}</Button>
                            </form></CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
