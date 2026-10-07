import { Head, router, useForm } from '@inertiajs/react';
import { Boxes, Plus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';

interface Depot {
    id: number;
    code: string;
    name: string;
    description: string | null;
    active: boolean;
}

interface Props {
    depots: PaginationData & { data: Depot[] };
    filters: { search: string };
}

const emptyDepot = { kode: '', nama: '', deskripsi: '', is_active: true };

export default function DepotIndex({ depots, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [editingDepot, setEditingDepot] = useState<Depot | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm(emptyDepot);

    function applyFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/master/depo-obat', { search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function createDepot() {
        setEditingDepot(null);
        form.clearErrors();
        form.setData({ ...emptyDepot });
        setDialogOpen(true);
    }

    function editDepot(depot: Depot) {
        setEditingDepot(depot);
        form.clearErrors();
        form.setData({ kode: depot.code, nama: depot.name, deskripsi: depot.description ?? '', is_active: depot.active });
        setDialogOpen(true);
    }

    function saveDepot(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        if (editingDepot) form.put(`/master/depo-obat/${editingDepot.id}`, options);
        else form.post('/master/depo-obat', options);
    }

    function deleteDepot(depot: Depot) {
        if (window.confirm(`Hapus depo ${depot.name}?`)) router.delete(`/master/depo-obat/${depot.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Depo Obat" />
            <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-blue-700">Manajemen persediaan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Depo obat</h2><p className="mt-1 text-sm text-slate-500">Kelola lokasi penyimpanan obat klinik dan status penggunaannya.</p></div><Button onClick={createDepot}><Plus className="size-4" />Tambah depo</Button></div>
                <Card className="overflow-hidden"><CardHeader className="border-b border-slate-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><Boxes className="size-5" /></span><div><CardTitle>Daftar depo penyimpanan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(depots.total)} depo terdaftar.</CardDescription></div></div><form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_auto]" onSubmit={applyFilter}><label className="sr-only" htmlFor="depot-search">Cari nama atau kode depo</label><Input id="depot-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode depo…" value={search} /><Button type="submit"><Search className="size-4" />Cari</Button></form></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[760px]"><TableHeader><tr><TableHead>Kode</TableHead><TableHead>Nama depo</TableHead><TableHead>Deskripsi</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{depots.data.length ? depots.data.map((depot) => <TableRow key={depot.id}><TableCell className="font-mono text-xs font-semibold">{depot.code}</TableCell><TableCell className="font-medium text-slate-900">{depot.name}</TableCell><TableCell className="max-w-md truncate text-slate-500">{depot.description || '—'}</TableCell><TableCell><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${depot.active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{depot.active ? 'Aktif' : 'Nonaktif'}</span></TableCell><TableCell><div className="flex justify-end gap-1"><Button onClick={() => editDepot(depot)} size="sm" variant="secondary">Edit</Button><Button onClick={() => deleteDepot(depot)} size="sm" variant="destructive">Hapus</Button></div></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-slate-500" colSpan={5}>Belum ada depo yang sesuai filter.</TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={depots} /></CardContent></Card>
            </div>
            <Dialog onOpenChange={setDialogOpen} open={dialogOpen}><DialogContent><DialogHeader><DialogTitle>{editingDepot ? 'Edit depo obat' : 'Tambah depo obat'}</DialogTitle><DialogDescription>Atur identitas lokasi penyimpanan obat.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={saveDepot}><Field error={form.errors.kode} htmlFor="depot-code" label="Kode" required><Input id="depot-code" maxLength={20} onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field><Field error={form.errors.nama} htmlFor="depot-name" label="Nama depo" required><Input id="depot-name" maxLength={100} onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field><Field error={form.errors.deskripsi} htmlFor="depot-description" label="Deskripsi"><Textarea id="depot-description" onChange={(event) => form.setData('deskripsi', event.target.value)} rows={3} value={form.data.deskripsi} /></Field><label className="flex items-center gap-2 text-sm text-slate-700"><input checked={form.data.is_active} className="size-4 rounded border-slate-300 text-blue-600" onChange={(event) => form.setData('is_active', event.target.checked)} type="checkbox" />Depo aktif</label><div className="flex justify-end gap-2 border-t border-slate-100 pt-4"><Button onClick={() => setDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan depo'}</Button></div></form></DialogContent></Dialog>
        </>
    );
}
