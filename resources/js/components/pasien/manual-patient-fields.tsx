import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DateOfBirthPicker } from '@/components/ui/date-picker';

export interface ManualPatientIdentity {
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
    value: ManualPatientIdentity;
    errors: Partial<Record<keyof ManualPatientIdentity, string>>;
    onChange: (field: keyof ManualPatientIdentity, value: string) => void;
}

export function ManualPatientFields({ value, errors, onChange }: Props) {
    return (
        <div className="space-y-5">
            <div className="grid gap-4 md:grid-cols-2">
                <Field error={errors.nama} htmlFor="manual-nama" label="Nama lengkap" required><Input autoComplete="name" id="manual-nama" maxLength={255} onChange={(event) => onChange('nama', event.target.value)} required value={value.nama} /></Field>
                <Field error={errors.nik} htmlFor="manual-nik" label="NIK"><Input autoComplete="off" id="manual-nik" inputMode="numeric" maxLength={16} onChange={(event) => onChange('nik', event.target.value.replace(/\D/g, ''))} value={value.nik} /></Field>
                <Field error={errors.tempat_lahir} htmlFor="manual-tempat-lahir" label="Tempat lahir"><Input id="manual-tempat-lahir" onChange={(event) => onChange('tempat_lahir', event.target.value)} value={value.tempat_lahir} /></Field>
                <Field error={errors.tanggal_lahir} htmlFor="manual-tanggal-lahir" label="Tanggal lahir"><DateOfBirthPicker id="manual-tanggal-lahir" value={value.tanggal_lahir} onChange={(event) => onChange('tanggal_lahir', event.target.value)} /></Field>
                <Field error={errors.jenis_kelamin} htmlFor="manual-jenis-kelamin" label="Jenis kelamin"><Select id="manual-jenis-kelamin" onChange={(event) => onChange('jenis_kelamin', event.target.value)} value={value.jenis_kelamin}><option value="">Pilih jenis kelamin</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></Select></Field>
                <Field error={errors.golongan_darah} htmlFor="manual-golongan-darah" label="Golongan darah"><Select id="manual-golongan-darah" onChange={(event) => onChange('golongan_darah', event.target.value)} value={value.golongan_darah}><option value="">Tidak diketahui</option>{['A', 'B', 'AB', 'O'].map((type) => <option key={type} value={type}>{type}</option>)}</Select></Field>
                <Field error={errors.agama} htmlFor="manual-agama" label="Agama"><Input id="manual-agama" onChange={(event) => onChange('agama', event.target.value)} value={value.agama} /></Field>
                <Field error={errors.nama_ibu} htmlFor="manual-nama-ibu" label="Nama ibu kandung"><Input id="manual-nama-ibu" onChange={(event) => onChange('nama_ibu', event.target.value)} value={value.nama_ibu} /></Field>
                <Field error={errors.telepon} htmlFor="manual-telepon" label="Nomor telepon"><Input autoComplete="tel" id="manual-telepon" onChange={(event) => onChange('telepon', event.target.value)} type="tel" value={value.telepon} /></Field>
            </div>
            <Field error={errors.alamat} htmlFor="manual-alamat" label="Alamat"><Textarea id="manual-alamat" onChange={(event) => onChange('alamat', event.target.value)} rows={2} value={value.alamat} /></Field>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Field error={errors.rt} htmlFor="manual-rt" label="RT"><Input id="manual-rt" maxLength={5} onChange={(event) => onChange('rt', event.target.value)} value={value.rt} /></Field>
                <Field error={errors.rw} htmlFor="manual-rw" label="RW"><Input id="manual-rw" maxLength={5} onChange={(event) => onChange('rw', event.target.value)} value={value.rw} /></Field>
                <Field error={errors.kelurahan} htmlFor="manual-kelurahan" label="Kelurahan / desa"><Input id="manual-kelurahan" onChange={(event) => onChange('kelurahan', event.target.value)} value={value.kelurahan} /></Field>
                <Field error={errors.kecamatan} htmlFor="manual-kecamatan" label="Kecamatan"><Input id="manual-kecamatan" onChange={(event) => onChange('kecamatan', event.target.value)} value={value.kecamatan} /></Field>
            </div>
            <Field error={errors.riwayat_alergi} htmlFor="manual-alergi" label="Riwayat alergi"><Textarea id="manual-alergi" onChange={(event) => onChange('riwayat_alergi', event.target.value)} placeholder="Obat, makanan, atau alergi lainnya" value={value.riwayat_alergi} /></Field>
        </div>
    );
}
