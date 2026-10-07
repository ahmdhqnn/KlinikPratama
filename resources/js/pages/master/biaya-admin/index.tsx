import { Head, router, useForm } from '@inertiajs/react';
import { CircleDollarSign, Plus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/format';

interface Fee {
    id: number;
    name: string;
    tariff: number;
    description: string | null;
    active: boolean;
}

interface Props {
    fees: PaginationData & { data: Fee[] };
    filters: { search: string };
}

const emptyFee = { nama: '', tarif: '0', keterangan: '', is_active: true };

export default function AdministrationFees({ fees, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [editingFee, setEditingFee] = useState<Fee | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm(emptyFee);

    function applyFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/master/biaya-admin', { search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function createFee() {
        setEditingFee(null);
        form.clearErrors();
        form.setData({ ...emptyFee });
        setDialogOpen(true);
    }

    function editFee(fee: Fee) {
        setEditingFee(fee);
        form.clearErrors();
        form.setData({ nama: fee.name, tarif: String(fee.tariff), keterangan: fee.description ?? '', is_active: fee.active });
        setDialogOpen(true);
    }

    function saveFee(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        if (editingFee) form.put(`/master/biaya-admin/${editingFee.id}`, options);
        else form.post('/master/biaya-admin', options);
    }

    function deleteFee(fee: Fee) {
        if (window.confirm(`Hapus biaya ${fee.name}?`)) router.delete(`/master/biaya-admin/${fee.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Biaya Administrasi" />
            <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-blue-700">Tarif layanan nonmedis</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Biaya administrasi</h2><p className="mt-1 text-sm text-slate-500">Atur biaya tambahan seperti administrasi, surat, dan layanan lainnya.</p></div><Button onClick={createFee}><Plus className="size-4" />Tambah biaya</Button></div>
                <Card className="overflow-hidden"><CardHeader className="border-b border-slate-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><CircleDollarSign className="size-5" /></span><div><CardTitle>Daftar biaya</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(fees.total)} biaya terdaftar.</CardDescription></div></div><form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_auto]" onSubmit={applyFilter}><label className="sr-only" htmlFor="fee-search">Cari nama biaya</label><Input id="fee-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama biaya…" value={search} /><Button type="submit"><Search className="size-4" />Cari</Button></form></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[760px]"><TableHeader><tr><TableHead>Nama layanan</TableHead><TableHead className="text-right">Tarif</TableHead><TableHead>Keterangan</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{fees.data.length ? fees.data.map((fee) => <TableRow key={fee.id}><TableCell className="font-medium text-slate-900">{fee.name}</TableCell><TableCell className="text-right font-semibold">{formatCurrency(fee.tariff)}</TableCell><TableCell className="max-w-md truncate text-slate-500">{fee.description || '—'}</TableCell><TableCell><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${fee.active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{fee.active ? 'Aktif' : 'Nonaktif'}</span></TableCell><TableCell><div className="flex justify-end gap-1"><Button onClick={() => editFee(fee)} size="sm" variant="secondary">Edit</Button><Button onClick={() => deleteFee(fee)} size="sm" variant="destructive">Hapus</Button></div></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-slate-500" colSpan={5}>Belum ada biaya administrasi yang sesuai.</TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={fees} /></CardContent></Card>
            </div>
            <Dialog onOpenChange={setDialogOpen} open={dialogOpen}><DialogContent><DialogHeader><DialogTitle>{editingFee ? 'Edit biaya administrasi' : 'Tambah biaya administrasi'}</DialogTitle><DialogDescription>Masukkan tarif layanan tambahan klinik.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={saveFee}><Field error={form.errors.nama} htmlFor="fee-name" label="Nama layanan" required><Input id="fee-name" maxLength={200} onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field><Field error={form.errors.tarif} htmlFor="fee-tariff" label="Tarif (Rp)" required><Input id="fee-tariff" min="0" onChange={(event) => form.setData('tarif', event.target.value)} required type="number" value={form.data.tarif} /></Field><Field error={form.errors.keterangan} htmlFor="fee-description" label="Keterangan"><Textarea id="fee-description" onChange={(event) => form.setData('keterangan', event.target.value)} rows={3} value={form.data.keterangan} /></Field><label className="flex items-center gap-2 text-sm text-slate-700"><input checked={form.data.is_active} className="size-4 rounded border-slate-300 text-blue-600" onChange={(event) => form.setData('is_active', event.target.checked)} type="checkbox" />Biaya aktif</label><div className="flex justify-end gap-2 border-t border-slate-100 pt-4"><Button onClick={() => setDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan biaya'}</Button></div></form></DialogContent></Dialog>
        </>
    );
}
