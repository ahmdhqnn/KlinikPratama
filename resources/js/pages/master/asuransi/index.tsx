import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Search, ShieldCheck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface Insurance { id: number; code: string; name: string; type: string; address: string | null; phone: string | null; notes: string | null; active: boolean }
interface Props { insurances: PaginationData & { data: Insurance[] }; filters: { search: string; type: string }; types: { value: string; label: string }[] }
const emptyForm = { kode: '', nama: '', jenis: 'umum', alamat: '', telepon: '', catatan: '', is_active: true };

export default function InsuranceIndex({ insurances, filters, types }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [type, setType] = useState(filters.type);
    const [editing, setEditing] = useState<Insurance | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);
    function filter(event: FormEvent<HTMLFormElement>) { event.preventDefault(); router.get('/master/asuransi', { search, jenis: type }, { preserveState: true, preserveScroll: true, replace: true }); }
    function create() { setEditing(null); form.clearErrors(); form.setData({ ...emptyForm }); setOpen(true); }
    function edit(item: Insurance) { setEditing(item); form.clearErrors(); form.setData({ kode: item.code, nama: item.name, jenis: item.type, alamat: item.address ?? '', telepon: item.phone ?? '', catatan: item.notes ?? '', is_active: item.active }); setOpen(true); }
    function save(event: FormEvent<HTMLFormElement>) { event.preventDefault(); const options = { preserveScroll: true, onSuccess: () => setOpen(false) }; if (editing) { form.put(`/master/asuransi/${editing.id}`, options); } else { form.post('/master/asuransi', options); } }
    function remove(item: Insurance) { confirmAction(`Hapus penjamin ${item.name}?`, () => { router.delete(`/master/asuransi/${item.id}`, { preserveScroll: true }); }, 'Hapus'); }

    return <>
        <Head title="Asuransi & Penjamin" />
        <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Administrasi penjamin</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Asuransi & penjamin</h2><p className="mt-1 text-sm text-neutral-500">Kelola skema penjamin dan harga obat khusus untuk pasien.</p></div><Button onClick={create}><Plus className="size-4" />Tambah penjamin</Button></div>
            <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ShieldCheck className="size-5" /></span><div><CardTitle>Daftar penjamin</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(insurances.total)} penjamin tercatat.</CardDescription></div></div><form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_14rem_auto]" onSubmit={filter}><Input aria-label="Cari penjamin" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode…" value={search} /><Select aria-label="Jenis penjamin" onChange={(event) => setType(event.target.value)} value={type}><option value="">Semua jenis</option>{types.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select><Button type="submit"><Search className="size-4" />Filter</Button></form></CardHeader>
                <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[900px]"><TableHeader><tr><TableHead>Kode</TableHead><TableHead>Nama penjamin</TableHead><TableHead>Jenis</TableHead><TableHead>Telepon</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{insurances.data.length ? insurances.data.map((item) => <TableRow key={item.id}><TableCell className="font-mono text-xs font-semibold">{item.code}</TableCell><TableCell className="font-medium text-neutral-900">{item.name}</TableCell><TableCell className="capitalize">{types.find((entry) => entry.value === item.type)?.label ?? item.type}</TableCell><TableCell>{item.phone ?? '—'}</TableCell><TableCell>{item.active ? 'Aktif' : 'Nonaktif'}</TableCell><TableCell><div className="flex justify-end gap-1"><Button asChild size="sm" variant="ghost"><Link href={`/master/asuransi/${item.id}`}>Harga khusus</Link></Button><Button onClick={() => edit(item)} size="sm" variant="secondary">Edit</Button><Button onClick={() => remove(item)} size="sm" variant="destructive">Hapus</Button></div></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-neutral-500" colSpan={6}>Belum ada penjamin sesuai filter.</TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={insurances} /></CardContent>
            </Card>
        </div>
        <Dialog onOpenChange={setOpen} open={open}><DialogContent className="max-w-xl"><DialogHeader><DialogTitle>{editing ? 'Edit penjamin' : 'Tambah penjamin'}</DialogTitle><DialogDescription>Atur identitas, jenis penjamin, dan informasi kontak.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={save}><div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.kode} htmlFor="insurance-code" label="Kode" required><Input id="insurance-code" maxLength={30} onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field><Field error={form.errors.jenis} htmlFor="insurance-type" label="Jenis" required><Select id="insurance-type" onChange={(event) => form.setData('jenis', event.target.value)} value={form.data.jenis}>{types.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field></div><Field error={form.errors.nama} htmlFor="insurance-name" label="Nama penjamin" required><Input id="insurance-name" maxLength={200} onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field><Field error={form.errors.telepon} htmlFor="insurance-phone" label="Telepon / PIC"><Input id="insurance-phone" maxLength={20} onChange={(event) => form.setData('telepon', event.target.value)} value={form.data.telepon} /></Field><Field error={form.errors.alamat} htmlFor="insurance-address" label="Alamat"><Textarea id="insurance-address" onChange={(event) => form.setData('alamat', event.target.value)} rows={2} value={form.data.alamat} /></Field><Field error={form.errors.catatan} htmlFor="insurance-notes" label="Catatan"><Textarea id="insurance-notes" onChange={(event) => form.setData('catatan', event.target.value)} rows={2} value={form.data.catatan} /></Field><Label className="flex items-center gap-2 text-sm text-neutral-700"><Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', (checked === true))} />Penjamin aktif</Label><div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan penjamin'}</Button></div></form></DialogContent></Dialog>
    </>;
}
