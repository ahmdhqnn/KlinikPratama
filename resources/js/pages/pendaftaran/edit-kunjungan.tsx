import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

interface Clinic { id: number; name: string }
interface Insurance { id: number; name: string }
interface Doctor { id: number; name: string }
interface Visit { id: number; patient: { name: string; medicalRecordNumber: string; gender: string | null; age: number | null }; clinicId: string; doctorId: string; doctor: Doctor | null; insuranceId: string; paymentType: string; patientType: string; notes: string }
interface Props { visit: Visit; clinics: Clinic[]; insuranceProviders: Insurance[]; day: string; doctorsUrl: string; updateUrl: string; cancelUrl: string }

export default function EditRegistrationVisit({ visit, clinics, insuranceProviders, day, doctorsUrl, updateUrl, cancelUrl }: Props) {
    const form = useForm({ poliklinik_id: visit.clinicId, dokter_id: visit.doctorId, asuransi_id: visit.insuranceId, jenis_bayar: visit.paymentType, catatan: visit.notes });
    const [doctors, setDoctors] = useState<Doctor[]>(visit.doctor ? [visit.doctor] : []);
    const [loadingDoctors, setLoadingDoctors] = useState(false);
    const [doctorLoadError, setDoctorLoadError] = useState<string | null>(null);

    useEffect(() => {
        if (!form.data.poliklinik_id) {
            setDoctors([]);
            return;
        }

        const controller = new AbortController();
        const params = new URLSearchParams({ poliklinik_id: form.data.poliklinik_id, hari: day });
        setLoadingDoctors(true);
        setDoctorLoadError(null);

        fetch(`${doctorsUrl}?${params.toString()}`, { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
            .then(async (response) => {
                if (!response.ok) throw new Error('Daftar dokter belum dapat dimuat.');
                return response.json() as Promise<Doctor[]>;
            })
            .then((availableDoctors) => {
                const options = [...availableDoctors];
                if (visit.doctor && !options.some((doctor) => doctor.id === visit.doctor?.id)) options.unshift(visit.doctor);
                setDoctors(options);
            })
            .catch((error: Error) => {
                if (error.name !== 'AbortError') setDoctorLoadError(error.message);
            })
            .finally(() => {
                if (!controller.signal.aborted) setLoadingDoctors(false);
            });

        return () => controller.abort();
    }, [day, doctorsUrl, form.data.poliklinik_id, visit.doctor]);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(updateUrl, { preserveScroll: true });
    }

    return <><Head title="Edit Kunjungan" /><div className="mx-auto max-w-4xl space-y-5"><Button asChild size="sm" variant="ghost"><Link href={cancelUrl}><ArrowLeft className="size-4" />Kembali ke laporan kunjungan</Link></Button><div><p className="text-sm font-medium text-neutral-700">Pendaftaran</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ubah data kunjungan</h2><p className="mt-1 text-sm text-neutral-500">Pembaruan poli dan dokter akan diperiksa terhadap jadwal praktik.</p></div>
        <Card><CardHeader><CardTitle>Informasi pasien</CardTitle><CardDescription>Identitas pasien hanya ditampilkan pada halaman ini.</CardDescription></CardHeader><CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{[['Nama', visit.patient.name], ['No. rekam medis', visit.patient.medicalRecordNumber], ['Jenis kelamin', visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : '—'], ['Umur', visit.patient.age === null ? '—' : `${visit.patient.age} tahun`]].map(([label, value]) => <div key={label}><p className="text-xs font-medium text-neutral-500">{label}</p><p className="mt-1 text-sm font-semibold text-neutral-900">{value}</p></div>)}</CardContent></Card>
        <Card><CardHeader><CardTitle>Data kunjungan</CardTitle><CardDescription>Pilih poli tujuan dan dokter sesuai jadwal pada hari {day}.</CardDescription></CardHeader><CardContent><form className="space-y-5" onSubmit={submit}><div className="grid gap-4 md:grid-cols-2"><Field error={form.errors.poliklinik_id} htmlFor="visit-clinic" label="Poliklinik tujuan" required><Select id="visit-clinic" onChange={(event) => { form.setData((data) => ({ ...data, poliklinik_id: event.target.value, dokter_id: '' })); }} required value={form.data.poliklinik_id}><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field><Field error={form.errors.dokter_id || doctorLoadError || undefined} htmlFor="visit-doctor" label="Dokter"><Select disabled={loadingDoctors || !form.data.poliklinik_id} id="visit-doctor" onChange={(event) => form.setData('dokter_id', event.target.value)} value={form.data.dokter_id}><option value="">Belum ditentukan</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</Select>{loadingDoctors && <p className="text-xs text-neutral-500">Memuat dokter sesuai jadwal…</p>}{!loadingDoctors && !doctorLoadError && form.data.poliklinik_id && doctors.length === 0 && <p className="text-xs text-amber-700">Belum ada dokter aktif dengan jadwal pada hari ini.</p>}</Field><Field error={form.errors.asuransi_id} htmlFor="visit-insurance" label="Asuransi / penjamin"><Select id="visit-insurance" onChange={(event) => form.setData('asuransi_id', event.target.value)} value={form.data.asuransi_id}><option value="">Umum (bayar sendiri)</option>{insuranceProviders.map((insurance) => <option key={insurance.id} value={insurance.id}>{insurance.name}</option>)}</Select></Field><Field error={form.errors.jenis_bayar} htmlFor="visit-payment" label="Cara bayar" required><Select id="visit-payment" onChange={(event) => form.setData('jenis_bayar', event.target.value)} required value={form.data.jenis_bayar}><option value="umum">Umum</option><option value="bpjs">BPJS</option><option value="asuransi">Asuransi</option></Select></Field></div><Field error={form.errors.catatan} htmlFor="visit-note" label="Catatan"><Textarea id="visit-note" maxLength={2000} onChange={(event) => form.setData('catatan', event.target.value)} rows={4} value={form.data.catatan} /></Field><div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button asChild variant="secondary"><Link href={cancelUrl}>Batal</Link></Button><Button disabled={form.processing || loadingDoctors} type="submit"><Save className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan perubahan'}</Button></div></form></CardContent></Card>
    </div></>;
}
