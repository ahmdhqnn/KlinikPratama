import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, Plus, Search, ShoppingCart } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency } from '@/lib/format';

interface Sale { id: number; number: string; date: string | null; buyer: string; cashier: string; itemCount: number; total: number; paymentMethod: string; receiptUrl: string }
interface Props { sales: PaginationData & { data: Sale[] }; filters: { search: string; tanggal: string } }

export default function DirectSales({ sales, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [date, setDate] = useState(filters.tanggal);
    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/stok/penjualan-langsung', { search, tanggal: date }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <><Head title="Penjualan Langsung" /><div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-violet-700">Transaksi farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Penjualan langsung</h2><p className="mt-1 text-sm text-slate-500">Catat penjualan obat dan cetak nota untuk pembeli.</p></div><Button asChild><Link href="/stok/penjualan-langsung/create"><Plus className="size-4" />Transaksi baru</Link></Button></div>
        <Card className="overflow-hidden"><CardHeader className="border-b border-slate-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><ShoppingCart className="size-5" /></span><div><CardTitle>Riwayat transaksi</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(sales.total)} transaksi tersimpan.</CardDescription></div></div><form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_13rem_auto]" onSubmit={applyFilters}><Input aria-label="Cari transaksi atau pembeli" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nomor transaksi atau pembeli…" value={search} /><div className="relative"><CalendarDays className="pointer-events-none absolute left-3 top-3 size-4 text-slate-400" /><Input className="pl-9" onChange={(event) => setDate(event.target.value)} type="date" value={date} /></div><Button type="submit"><Search className="size-4" />Filter</Button></form></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[900px]"><TableHeader><tr><TableHead>No. transaksi</TableHead><TableHead>Tanggal</TableHead><TableHead>Pembeli</TableHead><TableHead>Kasir</TableHead><TableHead>Item</TableHead><TableHead>Metode</TableHead><TableHead className="text-right">Total</TableHead><TableHead className="text-right">Nota</TableHead></tr></TableHeader><TableBody>{sales.data.length ? sales.data.map((sale) => <TableRow key={sale.id}><TableCell className="font-mono text-xs font-semibold">{sale.number}</TableCell><TableCell>{sale.date ?? '—'}</TableCell><TableCell className="font-medium text-slate-900">{sale.buyer}</TableCell><TableCell>{sale.cashier}</TableCell><TableCell>{sale.itemCount} item</TableCell><TableCell className="capitalize">{sale.paymentMethod}</TableCell><TableCell className="text-right font-semibold">{formatCurrency(sale.total)}</TableCell><TableCell className="text-right"><Button asChild size="sm" variant="secondary"><Link href={sale.receiptUrl}>Buka nota</Link></Button></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-slate-500" colSpan={8}>Belum ada transaksi untuk filter yang dipilih.</TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={sales} /></CardContent></Card>
    </div></>;
}
