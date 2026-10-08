import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, ClipboardPlus, HeartPulse, Pencil, UserRound } from 'lucide-react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Patient {
    id: number | null;
    name: string;
    medicalRecordNumber: string;
    gender: string | null;
    age: number | null;
    bloodType: string | null;
    allergies: string | null;
}

interface Visit {
    id: number;
    number: string;
    date: string | null;
    status: string;
    clinic: string;
    doctor: string | null;
    paymentType: string;
    patientType: string;
    notes: string | null;
    patient: Patient;
    progress: { label: string; done: boolean }[];
    billId: number | null;
}

export default function VisitDetail({ visit, permissions }: { visit: Visit; permissions: { editVisit: boolean; viewRme: boolean; processScreening: boolean; processCashier: boolean } }) {
    const nextStep = permissions.processScreening && (visit.status === 'menunggu' || visit.status === 'screening')
        ? { label: 'Proses skrining', href: `/pelayanan/screening/${visit.id}`, icon: HeartPulse }
        : permissions.processCashier && visit.status === 'kasir'
                    ? { label: 'Proses pembayaran', href: `/pelayanan/kasir/${visit.id}`, icon: ClipboardPlus }
                    : null;

    return (
        <>
            <Head title={`Kunjungan ${visit.number}`} />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-neutral-700">Detail layanan</p><h2 className="mt-1 font-mono text-2xl font-semibold tracking-tight text-neutral-950">{visit.number}</h2><p className="mt-1 text-sm text-neutral-500">{visit.date ?? 'Tanggal tidak tersedia'}</p></div><div className="flex flex-wrap gap-2"><Button asChild variant="secondary"><Link href="/pelayanan/kunjungan"><ArrowLeft className="size-4" />Kembali</Link></Button>{permissions.editVisit && <Button asChild variant="secondary"><Link href={`/pelayanan/kunjungan/${visit.id}/edit`}><Pencil className="size-4" />Edit</Link></Button>}{nextStep && <Button asChild><Link href={nextStep.href}><nextStep.icon className="size-4" />{nextStep.label}</Link></Button>}{permissions.processCashier && visit.status === 'selesai' && visit.billId && <Button asChild><a href={`/pelayanan/kasir/${visit.billId}/kuitansi`}>Cetak kuitansi</a></Button>}</div></div>
                <div className="grid gap-6 lg:grid-cols-3">
                    <Card><CardHeader><CardTitle>Informasi kunjungan</CardTitle></CardHeader><CardContent className="space-y-4"><Info label="Status"><StatusBadge status={visit.status} /></Info><Info label="Poliklinik" value={visit.clinic} /><Info label="Dokter" value={visit.doctor ?? 'Belum ditentukan'} /><Info label="Jenis pasien" value={visit.patientType === 'baru' ? 'Pasien baru' : 'Pasien lama'} /><Info label="Cara bayar" value={visit.paymentType.toUpperCase()} />{visit.notes && <Info label="Catatan" value={visit.notes} />}</CardContent></Card>
                    <Card><CardHeader><div className="flex items-center gap-3"><span className="flex size-9 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UserRound className="size-5" /></span><div><CardTitle>Identitas pasien</CardTitle><CardDescription className="mt-1">{visit.patient.medicalRecordNumber}</CardDescription></div></div></CardHeader><CardContent className="space-y-4"><Info label="Nama pasien"><Link className="font-semibold text-neutral-700 hover:underline" href={`/pelayanan/pasien/${visit.patient.id}`}>{visit.patient.name}</Link></Info><Info label="Jenis kelamin / umur" value={`${visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : '—'}, ${visit.patient.age === null ? 'umur belum diketahui' : `${visit.patient.age} tahun`}`} /><Info label="Golongan darah" value={visit.patient.bloodType} />{permissions.viewRme && <Link className="text-sm font-medium text-neutral-700 hover:underline" href={`/pelayanan/pasien/${visit.patient.id}/rekam-medis`}>Lihat rekam medis lengkap →</Link>}{visit.patient.allergies && <div className="rounded-xl border border-red-200 bg-red-50 p-3"><p className="text-xs font-semibold uppercase tracking-wide text-red-700">Riwayat alergi</p><p className="mt-1 text-sm text-red-900">{visit.patient.allergies}</p></div>}</CardContent></Card>
                    <Card><CardHeader><CardTitle>Progres pelayanan</CardTitle><CardDescription>Tahapan layanan pasien.</CardDescription></CardHeader><CardContent className="space-y-4">{visit.progress.map((step, index) => <div className="flex items-center gap-3" key={step.label}><span className={`flex size-7 items-center justify-center rounded-full ${step.done ? 'bg-emerald-100 text-emerald-700' : 'bg-neutral-100 text-neutral-400'}`}>{step.done ? <Check className="size-4" /> : <span className="text-xs font-semibold">{index + 1}</span>}</span><span className={`text-sm ${step.done ? 'font-medium text-neutral-900' : 'text-neutral-400'}`}>{step.label}</span></div>)}</CardContent></Card>
                </div>
            </div>
        </>
    );
}

function Info({ label, value, children }: { label: string; value?: string | null; children?: React.ReactNode }) {
    return <div><p className="text-xs font-medium text-neutral-500">{label}</p><div className="mt-1 text-sm text-neutral-900">{children ?? value ?? '—'}</div></div>;
}
