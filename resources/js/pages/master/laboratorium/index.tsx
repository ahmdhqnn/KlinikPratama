import { Head, Link, router, useForm } from '@inertiajs/react';
import { FlaskConical, Plus, Search } from 'lucide-react';
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

interface Laboratory { id: number; code: string; name: string; clinicId: number | null; clinic: string | null; tariff: number; description: string | null; active: boolean }
interface Props { laboratories: PaginationData & { data: Laboratory[] }; filters: { search: string }; clinics: { id: number; name: string }[] }
const emptyForm = { kode: '', nama: '', poliklinik_id: '', tarif: '0', deskripsi: '', is_active: true };

export default function LaboratoryIndex({ laboratories, filters, clinics }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<Laboratory | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);
    function filter(event: FormEvent<HTMLFormElement>) { event.preventDefault(); router.get('/master/laboratorium', { search }, { preserveState: true, preserveScroll: true, replace: true }); }
    function create() { setEditing(null); form.clearErrors(); form.setData({ ...emptyForm }); setOpen(true); }
    function edit(item: Laboratory) { setEditing(item); form.clearErrors(); form.setData({ kode: item.code, nama: item.name, poliklinik_id: String(item.clinicId ?? ''), tarif: String(item.tariff), deskripsi: item.description ?? '', is_active: item.active }); setOpen(true); }
    function save(event: FormEvent<HTMLFormElement>) { event.preventDefault(); const options = { preserveScroll: true, onSuccess: () => setOpen(false) }; if (editing) { form.put(`/master/laboratorium/${editing.id}`, options); } else { form.post('/master/laboratorium', options); } }
    function remove(item: Laboratory) { confirmAction(`Hapus pemeriksaan ${item.name}?`, () => { router.delete(`/master/laboratorium/${item.id}`, { preserveScroll: true }); }, 'Hapus'); }

    return <>
        <Head title="Master Laboratorium" />
        <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Katalog diagnostik</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laboratorium</h2><p className="mt-1 text-sm text-neutral-500">Kelola pemeriksaan internal, indikator hasil, nilai rujukan, dan BHP.</p></div><Button onClick={create}><Plus className="size-4" />Tambah pemeriksaan</Button></div>
            <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><FlaskConical className="size-5" /></span><div><CardTitle>Daftar pemeriksaan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(laboratories.total)} pemeriksaan terdaftar.</CardDescription></div></div><form className="flex gap-3 pt-3" onSubmit={filter}><Input aria-label="Cari pemeriksaan" className="max-w-xl" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode pemeriksaan…" value={search} /><Button type="submit"><Search className="size-4" />Cari</Button></form></CardHeader>
                <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>Kode</TableHead><TableHead>Nama pemeriksaan</TableHead><TableHead>Poliklinik</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{laboratories.data.length ? laboratories.data.map((item) => <TableRow key={item.id}><TableCell className="font-mono text-xs font-semibold">{item.code}</TableCell><TableCell className="font-medium text-neutral-900">{item.name}</TableCell><TableCell>{item.clinic ?? 'Umum'}</TableCell><TableCell>{item.active ? 'Aktif' : 'Nonaktif'}</TableCell><TableCell><div className="flex justify-end gap-1"><Button asChild size="sm" variant="ghost"><Link href={`/master/laboratorium/${item.id}`}>Indikator & BHP</Link></Button><Button onClick={() => edit(item)} size="sm" variant="secondary">Edit</Button><Button onClick={() => remove(item)} size="sm" variant="destructive">Hapus</Button></div></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada pemeriksaan sesuai pencarian." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={laboratories} /></CardContent>
            </Card>
        </div>
        <Dialog onOpenChange={setOpen} open={open}><DialogContent className="max-w-xl"><DialogHeader><DialogTitle>{editing ? 'Edit pemeriksaan' : 'Tambah pemeriksaan'}</DialogTitle><DialogDescription>Atur identitas dan status pemeriksaan internal.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={save}><div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.kode} htmlFor="laboratory-code" label="Kode" required><Input id="laboratory-code" maxLength={30} onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field></div><Field error={form.errors.nama} htmlFor="laboratory-name" label="Nama pemeriksaan" required><Input id="laboratory-name" maxLength={200} onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field><Field error={form.errors.poliklinik_id} htmlFor="laboratory-clinic" label="Poliklinik"><Select id="laboratory-clinic" onChange={(event) => form.setData('poliklinik_id', event.target.value)} value={form.data.poliklinik_id}><option value="">Umum</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field><Field error={form.errors.deskripsi} htmlFor="laboratory-description" label="Deskripsi"><Textarea id="laboratory-description" onChange={(event) => form.setData('deskripsi', event.target.value)} rows={3} value={form.data.deskripsi} /></Field><Label className="flex items-center gap-2 text-sm text-neutral-700"><Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', (checked === true))} />Pemeriksaan aktif</Label><div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan pemeriksaan'}</Button></div></form></DialogContent></Dialog>
    </>;
}
