import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Field } from '@/components/ui/field';
import { confirmReason } from '@/components/ui/confirm-dialog';

const descriptions = {
    waiting: 'Menunggu pasien atau wakil membubuhkan tanda tangan.',
    signed: 'Tanda tangan diterima. Perawat yang menyaksikan dapat membuka Saksi persetujuan tindakan di dasbor perawat.',
    complete: 'Konfirmasi saksi tercatat. PDF bukti tersedia.',
    expired: 'Sesi QR sudah berakhir. Dokter dapat membuka sesi baru.',
    cancelled: 'Sesi QR dibatalkan.',
};

export default function ProcedureSignature({ id, procedure, decision, signer, status, patientUrl, qr, code, canWitness, canCancel, backUrl, workflowUrl, evidenceUrl }: {
    id: string; procedure: string; decision: string; signer: string; status: keyof typeof descriptions;
    patientUrl: string | null; qr: string | null; code: string | null; canWitness: boolean; canCancel: boolean;
    backUrl: string; workflowUrl: string; evidenceUrl: string | null;
}) {
    const form = useForm({ witnessed: false });
    const url = `/persetujuan-tindakan/sesi/${id}`;

    useEffect(() => {
        if (status !== 'waiting' && status !== 'signed') return;
        const interval = window.setInterval(() => {
            if (document.visibilityState === 'visible') router.reload({
                only: ['status', 'patientUrl', 'qr', 'code', 'evidenceUrl'],
                preserveErrors: true,
            });
        }, 4000);
        return () => window.clearInterval(interval);
    }, [status]);

    function confirm(event: FormEvent) {
        event.preventDefault();
        form.post(`${url}/saksi`, { preserveScroll: true });
    }

    return <>
        <Head title="Sesi tanda tangan tindakan" />
        <Card className="mx-auto max-w-2xl">
            <CardHeader>
                <CardTitle>Sesi tanda tangan tindakan</CardTitle>
                <CardDescription>{procedure} · {signer} · {{ accepted: 'Setuju', refused: 'Menolak', withdrawn: 'Mencabut' }[decision]}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-5">
                <p role="status" className="text-sm font-medium">{descriptions[status]}</p>
                {qr && <div className="flex flex-col items-start gap-4 rounded-xl border border-neutral-200 p-4 sm:flex-row sm:items-center">
                    <div className="relative w-40 shrink-0 bg-white">
                        <img src={qr} width={160} height={160} alt="QR sesi tanda tangan pasien" />
                        <img src="/logo_kemenpar.webp" alt="" className="absolute top-1/2 left-1/2 size-[12.5%] -translate-x-1/2 -translate-y-1/2 rounded-sm bg-white object-contain" />
                    </div>
                    <div className="space-y-2 text-sm">
                        <p>Pasien memindai QR dan memasukkan kode pendamping berikut.</p>
                        <p className="text-2xl font-semibold tracking-widest tabular-nums">{code}</p>
                        {patientUrl && <a className="underline underline-offset-4" href={patientUrl} target="_blank" rel="noreferrer">Tanda tangan di perangkat ini</a>}
                    </div>
                </div>}
                {canWitness && status === 'signed' && <form className="space-y-3" onSubmit={confirm}>
                    <Field htmlFor="confirm-signature-witness" label="Konfirmasi saksi" error={form.errors.witnessed}>
                        <label className="flex items-start gap-2 text-sm">
                            <Checkbox id="confirm-signature-witness" checked={form.data.witnessed} onCheckedChange={(value) => form.setData('witnessed', value === true)} />
                            <span>Saya hadir, memeriksa identitas pasien atau wakil, dan menyaksikan pengambilan keputusan serta tanda tangan.</span>
                        </label>
                    </Field>
                    <Button type="submit" disabled={!form.data.witnessed || form.processing}>Konfirmasi dan arsipkan PDF</Button>
                </form>}
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="secondary"><Link href={backUrl}>Kembali ke persetujuan tindakan</Link></Button>
                    <Button asChild variant="secondary"><Link href={workflowUrl}>{canWitness ? 'Kembali ke dasbor perawat' : 'Kembali ke pemeriksaan'}</Link></Button>
                    {evidenceUrl && <Button asChild><a href={evidenceUrl}>Unduh bukti PDF</a></Button>}
                    {canCancel && ['waiting', 'signed'].includes(status) && <Button variant="secondary" onClick={() => confirmReason(
                        'Jelaskan alasan membatalkan sesi ini.',
                        (reason) => router.post(`${url}/batal`, { reason }),
                        'Batalkan sesi',
                    )}>Batalkan sesi</Button>}
                </div>
            </CardContent>
        </Card>
    </>;
}
