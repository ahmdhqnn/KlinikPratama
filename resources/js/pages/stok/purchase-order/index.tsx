import { Head, Link, router } from '@inertiajs/react';
import { FilePlus2, PackageCheck, Search, ShoppingBag } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency } from '@/lib/format';

interface Order {
    id: number;
    number: string;
    supplier: string;
    date: string | null;
    status: string;
    total: number;
    depot: string | null;
    showUrl: string;
    receiveUrl: string;
}

interface Props {
    orders: PaginationData & { data: Order[] };
    filters: { search: string; status: string };
}

const statusLabel: Record<string, string> = { draft: 'Draft', dikirim: 'Dikirim', sebagian: 'Diterima sebagian', diterima: 'Selesai diterima' };

export default function PurchaseOrders({ orders, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/stok/purchase-order', { search, status }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <><Head title="Pesanan Pembelian" /><div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Persediaan & pengadaan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pesanan pembelian</h2><p className="mt-1 text-sm text-neutral-500">Pantau pesanan supplier dan catat barang yang diterima ke stok.</p></div><Button asChild><Link href="/stok/purchase-order/create"><FilePlus2 className="size-4" />Buat pesanan</Link></Button></div>
        <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ShoppingBag className="size-5" /></span><div><CardTitle>Daftar purchase order</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(orders.total)} pesanan tercatat.</CardDescription></div></div><form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_13rem_auto]" onSubmit={applyFilters}><Input aria-label="Cari nomor PO atau supplier" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nomor PO atau supplier…" value={search} /><Select aria-label="Filter status" onChange={(event) => setStatus(event.target.value)} value={status}><option value="">Semua status</option><option value="draft">Draft</option><option value="dikirim">Dikirim</option><option value="sebagian">Diterima sebagian</option><option value="diterima">Selesai diterima</option></Select><Button type="submit"><Search className="size-4" />Cari</Button></form></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>No. PO</TableHead><TableHead>Tanggal</TableHead><TableHead>Supplier</TableHead><TableHead>Depo</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Total</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{orders.data.length ? orders.data.map((order) => <TableRow key={order.id}><TableCell className="font-mono text-xs font-semibold">{order.number}</TableCell><TableCell>{order.date ?? '—'}</TableCell><TableCell className="font-medium text-neutral-900">{order.supplier}</TableCell><TableCell>{order.depot ?? '—'}</TableCell><TableCell><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${order.status === 'diterima' ? 'bg-emerald-50 text-emerald-700' : order.status === 'sebagian' ? 'bg-amber-50 text-amber-700' : order.status === 'dikirim' ? 'bg-neutral-50 text-neutral-700' : 'bg-neutral-100 text-neutral-600'}`}>{statusLabel[order.status] ?? order.status}</span></TableCell><TableCell className="text-right font-semibold">{formatCurrency(order.total)}</TableCell><TableCell><div className="flex justify-end gap-1"><Button asChild size="sm" variant="secondary"><Link href={order.showUrl}>Detail</Link></Button>{['dikirim', 'sebagian'].includes(order.status) && <Button asChild size="sm"><Link href={order.receiveUrl}><PackageCheck className="size-4" />Terima</Link></Button>}</div></TableCell></TableRow>) : <TableRow><TableCell colSpan={7}><Empty size="compact" title="Belum ada pesanan pembelian sesuai filter." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={orders} /></CardContent></Card>
    </div></>;
}
