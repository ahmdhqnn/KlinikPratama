import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, PackageCheck, Send } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency, formatNumber } from '@/lib/format';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface OrderItem { id: number; medicine: string; unit: string | null; quantity: number; received: number; price: number; total: number }
interface Order { id: number; number: string; supplier: string; date: string | null; deliveryDate: string | null; status: string; depot: string | null; note: string | null; total: number; items: OrderItem[]; dispatchUrl: string; receiveUrl: string }
const labels: Record<string, string> = { draft: 'Draft', dikirim: 'Dikirim', sebagian: 'Diterima sebagian', diterima: 'Selesai diterima' };

export default function PurchaseOrderDetail({ order }: { order: Order }) {
    function dispatch() {
        confirmAction(`Kirim PO ${order.number} ke proses penerimaan?`, () => { router.post(order.dispatchUrl, {}, { preserveScroll: true }); }, 'Lanjutkan');
    }

    return <><Head title={`PO ${order.number}`} /><div className="mx-auto max-w-5xl space-y-5"><Button asChild size="sm" variant="ghost"><Link href="/stok/purchase-order"><ArrowLeft className="size-4" />Daftar pesanan</Link></Button><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Detail pesanan pembelian</p><h2 className="mt-1 font-mono text-2xl font-semibold tracking-tight text-neutral-950">{order.number}</h2><p className="mt-1 text-sm text-neutral-500">{order.supplier} · {order.depot ?? 'Depo tidak tersedia'}</p></div><div className="flex gap-2">{order.status === 'draft' && <Button onClick={dispatch}><Send className="size-4" />Tandai dikirim</Button>}{['dikirim', 'sebagian'].includes(order.status) && <Button asChild><Link href={order.receiveUrl}><PackageCheck className="size-4" />Terima barang</Link></Button>}</div></div>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">{[['Status', labels[order.status] ?? order.status], ['Tanggal PO', order.date ?? '—'], ['Perkiraan kirim', order.deliveryDate ?? '—'], ['Total pesanan', formatCurrency(order.total)]].map(([label, value]) => <Card key={label}><CardContent className="p-4"><p className="text-xs font-medium uppercase tracking-wide text-neutral-500">{label}</p><p className="mt-2 font-semibold text-neutral-900">{value}</p></CardContent></Card>)}</div>
        <Card className="overflow-hidden"><CardHeader><CardTitle>Rincian barang</CardTitle><CardDescription>{order.items.length} jenis obat dalam pesanan ini.</CardDescription></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[750px]"><TableHeader><tr><TableHead>Nama obat</TableHead><TableHead className="text-right">Dipesan</TableHead><TableHead className="text-right">Diterima</TableHead><TableHead className="text-right">Harga satuan</TableHead><TableHead className="text-right">Subtotal</TableHead></tr></TableHeader><TableBody>{order.items.map((item) => <TableRow key={item.id}><TableCell className="font-medium text-neutral-900">{item.medicine}</TableCell><TableCell className="text-right font-mono">{formatNumber(item.quantity)} {item.unit ?? ''}</TableCell><TableCell className="text-right font-mono">{formatNumber(item.received)}</TableCell><TableCell className="text-right">{formatCurrency(item.price)}</TableCell><TableCell className="text-right font-semibold">{formatCurrency(item.total)}</TableCell></TableRow>)}</TableBody></Table></div><div className="flex justify-end border-t border-neutral-100 bg-neutral-50 px-5 py-4 text-lg font-bold"><span>Total: {formatCurrency(order.total)}</span></div></CardContent></Card>{order.note && <Card><CardHeader><CardTitle>Catatan</CardTitle></CardHeader><CardContent className="whitespace-pre-wrap text-sm text-neutral-600">{order.note}</CardContent></Card>}</div></>;
}
