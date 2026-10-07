import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, UserRoundPlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { PatientFields, type PatientFormData } from '@/components/pasien/patient-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Patient {
    id: number;
    name: string;
    nik: string | null;
    birthDate: string | null;
    gender: string | null;
    bloodType: string | null;
    religion: string | null;
    phone: string | null;
    occupation: string | null;
    address: string | null;
    insuranceId: number | null;
    insuranceNumber: string | null;
    allergies: string | null;
}

interface InsuranceProvider {
    id: number;
    name: string;
    type: string;
}

interface Props {
    patient?: Patient;
    insuranceProviders: InsuranceProvider[];
}

export default function PatientForm({ patient, insuranceProviders }: Props) {
    const form = useForm<PatientFormData>({
        nama: patient?.name ?? '',
        nik: patient?.nik ?? '',
        tanggal_lahir: patient?.birthDate ?? '',
        jenis_kelamin: patient?.gender ?? '',
        golongan_darah: patient?.bloodType ?? '',
        agama: patient?.religion ?? '',
        telepon: patient?.phone ?? '',
        pekerjaan: patient?.occupation ?? '',
        alamat: patient?.address ?? '',
        asuransi_id: patient?.insuranceId?.toString() ?? '',
        no_asuransi: patient?.insuranceNumber ?? '',
        riwayat_alergi: patient?.allergies ?? '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (patient) {
            form.put(`/pelayanan/pasien/${patient.id}`);
        } else {
            form.post('/pelayanan/pasien');
        }
    }

    return (
        <>
            <Head title={patient ? `Edit ${patient.name}` : 'Daftarkan pasien baru'} />
            <form className="mx-auto max-w-5xl space-y-6" onSubmit={submit}>
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div><p className="text-sm font-medium text-neutral-700">Data induk pasien</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{patient ? 'Perbarui data pasien' : 'Daftarkan pasien baru'}</h2><p className="mt-1 text-sm text-neutral-500">Data tersimpan akan menjadi bagian dari rekam medis klinik.</p></div>
                    <Button asChild variant="secondary"><Link href={patient ? `/pelayanan/pasien/${patient.id}` : '/pelayanan/pasien'}><ArrowLeft className="size-4" />Kembali</Link></Button>
                </div>
                <Card>
                    <CardHeader><div className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UserRoundPlus className="size-5" /></span><div><CardTitle>Informasi pasien</CardTitle><CardDescription className="mt-1">Kolom bertanda bintang wajib diisi.</CardDescription></div></div></CardHeader>
                    <CardContent className="space-y-7">
                        <PatientFields data={form.data} errors={form.errors} insuranceProviders={insuranceProviders} onChange={(field, value) => form.setData(field, value)} />
                        <div className="flex flex-col-reverse justify-end gap-3 border-t border-neutral-100 pt-5 sm:flex-row">
                            <Button asChild variant="secondary"><Link href={patient ? `/pelayanan/pasien/${patient.id}` : '/pelayanan/pasien'}>Batalkan</Link></Button>
                            <Button disabled={form.processing} type="submit"><Save className="size-4" />{form.processing ? 'Menyimpan…' : patient ? 'Simpan perubahan' : 'Daftarkan pasien'}</Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </>
    );
}
