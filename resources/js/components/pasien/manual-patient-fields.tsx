import { identityPlaceholders } from '@/lib/form-placeholders';
import { ExistingPatientSuggestions } from '@/components/pasien/existing-patient-suggestions';
import { IdentitySuggestionInput } from '@/components/pasien/identity-suggestion-input';
import { ReligionSelect } from '@/components/pasien/religion-select';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DateOfBirthPicker } from '@/components/ui/date-picker';

export interface ManualPatientIdentity {
    nama: string;
    nik: string;
    nip: string;
    tempat_lahir: string;
    tanggal_lahir: string;
    jenis_kelamin: string;
    golongan_darah: string;
    agama: string;
    unit_kerja: string;
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
    showAllergy?: boolean;
    lockIdentity?: boolean;
    suggestExisting?: boolean;
    errors: Partial<Record<keyof ManualPatientIdentity, string>>;
    onChange: (field: keyof ManualPatientIdentity, value: string) => void;
}

export function ManualPatientFields({ value, errors, onChange, showAllergy = true, lockIdentity = false, suggestExisting = true }: Props) {
    return (
        <div className="space-y-5">
            {suggestExisting && <ExistingPatientSuggestions query={value.nik || value.nip || value.nama} />}
            <div className="grid gap-4 md:grid-cols-2">
                <Field error={errors.nama} htmlFor="manual-nama" label="Nama lengkap" required><Input placeholder={identityPlaceholders.nama} autoComplete="off" id="manual-nama" maxLength={255} onChange={(event) => onChange('nama', event.target.value)} required value={value.nama} /></Field>
                <Field error={errors.nik} htmlFor="manual-nik" label="NIK (16 digit)"><Input placeholder={lockIdentity ? undefined : identityPlaceholders.nik} autoComplete="off" id="manual-nik" readOnly={lockIdentity} inputMode="numeric" maxLength={16} onChange={(event) => onChange('nik', event.target.value.replace(/\D/g, ''))} value={value.nik} /></Field>
                <Field error={errors.nip} htmlFor="manual-nip" label="NIP">
                    <Input placeholder={lockIdentity ? undefined : identityPlaceholders.nip} readOnly={lockIdentity} id="manual-nip" inputMode="numeric" maxLength={30} onChange={(event) => onChange('nip', event.target.value.replace(/\D/g, ''))} value={value.nip} />
                </Field>
                <Field error={errors.tempat_lahir} htmlFor="manual-tempat-lahir" label="Tempat lahir"><IdentitySuggestionInput placeholder={identityPlaceholders.tempat_lahir} field="tempat_lahir" id="manual-tempat-lahir" onChange={(event) => onChange('tempat_lahir', event.target.value)} value={value.tempat_lahir} /></Field>
                <Field error={errors.tanggal_lahir} htmlFor="manual-tanggal-lahir" label="Tanggal lahir"><DateOfBirthPicker id="manual-tanggal-lahir" value={value.tanggal_lahir} onChange={(event) => onChange('tanggal_lahir', event.target.value)} /></Field>
                <Field error={errors.jenis_kelamin} htmlFor="manual-jenis-kelamin" label="Jenis kelamin"><Select id="manual-jenis-kelamin" onChange={(event) => onChange('jenis_kelamin', event.target.value)} value={value.jenis_kelamin}><option value="">Pilih jenis kelamin</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></Select></Field>
                <Field error={errors.golongan_darah} htmlFor="manual-golongan-darah" label="Golongan darah"><Select id="manual-golongan-darah" onChange={(event) => onChange('golongan_darah', event.target.value)} value={value.golongan_darah}><option value="">Tidak diketahui</option>{['A', 'B', 'AB', 'O'].map((type) => <option key={type} value={type}>{type}</option>)}</Select></Field>
                <Field error={errors.agama} htmlFor="manual-agama" label="Agama"><ReligionSelect id="manual-agama" onChange={(event) => onChange('agama', event.target.value)} value={value.agama} /></Field>
                <Field error={errors.unit_kerja} htmlFor="manual-unit-kerja" label="Unit kerja"><Input id="manual-unit-kerja" maxLength={200} onChange={(event) => onChange('unit_kerja', event.target.value)} placeholder={identityPlaceholders.unit_kerja} value={value.unit_kerja} /></Field>
                <Field error={errors.telepon} htmlFor="manual-telepon" label="Nomor telepon"><Input placeholder={identityPlaceholders.telepon} autoComplete="tel" id="manual-telepon" onChange={(event) => onChange('telepon', event.target.value)} type="tel" value={value.telepon} /></Field>
            </div>
            <Field error={errors.alamat} htmlFor="manual-alamat" label="Alamat"><Textarea placeholder={identityPlaceholders.alamat} id="manual-alamat" onChange={(event) => onChange('alamat', event.target.value)} rows={2} value={value.alamat} /></Field>
            {showAllergy && <Field error={errors.riwayat_alergi} htmlFor="manual-alergi" label="Riwayat alergi"><Textarea id="manual-alergi" onChange={(event) => onChange('riwayat_alergi', event.target.value)} placeholder={identityPlaceholders.riwayat_alergi} value={value.riwayat_alergi} /></Field>}
        </div>
    );
}
