import { AllergyHistory } from '@/components/kunjungan/allergy-history';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CalendarPlus, HeartPulse, Pencil, UserRound } from 'lucide-react';
import { Pagination } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Visit {
    id: number;
    url: string;
    number: string;
    date: string | null;
    clinic: string;
    doctor: string;
    status: string;
    examinationStarted?: boolean;
    isReservation?: boolean;
}

interface Patient {
    id: number;
    medicalRecordNumber: string;
    name: string;
    nik: string | null;
    memberNip: string | null;
    unit: string | null;
    birthPlace: string | null;
    birthDate: string | null;
    gender: string | null;
    age: number | null;
    bloodType: string | null;
    religion: string | null;
    phone: string | null;
    address: string | null;
    rt: string | null;
    rw: string | null;
    village: string | null;
    district: string | null;
    membership: string;
    allergies: string | null;
    visits: Visit[];
};

export default function PatientDetail({ patient, permissions, backUrl, openVisitUrl }: { backUrl: string; openVisitUrl: string; patient: Patient; permissions: { viewRme: boolean; editPatient: boolean; openVisit: boolean } }) {
    return (
        <>
            <Head title={`Profil ${patient.name}`} />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div><p className="text-sm font-medium text-neutral-700">Profil pasien</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{patient.name}</h2><p className="mt-1 font-mono text-sm text-neutral-500">{patient.medicalRecordNumber}</p></div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="secondary"><Link href={backUrl}><ArrowLeft className="size-4" />Kembali</Link></Button>
                        {permissions.editPatient && <Button asChild variant="secondary"><Link href={`/pelayanan/pasien/${patient.id}/edit`}><Pencil className="size-4" />Edit data</Link></Button>}
                        {permissions.viewRme && <Button asChild variant="secondary"><Link href={`/pelayanan/pasien/${patient.id}/rekam-medis`}><HeartPulse className="size-4" />Rekam medis lengkap</Link></Button>}
                        {permissions.openVisit && <Button asChild><Link href={openVisitUrl}><CalendarPlus className="size-4" />Buka kunjungan</Link></Button>}
                    </div>
                </div>
                <div className="grid gap-6 lg:grid-cols-[minmax(18rem,0.8fr)_minmax(0,1.7fr)]">
                    <Card>
                        <CardHeader><div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-neutral-100 text-neutral-700"><UserRound className="size-8" /></div><CardTitle className="text-center">Informasi pasien</CardTitle><CardDescription className="text-center">{patient.gender === 'L' ? 'Laki-laki' : patient.gender === 'P' ? 'Perempuan' : 'Jenis kelamin belum diisi'} · {patient.age === null ? 'umur belum diketahui' : `${patient.age} tahun`}</CardDescription></CardHeader>
                        <CardContent className="space-y-4">
                            <Info label="NIK" value={patient.nik} />
                            <Info label="NIP" value={patient.memberNip} />
                            <Info label="Unit kerja" value={patient.unit} />
                            <Info label="Tempat, tanggal lahir" value={[patient.birthPlace, patient.birthDate].filter(Boolean).join(', ')} />
                            <Info label="Agama" value={patient.religion} />
                            <Info label="Golongan darah" value={patient.bloodType} />
                            <Info label="Nomor telepon" value={patient.phone} />
                            <Info label="Alamat" value={patient.address} />
                            <Info label="Kategori kepesertaan" value={patient.membership} />
                            <AllergyHistory value={patient.allergies} />
                        </CardContent>
                    </Card>
                    <Card className="overflow-hidden">
                        <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><CalendarPlus className="size-5" /></span><div><CardTitle>Riwayat kunjungan</CardTitle><CardDescription className="mt-1">Menampilkan hingga 20 kunjungan terakhir pasien.</CardDescription></div></div></CardHeader>
                        <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[720px]"><TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Poli</TableHead><TableHead>Dokter</TableHead><TableHead>Status</TableHead></tr></TableHeader><TableBody>
                            {patient.visits.length ? patient.visits.map((visit) => <TableRow key={visit.id}><TableCell><Link className="font-mono text-xs font-semibold text-neutral-700 hover:underline" href={visit.url}>{visit.number}</Link></TableCell><TableCell className="whitespace-nowrap text-neutral-600">{visit.date ?? 'Belum diisi'}</TableCell><TableCell>{visit.clinic}</TableCell><TableCell>{visit.doctor}</TableCell><TableCell><StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} status={visit.status} /></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Belum ada riwayat kunjungan." /></TableCell></TableRow>}
                        </TableBody></Table></div></CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Info({ label, value }: { label: string; value: string | null }) {
    return <div><p className="text-xs font-medium text-neutral-500">{label}</p><p className="mt-1 whitespace-pre-wrap text-sm text-neutral-900">{value || 'Belum diisi'}</p></div>;
}
