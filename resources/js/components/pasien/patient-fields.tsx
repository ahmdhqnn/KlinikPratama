import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

export interface PatientFormData {
    nama: string;
    nik: string;
    tanggal_lahir: string;
    jenis_kelamin: string;
    golongan_darah: string;
    agama: string;
    telepon: string;
    pekerjaan: string;
    alamat: string;
    asuransi_id: string;
    no_asuransi: string;
    riwayat_alergi: string;
}

interface InsuranceProvider {
    id: number;
    name: string;
    type: string;
}

interface Props {
    data: PatientFormData;
    errors: Partial<Record<keyof PatientFormData, string>>;
    insuranceProviders: InsuranceProvider[];
    onChange: (field: keyof PatientFormData, value: string) => void;
}

export function PatientFields({ data, errors, insuranceProviders, onChange }: Props) {
    const field = (name: keyof PatientFormData, value: string) => ({
        id: name,
        onChange: (event: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => onChange(name, event.target.value),
        value,
    });

    return (
        <div className="space-y-7">
            <section className="space-y-4">
                <div><h3 className="text-sm font-semibold text-slate-950">Data pribadi</h3><p className="mt-1 text-sm text-slate-500">Informasi identitas pasien untuk rekam medis.</p></div>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field error={errors.nama} htmlFor="nama" label="Nama lengkap" required><Input {...field('nama', data.nama)} autoComplete="name" required /></Field>
                    <Field error={errors.nik} htmlFor="nik" label="NIK"><Input {...field('nik', data.nik)} inputMode="numeric" maxLength={16} /></Field>
                    <Field error={errors.tanggal_lahir} htmlFor="tanggal_lahir" label="Tanggal lahir"><Input {...field('tanggal_lahir', data.tanggal_lahir)} type="date" /></Field>
                    <Field error={errors.jenis_kelamin} htmlFor="jenis_kelamin" label="Jenis kelamin"><NativeSelect {...field('jenis_kelamin', data.jenis_kelamin)}><option value="">Belum dipilih</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></NativeSelect></Field>
                    <Field error={errors.golongan_darah} htmlFor="golongan_darah" label="Golongan darah"><NativeSelect {...field('golongan_darah', data.golongan_darah)}><option value="">Belum diketahui</option>{['A', 'B', 'AB', 'O'].map((bloodType) => <option key={bloodType}>{bloodType}</option>)}</NativeSelect></Field>
                    <Field error={errors.agama} htmlFor="agama" label="Agama"><NativeSelect {...field('agama', data.agama)}><option value="">Belum dipilih</option>{['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'].map((religion) => <option key={religion}>{religion}</option>)}</NativeSelect></Field>
                </div>
            </section>
            <section className="space-y-4">
                <div><h3 className="text-sm font-semibold text-slate-950">Kontak dan alamat</h3><p className="mt-1 text-sm text-slate-500">Gunakan nomor telepon yang dapat dihubungi.</p></div>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field error={errors.telepon} htmlFor="telepon" label="Nomor telepon"><Input {...field('telepon', data.telepon)} autoComplete="tel" type="tel" /></Field>
                    <Field error={errors.pekerjaan} htmlFor="pekerjaan" label="Pekerjaan"><Input {...field('pekerjaan', data.pekerjaan)} /></Field>
                    <div className="md:col-span-2"><Field error={errors.alamat} htmlFor="alamat" label="Alamat lengkap"><Textarea {...field('alamat', data.alamat)} rows={3} /></Field></div>
                </div>
            </section>
            <section className="space-y-4">
                <div><h3 className="text-sm font-semibold text-slate-950">Penjamin dan informasi medis</h3><p className="mt-1 text-sm text-slate-500">Riwayat alergi akan ditampilkan kepada tenaga medis.</p></div>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field error={errors.asuransi_id} htmlFor="asuransi_id" label="Penjamin"><NativeSelect {...field('asuransi_id', data.asuransi_id)}><option value="">Umum (bayar sendiri)</option>{insuranceProviders.map((provider) => <option key={provider.id} value={provider.id}>{provider.name} ({provider.type.toUpperCase()})</option>)}</NativeSelect></Field>
                    <Field error={errors.no_asuransi} htmlFor="no_asuransi" label="Nomor kartu penjamin"><Input {...field('no_asuransi', data.no_asuransi)} /></Field>
                    <div className="md:col-span-2"><Field error={errors.riwayat_alergi} htmlFor="riwayat_alergi" label="Riwayat alergi"><Textarea {...field('riwayat_alergi', data.riwayat_alergi)} className="border-red-200 bg-red-50/60 focus-visible:bg-white" placeholder="Contoh: alergi Amoxicillin atau makanan laut" rows={3} /></Field></div>
                </div>
            </section>
        </div>
    );
}
