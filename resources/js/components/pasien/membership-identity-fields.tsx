import { MembershipPicker, type MemberOption } from '@/components/pasien/membership-picker';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

export interface MemberIdentity {
    name: string;
    nik: string | null;
    nip: string | null;
    unit: string;
    tempatLahir: string | null;
    tanggalLahir: string | null;
    jenisKelamin: string | null;
    agama: string | null;
    golonganDarah: string | null;
    alamat: string | null;
    rt: string | null;
    rw: string | null;
    kelurahan: string | null;
    kecamatan: string | null;
}

interface Props {
    value: string;
    identity: MemberIdentity;
    error?: string;
    onSelect: (member: MemberOption) => void;
}

export function MembershipIdentityFields({ value, identity, error, onSelect }: Props) {
    const readOnlyField = (id: string, label: string, fieldValue: string | null | undefined) => (
        <Field htmlFor={id} label={label}>
            <Input id={id} readOnly value={fieldValue ?? ''} placeholder="Belum tersedia di direktori" />
        </Field>
    );

    return (
        <div className="space-y-5">
            <MembershipPicker value={value} label={identity.name} error={error} onSelect={onSelect} />
            {value && (
                <div className="space-y-5 rounded-xl border border-neutral-200 bg-neutral-50/70 p-4">
                    <section className="space-y-3">
                        <div><h3 className="text-sm font-semibold text-neutral-950">Identitas peserta terverifikasi</h3><p className="mt-1 text-xs text-neutral-500">Nama, NIK/NIP, dan data kependudukan diambil dari direktori instansi.</p></div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {readOnlyField('member-name', 'Nama lengkap', identity.name)}
                            {readOnlyField('member-nik', 'NIK', identity.nik)}
                            {readOnlyField('member-nip', 'NIP', identity.nip)}
                            {readOnlyField('member-unit', 'Unit kerja', identity.unit)}
                            {readOnlyField('member-birth-place', 'Tempat lahir', identity.tempatLahir)}
                            {readOnlyField('member-birth-date', 'Tanggal lahir', identity.tanggalLahir)}
                            {readOnlyField('member-gender', 'Jenis kelamin', identity.jenisKelamin === 'L' ? 'Laki-laki' : identity.jenisKelamin === 'P' ? 'Perempuan' : null)}
                            {readOnlyField('member-religion', 'Agama', identity.agama)}
                            {readOnlyField('member-blood-type', 'Golongan darah', identity.golonganDarah)}
                        </div>
                    </section>
                    <section className="space-y-3">
                        <div><h3 className="text-sm font-semibold text-neutral-950">Alamat sesuai data peserta</h3><p className="mt-1 text-xs text-neutral-500">Perbarui alamat melalui data kepesertaan bila terdapat koreksi.</p></div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="sm:col-span-2 lg:col-span-4">{readOnlyField('member-address', 'Alamat', identity.alamat)}</div>
                            {readOnlyField('member-rt', 'RT', identity.rt)}
                            {readOnlyField('member-rw', 'RW', identity.rw)}
                            {readOnlyField('member-village', 'Kelurahan/desa', identity.kelurahan)}
                            {readOnlyField('member-district', 'Kecamatan', identity.kecamatan)}
                        </div>
                    </section>
                </div>
            )}
        </div>
    );
}
