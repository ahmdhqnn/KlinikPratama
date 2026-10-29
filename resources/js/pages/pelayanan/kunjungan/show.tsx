import { AllergyHistory } from '@/components/kunjungan/allergy-history';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, ClipboardList, HeartPulse, ListChecks, Pencil, UserRound } from 'lucide-react';
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
    examinationStarted?: boolean;
    isReservation?: boolean;
    clinic: string;
    doctor: string | null;
    eligibility: { unit_kerja?: string | null; kategori?: string | null; referensi_bukti?: string | null; status?: string } | null;
    verifiedAt: string | null;
    patientType: string;
    notes: string | null;
    patient: Patient;
    progress: { label: string; done: boolean; active?: boolean; note?: string | null }[];
}

interface VisitLinks {
    edit: string | null;
    patient: string | null;
    screening: string | null;
    medicalRecord: string | null;
    screeningPrint?: string | null;
}

export default function VisitDetail({ visit, permissions, backUrl, links }: { visit: Visit; permissions: { editVisit: boolean; viewRme: boolean; processScreening: boolean }; backUrl: string; links: VisitLinks }) {
    const nextStep = permissions.processScreening && links.screening
        ? { label: visit.status === 'screening' ? 'Lanjutkan pengkajian awal' : 'Mulai pengkajian awal', href: links.screening, icon: HeartPulse }
        : null;

    return (
        <>
            <Head title={`Kunjungan ${visit.number}`} />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-neutral-700">Detail layanan</p><h2 className="mt-1 font-mono text-2xl font-semibold tracking-tight text-neutral-950">{visit.number}</h2><p className="mt-1 text-sm text-neutral-500">{visit.date ?? 'Tanggal tidak tersedia'}</p></div><div className="flex flex-wrap gap-2"><Button asChild variant="secondary"><Link href={backUrl}><ArrowLeft className="size-4" />Kembali</Link></Button>{permissions.editVisit && links.edit && <Button asChild variant="secondary"><Link href={links.edit}><Pencil className="size-4" />Edit</Link></Button>}{links.screeningPrint && <Button asChild variant="secondary"><a href={links.screeningPrint}>Cetak pengkajian</a></Button>}{nextStep && <Button asChild><Link href={nextStep.href}><nextStep.icon className="size-4" />{nextStep.label}</Link></Button>}</div></div>
                <div className="grid min-w-0 items-stretch gap-4 lg:grid-cols-2">
                    <Card className="flex min-w-0 flex-col overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-center gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ClipboardList className="size-5" /></span><div className="min-w-0"><CardTitle>Informasi kunjungan</CardTitle><CardDescription className="mt-1">Poli, dokter, dan hak layanan.</CardDescription></div></div></CardHeader><CardContent className="grid flex-1 content-start gap-x-6 gap-y-4 pt-5 sm:grid-cols-2 sm:pt-6"><Info label="Status"><StatusBadge examinationStarted={visit.examinationStarted} reservation={visit.isReservation} status={visit.status} /></Info><Info label="Poliklinik" value={visit.clinic} /><Info label="Dokter" value={visit.doctor ?? 'Belum ditentukan'} /><Info label="Jenis pasien" value={visit.patientType === 'baru' ? 'Pasien baru' : 'Pasien lama'} /><Info label="Verifikasi hak" value={visit.verifiedAt ?? (visit.eligibility?.status === 'pending_verification' ? 'Menunggu verifikasi kepesertaan' : 'Belum diverifikasi')} />{visit.eligibility && <><Info label="Unit kerja" value={visit.eligibility.unit_kerja} /><Info label="Bukti kepesertaan" value={visit.eligibility.referensi_bukti} /></>}{visit.notes && <div className="sm:col-span-2"><Info label="Catatan" value={visit.notes} /></div>}</CardContent></Card>
                    <Card className="flex min-w-0 flex-col overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-center gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UserRound className="size-5" /></span><div className="min-w-0"><CardTitle>Identitas pasien</CardTitle><CardDescription className="mt-1">{visit.patient.medicalRecordNumber}</CardDescription></div></div></CardHeader><CardContent className="flex-1 space-y-4 pt-5 sm:pt-6"><div className="grid gap-x-6 gap-y-4 sm:grid-cols-2"><Info label="Nama pasien">{links.patient ? <Link className="font-semibold text-neutral-700 hover:underline" href={links.patient}>{visit.patient.name}</Link> : visit.patient.name}</Info><Info label="Jenis kelamin / umur" value={`${visit.patient.gender === 'L' ? 'Laki-laki' : visit.patient.gender === 'P' ? 'Perempuan' : 'Belum diisi'}, ${visit.patient.age === null ? 'umur belum diketahui' : `${visit.patient.age} tahun`}`} /><Info label="Golongan darah" value={visit.patient.bloodType} /></div>{permissions.viewRme && links.medicalRecord && <Link className="inline-flex text-sm font-medium text-neutral-700 hover:underline" href={links.medicalRecord}>Lihat rekam medis lengkap →</Link>}{permissions.viewRme && <AllergyHistory value={visit.patient.allergies} />}</CardContent></Card>
                    <Card className="min-w-0 overflow-hidden lg:col-span-2"><CardHeader className="border-b border-neutral-100"><div className="flex items-center gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ListChecks className="size-5" /></span><div className="min-w-0"><CardTitle>Progres pelayanan</CardTitle><CardDescription className="mt-1">Tahapan layanan pasien.</CardDescription></div></div></CardHeader><CardContent className="grid gap-3 pt-5 sm:grid-cols-2 sm:pt-6 xl:grid-cols-3">{visit.progress.map((step, index) => <div className="flex min-w-0 items-start gap-3 rounded-xl border border-neutral-100 bg-neutral-50/50 p-3" key={step.label}><span className={`flex size-7 shrink-0 items-center justify-center rounded-full ${step.done ? 'bg-emerald-100 text-emerald-700' : step.active ? 'bg-blue-100 text-blue-700' : 'bg-neutral-100 text-neutral-400'}`}>{step.done ? <Check className="size-4" /> : <span className="text-xs font-semibold">{index + 1}</span>}</span><div className="min-w-0"><p className={`text-sm ${step.done || step.active ? 'font-medium text-neutral-900' : 'text-neutral-500'}`}>{step.label}</p>{step.note && <p className="mt-1 text-xs leading-5 text-neutral-500">{step.note}</p>}</div></div>)}</CardContent></Card>
                </div>
            </div>
        </>
    );
}

function Info({ label, value, children }: { label: string; value?: string | null; children?: React.ReactNode }) {
    return <div><p className="text-xs font-medium text-neutral-500">{label}</p><div className="mt-1 whitespace-pre-wrap break-words text-sm text-neutral-900 [overflow-wrap:anywhere]">{children ?? value ?? 'Belum diisi'}</div></div>;
}
