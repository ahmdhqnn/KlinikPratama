import { DatePicker } from '@/components/ui/date-picker';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, PackageCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatNumber } from '@/lib/format';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface OrderItem { id: number; medicine: string; unit: string | null; quantity: number; received: number }
interface Order { id: number; number: string; supplier: string; depot: string | null; items: OrderItem[] }
interface Props { order: Order; submitUrl: string; showUrl: string }

export default function ReceivePurchaseOrder({ order, submitUrl, showUrl }: Props) {
    const form = useForm({ items: order.items.map((item) => ({ id: item.id, nomor_batch: '', expired_at: '', jumlah_terima: String(Math.max(0, item.quantity - item.received)) })) });
    const errors = form.errors as Record<string, string | undefined>;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        confirmAction('Konfirmasi penerimaan? Stok akan diperbarui sesuai jumlah yang dimasukkan.', () => { form.post(submitUrl, { preserveScroll: true }); }, 'Lanjutkan');
    }

    return <><Head title={`Penerimaan ${order.number}`} /><div className="mx-auto max-w-5xl space-y-5"><Button asChild size="sm" variant="ghost"><Link href={showUrl}><ArrowLeft className="size-4" />Kembali ke detail PO</Link></Button><div><p className="text-sm font-medium text-neutral-700">Verifikasi penerimaan barang</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{order.number}</h2><p className="mt-1 text-sm text-neutral-500">{order.supplier} · {order.depot ?? 'Depo tidak tersedia'}. Stok bertambah setelah penerimaan berhasil disimpan.</p></div>
        <Card className="overflow-hidden"><CardHeader><CardTitle>Barang yang diterima</CardTitle><CardDescription>Jumlah yang dimasukkan tidak boleh melebihi sisa pesanan.</CardDescription></CardHeader><CardContent className="p-0"><form onSubmit={submit}><div className="overflow-x-auto"><Table className="min-w-[1150px]"><TableHeader><tr><TableHead>Nama obat</TableHead><TableHead className="text-right">Dipesan</TableHead><TableHead className="text-right">Sudah diterima</TableHead><TableHead className="text-right">Sisa</TableHead><TableHead className="w-44 text-right">Terima sekarang</TableHead><TableHead>Nomor batch</TableHead><TableHead>Kedaluwarsa</TableHead></tr></TableHeader><TableBody>{order.items.map((item, index) => { const remaining = Math.max(0, item.quantity - item.received); return <TableRow key={item.id}><TableCell className="font-medium text-neutral-900">{item.medicine}</TableCell><TableCell className="text-right font-mono">{formatNumber(item.quantity)} {item.unit ?? ''}</TableCell><TableCell className="text-right font-mono text-neutral-700">{formatNumber(item.received)}</TableCell><TableCell className="text-right font-mono font-semibold text-neutral-800">{formatNumber(remaining)}</TableCell><TableCell><Input aria-label={`Jumlah penerimaan ${item.medicine}`} max={remaining} min="0" onChange={(event) => { const items = [...form.data.items]; items[index] = { ...items[index], jumlah_terima: event.target.value }; form.setData('items', items); }} step="1" type="number" value={form.data.items[index]?.jumlah_terima ?? '0'} />{errors[`items.${index}.jumlah_terima`] && <p className="mt-1 text-xs text-red-600">{errors[`items.${index}.jumlah_terima`]}</p>}</TableCell><TableCell><Input aria-label={`Batch ${item.medicine}`} disabled={remaining <= 0} required={Number(form.data.items[index]?.jumlah_terima) > 0} value={form.data.items[index]?.nomor_batch ?? ''} onChange={(event) => form.setData('items', form.data.items.map((row, rowIndex) => rowIndex === index ? { ...row, nomor_batch: event.target.value } : row))} />{errors[`items.${index}.nomor_batch`] && <p className="mt-1 text-xs text-red-700">{errors[`items.${index}.nomor_batch`]}</p>}</TableCell><TableCell><DatePicker aria-label={`Kedaluwarsa ${item.medicine}`} disabled={remaining <= 0} required={Number(form.data.items[index]?.jumlah_terima) > 0} value={form.data.items[index]?.expired_at ?? ''} onChange={(event) => form.setData('items', form.data.items.map((row, rowIndex) => rowIndex === index ? { ...row, expired_at: event.target.value } : row))} />{errors[`items.${index}.expired_at`] && <p className="mt-1 text-xs text-red-700">{errors[`items.${index}.expired_at`]}</p>}</TableCell></TableRow>; })}</TableBody></Table></div>{form.errors.items && <p className="px-5 pt-4 text-sm text-red-600">{form.errors.items}</p>}<div className="flex justify-end gap-2 border-t border-neutral-100 p-4"><Button asChild variant="secondary"><Link href={showUrl}>Batal</Link></Button><Button disabled={form.processing || !order.items.some((item) => item.quantity > item.received)} type="submit"><PackageCheck className="size-4" />{form.processing ? 'Menyimpan…' : 'Konfirmasi penerimaan'}</Button></div></form></CardContent></Card>
    </div></>;
}
