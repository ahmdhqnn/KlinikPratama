import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface Record { number: string; date: string; patient: string; medicalRecordNumber: string; medicine: string; quantity: number; unit: string; instruction: string; basis: string; notes: string | null; pharmacist: string }

export default function DirectDispensingPrint({ record, appName }: { record: Record; appName: string }) {
    return <><Head title={`Bukti pemberian obat ${record.number}`} /><div className="mx-auto max-w-3xl space-y-5">
        <div className="flex flex-wrap justify-between gap-3 print:hidden"><Button asChild variant="secondary"><Link href="/farmasi/pemberian-obat"><ArrowLeft className="size-4" />Kembali ke obat langsung</Link></Button><Button onClick={() => window.print()}><Printer className="size-4" />Cetak bukti</Button></div>
        <Card className="print:border-0 print:shadow-none"><CardHeader className="border-b border-neutral-100 text-center"><CardTitle>{appName}</CardTitle><CardDescription>Bukti pemberian obat langsung · {record.number}</CardDescription></CardHeader><CardContent className="space-y-6 p-6 sm:p-8">
            <div className="grid gap-4 sm:grid-cols-2"><Info label="Tanggal penyerahan" value={record.date} /><Info label="Pasien" value={`${record.patient} · ${record.medicalRecordNumber}`} /><Info label="Obat" value={record.medicine} /><Info label="Jumlah" value={`${record.quantity} ${record.unit}`} /><Info label="Aturan pakai" value={record.instruction} /><Info label="Petugas farmasi" value={record.pharmacist} /></div>
            <div className="rounded-xl border border-neutral-200 p-4"><p className="text-xs font-semibold uppercase tracking-wide text-neutral-600">Alasan pemberian langsung</p><p className="mt-2 whitespace-pre-wrap text-sm">{record.basis}</p></div>
            {record.notes && <div className="rounded-xl border border-neutral-200 p-4"><p className="text-xs font-semibold uppercase tracking-wide text-neutral-600">Catatan tambahan</p><p className="mt-2 whitespace-pre-wrap text-sm">{record.notes}</p></div>}
            <p className="text-center text-xs text-neutral-500">Catatan pemberian obat langsung ini tidak menggantikan pemeriksaan dokter dan tersimpan terpisah dari resep kunjungan.</p>
        </CardContent></Card>
    </div><style>{'@media print { body { background: white !important; } @page { margin: 16mm; } }'}</style></>;
}

function Info({ label, value }: { label: string; value: string }) {
    return <div><p className="text-xs font-semibold uppercase tracking-wide text-neutral-600">{label}</p><p className="mt-1 text-sm font-medium text-neutral-950">{value}</p></div>;
}

