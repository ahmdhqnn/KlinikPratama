import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardPlus, UserRoundPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { VisitFields, type VisitFormValues } from '@/components/pendaftaran/visit-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

interface Option {
    id: number;
    name: string;
}

interface PatientForm extends VisitFormValues {
    nama: string;
    nik: string;
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
    day: string;
}

export default function NewPatientRegistration({ clinics, insuranceProviders, today, day }: Props) {
    const form = useForm<PatientForm>({
        nama: '', nik: '', tempat_lahir: '', tanggal_lahir: '', jenis_kelamin: '', golongan_darah: '',
        agama: '', nama_ibu: '', telepon: '', alamat: '', rt: '', rw: '', kelurahan: '', kecamatan: '',
        riwayat_alergi: '', poliklinik_id: '', dokter_id: '', asuransi_id: '', no_asuransi: '', jenis_bayar: 'umum', catatan: '',
    });

    function updateField(field: keyof PatientForm, value: string) {
        form.setData((data) => ({ ...data, [field]: value }));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/pendaftaran/pendaftaran-baru');
    }

    const age = form.data.tanggal_lahir ? calculateAge(form.data.tanggal_lahir, today) : null;

    return (
        <>
            <Head title="Pendaftaran Pasien Baru" />
            <form className="mx-auto max-w-5xl space-y-6" onSubmit={submit}>
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-blue-700">Pendaftaran pasien</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Daftarkan pasien baru</h2>
                        <p className="mt-1 text-sm text-slate-500">Lengkapi data identitas dan tujuan kunjungan pasien.</p>
                    </div>
                    <Button asChild variant="secondary"><Link href="/pendaftaran"><ArrowLeft className="size-4" />Kembali ke dashboard</Link></Button>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><UserRoundPlus className="size-5" /></span>
                            <div><CardTitle>Data identitas pasien</CardTitle><CardDescription className="mt-1">Informasi ini digunakan untuk rekam medis dan komunikasi layanan.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field error={form.errors.nama} htmlFor="nama" label="Nama lengkap" required>
                                <Input aria-describedby={form.errors.nama ? 'nama-error' : undefined} autoComplete="name" id="nama" onChange={(event) => updateField('nama', event.target.value)} placeholder="Nama lengkap pasien" required value={form.data.nama} />
                            </Field>
                            <Field error={form.errors.nik} htmlFor="nik" label="NIK">
                                <Input aria-describedby={form.errors.nik ? 'nik-error' : undefined} autoComplete="off" id="nik" inputMode="numeric" maxLength={16} onChange={(event) => updateField('nik', event.target.value.replace(/\D/g, '').slice(0, 16))} placeholder="16 digit nomor identitas" value={form.data.nik} />
                            </Field>
                            <Field error={form.errors.tempat_lahir} htmlFor="tempat_lahir" label="Tempat lahir">
                                <Input id="tempat_lahir" onChange={(event) => updateField('tempat_lahir', event.target.value)} placeholder="Kota atau kabupaten" value={form.data.tempat_lahir} />
                            </Field>
                            <Field error={form.errors.tanggal_lahir} htmlFor="tanggal_lahir" label="Tanggal lahir" required>
                                <Input aria-describedby={form.errors.tanggal_lahir ? 'tanggal_lahir-error' : undefined} id="tanggal_lahir" max={today} onChange={(event) => updateField('tanggal_lahir', event.target.value)} required type="date" value={form.data.tanggal_lahir} />
                                <p aria-live="polite" className="text-xs text-slate-500">{age === null ? 'Umur akan dihitung otomatis.' : `Umur pasien: ${age} tahun`}</p>
                            </Field>
                            <Field error={form.errors.jenis_kelamin} htmlFor="jenis_kelamin" label="Jenis kelamin" required>
                                <NativeSelect aria-describedby={form.errors.jenis_kelamin ? 'jenis_kelamin-error' : undefined} id="jenis_kelamin" onChange={(event) => updateField('jenis_kelamin', event.target.value)} required value={form.data.jenis_kelamin}>
                                    <option value="">Pilih jenis kelamin</option><option value="L">Laki-laki</option><option value="P">Perempuan</option>
                                </NativeSelect>
                            </Field>
                            <Field error={form.errors.golongan_darah} htmlFor="golongan_darah" label="Golongan darah">
                                <NativeSelect id="golongan_darah" onChange={(event) => updateField('golongan_darah', event.target.value)} value={form.data.golongan_darah}>
                                    <option value="">Belum diketahui</option><option value="A">A</option><option value="B">B</option><option value="AB">AB</option><option value="O">O</option>
                                </NativeSelect>
                            </Field>
                            <Field error={form.errors.agama} htmlFor="agama" label="Agama">
                                <NativeSelect id="agama" onChange={(event) => updateField('agama', event.target.value)} value={form.data.agama}>
                                    <option value="">Pilih agama</option><option>Islam</option><option>Kristen</option><option>Katolik</option><option>Hindu</option><option>Buddha</option>
                                </NativeSelect>
                            </Field>
                            <Field error={form.errors.nama_ibu} htmlFor="nama_ibu" label="Nama ibu kandung">
                                <Input id="nama_ibu" onChange={(event) => updateField('nama_ibu', event.target.value)} value={form.data.nama_ibu} />
                            </Field>
                            <Field error={form.errors.telepon} htmlFor="telepon" label="Nomor telepon">
                                <Input autoComplete="tel" id="telepon" onChange={(event) => updateField('telepon', event.target.value)} placeholder="08xxxxxxxxxx" type="tel" value={form.data.telepon} />
                            </Field>
                        </div>
                        <Field error={form.errors.alamat} htmlFor="alamat" label="Alamat lengkap">
                            <Textarea autoComplete="street-address" id="alamat" onChange={(event) => updateField('alamat', event.target.value)} placeholder="Alamat domisili pasien" value={form.data.alamat} />
                        </Field>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <Field error={form.errors.rt} htmlFor="rt" label="RT"><Input id="rt" onChange={(event) => updateField('rt', event.target.value)} value={form.data.rt} /></Field>
                            <Field error={form.errors.rw} htmlFor="rw" label="RW"><Input id="rw" onChange={(event) => updateField('rw', event.target.value)} value={form.data.rw} /></Field>
                            <Field error={form.errors.kelurahan} htmlFor="kelurahan" label="Kelurahan/desa"><Input id="kelurahan" onChange={(event) => updateField('kelurahan', event.target.value)} value={form.data.kelurahan} /></Field>
                            <Field error={form.errors.kecamatan} htmlFor="kecamatan" label="Kecamatan"><Input id="kecamatan" onChange={(event) => updateField('kecamatan', event.target.value)} value={form.data.kecamatan} /></Field>
                        </div>
                        <Field error={form.errors.riwayat_alergi} htmlFor="riwayat_alergi" label="Riwayat alergi">
                            <Textarea id="riwayat_alergi" onChange={(event) => updateField('riwayat_alergi', event.target.value)} placeholder="Obat, makanan, atau alergi lainnya (opsional)" value={form.data.riwayat_alergi} />
                        </Field>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><ClipboardPlus className="size-5" /></span>
                            <div><CardTitle>Data kunjungan pertama</CardTitle><CardDescription className="mt-1">Pasien baru otomatis dibuatkan nomor rekam medis dan kunjungan awal.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <VisitFields
                            clinics={clinics}
                            day={day}
                            errors={form.errors}
                            insuranceProviders={insuranceProviders}
                            onChange={(field, value) => updateField(field, value)}
                            showInsuranceNumber
                            values={form.data}
                        />
                    </CardContent>
                </Card>

                <div className="flex flex-col-reverse justify-end gap-3 sm:flex-row">
                    <Button asChild variant="secondary"><Link href="/pendaftaran">Batal</Link></Button>
                    <Button disabled={form.processing} type="submit"><UserRoundPlus className="size-4" />{form.processing ? 'Mendaftarkan…' : 'Daftarkan pasien'}</Button>
                </div>
            </form>
        </>
    );
}

function calculateAge(birthDate: string, today: string): number | null {
    const birth = new Date(`${birthDate}T00:00:00`);
    const current = new Date(`${today}T00:00:00`);

    if (Number.isNaN(birth.getTime()) || birth > current) {
        return null;
    }

    let age = current.getFullYear() - birth.getFullYear();
    const monthDifference = current.getMonth() - birth.getMonth();

    if (monthDifference < 0 || (monthDifference === 0 && current.getDate() < birth.getDate())) {
        age -= 1;
    }

    return age;
}
