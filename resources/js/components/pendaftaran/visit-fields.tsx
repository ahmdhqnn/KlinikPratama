import { DatePicker } from '@/components/ui/date-picker';
import { useEffect, useRef, useState } from 'react';
import { Field } from '@/components/ui/field';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

interface Option {
    id: number;
    name: string;
    type?: string;
}

export interface VisitFormValues {
    tanggal: string;
    poliklinik_id: string;
    dokter_id: string;
    catatan: string;
}

interface Props {
    clinics: Option[];
    today: string;
    doctorsUrl: string;
    values: VisitFormValues;
    errors: Partial<Record<keyof VisitFormValues, string>>;
    onChange: (fields: Partial<VisitFormValues>) => void;
}

interface ScheduledDoctor {
    id: number;
    nama: string;
    poliklinik_id: number;
    poliklinik_nama: string;
}

export function VisitFields({ clinics, today, doctorsUrl, values, errors, onChange }: Props) {
    const isDental = clinics.find((clinic) => String(clinic.id) === values.poliklinik_id)?.type === 'gigi';
    const [doctors, setDoctors] = useState<ScheduledDoctor[]>([]);
    const [loadingDoctors, setLoadingDoctors] = useState(true);
    const [doctorLoadError, setDoctorLoadError] = useState(false);
    const onChangeRef = useRef(onChange);

    useEffect(() => { onChangeRef.current = onChange; }, [onChange]);

    useEffect(() => {
        if (!values.tanggal) {
            setDoctors([]);
            setLoadingDoctors(false);
            setDoctorLoadError(false);
            return;
        }

        const abortController = new AbortController();
        setLoadingDoctors(true);
        setDoctorLoadError(false);

        fetch(`${doctorsUrl}?tanggal=${encodeURIComponent(values.tanggal || today)}`, {
            headers: { Accept: 'application/json' },
            signal: abortController.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error('Gagal memuat jadwal dokter.');
                }

                return response.json() as Promise<ScheduledDoctor[]>;
            })
            .then((availableDoctors) => {
                if (!abortController.signal.aborted) setDoctors(availableDoctors);
            })
            .catch((error: unknown) => {
                if (abortController.signal.aborted) {
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
    }, [doctorsUrl, today, values.tanggal]);

    const clinicDoctors = doctors.filter((doctor) => doctor.poliklinik_id.toString() === values.poliklinik_id);

    useEffect(() => {
        if (loadingDoctors || !values.poliklinik_id) {
            return;
        }

        if (clinicDoctors.length === 1 && values.dokter_id !== clinicDoctors[0].id.toString()) {
            onChangeRef.current({ dokter_id: clinicDoctors[0].id.toString() });
        } else if (values.dokter_id && !clinicDoctors.some((doctor) => doctor.id.toString() === values.dokter_id)) {
            onChangeRef.current({ dokter_id: '' });
        }
    }, [clinicDoctors, loadingDoctors, values.dokter_id, values.poliklinik_id]);

    const displayedDoctors = values.poliklinik_id ? clinicDoctors : doctors;
    const selectedDoctor = values.poliklinik_id
        ? values.dokter_id
        : doctors.find((doctor) => `${doctor.poliklinik_id}:${doctor.id}` === values.dokter_id)
            ? values.dokter_id
            : '';

    function selectDoctor(value: string) {
        if (!values.poliklinik_id && value) {
            const [clinicId, doctorId] = value.split(':');
            onChange({ poliklinik_id: clinicId, dokter_id: doctorId });

            return;
        }

        onChange({ dokter_id: value });
    }

    return (
        <div className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
                <Field error={errors.poliklinik_id} htmlFor="poliklinik_id" label="Poliklinik tujuan" required>
                    <Select aria-describedby={errors.poliklinik_id ? 'poliklinik_id-error' : undefined} id="poliklinik_id" onChange={(event) => {
                        onChange({ poliklinik_id: event.target.value, dokter_id: '', tanggal: values.tanggal >= today ? values.tanggal : today });
                    }} required value={values.poliklinik_id}>
                        <option value="">Pilih poliklinik</option>
                        {clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                    </Select>
                </Field>
                <Field error={doctorLoadError ? 'Jadwal dokter gagal dimuat. Silakan muat ulang halaman.' : errors.dokter_id} htmlFor="dokter_id" label="Dokter terjadwal" required>
                    <Select aria-describedby={(errors.dokter_id || doctorLoadError) ? 'dokter_id-error' : undefined} disabled={loadingDoctors || doctorLoadError || displayedDoctors.length === 0} id="dokter_id" required onChange={(event) => selectDoctor(event.target.value)} value={selectedDoctor}>
                        <option value="">{loadingDoctors ? 'Memuat dokter…' : doctorLoadError ? 'Jadwal dokter tidak tersedia' : displayedDoctors.length ? 'Pilih dokter' : 'Tidak ada dokter terjadwal'}</option>
                        {displayedDoctors.map((doctor) => (
                            <option key={`${doctor.poliklinik_id}:${doctor.id}`} value={values.poliklinik_id ? doctor.id : `${doctor.poliklinik_id}:${doctor.id}`}>
                                {doctor.nama}{values.poliklinik_id ? '' : ` · ${doctor.poliklinik_nama}`}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field error={errors.tanggal} htmlFor="reservation-date" label={isDental ? 'Tanggal kunjungan Poli Gigi' : 'Tanggal kunjungan'} required>
                    <DatePicker id="reservation-date" min={today} required value={values.tanggal} onChange={(event) => onChange({ tanggal: event.target.value, dokter_id: '' })} />
                    <p className="text-xs text-neutral-500">{isDental ? 'Poli Gigi dapat didaftarkan hari ini atau tanggal berikutnya sesuai jadwal dokter.' : 'Poli Umum tersedia untuk kunjungan hari ini sesuai jadwal praktik.'}</p>
                </Field>
                <div className="rounded-xl bg-neutral-50 p-4 md:col-span-2"><p className="text-sm font-medium">Fasilitas klinik internal</p><p className="mt-1 text-xs text-neutral-500">Pasien baru dapat langsung didaftarkan untuk kunjungan. Verifikasi kepesertaan dilakukan oleh admin.</p></div>
            </div>
            <Field error={errors.catatan} htmlFor="catatan" label="Catatan kunjungan">
                <Textarea aria-describedby={errors.catatan ? 'catatan-error' : undefined} id="catatan" onChange={(event) => onChange({ catatan: event.target.value })} placeholder="Catatan khusus atau informasi rujukan awal" value={values.catatan} />
            </Field>
        </div>
    );
}
