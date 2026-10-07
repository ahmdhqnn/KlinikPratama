import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CalendarPlus, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { VisitFields, type VisitFormData } from '@/components/kunjungan/visit-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Option {
    id: number;
    name: string;
    type?: string;
}

interface SelectedPatient extends Option {
    medicalRecordNumber?: string;
}

interface Visit {
    id: number;
    patient: SelectedPatient;
    clinicId: number | null;
    doctorId: number | null;
    date: string;
    patientType: string;
    paymentType: string;
    insuranceId: number | null;
    notes: string | null;
}

interface Props {
    patient?: SelectedPatient | null;
    visit?: Visit;
    patients?: (Option & { medicalRecordNumber: string })[];
    clinics: Option[];
    doctors: Option[];
    insuranceProviders: Option[];
    today: string;
}

export default function VisitForm({ patient, visit, patients, clinics, doctors, insuranceProviders, today }: Props) {
    const form = useForm<VisitFormData>({
        pasien_id: (patient?.id ?? visit?.patient.id)?.toString() ?? '',
        poliklinik_id: visit?.clinicId?.toString() ?? '',
        dokter_id: visit?.doctorId?.toString() ?? '',
        tanggal: visit?.date ?? today,
        jenis_pasien: visit?.patientType ?? (patient ? 'lama' : 'baru'),
        jenis_bayar: visit?.paymentType ?? 'umum',
        asuransi_id: visit?.insuranceId?.toString() ?? '',
        catatan: visit?.notes ?? '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (visit) {
            form.put(`/pelayanan/kunjungan/${visit.id}`);
        } else {
            form.post('/pelayanan/kunjungan');
        }
    }

    return (
        <>
            <Head title={visit ? `Edit kunjungan ${visit.patient.name}` : 'Buka kunjungan'} />
            <form className="mx-auto max-w-5xl space-y-6" onSubmit={submit}>
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-neutral-700">Layanan pasien</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{visit ? 'Edit data kunjungan' : 'Buka kunjungan baru'}</h2><p className="mt-1 text-sm text-neutral-500">Lengkapi pasien, tujuan layanan, dan metode pembayaran.</p></div><Button asChild variant="secondary"><Link href={visit ? `/pelayanan/kunjungan/${visit.id}` : '/pelayanan/kunjungan'}><ArrowLeft className="size-4" />Kembali</Link></Button></div>
                <Card><CardHeader><div className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><CalendarPlus className="size-5" /></span><div><CardTitle>Informasi kunjungan</CardTitle><CardDescription className="mt-1">Nomor kunjungan akan dibuat otomatis setelah disimpan.</CardDescription></div></div></CardHeader><CardContent className="space-y-6"><VisitFields data={form.data} errors={form.errors} clinics={clinics} doctors={doctors} insuranceProviders={insuranceProviders} onChange={(field, value) => form.setData(field, value)} patients={patients} selectedPatient={!patients ? patient ?? visit?.patient : undefined} /><div className="flex flex-col-reverse justify-end gap-3 border-t border-neutral-100 pt-5 sm:flex-row"><Button asChild variant="secondary"><Link href={visit ? `/pelayanan/kunjungan/${visit.id}` : '/pelayanan/kunjungan'}>Batalkan</Link></Button><Button disabled={form.processing} type="submit"><Save className="size-4" />{form.processing ? 'Menyimpan…' : visit ? 'Simpan perubahan' : 'Daftarkan kunjungan'}</Button></div></CardContent></Card>
            </form>
        </>
    );
}
