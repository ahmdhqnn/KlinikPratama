import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowDownLeft, ArrowLeft, PackagePlus } from 'lucide-react';
import type { FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

interface Movement { id: number; date: string | null; type: string; quantity: number; stockBefore: number; stockAfter: number; description: string | null }
interface Props { medicine: { id: number; code: string; name: string; stock: number; minimumStock: number; unit: string | null }; movements: PaginationData & { data: Movement[] } }

export default function MedicineStock({ medicine, movements }: Props) {
    const form = useForm({ jumlah: '', keterangan: '' });
    function addStock(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/master/obat/${medicine.id}/tambah-stok`, { preserveScroll: true, onSuccess: () => form.reset() });
    }
    const dateFormatter = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

    return <>
        <Head title={`Kartu Stok ${medicine.name}`} />
        <div className="space-y-6"><div><Button asChild className="mb-3" size="sm" variant="ghost"><Link href="/master/obat"><ArrowLeft className="size-4" />Kembali ke master obat</Link></Button><p className="text-sm font-medium text-violet-700">Persediaan farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{medicine.name} <span className="font-mono text-base text-slate-500">({medicine.code})</span></h2><p className="mt-1 text-sm text-slate-500">Stok tersedia <strong className="text-slate-900">{medicine.stock} {medicine.unit ?? ''}</strong> · batas minimum {medicine.minimumStock}</p></div>
            <Card><CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"><PackagePlus className="size-5" /></span><div><CardTitle>Penambahan stok manual</CardTitle><CardDescription className="mt-1">Catat stok masuk beserta alasan agar riwayat persediaan tetap terlacak.</CardDescription></div></div></CardHeader><CardContent><form className="grid gap-4 sm:grid-cols-[12rem_minmax(0,1fr)_auto] sm:items-end" onSubmit={addStock}><Field error={form.errors.jumlah} htmlFor="stock-quantity" label={`Jumlah (${medicine.unit ?? 'unit'})`} required><Input id="stock-quantity" min="1" onChange={(event) => form.setData('jumlah', event.target.value)} required step="1" type="number" value={form.data.jumlah} /></Field><Field error={form.errors.keterangan} htmlFor="stock-reason" label="Keterangan"><Textarea id="stock-reason" onChange={(event) => form.setData('keterangan', event.target.value)} rows={2} value={form.data.keterangan} /></Field><Button disabled={form.processing} type="submit"><ArrowDownLeft className="size-4" />Tambah stok</Button></form></CardContent></Card>
            <Card className="overflow-hidden"><CardHeader className="border-b border-slate-100"><CardTitle>Kartu stok</CardTitle><CardDescription>{new Intl.NumberFormat('id-ID').format(movements.total)} mutasi tercatat.</CardDescription></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>Tanggal</TableHead><TableHead>Jenis mutasi</TableHead><TableHead className="text-right">Stok sebelum</TableHead><TableHead className="text-right">Jumlah</TableHead><TableHead className="text-right">Stok sesudah</TableHead><TableHead>Keterangan</TableHead></tr></TableHeader><TableBody>{movements.data.length ? movements.data.map((item) => <TableRow key={item.id}><TableCell className="whitespace-nowrap">{item.date ? dateFormatter.format(new Date(item.date)) : '—'}</TableCell><TableCell className="capitalize">{item.type}</TableCell><TableCell className="text-right font-mono">{item.stockBefore}</TableCell><TableCell className={`text-right font-mono font-semibold ${item.type === 'masuk' ? 'text-emerald-700' : 'text-red-700'}`}>{item.type === 'masuk' ? '+' : '−'}{item.quantity}</TableCell><TableCell className="text-right font-mono font-bold">{item.stockAfter}</TableCell><TableCell>{item.description ?? '—'}</TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-slate-500" colSpan={6}>Belum ada riwayat mutasi stok.</TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={movements} /></CardContent></Card>
        </div>
    </>;
}
