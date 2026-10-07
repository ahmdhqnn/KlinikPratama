import { Head, Link, router, useForm } from '@inertiajs/react';
import { ClipboardList, Plus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/format';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface PackageItem { id: number; code: string; name: string; description: string | null; tariff: number; active: boolean; itemCount: number }
interface Props { packages: PaginationData & { data: PackageItem[] }; filters: { search: string } }
const emptyForm = { kode: '', nama: '', tarif: '0', deskripsi: '', is_active: true };

export default function PackageIndex({ packages, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<PackageItem | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/master/paket-tindakan', { search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function create() {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm });
        setOpen(true);
    }

    function edit(item: PackageItem) {
        setEditing(item);
        form.clearErrors();
        form.setData({ kode: item.code, nama: item.name, tarif: String(item.tariff), deskripsi: item.description ?? '', is_active: item.active });
        setOpen(true);
    }

    function save(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) {
            form.put(`/master/paket-tindakan/${editing.id}`, options);
        } else {
            form.post('/master/paket-tindakan', options);
        }
    }

    function remove(item: PackageItem) {
        confirmAction(`Hapus paket ${item.name}?`, () => { router.delete(`/master/paket-tindakan/${item.id}`, { preserveScroll: true }); }, 'Hapus');
    }

    return <>
        <Head title="Paket Tindakan" />
        <div className="space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div><p className="text-sm font-medium text-neutral-700">Katalog layanan terintegrasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Paket tindakan</h2><p className="mt-1 text-sm text-neutral-500">Susun paket tindakan dan laboratorium dengan tarif khusus.</p></div>
                <Button onClick={create}><Plus className="size-4" />Tambah paket</Button>
            </div>
            <Card className="overflow-hidden">
                <CardHeader className="border-b border-neutral-100">
                    <div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ClipboardList className="size-5" /></span><div><CardTitle>Daftar paket layanan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(packages.total)} paket terdaftar.</CardDescription></div></div>
                    <form className="flex gap-3 pt-3" onSubmit={filter}><Input aria-label="Cari paket" className="max-w-xl" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode paket…" value={search} /><Button type="submit"><Search className="size-4" />Cari</Button></form>
                </CardHeader>
                <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>Kode</TableHead><TableHead>Nama paket</TableHead><TableHead>Komposisi</TableHead><TableHead>Deskripsi</TableHead><TableHead className="text-right">Tarif paket</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>
                    {packages.data.length ? packages.data.map((item) => <TableRow key={item.id}><TableCell className="font-mono text-xs font-semibold">{item.code}</TableCell><TableCell className="font-medium text-neutral-900">{item.name}</TableCell><TableCell>{item.itemCount} layanan</TableCell><TableCell className="max-w-64 truncate">{item.description || '—'}</TableCell><TableCell className="text-right font-semibold">{formatCurrency(item.tariff)}</TableCell><TableCell>{item.active ? 'Aktif' : 'Nonaktif'}</TableCell><TableCell><div className="flex justify-end gap-1"><Button asChild size="sm" variant="ghost"><Link href={`/master/paket-tindakan/${item.id}`}>Atur layanan</Link></Button><Button onClick={() => edit(item)} size="sm" variant="secondary">Edit</Button><Button onClick={() => remove(item)} size="sm" variant="destructive">Hapus</Button></div></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-neutral-500" colSpan={7}>Belum ada paket sesuai pencarian.</TableCell></TableRow>}
                </TableBody></Table></div><Pagination pagination={packages} /></CardContent>
            </Card>
        </div>
        <Dialog onOpenChange={setOpen} open={open}><DialogContent className="max-w-xl"><DialogHeader><DialogTitle>{editing ? 'Edit paket tindakan' : 'Tambah paket tindakan'}</DialogTitle><DialogDescription>Atur identitas, tarif, dan status paket layanan.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={save}>
            <div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.kode} htmlFor="package-code" label="Kode" required><Input id="package-code" onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field><Field error={form.errors.tarif} htmlFor="package-tariff" label="Tarif paket (Rp)" required><Input id="package-tariff" min="0" onChange={(event) => form.setData('tarif', event.target.value)} required type="number" value={form.data.tarif} /></Field></div>
            <Field error={form.errors.nama} htmlFor="package-name" label="Nama paket" required><Input id="package-name" onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field>
            <Field error={form.errors.deskripsi} htmlFor="package-description" label="Deskripsi"><Textarea id="package-description" onChange={(event) => form.setData('deskripsi', event.target.value)} rows={3} value={form.data.deskripsi} /></Field>
            <Label className="flex items-center gap-2 text-sm text-neutral-700"><Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', (checked === true))} />Paket aktif</Label>
            <div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan paket'}</Button></div>
        </form></DialogContent></Dialog>
    </>;
}
