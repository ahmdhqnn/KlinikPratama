import { ActionButton } from '@/components/ui/action-button';
import { Badge } from '@/components/ui/badge';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileUp, Package, Plus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Attachment } from '@/components/ui/attachment';

interface Medicine {
    id: number; code: string; kfaCode: string | null; name: string; largeUnit: string | null; smallUnit: string | null;
    unitConversion: number; stock: number; minimumStock: number;
    indication: string | null; ingredients: string | null; type: string; active: boolean;
}
interface Props { medicines: PaginationData & { data: Medicine[] }; filters: { search: string; type: string; lowStock: boolean } }
const emptyForm = {
    kode: '', kode_kfa: '', nama: '', satuan_besar: '', satuan_kecil: '', konversi_satuan: '1',
    stok: '0', stok_minimum: '10', jenis: 'obat',
    indikasi: '', kandungan: '', is_active: true,
};

export default function MedicineIndex({ medicines, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [type, setType] = useState(filters.type);
    const [lowStock, setLowStock] = useState(filters.lowStock);
    const [editing, setEditing] = useState<Medicine | null>(null);
    const [open, setOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const form = useForm(emptyForm);
    const importForm = useForm<{ file: File | null }>({ file: null });

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/master/obat', { search, jenis: type, stok_rendah: lowStock ? '1' : '' }, { preserveState: true, preserveScroll: true, replace: true });
    }
    function create() {
        setEditing(null);
        form.clearErrors();
        form.setData({ ...emptyForm });
        setOpen(true);
    }
    function edit(item: Medicine) {
        setEditing(item);
        form.clearErrors();
        form.setData({
            kode: item.code, kode_kfa: item.kfaCode ?? '', nama: item.name, satuan_besar: item.largeUnit ?? '',
            satuan_kecil: item.smallUnit ?? '', konversi_satuan: String(item.unitConversion),
            stok: String(item.stock),
            stok_minimum: String(item.minimumStock), jenis: item.type, indikasi: item.indication ?? '',
            kandungan: item.ingredients ?? '', is_active: item.active,
        });
        setOpen(true);
    }
    function save(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) {
            form.put(`/master/obat/${editing.id}`, options);
        } else {
            form.post('/master/obat', options);
        }
    }
    function remove(item: Medicine) {
        confirmAction(`Hapus ${item.name} dari master obat dan BHP?`, () => { router.delete(`/master/obat/${item.id}`, { preserveScroll: true }); }, 'Hapus');
    }
    function importFile(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        importForm.post('/master/obat-import', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { setImportOpen(false); importForm.reset(); },
        });
    }

    return <>
        <Head title="Master Obat & BHP" />
        <div className="space-y-6">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div><p className="text-sm font-medium text-neutral-700">Katalog farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Obat & bahan habis pakai</h2><p className="mt-1 text-sm text-neutral-500">Kelola identitas, satuan, dan batas stok. Saldo fisik dicatat per batch.</p></div>
                <div className="flex flex-wrap gap-2"><a className="inline-flex h-10 items-center gap-2 rounded-lg border border-neutral-200 bg-surface px-3 text-sm font-medium text-neutral-700 hover:bg-neutral-50" href="/master/obat-template"><Download className="size-4" />Template Excel</a><Button onClick={() => { importForm.clearErrors(); importForm.setData('file', null); setImportOpen(true); }} variant="secondary"><FileUp className="size-4" />Impor Excel</Button><a className="inline-flex h-10 items-center gap-2 rounded-lg bg-neutral-900 px-3 text-sm font-medium text-neutral-50 hover:bg-neutral-800 " href="/master/obat-export"><Download className="size-4" />Ekspor Excel</a><Button onClick={create}><Plus className="size-4" />Tambah item</Button></div>
            </div>
            <Card className="overflow-hidden">
                <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Package className="size-5" /></span><div><CardTitle>Daftar obat & BHP</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(medicines.total)} item terdaftar.</CardDescription></div></div><FilterPanel><form className="grid gap-3  sm:grid-cols-2 lg:grid-cols-4" onSubmit={filter}><Input aria-label="Cari obat atau BHP" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama, kode, atau KFA" value={search} /><Select aria-label="Jenis item" onChange={(event) => setType(event.target.value)} value={type}><option value="">Semua jenis</option><option value="obat">Obat</option><option value="bhp">BHP</option></Select><Label className="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 text-sm text-neutral-700"><Checkbox checked={lowStock} onCheckedChange={(checked) => setLowStock((checked === true))} />Stok kritis</Label><Button type="submit"><Search className="size-4" />Filter</Button></form></FilterPanel></CardHeader>
                <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[900px]"><TableHeader><tr><TableHead>Kode / KFA</TableHead><TableHead>Nama item</TableHead><TableHead>Satuan</TableHead><TableHead className="text-center">Stok / minimum</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></tr></TableHeader><TableBody>
                    {medicines.data.length ? medicines.data.map((item) => <TableRow key={item.id}><TableCell className="font-mono text-xs font-semibold">{item.code}{item.kfaCode && <span className="mt-1 block font-normal text-neutral-700">KFA: {item.kfaCode}</span>}</TableCell><TableCell className="font-medium text-neutral-900">{item.name}<span className="mt-1 block text-xs uppercase text-neutral-500">{item.type}</span></TableCell><TableCell>{item.largeUnit ?? 'Belum diisi'} / {item.smallUnit ?? 'Belum diisi'}{item.unitConversion > 1 && <span className="mt-1 block text-xs text-neutral-500">1 {item.largeUnit} = {item.unitConversion} {item.smallUnit}</span>}</TableCell><TableCell className="text-center"><span className={item.stock <= 0 ? 'font-semibold text-red-700' : item.stock <= item.minimumStock ? 'font-semibold text-amber-700' : 'font-medium text-neutral-700'}>{item.stock} {item.smallUnit ?? ''}</span><span className="mt-1 block text-xs text-neutral-500">Minimum {item.minimumStock}</span></TableCell><TableCell><Badge>{item.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell><TableCell><div className="flex flex-wrap items-center justify-center gap-2"><ActionButton asChild action="stock" label="Kartu stok"><Link href={`/stok/persediaan/${item.id}`} /></ActionButton><ActionButton onClick={() => edit(item)} action="edit" label="Edit" /><ActionButton onClick={() => remove(item)} action="delete" label="Hapus" /></div></TableCell></TableRow>) : <TableRow><TableCell colSpan={6}><Empty size="compact" title="Belum ada obat atau BHP sesuai filter." /></TableCell></TableRow>}
                </TableBody></Table></div><Pagination pagination={medicines} /></CardContent>
            </Card>
        </div>
        <Dialog onOpenChange={setOpen} open={open}><DialogContent className="max-w-3xl"><DialogHeader><DialogTitle>{editing ? 'Edit obat / BHP' : 'Tambah obat / BHP'}</DialogTitle><DialogDescription>Lengkapi informasi produk dan satuan. Stok dicatat melalui penerimaan batch pada Persediaan.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={save}>
            <div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.kode} htmlFor="medicine-code" label="Kode item" required><Input placeholder="Contoh: OBT-001" id="medicine-code" maxLength={30} onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field><Field error={form.errors.kode_kfa} htmlFor="medicine-kfa" label="Kode KFA"><Input placeholder="Kode sesuai katalog KFA" id="medicine-kfa" maxLength={30} onChange={(event) => form.setData('kode_kfa', event.target.value)} value={form.data.kode_kfa} /></Field></div>
            <Field error={form.errors.nama} htmlFor="medicine-name" label="Nama obat / BHP" required><Input placeholder="Nama item dan kekuatan sediaan" id="medicine-name" maxLength={200} onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field>
            <div className="grid gap-4 sm:grid-cols-3"><Field error={form.errors.satuan_besar} htmlFor="medicine-large-unit" label="Satuan besar"><Input placeholder="Contoh: dus atau kotak" id="medicine-large-unit" onChange={(event) => form.setData('satuan_besar', event.target.value)} value={form.data.satuan_besar} /></Field><Field error={form.errors.satuan_kecil} htmlFor="medicine-small-unit" label="Satuan kecil"><Input placeholder="Contoh: tablet, ampul, atau buah" id="medicine-small-unit" onChange={(event) => form.setData('satuan_kecil', event.target.value)} value={form.data.satuan_kecil} /></Field><Field error={form.errors.konversi_satuan} htmlFor="medicine-conversion" label="Isi per satuan besar" required><Input id="medicine-conversion" min="1" onChange={(event) => form.setData('konversi_satuan', event.target.value)} required step="any" type="number" value={form.data.konversi_satuan} /></Field></div>
            <div className="grid gap-4 sm:grid-cols-3"><Field error={form.errors.stok_minimum} htmlFor="medicine-minimum" label="Stok minimum" required><Input id="medicine-minimum" min="0" onChange={(event) => form.setData('stok_minimum', event.target.value)} required type="number" value={form.data.stok_minimum} /></Field><Field error={form.errors.jenis} htmlFor="medicine-type" label="Jenis" required><Select id="medicine-type" onChange={(event) => form.setData('jenis', event.target.value)} value={form.data.jenis}><option value="obat">Obat</option><option value="bhp">BHP</option></Select></Field></div>
            <div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.indikasi} htmlFor="medicine-indication" label="Indikasi"><Textarea id="medicine-indication" placeholder="Indikasi sesuai formularium klinik" onChange={(event) => form.setData('indikasi', event.target.value)} rows={3} value={form.data.indikasi} /></Field><Field error={form.errors.kandungan} htmlFor="medicine-ingredients" label="Kandungan"><Textarea id="medicine-ingredients" placeholder="Zat aktif dan kekuatan sediaan" onChange={(event) => form.setData('kandungan', event.target.value)} rows={3} value={form.data.kandungan} /></Field></div>
            <Label className="flex items-center gap-2 text-sm text-neutral-700"><Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', (checked === true))} />Item aktif</Label><div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan item'}</Button></div>
        </form></DialogContent></Dialog>
        <Dialog onOpenChange={setImportOpen} open={importOpen}><DialogContent><DialogHeader><DialogTitle>Impor obat & BHP</DialogTitle><DialogDescription>Impor katalog sesuai template. Kode item harus unik; penerimaan stok dicatat terpisah beserta batch dan kedaluwarsa.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={importFile}><Attachment accept=".xlsx,.xls,.csv" error={importForm.errors.file} fileName={importForm.data.file?.name} id="medicine-import" label="File Excel / CSV" onFileChange={(file) => importForm.setData('file', file)} required /><div className="flex items-center justify-between"><a className="text-sm font-medium text-neutral-700 hover:text-neutral-800" href="/master/obat-template">Unduh template</a><div className="flex gap-2"><Button onClick={() => setImportOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={importForm.processing} type="submit">{importForm.processing ? 'Mengimpor…' : 'Impor data'}</Button></div></div></form></DialogContent></Dialog>
    </>;
}
