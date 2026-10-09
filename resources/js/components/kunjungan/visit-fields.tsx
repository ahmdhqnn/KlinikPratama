import { useEffect, useRef, useState } from 'react';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { DatePicker } from '@/components/ui/date-picker';

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
    insuranceProviders: Option[];
    doctorsUrl: string;
    patients?: (Option & { medicalRecordNumber: string })[];
    selectedPatient?: Option & { medicalRecordNumber?: string };
    onChange: (field: keyof VisitFormData, value: string) => void;
}

export function VisitFields({ data, errors, clinics, doctorsUrl, insuranceProviders, patients, selectedPatient, onChange }: Props) {
    const [doctors, setDoctors] = useState<Option[]>([]);
    const [loadingDoctors, setLoadingDoctors] = useState(false);
    const [doctorLoadError, setDoctorLoadError] = useState(false);
    const onChangeRef = useRef(onChange);

    useEffect(() => { onChangeRef.current = onChange; }, [onChange]);

    useEffect(() => {
        if (!data.poliklinik_id || !data.tanggal) {
            setDoctors([]);
            return;
        }
        const controller = new AbortController();
        setLoadingDoctors(true);
        setDoctorLoadError(false);
        const params = new URLSearchParams({ poliklinik_id: data.poliklinik_id, tanggal: data.tanggal });
        fetch(`${doctorsUrl}?${params.toString()}`, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
            .then(async (response) => {
                if (!response.ok) throw new Error('Jadwal dokter gagal dimuat.');
                return response.json() as Promise<Array<{ id: number; nama: string }>>;
            })
            .then((availableDoctors) => {
                const options = availableDoctors.map((doctor) => ({ id: doctor.id, name: doctor.nama }));
                setDoctors(options);
                if (data.dokter_id && !options.some((doctor) => String(doctor.id) === data.dokter_id)) onChangeRef.current('dokter_id', '');
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') return;
                setDoctorLoadError(true);
                setDoctors([]);
                onChangeRef.current('dokter_id', '');
            })
            .finally(() => { if (!controller.signal.aborted) setLoadingDoctors(false); });
        return () => controller.abort();
    }, [data.poliklinik_id, data.tanggal, data.dokter_id, doctorsUrl]);

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {patients && <Field error={errors.pasien_id} htmlFor="pasien_id" label="Pasien" required><Select id="pasien_id" onChange={(event) => onChange('pasien_id', event.target.value)} required value={data.pasien_id}><option value="">Pilih pasien</option>{patients.map((patient) => <option key={patient.id} value={patient.id}>{patient.medicalRecordNumber} · {patient.name}</option>)}</Select></Field>}
            {selectedPatient && <Field error={errors.pasien_id} htmlFor="pasien_id" label="Pasien" required><Input aria-readonly="true" readOnly value={`${selectedPatient.medicalRecordNumber ?? ''} · ${selectedPatient.name}`} /><input name="pasien_id" type="hidden" value={selectedPatient.id} /></Field>}
            <Field error={errors.poliklinik_id} htmlFor="poliklinik_id" label="Poliklinik tujuan" required><Select id="poliklinik_id" onChange={(event) => { onChange('poliklinik_id', event.target.value); onChange('dokter_id', ''); }} required value={data.poliklinik_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>
            <Field error={doctorLoadError ? 'Jadwal dokter gagal dimuat; coba pilih poli kembali.' : errors.dokter_id} htmlFor="dokter_id" label="Dokter"><Select disabled={!data.poliklinik_id || loadingDoctors || doctorLoadError} id="dokter_id" onChange={(event) => onChange('dokter_id', event.target.value)} value={data.dokter_id}><option value="">{loadingDoctors ? 'Memuat dokter…' : doctors.length ? 'Tentukan nanti (opsional)' : 'Tidak ada dokter terjadwal'}</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select></Field>
            <Field error={errors.tanggal} htmlFor="tanggal" label="Tanggal kunjungan" required><DatePicker id="tanggal" onChange={(event) => onChange('tanggal', event.target.value)} required  value={data.tanggal} /></Field>
            <Field error={errors.jenis_pasien} htmlFor="jenis_pasien" label="Jenis pasien" required><Select id="jenis_pasien" onChange={(event) => onChange('jenis_pasien', event.target.value)} required value={data.jenis_pasien}><option value="">Pilih jenis pasien</option><option value="baru">Pasien baru</option><option value="lama">Pasien lama</option></Select></Field>
            <div className="rounded-xl bg-neutral-50 p-4 md:col-span-2"><p className="text-sm font-medium">Layanan internal ditanggung instansi</p><p className="mt-1 text-xs text-neutral-500">Kunjungan hanya dapat didaftarkan setelah peserta dinyatakan berhak.</p></div>
            <div className="md:col-span-2"><Field error={errors.catatan} htmlFor="catatan" label="Catatan kunjungan"><Textarea id="catatan" onChange={(event) => onChange('catatan', event.target.value)} placeholder="Catatan khusus atau rujukan awal" rows={3} value={data.catatan} /></Field></div>
        </div>
    );
}
