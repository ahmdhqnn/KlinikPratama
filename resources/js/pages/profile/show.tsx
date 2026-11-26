import { identityPlaceholders } from '@/lib/form-placeholders';
import { Head, router, useForm } from '@inertiajs/react';
import { Camera, IdCard, KeyRound, PenTool, Save, ShieldCheck, Trash2, UserRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Attachment } from '@/components/ui/attachment';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { FormActionFooter } from '@/components/ui/form-action-footer';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
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
        supportsSigningPin?: boolean;
        hasSigningPin?: boolean;
        mustSetupSigningPin?: boolean;
        signingPinSetAt?: string | null;
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
    };
    const [photoInputKey, setPhotoInputKey] = useState(0);
    const [signatureInputKey, setSignatureInputKey] = useState(0);
    const details = useForm({ name: profile.name, email: profile.email, phone: profile.phone, address: profile.address });
    const photo = useForm<{ photo: File | null }>({ photo: null });
    const signature = useForm<{ signature: File | null; signature_password: string }>({ signature: null, signature_password: '' });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const signingPin = useForm({ current_password: '', current_signing_pin: '', signing_pin: '', signing_pin_confirmation: '' });

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

    function saveSigningPin(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        signingPin.put('/profile/signing-pin', {
            preserveScroll: true,
            onSuccess: () => signingPin.reset(),
        });
    }

    return (
        <>
            <Head title="Profil saya" />
            <div className="space-y-6">
                <PageHeaderWithActions
                    eyebrow="Akun pribadi"
                    subtitle={roleDescriptions[profile.role] ?? 'Kelola identitas akun, foto, informasi kontak, dan keamanan masuk Anda.'}
                    title="Profil saya"
                />

                {profile.mustSetupSigningPin && (
                    <Alert dismissible={false} variant="default">
                        <ShieldCheck aria-hidden="true" className="size-4" />
                        <AlertTitle>PIN tanda tangan klinis wajib disetel</AlertTitle>
                        <AlertDescription>Atur PIN/Passphrase tanda tangan pribadi Anda pada bagian PIN pengesahan klinis di bawah sebelum menandatangani rekam medis atau dokumen layanan.</AlertDescription>
                    </Alert>
                )}

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
                                <Badge>{profile.active ? 'Akun aktif' : 'Akun nonaktif'}</Badge>
                            </div>
                        </div>
                        {profile.joinedAt && <p className="text-xs text-neutral-500">Terdaftar sejak {profile.joinedAt}</p>}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><UserRound aria-hidden="true" className="size-5 shrink-0" /></span><div className="min-w-0"><CardTitle>Informasi akun</CardTitle><CardDescription className="mt-1">Data ini dapat Anda perbarui sendiri.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-5 sm:p-6">
                        <form className="space-y-5" onSubmit={saveDetails}>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <Field error={details.errors.name} htmlFor="profile-name" label="Nama tampilan" required><Input placeholder={identityPlaceholders.nama} autoComplete="name" id="profile-name" onChange={(event) => details.setData('name', event.target.value)} required value={details.data.name} /></Field>
                                <Field error={details.errors.email} htmlFor="profile-email" label="Email untuk masuk" required><Input placeholder={identityPlaceholders.email} autoComplete="email" id="profile-email" onChange={(event) => details.setData('email', event.target.value)} required type="email" value={details.data.email} /></Field>
                                <Field error={details.errors.phone} htmlFor="profile-phone" label="Nomor telepon pribadi"><Input placeholder={identityPlaceholders.telepon} autoComplete="tel" id="profile-phone" onChange={(event) => details.setData('phone', event.target.value)} type="tel" value={details.data.phone} /></Field>
                            </div>
                            <Field error={details.errors.address} htmlFor="profile-address" label="Alamat kontak"><Textarea placeholder="Alamat yang dapat dihubungi" autoComplete="street-address" id="profile-address" onChange={(event) => details.setData('address', event.target.value)} rows={3} value={details.data.address} /></Field>
                            <FormActionFooter>
                                <Button disabled={details.processing} type="submit"><Save className="size-4" />{details.processing ? 'Menyimpan…' : 'Simpan informasi'}</Button>
                            </FormActionFooter>
                        </form>
                    </CardContent>
                </Card>

                {profile.professional && (
                    <Card>
                        <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><IdCard aria-hidden="true" className="size-5 shrink-0" /></span><div className="min-w-0"><CardTitle>Data kepegawaian</CardTitle><CardDescription className="mt-1">Tercatat di data tenaga kesehatan. Perubahan identitas profesi dan izin praktik dikelola administrator.</CardDescription></div></div></CardHeader>
                        <CardContent className="p-5 sm:p-6">
                            <dl className="grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-4">
                                <Detail label="Nama tercatat" value={profile.professional.name} />
                                <Detail label="Kode pegawai" value={profile.professional.code} />
                                <Detail label="Jabatan" value={profile.professional.position} />
                                <Detail label="Kategori" value={profile.professional.category === 'medis' ? 'Tenaga medis' : 'Non-medis'} />
                                <Detail label="Jenis kelamin" value={profile.professional.gender === 'L' ? 'Laki-laki' : profile.professional.gender === 'P' ? 'Perempuan' : null} />
                                <Detail label="Telepon dinas" value={profile.professional.phone} />
                                {profile.professional.sip && <Detail label="Nomor SIP" value={profile.professional.sip} />}
                                {profile.professional.str && <Detail label="Nomor STR" value={profile.professional.str} />}
                            </dl>
                        </CardContent>
                    </Card>
                )}

                <div className="grid items-stretch gap-6 lg:grid-cols-2">
                    <Card className="flex flex-col">
                        <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Camera aria-hidden="true" className="size-5 shrink-0" /></span><div className="min-w-0"><CardTitle>Foto profil</CardTitle><CardDescription className="mt-1">JPG, PNG, atau WebP, maksimal 2 MB.</CardDescription></div></div></CardHeader>
                        <CardContent className="flex flex-1 flex-col justify-between gap-4 p-5 sm:p-6">
                            <form className="flex flex-1 flex-col justify-between gap-4" onSubmit={uploadPhoto}>
                                <Attachment accept="image/jpeg,image/png,image/webp" error={photo.errors.photo} fileName={photo.data.photo?.name} id="profile-photo" inputKey={photoInputKey} label="Pilih foto baru" onFileChange={(file) => photo.setData('photo', file)} required />
                                <FormActionFooter>
                                    {profile.photoUrl && <Button onClick={() => router.delete('/profile/photo', { preserveScroll: true })} type="button" variant="secondary"><Trash2 className="size-4" />Hapus foto</Button>}
                                    <Button disabled={photo.processing || !photo.data.photo} type="submit"><Camera className="size-4" />{photo.processing ? 'Mengunggah…' : 'Unggah foto'}</Button>
                                </FormActionFooter>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="flex flex-col">
                        <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><PenTool aria-hidden="true" className="size-5 shrink-0" /></span><div className="min-w-0"><CardTitle>Tanda tangan</CardTitle><CardDescription className="mt-1">{profile.role === 'perawat' ? 'Tanda tangan ini otomatis tampil pada cetak pengkajian awal yang Anda selesaikan.' : profile.role === 'dokter' ? 'Tanda tangan ini otomatis tampil pada resep luar, surat medis, rujukan, dan rekam medis yang Anda finalisasi.' : 'Tanda tangan tersimpan privat untuk dokumen yang diterbitkan atas nama Anda.'}</CardDescription></div></div></CardHeader>
                        <CardContent className="flex flex-1 flex-col justify-between gap-4 p-5 sm:p-6">
                            {profile.signatureUrl && <div className="flex min-h-24 items-center justify-center rounded-xl border border-dashed border-neutral-300 bg-white p-4"><img alt={`Tanda tangan ${profile.name}`} className="max-h-20 max-w-full object-contain" src={profile.signatureUrl} /></div>}
                            <form className="flex flex-1 flex-col justify-between gap-4" onSubmit={uploadSignature}>
                                <div className="space-y-4">
                                    <Attachment accept="image/png,image/jpeg" error={signature.errors.signature} fileName={signature.data.signature?.name} id="profile-signature" inputKey={signatureInputKey} label="Pilih gambar tanda tangan" onFileChange={(file) => signature.setData('signature', file)} required />
                                    {(profile.hasSigningPin || profile.mustSetupSigningPin) && (
                                        <Field error={signature.errors.signature_password} htmlFor="signature-pin-confirm" label="PIN pengesahan klinis" required>
                                            <Input autoComplete="off" id="signature-pin-confirm" onChange={(event) => signature.setData('signature_password', event.target.value)} placeholder="Masukkan PIN pengesahan pribadi" required type="password" value={signature.data.signature_password} />
                                        </Field>
                                    )}
                                </div>
                                <FormActionFooter>
                                    {profile.signatureUrl && <Button onClick={() => router.delete('/profile/signature', { data: signature.data.signature_password ? { signature_password: signature.data.signature_password } : {}, preserveScroll: true })} type="button" variant="secondary"><Trash2 className="size-4" />Hapus tanda tangan</Button>}
                                    <Button disabled={signature.processing || !signature.data.signature} type="submit"><PenTool className="size-4" />{signature.processing ? 'Menyimpan…' : 'Simpan tanda tangan'}</Button>
                                </FormActionFooter>
                            </form>
                        </CardContent>
                    </Card>
                </div>

                <div className={profile.supportsSigningPin ? 'grid items-stretch gap-6 lg:grid-cols-2' : 'space-y-6'}>
                    {profile.supportsSigningPin && (
                        <Card className="flex flex-col">
                            <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ShieldCheck aria-hidden="true" className="size-5 shrink-0" /></span><div className="min-w-0"><CardTitle>PIN pengesahan klinis</CardTitle><CardDescription className="mt-1">Kredensial pribadi terpisah dari kata sandi login untuk mengesahkan RME, persetujuan tindakan, surat medis, dan review farmasi.{profile.signingPinSetAt ? ` Aktif sejak ${profile.signingPinSetAt}.` : ''}</CardDescription></div></div></CardHeader>
                            <CardContent className="flex flex-1 flex-col p-5 sm:p-6">
                                <form className="flex flex-1 flex-col justify-between gap-4" onSubmit={saveSigningPin}>
                                    <div className="space-y-4">
                                        <Field error={signingPin.errors.current_password} htmlFor="pin-current-password" label="Kata sandi login saat ini" required><Input autoComplete="current-password" id="pin-current-password" onChange={(event) => signingPin.setData('current_password', event.target.value)} placeholder="Masukkan kata sandi login" required type="password" value={signingPin.data.current_password} /></Field>
                                        {profile.hasSigningPin && <Field error={signingPin.errors.current_signing_pin} htmlFor="current-signing-pin" label="PIN pengesahan saat ini" required><Input autoComplete="off" id="current-signing-pin" onChange={(event) => signingPin.setData('current_signing_pin', event.target.value)} placeholder="Masukkan PIN pengesahan lama" required type="password" value={signingPin.data.current_signing_pin} /></Field>}
                                        <Field error={signingPin.errors.signing_pin} htmlFor="new-signing-pin" label="PIN / passphrase baru" required><Input autoComplete="new-password" id="new-signing-pin" minLength={6} onChange={(event) => signingPin.setData('signing_pin', event.target.value)} placeholder="Minimal 6 karakter (berbeda dari sandi login)" required type="password" value={signingPin.data.signing_pin} /></Field>
                                        <Field error={signingPin.errors.signing_pin_confirmation} htmlFor="confirm-signing-pin" label="Ulangi PIN / passphrase baru" required><Input autoComplete="new-password" id="confirm-signing-pin" minLength={6} onChange={(event) => signingPin.setData('signing_pin_confirmation', event.target.value)} placeholder="Ketik ulang PIN pengesahan" required type="password" value={signingPin.data.signing_pin_confirmation} /></Field>
                                    </div>
                                    <FormActionFooter>
                                        <Button disabled={signingPin.processing} type="submit"><ShieldCheck className="size-4" />{signingPin.processing ? 'Menyimpan…' : profile.hasSigningPin ? 'Perbarui PIN pengesahan' : 'Aktifkan PIN pengesahan'}</Button>
                                    </FormActionFooter>
                                </form>
                            </CardContent>
                        </Card>
                    )}

                    <Card className="flex flex-col">
                        <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><KeyRound aria-hidden="true" className="size-5 shrink-0" /></span><div className="min-w-0"><CardTitle>Ganti kata sandi</CardTitle><CardDescription className="mt-1">Gunakan minimal 8 karakter dan konfirmasi kata sandi baru.</CardDescription></div></div></CardHeader>
                        <CardContent className="flex flex-1 flex-col p-5 sm:p-6">
                            <form className="flex flex-1 flex-col justify-between gap-4" onSubmit={savePassword}>
                                <div className={profile.supportsSigningPin ? 'space-y-4' : 'grid gap-4 sm:grid-cols-3'}>
                                    <Field error={password.errors.current_password} htmlFor="current-password" label="Kata sandi saat ini" required><Input placeholder="Masukkan kata sandi saat ini" autoComplete="current-password" id="current-password" onChange={(event) => password.setData('current_password', event.target.value)} required type="password" value={password.data.current_password} /></Field>
                                    <Field error={password.errors.password} htmlFor="new-password" label="Kata sandi baru" required><Input placeholder="Minimal 8 karakter" autoComplete="new-password" id="new-password" minLength={8} onChange={(event) => password.setData('password', event.target.value)} required type="password" value={password.data.password} /></Field>
                                    <Field error={password.errors.password_confirmation} htmlFor="confirm-password" label="Ulangi kata sandi baru" required><Input placeholder="Ketik ulang kata sandi baru" autoComplete="new-password" id="confirm-password" minLength={8} onChange={(event) => password.setData('password_confirmation', event.target.value)} required type="password" value={password.data.password_confirmation} /></Field>
                                </div>
                                <FormActionFooter>
                                    <Button disabled={password.processing} type="submit"><KeyRound className="size-4" />{password.processing ? 'Menyimpan…' : 'Perbarui kata sandi'}</Button>
                                </FormActionFooter>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
