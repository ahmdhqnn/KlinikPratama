import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CreditCard, ReceiptText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency } from '@/lib/format';

interface BillingComponent {
    type: string;
    referenceId: number | null;
    name: string;
    quantity: number;
    tariff: number;
}

interface Props {
    visit: {
        id: number;
        number: string;
        patient: { name: string; medicalRecordNumber: string; allergies: string | null };
        clinic: string;
        doctor: string | null;
        payer: string;
        status: string;
    };
    billing: { id: number; status: string; receiptUrl: string } | null;
    components: BillingComponent[];
    subtotal: number;
    paymentMethods: { value: string; label: string }[];
}

export default function CashierPayment({ visit, billing, components, subtotal, paymentMethods }: Props) {
    const form = useForm({
        metode_bayar: 'tunai',
        bayar: String(subtotal),
        diskon: '0',
        items: components.map((item) => ({
            nama: item.name,
            jenis: item.type,
            referensi_id: item.referenceId ?? '',
            jumlah: String(item.quantity),
            tarif: String(item.tariff),
        })),
    });
    const discount = Math.max(0, Number(form.data.diskon) || 0);
    const amountPaid = Math.max(0, Number(form.data.bayar) || 0);
    const total = Math.max(0, subtotal - discount);
    const change = Math.max(0, amountPaid - total);

    function submitPayment(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/pelayanan/kasir/${visit.id}`);
    }

    return (
        <>
            <Head title={`Pembayaran ${visit.patient.name}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div><p className="text-sm font-medium text-neutral-700">Kasir pembayaran</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Proses tagihan</h2></div>
                    <Button asChild variant="secondary"><Link href="/pelayanan/kasir"><ArrowLeft className="size-4" />Kembali ke antrean</Link></Button>
                </div>

                <Card><CardContent className="flex flex-wrap items-center justify-between gap-4 p-5 sm:p-6">
                    <div><p className="text-xs font-semibold uppercase tracking-wider text-neutral-700">{visit.number} · {visit.payer.toUpperCase()}</p><h3 className="mt-1 text-xl font-semibold text-neutral-950">{visit.patient.name}</h3><p className="mt-1 text-sm text-neutral-500">No. RM {visit.patient.medicalRecordNumber} · {visit.clinic} · Dokter {visit.doctor ?? '—'}</p></div>
                    {visit.patient.allergies && <div className="max-w-md rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"><strong>Catatan alergi:</strong> {visit.patient.allergies}</div>}
                </CardContent></Card>

                {billing?.status === 'lunas' ? (
                    <Card><CardContent className="flex flex-col items-center gap-4 p-8 text-center"><span className="flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"><ReceiptText className="size-6" /></span><div><h3 className="font-semibold text-neutral-950">Tagihan sudah lunas</h3><p className="mt-1 text-sm text-neutral-500">Kunjungan ini sudah memiliki kuitansi pembayaran.</p></div><Button asChild><Link href={billing.receiptUrl}>Buka kuitansi</Link></Button></CardContent></Card>
                ) : (
                    <form className="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(19rem,0.9fr)]" onSubmit={submitPayment}>
                        <Card className="overflow-hidden">
                            <CardHeader className="border-b border-neutral-100"><CardTitle>Rincian komponen</CardTitle><CardDescription>Biaya terakumulasi dari pendaftaran, tindakan, obat, dan laboratorium.</CardDescription></CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-x-auto"><Table className="min-w-[680px]"><TableHeader><tr><TableHead>Komponen layanan</TableHead><TableHead>Kategori</TableHead><TableHead className="text-right">Qty</TableHead><TableHead className="text-right">Tarif</TableHead><TableHead className="text-right">Subtotal</TableHead></tr></TableHeader><TableBody>
                                    {components.map((item, index) => <TableRow key={`${item.type}-${item.referenceId ?? index}`}><TableCell className="font-medium text-neutral-900">{item.name}<input name={`items[${index}][nama]`} type="hidden" value={item.name} /><input name={`items[${index}][jenis]`} type="hidden" value={item.type} /><input name={`items[${index}][referensi_id]`} type="hidden" value={item.referenceId ?? ''} /><input name={`items[${index}][jumlah]`} type="hidden" value={item.quantity} /><input name={`items[${index}][tarif]`} type="hidden" value={item.tariff} /></TableCell><TableCell className="capitalize">{item.type}</TableCell><TableCell className="text-right">{item.quantity}</TableCell><TableCell className="text-right">{formatCurrency(item.tariff)}</TableCell><TableCell className="text-right font-semibold">{formatCurrency(item.tariff * item.quantity)}</TableCell></TableRow>)}
                                    {components.length === 0 && <TableRow><TableCell className="py-10 text-center text-neutral-500" colSpan={5}>Belum ada komponen tagihan.</TableCell></TableRow>}
                                </TableBody></Table></div>
                                <div className="flex justify-between border-t border-neutral-100 bg-neutral-50 px-5 py-4 font-semibold"><span>Subtotal</span><span>{formatCurrency(subtotal)}</span></div>
                            </CardContent>
                        </Card>

                        <Card className="h-fit">
                            <CardHeader><div className="flex items-center gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><CreditCard className="size-5" /></span><div><CardTitle>Pembayaran</CardTitle><CardDescription>Masukkan metode dan nominal pembayaran.</CardDescription></div></div></CardHeader>
                            <CardContent className="space-y-5">
                                <Field error={form.errors.metode_bayar} htmlFor="metode_bayar" label="Metode pembayaran" required><Select id="metode_bayar" onChange={(event) => form.setData('metode_bayar', event.target.value)} value={form.data.metode_bayar}>{paymentMethods.map((method) => <option key={method.value} value={method.value}>{method.label}</option>)}</Select></Field>
                                <Field error={form.errors.diskon} htmlFor="diskon" label="Potongan / diskon (Rp)"><Input id="diskon" min="0" onChange={(event) => form.setData('diskon', event.target.value)} type="number" value={form.data.diskon} /></Field>
                                <div className="space-y-2 rounded-xl border border-neutral-100 bg-neutral-50 p-4 text-sm"><div className="flex justify-between text-neutral-600"><span>Subtotal</span><span>{formatCurrency(subtotal)}</span></div><div className="flex justify-between text-neutral-600"><span>Diskon</span><span className="text-red-700">− {formatCurrency(discount)}</span></div><div className="flex justify-between border-t border-neutral-200 pt-3 text-base font-bold"><span>Total bayar</span><span className="text-neutral-700">{formatCurrency(total)}</span></div></div>
                                <Field error={form.errors.bayar} htmlFor="bayar" label="Nominal diterima (Rp)" required><Input id="bayar" min="0" onChange={(event) => form.setData('bayar', event.target.value)} required type="number" value={form.data.bayar} /></Field>
                                <div aria-live="polite" className="rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-center"><p className="text-xs font-semibold uppercase tracking-wider text-neutral-700">Kembalian</p><p className="mt-1 text-2xl font-bold text-neutral-800">{formatCurrency(change)}</p></div>
                                {Object.entries(form.errors).filter(([key]) => key.startsWith('items.')).length > 0 && <p className="text-sm text-red-600">Periksa kembali rincian item tagihan.</p>}
                                <Button className="w-full" disabled={form.processing || components.length === 0 || (billing?.status === 'lunas')} size="lg" type="submit">{form.processing ? 'Memproses pembayaran…' : 'Proses dan terbitkan kuitansi'}</Button>
                            </CardContent>
                        </Card>
                    </form>
                )}
            </div>
        </>
    );
}
