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
    catatan: string;
}

interface Option {
    id: number;
    name: string;
    type?: string;
}

interface Props {
    today: string;
    data: VisitFormData;
    errors: Partial<Record<keyof VisitFormData, string>>;
    clinics: Option[];
    doctorsUrl: string;
    patients?: (Option & { medicalRecordNumber: string })[];
    patientSearchUrl?: string;
    selectedPatient?: Option & { medicalRecordNumber?: string };
    onChange: (field: keyof VisitFormData, value: string) => void;
}

export function VisitFields({ today, data, errors, clinics, doctorsUrl, patients, patientSearchUrl, selectedPatient, onChange }: Props) {
    const isDental = clinics.find((clinic) => String(clinic.id) === data.poliklinik_id)?.type === 'gigi';
    const [doctors, setDoctors] = useState<Option[]>([]);
    const [patientOptions, setPatientOptions] = useState(patients ?? []);
    const [patientSearch, setPatientSearch] = useState('');
    const [searchingPatients, setSearchingPatients] = useState(false);
    const [patientSearchFailed, setPatientSearchFailed] = useState(false);
    const [loadingDoctors, setLoadingDoctors] = useState(false);
    const [doctorLoadError, setDoctorLoadError] = useState(false);
    const onChangeRef = useRef(onChange);

    useEffect(() => { onChangeRef.current = onChange; }, [onChange]);

    useEffect(() => {
        if (!patients || !patientSearchUrl) return;

        const query = patientSearch.trim();
        if (query.length < 2) {
            setPatientOptions(patients);
            setSearchingPatients(false);
            setPatientSearchFailed(false);
            return;
        }

        const controller = new AbortController();
        setPatientOptions([]);
        setSearchingPatients(true);
        setPatientSearchFailed(false);
        const timeout = window.setTimeout(() => {
            setSearchingPatients(true);
            setPatientSearchFailed(false);
            const params = new URLSearchParams({ q: query });
            fetch(`${patientSearchUrl}?${params.toString()}`, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                .then(async (response) => {
                    if (!response.ok) throw new Error('Pencarian pasien gagal.');
                    return response.json() as Promise<(Option & { medicalRecordNumber: string })[]>;
                })
                .then((options) => { if (!controller.signal.aborted) setPatientOptions(options); })
                .catch((error: unknown) => {
                    if (controller.signal.aborted) return;
                    setPatientOptions([]);
                    setPatientSearchFailed(true);
                })
                .finally(() => { if (!controller.signal.aborted) setSearchingPatients(false); });
        }, 250);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [patientSearch, patientSearchUrl, patients]);

    useEffect(() => {
        if (!data.poliklinik_id || !data.tanggal) {
            setDoctors([]);
            setLoadingDoctors(false);
            setDoctorLoadError(false);
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
                if (controller.signal.aborted) return;
                const options = availableDoctors.map((doctor) => ({ id: doctor.id, name: doctor.nama }));
                setDoctors(options);
                if (options.length === 1 && !data.dokter_id) onChangeRef.current('dokter_id', String(options[0].id));
                else if (data.dokter_id && !options.some((doctor) => String(doctor.id) === data.dokter_id)) onChangeRef.current('dokter_id', '');
            })
            .catch((error: unknown) => {
                if (controller.signal.aborted) return;
                setDoctorLoadError(true);
                setDoctors([]);
                onChangeRef.current('dokter_id', '');
            })
            .finally(() => { if (!controller.signal.aborted) setLoadingDoctors(false); });
        return () => controller.abort();
    }, [data.poliklinik_id, data.tanggal, data.dokter_id, doctorsUrl]);

    return (
        <div className="grid gap-4 md:grid-cols-2">
            {patients && <><Field htmlFor="pasien-search" label="Cari pasien"><Input autoComplete="off" id="pasien-search" onChange={(event) => { setPatientSearch(event.target.value); onChange('pasien_id', ''); }} placeholder="Cari nama, NIK, atau No. RM" value={patientSearch} />{patientSearchFailed && <p className="text-xs text-red-700">Pencarian gagal. Periksa koneksi lalu coba kembali.</p>}</Field><Field error={errors.pasien_id} htmlFor="pasien_id" label="Pasien" required><Select id="pasien_id" onChange={(event) => onChange('pasien_id', event.target.value)} required value={data.pasien_id}><option value="">{searchingPatients ? 'Mencari pasien…' : patientOptions.length ? 'Pilih pasien' : patientSearch.length >= 2 ? 'Tidak ada pasien ditemukan' : 'Ketik untuk mencari pasien'}</option>{patientOptions.map((patient) => <option key={patient.id} value={patient.id}>{patient.medicalRecordNumber} · {patient.name}</option>)}</Select><p className="text-xs text-neutral-500">{patientSearch.length < 2 ? 'Menampilkan 10 pasien terbaru. Ketik minimal 2 karakter untuk mencari.' : `${patientOptions.length} hasil teratas`}</p></Field></>}
            {selectedPatient && <Field error={errors.pasien_id} htmlFor="pasien_id" label="Pasien" required><Input aria-readonly="true" readOnly value={`${selectedPatient.medicalRecordNumber ?? ''} · ${selectedPatient.name}`} /><input name="pasien_id" type="hidden" value={selectedPatient.id} /></Field>}
            <Field error={errors.poliklinik_id} htmlFor="poliklinik_id" label="Poliklinik tujuan" required><Select id="poliklinik_id" onChange={(event) => { onChange('poliklinik_id', event.target.value); onChange('dokter_id', ''); onChange('tanggal', data.tanggal >= today ? data.tanggal : today); }} required value={data.poliklinik_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field>
            <Field error={doctorLoadError ? 'Jadwal dokter gagal dimuat; coba pilih poli kembali.' : errors.dokter_id} htmlFor="dokter_id" label="Dokter" required><Select required disabled={!data.poliklinik_id || loadingDoctors || doctorLoadError} id="dokter_id" onChange={(event) => onChange('dokter_id', event.target.value)} value={data.dokter_id}><option value="">{loadingDoctors ? 'Memuat dokter…' : doctors.length ? 'Pilih dokter yang terjadwal' : 'Tidak ada dokter terjadwal'}</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select></Field>
            <Field error={errors.tanggal} htmlFor="tanggal" label={isDental ? 'Tanggal kunjungan Poli Gigi' : 'Tanggal kunjungan'} required><DatePicker min={today} id="tanggal" onChange={(event) => onChange('tanggal', event.target.value)} required value={data.tanggal} /></Field>
            <Field error={errors.jenis_pasien} htmlFor="jenis_pasien" label="Jenis pasien" required><Select id="jenis_pasien" onChange={(event) => onChange('jenis_pasien', event.target.value)} required value={data.jenis_pasien}><option value="">Pilih jenis pasien</option><option value="baru">Pasien baru</option><option value="lama">Pasien lama</option></Select></Field>
            <div className="rounded-xl bg-neutral-50 p-4 md:col-span-2">{isDental && <p className="mb-2 text-sm font-medium">Poli Gigi dapat didaftarkan hari ini atau tanggal berikutnya sesuai jadwal dokter.</p>}<p className="text-sm font-medium">Layanan klinik internal</p><p className="mt-1 text-xs text-neutral-500">Kunjungan hanya dapat didaftarkan setelah hak layanan peserta diverifikasi.</p></div>
            <div className="md:col-span-2"><Field error={errors.catatan} htmlFor="catatan" label="Catatan kunjungan"><Textarea id="catatan" onChange={(event) => onChange('catatan', event.target.value)} placeholder="Catatan khusus atau informasi rujukan awal" rows={3} value={data.catatan} /></Field></div>
        </div>
    );
}
