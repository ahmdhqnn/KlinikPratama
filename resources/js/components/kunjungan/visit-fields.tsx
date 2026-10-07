import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';

export interface VisitFormData {
    pasien_id: string;
    poliklinik_id: string;
    dokter_id: string;
    tanggal: string;
    jenis_pasien: string;
    jenis_bayar: string;
    asuransi_id: string;
    catatan: string;
}

interface Option {
    id: number;
    name: string;
    type?: string;
}

interface Props {
    data: VisitFormData;
    errors: Partial<Record<keyof VisitFormData, string>>;
    clinics: Option[];
    doctors: Option[];
    insuranceProviders: Option[];
    patients?: (Option & { medicalRecordNumber: string })[];
    selectedPatient?: Option & { medicalRecordNumber?: string };
    onChange: (field: keyof VisitFormData, value: string) => void;
}

export function VisitFields({ data, errors, clinics, doctors, insuranceProviders, patients, selectedPatient, onChange }: Props) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            {patients && <Field error={errors.pasien_id} htmlFor="pasien_id" label="Pasien" required><NativeSelect id="pasien_id" onChange={(event) => onChange('pasien_id', event.target.value)} required value={data.pasien_id}><option value="">Pilih pasien</option>{patients.map((patient) => <option key={patient.id} value={patient.id}>{patient.medicalRecordNumber} · {patient.name}</option>)}</NativeSelect></Field>}
            {selectedPatient && <Field error={errors.pasien_id} htmlFor="pasien_id" label="Pasien" required><Input aria-readonly="true" readOnly value={`${selectedPatient.medicalRecordNumber ?? ''} · ${selectedPatient.name}`} /><input name="pasien_id" type="hidden" value={selectedPatient.id} /></Field>}
            <Field error={errors.poliklinik_id} htmlFor="poliklinik_id" label="Poliklinik tujuan" required><NativeSelect id="poliklinik_id" onChange={(event) => onChange('poliklinik_id', event.target.value)} required value={data.poliklinik_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</NativeSelect></Field>
            <Field error={errors.dokter_id} htmlFor="dokter_id" label="Dokter"><NativeSelect id="dokter_id" onChange={(event) => onChange('dokter_id', event.target.value)} value={data.dokter_id}><option value="">Tentukan nanti</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</NativeSelect></Field>
            <Field error={errors.tanggal} htmlFor="tanggal" label="Tanggal kunjungan" required><Input id="tanggal" onChange={(event) => onChange('tanggal', event.target.value)} required type="date" value={data.tanggal} /></Field>
            <Field error={errors.jenis_pasien} htmlFor="jenis_pasien" label="Jenis pasien" required><NativeSelect id="jenis_pasien" onChange={(event) => onChange('jenis_pasien', event.target.value)} required value={data.jenis_pasien}><option value="">Pilih jenis pasien</option><option value="baru">Pasien baru</option><option value="lama">Pasien lama</option></NativeSelect></Field>
            <Field error={errors.jenis_bayar} htmlFor="jenis_bayar" label="Cara bayar" required><NativeSelect id="jenis_bayar" onChange={(event) => onChange('jenis_bayar', event.target.value)} required value={data.jenis_bayar}><option value="">Pilih cara bayar</option><option value="umum">Umum</option><option value="bpjs">BPJS</option><option value="asuransi">Asuransi</option></NativeSelect></Field>
            <Field error={errors.asuransi_id} htmlFor="asuransi_id" label="Penjamin"><NativeSelect id="asuransi_id" onChange={(event) => onChange('asuransi_id', event.target.value)} value={data.asuransi_id}><option value="">Umum / tanpa penjamin</option>{insuranceProviders.map((provider) => <option key={provider.id} value={provider.id}>{provider.name}</option>)}</NativeSelect></Field>
            <div className="md:col-span-2"><Field error={errors.catatan} htmlFor="catatan" label="Catatan kunjungan"><Textarea id="catatan" onChange={(event) => onChange('catatan', event.target.value)} placeholder="Catatan khusus atau rujukan awal" rows={3} value={data.catatan} /></Field></div>
        </div>
    );
}
