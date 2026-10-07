import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarPlus, HeartPulse, Pencil, UserRound } from 'lucide-react';
import { Pagination } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Visit {
    id: number;
    number: string;
    date: string | null;
    clinic: string;
    doctor: string;
    status: string;
    billId: number | null;
}

interface Patient {
    id: number;
    medicalRecordNumber: string;
    name: string;
    nik: string | null;
    birthDate: string | null;
    gender: string | null;
    age: number;
    bloodType: string | null;
    phone: string | null;
    address: string | null;
    insurance: string;
    insuranceType: string | null;
    insuranceNumber: string | null;
    allergies: string | null;
    visits: Visit[];
};

export default function PatientDetail({ patient }: { patient: Patient }) {
    return (
        <>
            <Head title={`Profil ${patient.name}`} />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div><p className="text-sm font-medium text-neutral-700">Profil pasien</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{patient.name}</h2><p className="mt-1 font-mono text-sm text-neutral-500">{patient.medicalRecordNumber}</p></div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="secondary"><Link href="/pelayanan/pasien"><ArrowLeft className="size-4" />Kembali</Link></Button>
                        <Button asChild variant="secondary"><Link href={`/pelayanan/pasien/${patient.id}/edit`}><Pencil className="size-4" />Edit data</Link></Button>
                        <Button asChild variant="secondary"><Link href={`/pelayanan/pasien/${patient.id}/rekam-medis`}><HeartPulse className="size-4" />Rekam medis lengkap</Link></Button>
                        <Button asChild><Link href={`/pelayanan/kunjungan/create?pasien_id=${patient.id}`}><CalendarPlus className="size-4" />Buka kunjungan</Link></Button>
                    </div>
                </div>
                <div className="grid gap-6 lg:grid-cols-[minmax(18rem,0.8fr)_minmax(0,1.7fr)]">
                    <Card>
                        <CardHeader><div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-neutral-50 text-neutral-700"><UserRound className="size-8" /></div><CardTitle className="text-center">Informasi pasien</CardTitle><CardDescription className="text-center">{patient.gender === 'L' ? 'Laki-laki' : patient.gender === 'P' ? 'Perempuan' : 'Jenis kelamin belum diisi'} · {patient.age} tahun</CardDescription></CardHeader>
                        <CardContent className="space-y-4">
                            <Info label="NIK" value={patient.nik} />
                            <Info label="Tanggal lahir" value={patient.birthDate} />
                            <Info label="Golongan darah" value={patient.bloodType} />
                            <Info label="Nomor telepon" value={patient.phone} />
                            <Info label="Alamat" value={patient.address} />
                            <Info label="Penjamin" value={patient.insurance} />
                            {patient.insuranceNumber && <Info label="Nomor kartu penjamin" value={patient.insuranceNumber} />}
                            {patient.allergies && <div className="rounded-xl border border-red-200 bg-red-50 p-4"><p className="text-xs font-semibold uppercase tracking-wide text-red-700">Riwayat alergi</p><p className="mt-1 text-sm font-medium text-red-900">{patient.allergies}</p></div>}
                        </CardContent>
                    </Card>
                    <Card className="overflow-hidden">
                        <CardHeader className="border-b border-neutral-100"><CardTitle>Riwayat kunjungan</CardTitle><CardDescription>Menampilkan hingga 20 kunjungan terakhir pasien.</CardDescription></CardHeader>
                        <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[720px]"><TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Poli</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead></tr></TableHeader><TableBody>
                            {patient.visits.length ? patient.visits.map((visit) => <TableRow key={visit.id}><TableCell><Link className="font-mono text-xs font-semibold text-neutral-700 hover:underline" href={`/pelayanan/kunjungan/${visit.id}`}>{visit.number}</Link></TableCell><TableCell className="whitespace-nowrap text-neutral-600">{visit.date ?? '—'}</TableCell><TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor}</TableCell><TableCell><StatusBadge status={visit.status} /></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-neutral-500" colSpan={5}>Belum ada riwayat kunjungan.</TableCell></TableRow>}
                        </TableBody></Table></div></CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Info({ label, value }: { label: string; value: string | null }) {
    return <div><p className="text-xs font-medium text-neutral-500">{label}</p><p className="mt-1 whitespace-pre-wrap text-sm text-neutral-900">{value || '—'}</p></div>;
}
