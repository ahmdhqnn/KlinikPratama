import { Head, router, useForm } from '@inertiajs/react';
import { HeartPulse, Plus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency } from '@/lib/format';

interface MedicalSupply {
    id: number;
    code: string;
    name: string;
    unit: string | null;
    stock: number;
    minimumStock: number;
    purchasePrice: number;
    sellingPrice: number;
    active: boolean;
}

interface Props {
    items: PaginationData & { data: MedicalSupply[] };
    filters: { search: string };
}

const emptySupply = { kode: '', nama: '', satuan: '', stok: '0', stok_minimum: '5', harga_beli: '0', harga_jual: '0', is_active: true };

export default function MedicalSupplies({ items, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [editingItem, setEditingItem] = useState<MedicalSupply | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm(emptySupply);

    function applyFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/master/alkes', { search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function createItem() {
        setEditingItem(null);
        form.clearErrors();
        form.setData({ ...emptySupply });
        setDialogOpen(true);
    }

    function editItem(item: MedicalSupply) {
        setEditingItem(item);
        form.clearErrors();
        form.setData({ kode: item.code, nama: item.name, satuan: item.unit ?? '', stok: String(item.stock), stok_minimum: String(item.minimumStock), harga_beli: String(item.purchasePrice), harga_jual: String(item.sellingPrice), is_active: item.active });
        setDialogOpen(true);
    }

    function saveItem(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        if (editingItem) form.put(`/master/alkes/${editingItem.id}`, options);
        else form.post('/master/alkes', options);
    }

    function deleteItem(item: MedicalSupply) {
        if (window.confirm(`Hapus alat kesehatan ${item.name}?`)) router.delete(`/master/alkes/${item.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Alat Kesehatan" />
            <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-rose-700">Persediaan medis</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Alat kesehatan (alkes)</h2><p className="mt-1 text-sm text-slate-500">Kelola item medis, stok minimum, dan harga untuk kebutuhan tindakan.</p></div><Button onClick={createItem}><Plus className="size-4" />Tambah alkes</Button></div>
                <Card className="overflow-hidden"><CardHeader className="border-b border-slate-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-rose-50 text-rose-700"><HeartPulse className="size-5" /></span><div><CardTitle>Daftar alat kesehatan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(items.total)} item terdaftar.</CardDescription></div></div><form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_auto]" onSubmit={applyFilter}><label className="sr-only" htmlFor="supply-search">Cari nama atau kode alkes</label><Input id="supply-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode alkes…" value={search} /><Button type="submit"><Search className="size-4" />Cari</Button></form></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[1000px]"><TableHeader><tr><TableHead>Kode</TableHead><TableHead>Nama alkes</TableHead><TableHead>Satuan</TableHead><TableHead className="text-right">Stok</TableHead><TableHead className="text-right">Stok minimum</TableHead><TableHead className="text-right">Harga beli</TableHead><TableHead className="text-right">Harga jual</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{items.data.length ? items.data.map((item) => {const lowStock = item.stock <= item.minimumStock; return <TableRow key={item.id}><TableCell className="font-mono text-xs font-semibold">{item.code}</TableCell><TableCell className="font-medium text-slate-900">{item.name}</TableCell><TableCell>{item.unit ?? '—'}</TableCell><TableCell className={`text-right font-mono font-semibold ${lowStock ? 'text-amber-700' : 'text-slate-700'}`}>{item.stock}</TableCell><TableCell className="text-right font-mono text-slate-500">{item.minimumStock}</TableCell><TableCell className="text-right">{formatCurrency(item.purchasePrice)}</TableCell><TableCell className="text-right font-semibold">{formatCurrency(item.sellingPrice)}</TableCell><TableCell><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${item.active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{item.active ? 'Aktif' : 'Nonaktif'}</span></TableCell><TableCell><div className="flex justify-end gap-1"><Button onClick={() => editItem(item)} size="sm" variant="secondary">Edit</Button><Button onClick={() => deleteItem(item)} size="sm" variant="destructive">Hapus</Button></div></TableCell></TableRow>;}) : <TableRow><TableCell className="py-10 text-center text-slate-500" colSpan={9}>Belum ada alat kesehatan yang sesuai filter.</TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={items} /></CardContent></Card>
            </div>
            <Dialog onOpenChange={setDialogOpen} open={dialogOpen}><DialogContent className="max-w-2xl"><DialogHeader><DialogTitle>{editingItem ? 'Edit alat kesehatan' : 'Tambah alat kesehatan'}</DialogTitle><DialogDescription>Lengkapi identitas barang, stok, dan harga.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={saveItem}><div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.kode} htmlFor="supply-code" label="Kode" required><Input id="supply-code" maxLength={30} onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field><Field error={form.errors.satuan} htmlFor="supply-unit" label="Satuan"><Input id="supply-unit" maxLength={50} onChange={(event) => form.setData('satuan', event.target.value)} value={form.data.satuan} /></Field></div><Field error={form.errors.nama} htmlFor="supply-name" label="Nama alkes" required><Input id="supply-name" maxLength={200} onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field><div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.stok} htmlFor="supply-stock" label="Stok" required><Input id="supply-stock" min="0" onChange={(event) => form.setData('stok', event.target.value)} required type="number" value={form.data.stok} /></Field><Field error={form.errors.stok_minimum} htmlFor="supply-minimum" label="Stok minimum" required><Input id="supply-minimum" min="0" onChange={(event) => form.setData('stok_minimum', event.target.value)} required type="number" value={form.data.stok_minimum} /></Field></div><div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.harga_beli} htmlFor="supply-buy" label="Harga beli (Rp)" required><Input id="supply-buy" min="0" onChange={(event) => form.setData('harga_beli', event.target.value)} required type="number" value={form.data.harga_beli} /></Field><Field error={form.errors.harga_jual} htmlFor="supply-sell" label="Harga jual (Rp)" required><Input id="supply-sell" min="0" onChange={(event) => form.setData('harga_jual', event.target.value)} required type="number" value={form.data.harga_jual} /></Field></div><label className="flex items-center gap-2 text-sm text-slate-700"><input checked={form.data.is_active} className="size-4 rounded border-slate-300 text-blue-600" onChange={(event) => form.setData('is_active', event.target.checked)} type="checkbox" />Item aktif</label><div className="flex justify-end gap-2 border-t border-slate-100 pt-4"><Button onClick={() => setDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan alkes'}</Button></div></form></DialogContent></Dialog>
        </>
    );
}
