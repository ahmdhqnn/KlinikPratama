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
    age: number;
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

export default function VisitDetail({ visit }: { visit: Visit }) {
    const nextStep = visit.status === 'menunggu' || visit.status === 'screening'
        ? { label: 'Proses skrining', href: `/pelayanan/screening/${visit.id}`, icon: HeartPulse }
        : visit.status === 'pemeriksaan'
            ? { label: 'Lanjut pemeriksaan', href: `/pelayanan/pemeriksaan/${visit.id}`, icon: ClipboardPlus }
            : visit.status === 'farmasi'
                ? { label: 'Proses farmasi', href: `/pelayanan/farmasi/${visit.id}`, icon: ClipboardPlus }
                : visit.status === 'kasir'
                    ? { label: 'Proses pembayaran', href: `/pelayanan/kasir/${visit.id}`, icon: ClipboardPlus }
                    : null;

    return (
        <>
            <Head title={`Kunjungan ${visit.number}`} />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-blue-700">Detail layanan</p><h2 className="mt-1 font-mono text-2xl font-semibold tracking-tight text-slate-950">{visit.number}</h2><p className="mt-1 text-sm text-slate-500">{visit.date ?? 'Tanggal tidak tersedia'}</p></div><div className="flex flex-wrap gap-2"><Button asChild variant="secondary"><Link href="/pelayanan/kunjungan"><ArrowLeft className="size-4" />Kembali</Link></Button><Button asChild variant="secondary"><Link href={`/pelayanan/kunjungan/${visit.id}/edit`}><Pencil className="size-4" />Edit</Link></Button>{nextStep && <Button asChild><Link href={nextStep.href}><nextStep.icon className="size-4" />{nextStep.label}</Link></Button>}{visit.status === 'selesai' && visit.billId && <Button asChild><a href={`/pelayanan/kasir/${visit.billId}/kuitansi`}>Cetak kuitansi</a></Button>}</div></div>
                <div className="grid gap-6 lg:grid-cols-3">
                    <Card><CardHeader><CardTitle>Informasi kunjungan</CardTitle></CardHeader><CardContent className="space-y-4"><Info label="Status"><StatusBadge status={visit.status} /></Info><Info label="Poliklinik" value={visit.clinic} /><Info label="Dokter" value={visit.doctor ?? 'Belum ditentukan'} /><Info label="Jenis pasien" value={visit.patientType === 'baru' ? 'Pasien baru' : 'Pasien lama'} /><Info label="Cara bayar" value={visit.paymentType.toUpperCase()} />{visit.notes && <Info label="Catatan" value={visit.notes} />}</CardContent></Card>
                    <Card><CardHeader><div className="flex items-center gap-3"><span className="flex size-9 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><UserRound className="size-5" /></span><div><CardTitle>Identitas pasien</CardTitle><CardDescription className="mt-1">{visit.patient.medicalRecordNumber}</CardDescription></div></div></CardHeader><CardContent className="space-y-4"><Info label="Nama pasien"><Link className="font-semibold text-blue-700 hover:underline" href={`/pelayanan/pasien/${visit.patient.id}`}>{visit.patient.name}</Link></Info><Info label="Jenis kelamin / umur" value={`${visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : '—'}, ${visit.patient.age} tahun`} /><Info label="Golongan darah" value={visit.patient.bloodType} /><Link className="text-sm font-medium text-violet-700 hover:underline" href={`/pelayanan/pasien/${visit.patient.id}/rekam-medis`}>Lihat rekam medis lengkap →</Link>{visit.patient.allergies && <div className="rounded-xl border border-red-200 bg-red-50 p-3"><p className="text-xs font-semibold uppercase text-red-700">Riwayat alergi</p><p className="mt-1 text-sm text-red-900">{visit.patient.allergies}</p></div>}</CardContent></Card>
                    <Card><CardHeader><CardTitle>Progres pelayanan</CardTitle><CardDescription>Tahapan layanan pasien.</CardDescription></CardHeader><CardContent className="space-y-4">{visit.progress.map((step, index) => <div className="flex items-center gap-3" key={step.label}><span className={`flex size-7 items-center justify-center rounded-full ${step.done ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-400'}`}>{step.done ? <Check className="size-4" /> : <span className="text-xs font-semibold">{index + 1}</span>}</span><span className={`text-sm ${step.done ? 'font-medium text-slate-900' : 'text-slate-400'}`}>{step.label}</span></div>)}</CardContent></Card>
                </div>
            </div>
        </>
    );
}

function Info({ label, value, children }: { label: string; value?: string | null; children?: React.ReactNode }) {
    return <div><p className="text-xs font-medium text-slate-500">{label}</p><div className="mt-1 text-sm text-slate-900">{children ?? value ?? '—'}</div></div>;
}
