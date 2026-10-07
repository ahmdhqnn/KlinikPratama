import { useEffect, useState } from 'react';
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
    day: string;
    values: VisitFormValues;
    errors: Partial<Record<keyof VisitFormValues, string>>;
    onChange: (field: keyof VisitFormValues, value: string) => void;
    showInsuranceNumber?: boolean;
}

interface DoctorOption {
    id: number;
    nama: string;
}

export function VisitFields({ clinics, insuranceProviders, day, values, errors, onChange, showInsuranceNumber = false }: Props) {
    const [doctors, setDoctors] = useState<DoctorOption[]>([]);
    const [loadingDoctors, setLoadingDoctors] = useState(false);
    const [doctorLoadError, setDoctorLoadError] = useState(false);

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

        fetch(`/pendaftaran/dokter/by-poli?poliklinik_id=${encodeURIComponent(values.poliklinik_id)}&hari=${encodeURIComponent(day)}`, {
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
    }, [day, values.poliklinik_id]);

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
                <Field error={errors.asuransi_id} htmlFor="asuransi_id" label="Asuransi atau penjamin">
                    <Select aria-describedby={errors.asuransi_id ? 'asuransi_id-error' : undefined} id="asuransi_id" onChange={(event) => onChange('asuransi_id', event.target.value)} value={values.asuransi_id}>
                        <option value="">Umum</option>
                        {insuranceProviders.map((provider) => <option key={provider.id} value={provider.id}>{provider.name}</option>)}
                    </Select>
                </Field>
                <Field error={errors.jenis_bayar} htmlFor="jenis_bayar" label="Cara bayar" required>
                    <Select aria-describedby={errors.jenis_bayar ? 'jenis_bayar-error' : undefined} id="jenis_bayar" onChange={(event) => onChange('jenis_bayar', event.target.value)} required value={values.jenis_bayar}>
                        <option value="umum">Umum</option><option value="bpjs">BPJS</option><option value="asuransi">Asuransi</option>
                    </Select>
                </Field>
                {showInsuranceNumber && values.jenis_bayar === 'asuransi' && (
                    <Field error={errors.no_asuransi} htmlFor="no_asuransi" label="Nomor peserta asuransi">
                        <Input aria-describedby={errors.no_asuransi ? 'no_asuransi-error' : undefined} id="no_asuransi" onChange={(event) => onChange('no_asuransi', event.target.value)} value={values.no_asuransi} />
                    </Field>
                )}
            </div>
            <Field error={errors.catatan} htmlFor="catatan" label="Catatan kunjungan">
                <Textarea aria-describedby={errors.catatan ? 'catatan-error' : undefined} id="catatan" onChange={(event) => onChange('catatan', event.target.value)} placeholder="Catatan tambahan (opsional)" value={values.catatan} />
            </Field>
        </div>
    );
}
