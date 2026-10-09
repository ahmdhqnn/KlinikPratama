import { useEffect, useRef, useState } from 'react';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

interface Option {
    id: number;
    name: string;
}

export interface VisitFormValues {
    poliklinik_id: string;
    dokter_id: string;
    asuransi_id: string;
    no_asuransi: string;
    jenis_bayar: string;
    catatan: string;
}

interface Props {
    clinics: Option[];
    insuranceProviders: Option[];
    today: string;
    values: VisitFormValues;
    errors: Partial<Record<keyof VisitFormValues, string>>;
    onChange: (field: keyof VisitFormValues, value: string) => void;
    showInsuranceNumber?: boolean;
}

interface DoctorOption {
    id: number;
    nama: string;
}

export function VisitFields({ clinics, insuranceProviders, today, values, errors, onChange, showInsuranceNumber = false }: Props) {
    const [doctors, setDoctors] = useState<DoctorOption[]>([]);
    const [loadingDoctors, setLoadingDoctors] = useState(false);
    const [doctorLoadError, setDoctorLoadError] = useState(false);
    const onChangeRef = useRef(onChange);

    useEffect(() => { onChangeRef.current = onChange; }, [onChange]);

    useEffect(() => {
        if (!values.poliklinik_id) {
            setDoctors([]);
            setLoadingDoctors(false);
            setDoctorLoadError(false);
            return;
        }

        const abortController = new AbortController();
        setLoadingDoctors(true);
        setDoctorLoadError(false);

        fetch(`/pendaftaran/dokter/by-poli?poliklinik_id=${encodeURIComponent(values.poliklinik_id)}&tanggal=${encodeURIComponent(today)}`, {
            headers: { Accept: 'application/json' },
            signal: abortController.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat dokter.');
                }

                return response.json() as Promise<DoctorOption[]>;
            })
            .then(setDoctors)
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') {
                    return;
                }

                setDoctorLoadError(true);
                setDoctors([]);
            })
            .finally(() => {
                if (!abortController.signal.aborted) {
                    setLoadingDoctors(false);
                }
            });

        return () => abortController.abort();
    }, [today, values.poliklinik_id]);

    return (
        <div className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
                <Field error={errors.poliklinik_id} htmlFor="poliklinik_id" label="Poliklinik tujuan" required>
                    <Select aria-describedby={errors.poliklinik_id ? 'poliklinik_id-error' : undefined} id="poliklinik_id" onChange={(event) => {
                        onChange('poliklinik_id', event.target.value);
                        onChange('dokter_id', '');
                    }} required value={values.poliklinik_id}>
                        <option value="">Pilih poliklinik</option>
                        {clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                    </Select>
                </Field>
                <Field error={doctorLoadError ? 'Jadwal dokter gagal dimuat. Silakan pilih ulang poliklinik.' : errors.dokter_id} htmlFor="dokter_id" label="Dokter">
                    <Select aria-describedby={(errors.dokter_id || doctorLoadError) ? 'dokter_id-error' : undefined} disabled={!values.poliklinik_id || loadingDoctors || doctorLoadError} id="dokter_id" onChange={(event) => onChange('dokter_id', event.target.value)} value={values.dokter_id}>
                        <option value="">{loadingDoctors ? 'Memuat dokter…' : doctors.length ? 'Pilih dokter (opsional)' : 'Tidak ada dokter terjadwal'}</option>
                        {doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.nama}</option>)}
                    </Select>
                </Field>
                <div className="rounded-xl bg-neutral-50 p-4 md:col-span-2"><p className="text-sm font-medium">Fasilitas klinik internal</p><p className="mt-1 text-xs text-neutral-500">Hak layanan diverifikasi saat pendaftaran. Layanan, obat, dan BHP ditanggung anggaran instansi.</p></div>
            </div>
            <Field error={errors.catatan} htmlFor="catatan" label="Catatan kunjungan">
                <Textarea aria-describedby={errors.catatan ? 'catatan-error' : undefined} id="catatan" onChange={(event) => onChange('catatan', event.target.value)} placeholder="Catatan tambahan (opsional)" value={values.catatan} />
            </Field>
        </div>
    );
}
