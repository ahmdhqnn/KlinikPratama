import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, Play, Printer, Save, Syringe, UserRound } from 'lucide-react';
import { type FormEvent } from 'react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { AllergyHistory } from '@/components/kunjungan/allergy-history';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';

interface Treatment {
    id: number;
    name: string;
    code: string | null;
    quantity: number;
    toothFdi: string | null;
    instructions: string | null;
    status: string;
    result: string | null;
    performedBy: string | null;
    performedAt: string | null;
    completeUrl: string;
}

interface Props {
    visit: {
        id: number;
        number: string;
        patient: string;
        medicalRecordNumber: string;
        clinic: string;
        doctor: string | null;
        status: string;
        treatmentStartedAt: string | null;
        startUrl: string;
        allergies: string | null;
        education: string | null;
        nonPharmacological: string | null;
        diagnoses: string[];
        hasMedication: boolean;
        recordUrl: string;
        treatments: Treatment[];
    };
}

export default function NursingProcedureDetail({ visit }: Props) {
    const startForm = useForm({});
    const canStart = visit.status === 'tindakan' && !visit.treatmentStartedAt && visit.treatments.some((item) => item.status === 'pending');
    const canComplete = visit.status === 'tindakan' && Boolean(visit.treatmentStartedAt);

    function start() {
        confirmAction('Mulai pelaksanaan tindakan perawat sesuai instruksi dokter?', () => startForm.post(visit.startUrl, { preserveScroll: true }), 'Mulai tindakan', 'Mulai tindakan perawat');
    }

    return <>
        <Head title="Pelaksanaan Tindakan" />
        <div className="mx-auto max-w-5xl space-y-6">
            <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div><p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pelaksanaan tindakan</h2><p className="mt-1 text-sm text-neutral-500">Tinjau instruksi dokter, mulai tindakan, lalu catat hasil setiap tindakan.</p></div>
                <div className="flex flex-wrap gap-2"><Button asChild variant="secondary"><Link href="/pelayanan/tindakan-perawat"><ArrowLeft className="size-4" />Kembali</Link></Button><Button asChild variant="secondary"><a href={`/perawat/laporan-tindakan/cetak?search=${encodeURIComponent(visit.number)}`} target="_blank" rel="noreferrer"><Printer className="size-4" />Cetak tindakan</a></Button></div>
            </div>
            <Card className="overflow-hidden"><CardContent className="p-0 sm:p-0">
                <div className="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6"><div className="flex min-w-0 items-center gap-3 sm:gap-4"><span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-600"><UserRound aria-hidden="true" className="size-6" /></span><div className="min-w-0"><p className="text-xs font-medium text-neutral-500">Pasien</p><h3 className="mt-1 break-words text-lg font-semibold text-neutral-950">{visit.patient}</h3><p className="mt-1 text-sm text-neutral-600">No. RM {visit.medicalRecordNumber} · {visit.number}</p></div></div><div className="shrink-0 self-start sm:self-center"><StatusBadge context="nurse" status={visit.status} treatmentStarted={Boolean(visit.treatmentStartedAt)} /></div></div>
                <dl className="grid gap-4 border-t border-neutral-100 bg-neutral-50/60 px-5 py-4 sm:grid-cols-3 sm:px-6"><div><dt className="text-xs text-neutral-500">Poliklinik</dt><dd className="mt-1 text-sm font-medium text-neutral-900">{visit.clinic}</dd></div><div><dt className="text-xs text-neutral-500">Dokter</dt><dd className="mt-1 text-sm font-medium text-neutral-900">{visit.doctor || 'Belum dicatat'}</dd></div><div><dt className="text-xs text-neutral-500">Mulai tindakan</dt><dd className="mt-1 text-sm font-medium text-neutral-900">{visit.treatmentStartedAt || 'Belum dimulai'}</dd></div></dl>
            </CardContent></Card>
            <Card><CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardCheck className="size-5" /></span><div><CardTitle>Instruksi dokter</CardTitle><CardDescription className="mt-1">{visit.diagnoses.length ? visit.diagnoses.join(', ') : 'Diagnosis belum dicatat'}</CardDescription></div></div></CardHeader><CardContent className="space-y-4"><AllergyHistory value={visit.allergies} unknownLabel="Belum diketahui; konfirmasi bersama pasien sebelum tindakan" /><div className="grid gap-4 md:grid-cols-2"><div><p className="text-sm font-medium">Perawatan tanpa obat</p><p className="whitespace-pre-wrap text-sm text-muted-foreground">{visit.nonPharmacological || 'Belum ada catatan'}</p></div><div><p className="text-sm font-medium">Edukasi dan anjuran</p><p className="whitespace-pre-wrap text-sm text-muted-foreground">{visit.education || 'Belum ada catatan'}</p></div></div><p className="text-sm text-muted-foreground">{visit.status === 'tindakan' ? (visit.hasMedication ? 'Setelah semua tindakan selesai, pasien melanjutkan ke farmasi.' : 'Setelah semua tindakan selesai, kunjungan berakhir dan pasien dapat pulang.') : visit.status === 'farmasi' ? 'Tindakan selesai. Pasien sedang menunggu pelayanan farmasi.' : 'Kunjungan telah selesai.'}</p></CardContent></Card>
            <section id="pelaksanaan-tindakan" className="space-y-4 scroll-mt-6" aria-label="Pelaksanaan tindakan perawat"><div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h3 className="text-lg font-semibold text-neutral-950">Tindakan perawat</h3><p className="mt-1 text-sm text-neutral-500">Catat hasil tiap tindakan setelah dilaksanakan.</p></div>{canStart && <Button className="w-full sm:w-auto" disabled={startForm.processing} onClick={start} type="button"><Play className="size-4" />{startForm.processing ? 'Memulai…' : 'Mulai tindakan'}</Button>}</div>{visit.treatments.length ? visit.treatments.map((item) => <TreatmentForm item={item} key={item.id} canComplete={canComplete} />) : <Empty title="Tidak ada tindakan perawat" />}</section>
            <div className="flex justify-end border-t border-neutral-100 pt-5"><Button asChild variant="secondary"><Link href={visit.recordUrl}>Lihat rekam medis</Link></Button></div>
        </div>
    </>;
}

function TreatmentForm({ item, canComplete }: { item: Treatment; canComplete: boolean }) {
    const form = useForm({ execution_result: '' });
    const stockError = (form.errors as Record<string, string>).items;
    const completed = item.status === 'completed';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        confirmAction(`Pastikan ${item.name} telah dilaksanakan dan hasilnya sesuai. Catatan yang disimpan tidak dapat ditimpa.`, () => form.post(item.completeUrl, { preserveScroll: true }), 'Simpan pelaksanaan', 'Konfirmasi pelaksanaan tindakan');
    }

    return (
        <Card>
            <CardHeader>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Syringe className="size-5" /></span>
                        <CardTitle className="break-words">{item.name}</CardTitle>
                    </div>
                    <Badge variant={completed ? 'complete' : 'waiting'}>{completed ? 'Sudah dilaksanakan' : 'Menunggu pelaksanaan'}</Badge>
                </div>
                <CardDescription>{item.code || 'Tanpa kode'} · {item.quantity} kali{item.toothFdi ? ` · Gigi ${item.toothFdi}` : ''}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                <p className="whitespace-pre-wrap text-sm text-neutral-700">{item.instructions || 'Tidak ada instruksi tambahan.'}</p>
                {completed ? (
                    <div className="space-y-2 rounded-xl border border-neutral-200 p-4">
                        <p className="whitespace-pre-wrap text-sm text-neutral-900">{item.result}</p>
                        <p className="text-xs text-neutral-500">Dicatat oleh {item.performedBy} · {item.performedAt}</p>
                    </div>
                ) : canComplete ? (
                    <form className="space-y-5" onSubmit={submit}>
                        {stockError && <Alert variant="destructive"><AlertTitle>Pelaksanaan belum tersimpan</AlertTitle><AlertDescription>{stockError}</AlertDescription></Alert>}
                        <Field htmlFor={`result-${item.id}`} label="Hasil pelaksanaan dan respons pasien" error={form.errors.execution_result} required>
                            <Textarea id={`result-${item.id}`} maxLength={5000} minLength={3} required disabled={form.processing} value={form.data.execution_result} onChange={(event) => form.setData('execution_result', event.target.value)} placeholder="Catat tindakan yang benar-benar dilakukan, hasil, dan respons pasien." />
                        </Field>
                        <div className="flex justify-end border-t border-neutral-100 pt-4">
                            <Button type="submit" disabled={form.processing || form.data.execution_result.trim().length < 3}>
                                <Save className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan pelaksanaan tindakan'}
                            </Button>
                        </div>
                    </form>
                ) : <p className="text-sm text-neutral-500">Mulai tindakan untuk membuka pencatatan hasil.</p>}
            </CardContent>
        </Card>
    );
}
