import { MembershipIdentityFields } from '@/components/pasien/membership-identity-fields';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

export interface PatientFormData {
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
    rt: string;
    rw: string;
    kelurahan: string;
    kecamatan: string;
    telepon: string;
    alamat: string;
    asuransi_id: string;
    no_asuransi: string;
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
    showPhone?: boolean;
}

export function PatientFields({ data, errors, onChange, showPhone = true }: Props) {
    return (
        <div className="space-y-7">
            <MembershipIdentityFields
                value={data.kepesertaan_id}
                error={errors.kepesertaan_id}
                identity={{
                    name: data.nama, nik: data.nik || null, nip: data.nip || null, unit: data.unit_kerja,
                    tempatLahir: data.tempat_lahir || null, tanggalLahir: data.tanggal_lahir || null,
                    jenisKelamin: data.jenis_kelamin || null, agama: data.agama || null, golonganDarah: data.golongan_darah || null,
                    alamat: data.alamat || null, rt: data.rt || null, rw: data.rw || null,
                    kelurahan: data.kelurahan || null, kecamatan: data.kecamatan || null,
                }}
                onSelect={(member) => {
                    onChange('kepesertaan_id', String(member.id)); onChange('nama', member.name); onChange('nik', member.nik ?? '');
                    onChange('nip', member.nip ?? ''); onChange('unit_kerja', member.unit); onChange('tempat_lahir', member.tempatLahir ?? '');
                    onChange('tanggal_lahir', member.tanggalLahir ?? ''); onChange('jenis_kelamin', member.jenisKelamin ?? '');
                    onChange('agama', member.agama ?? ''); onChange('golongan_darah', member.golonganDarah ?? '');
                    onChange('alamat', member.alamat ?? ''); onChange('rt', member.rt ?? ''); onChange('rw', member.rw ?? '');
                    onChange('kelurahan', member.kelurahan ?? ''); onChange('kecamatan', member.kecamatan ?? '');
                }}
            />
            {showPhone && <section className="space-y-4">
                <div><h3 className="text-sm font-semibold text-neutral-950">Kontak dan alamat</h3><p className="mt-1 text-sm text-neutral-500">Gunakan nomor telepon yang dapat dihubungi.</p></div>
                <div className="grid gap-4 md:grid-cols-2">
                    <Field error={errors.telepon} htmlFor="telepon" label="Nomor telepon"><Input id="telepon" value={data.telepon} onChange={(event) => onChange('telepon', event.target.value)} autoComplete="tel" type="tel" /></Field>
                </div>
            </section>}
        </div>
    );
}
