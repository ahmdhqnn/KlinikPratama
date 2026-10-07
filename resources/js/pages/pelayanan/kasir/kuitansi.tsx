import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { formatCurrency } from '@/lib/format';
import { Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface ReceiptItem {
    name: string;
    quantity: number;
    tariff: number;
    total: number;
}

interface Props {
    receipt: {
        number: string;
        dateTime: string | null;
        status: string;
        patient: { name: string; medicalRecordNumber: string; gender: string; age: number | null };
        clinic: string;
        doctor: string | null;
        paymentMethod: string;
        items: ReceiptItem[];
        subtotal: number;
        discount: number;
        total: number;
        paid: number;
        change: number;
        cashier: string;
    };
    clinicName: string;
}

export default function CashierReceipt({ receipt, clinicName }: Props) {
    return (
        <>
            <Head title={`Kuitansi ${receipt.number}`} />
            <div className="mx-auto max-w-3xl space-y-5">
                <div className="no-print flex items-center justify-between gap-3"><Button asChild variant="secondary"><Link href="/pelayanan/kasir"><ArrowLeft className="size-4" />Kembali ke kasir</Link></Button><Button onClick={() => window.print()}><Printer className="size-4" />Cetak kuitansi</Button></div>

                <Card className="p-6 shadow-sm sm:p-9 print:border-0 print:p-0 print:shadow-none">
                    <header className="flex flex-wrap items-start justify-between gap-4 border-b-2 border-neutral-800 pb-6">
                        <div><h1 className="text-2xl font-bold uppercase tracking-wide text-neutral-950">{clinicName}</h1><p className="mt-1 text-xs text-neutral-500">Sistem Rekam Medis & Pelayanan Klinik Pratama</p><p className="text-xs text-neutral-500">Jl. Contoh No. 1 · Telp: 021-12345678</p></div>
                        <div className="text-right"><span className="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-800">{receipt.status}</span><p className="mt-2 font-mono text-xs text-neutral-500">{receipt.number}</p><p className="text-xs text-neutral-600">{receipt.dateTime ?? '—'}</p></div>
                    </header>

                    <section className="grid gap-4 border-b border-neutral-100 py-5 text-sm sm:grid-cols-2">
                        <div><p className="text-[10px] font-semibold uppercase tracking-wider text-neutral-400">Data pasien</p><p className="mt-1 font-semibold text-neutral-900">{receipt.patient.name}</p><p className="font-mono text-xs text-neutral-600">No. RM: {receipt.patient.medicalRecordNumber}</p><p className="text-xs text-neutral-500">{receipt.patient.gender}{receipt.patient.age !== null ? `, ${receipt.patient.age} th` : ''}</p></div>
                        <div className="sm:text-right"><p className="text-[10px] font-semibold uppercase tracking-wider text-neutral-400">Layanan</p><p className="mt-1 font-semibold text-neutral-900">{receipt.clinic}</p><p className="text-xs text-neutral-600">Dokter: {receipt.doctor ?? '—'}</p><p className="text-xs text-neutral-500">Metode: <strong className="uppercase">{receipt.paymentMethod}</strong></p></div>
                    </section>

                    <section className="overflow-x-auto py-5"><Table className="min-w-[480px]"><TableHeader className="border-b border-neutral-200"><tr><TableHead className="px-0 py-2">Deskripsi layanan</TableHead><TableHead className="px-0 py-2 text-center">Qty</TableHead><TableHead className="px-0 py-2 text-right">Tarif</TableHead><TableHead className="px-0 py-2 text-right">Total</TableHead></tr></TableHeader><TableBody>{receipt.items.map((item, index) => <TableRow className="hover:bg-transparent" key={`${item.name}-${index}`}><TableCell className="px-0 py-3 font-medium text-neutral-800">{item.name}</TableCell><TableCell className="px-0 py-3 text-center font-mono">{item.quantity}</TableCell><TableCell className="px-0 py-3 text-right font-mono text-neutral-600">{formatCurrency(item.tariff)}</TableCell><TableCell className="px-0 py-3 text-right font-mono font-semibold text-neutral-800">{formatCurrency(item.total)}</TableCell></TableRow>)}</TableBody><TableFooter className="bg-transparent"><tr><TableCell className="px-0 pt-3 text-right text-neutral-600" colSpan={3}>Subtotal</TableCell><TableCell className="px-0 pt-3 text-right font-mono font-semibold">{formatCurrency(receipt.subtotal)}</TableCell></tr>{receipt.discount > 0 && <tr><TableCell className="px-0 py-1 text-right text-red-700" colSpan={3}>Diskon</TableCell><TableCell className="px-0 py-1 text-right font-mono text-red-700">− {formatCurrency(receipt.discount)}</TableCell></tr>}<tr className="text-base font-bold"><TableCell className="px-0 py-2 text-right" colSpan={3}>Total akhir</TableCell><TableCell className="px-0 py-2 text-right font-mono text-neutral-700">{formatCurrency(receipt.total)}</TableCell></tr><tr className="text-xs text-neutral-600"><TableCell className="px-0 py-1 text-right" colSpan={3}>Bayar ({receipt.paymentMethod.toUpperCase()})</TableCell><TableCell className="px-0 py-1 text-right font-mono">{formatCurrency(receipt.paid)}</TableCell></tr><tr className="text-xs text-neutral-600"><TableCell className="px-0 py-1 text-right" colSpan={3}>Kembalian</TableCell><TableCell className="px-0 py-1 text-right font-mono font-bold text-neutral-800">{formatCurrency(receipt.change)}</TableCell></tr></TableFooter></Table></section>

                    <footer className="mt-6 grid grid-cols-2 gap-4 border-t border-dashed border-neutral-300 pt-6 text-center text-xs"><div><p className="text-neutral-400">Pasien / Keluarga</p><div className="h-16" /><p className="font-semibold text-neutral-800">({receipt.patient.name})</p></div><div><p className="text-neutral-400">Petugas kasir</p><div className="h-16" /><p className="font-semibold text-neutral-800">({receipt.cashier})</p></div></footer>
                    <p className="mt-8 text-center text-[10px] italic text-neutral-400">Terima kasih atas kunjungan Anda. Semoga lekas sembuh.</p>
                </Card>
            </div>
        </>
    );
}
