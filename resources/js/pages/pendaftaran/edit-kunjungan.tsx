import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import type { FormEvent } from 'react';
import { VisitFields, type VisitFormValues } from '@/components/pendaftaran/visit-fields';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Clinic { id: number; name: string; type: string }
interface Visit {
    id: number;
    patient: { name: string; medicalRecordNumber: string; gender: string | null; age: number | null };
    clinicId: string;
    doctorId: string;
    date: string;
    notes: string | null;
}
interface Props { visit: Visit; clinics: Clinic[]; today: string; doctorsUrl: string; updateUrl: string; backUrl: string }

export default function EditRegistrationVisit({ visit, clinics, today, doctorsUrl, updateUrl, backUrl }: Props) {
    const form = useForm<VisitFormValues>({ poliklinik_id: visit.clinicId, dokter_id: visit.doctorId, catatan: visit.notes ?? '', tanggal: visit.date });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put(updateUrl, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Edit Kunjungan" />
            <div className="mx-auto max-w-4xl space-y-5">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p className="text-sm font-medium text-neutral-700">Pendaftaran</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ubah data kunjungan</h2>
                        <p className="mt-1 text-sm text-neutral-500">Pembaruan poli dan dokter akan diperiksa terhadap jadwal praktik.</p>
                    </div>
                    <Button asChild variant="secondary"><Link href={backUrl}><ArrowLeft className="size-4" />Kembali</Link></Button>
                </div>
                <Card>
                    <CardHeader><CardTitle>Informasi pasien</CardTitle><CardDescription>Identitas pasien untuk kunjungan ini.</CardDescription></CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {[
                            ['Nama', visit.patient.name],
                            ['No. rekam medis', visit.patient.medicalRecordNumber],
                            ['Jenis kelamin', visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : 'Belum diisi'],
                            ['Umur', visit.patient.age === null ? 'Belum diisi' : `${visit.patient.age} tahun`],
                        ].map(([label, value]) => <div key={label}><p className="text-xs font-medium text-neutral-500">{label}</p><p className="mt-1 text-sm font-semibold text-neutral-900">{value}</p></div>)}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Data kunjungan</CardTitle><CardDescription>Pilih tanggal dan dokter yang tersedia untuk poli tujuan.</CardDescription></CardHeader>
                    <CardContent>
                        <form className="space-y-5" onSubmit={submit}>
                            <VisitFields clinics={clinics} doctorsUrl={doctorsUrl} errors={form.errors} today={today} values={form.data} onChange={(fields) => form.setData((data) => ({ ...data, ...fields }))} />
                            <div className="flex flex-col-reverse gap-2 border-t border-neutral-100 pt-4 sm:flex-row sm:justify-end">
                                <Button asChild variant="secondary"><Link href={backUrl}>Batalkan</Link></Button>
                                <Button disabled={form.processing} type="submit"><Save className="size-4" />{form.processing ? 'Menyimpan.' : 'Simpan perubahan'}</Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
