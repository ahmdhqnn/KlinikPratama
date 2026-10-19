import { Head, Link, router, useForm } from '@inertiajs/react';
import { HeartPulse, Plus, Search } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface Treatment {
    id: number; code: string; icd9Code: string | null; name: string; category: string;
    clinicId: number | null; clinic: string | null; active: boolean;
}
interface Props {
    treatments: PaginationData & { data: Treatment[] };
    filters: { search: string; category: string };
    clinics: { id: number; name: string }[];
    categories: { value: string; label: string }[];
    procedureCatalogAvailable: boolean;
}
interface ProcedureCode { code: string; display: string; release: string | null }
const empty = { kode: '', kode_icd9: '', nama: '', kategori: 'medis', poliklinik_id: '', is_active: true };

export default function TreatmentIndex({ treatments, filters, clinics, categories, procedureCatalogAvailable }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [category, setCategory] = useState(filters.category);
    const [editing, setEditing] = useState<Treatment | null>(null);
    const [open, setOpen] = useState(false);
    const [procedureQuery, setProcedureQuery] = useState('');
    const [procedureResults, setProcedureResults] = useState<ProcedureCode[]>([]);
    const form = useForm(empty);
    useEffect(() => {
        if (!open || !procedureCatalogAvailable || procedureQuery.trim().length < 2) {
            setProcedureResults([]);
            return;
        }
        const abortController = new AbortController();
        const timer = window.setTimeout(() => {
            fetch(`/master/tindakan-terminologi?q=${encodeURIComponent(procedureQuery.trim())}`, { signal: abortController.signal, headers: { Accept: 'application/json' } })
                .then((response) => response.ok ? response.json() as Promise<ProcedureCode[]> : [])
                .then(setProcedureResults)
                .catch(() => { if (!abortController.signal.aborted) setProcedureResults([]); });
        }, 250);
        return () => { window.clearTimeout(timer); abortController.abort(); };
    }, [open, procedureCatalogAvailable, procedureQuery]);
    function filter(event: FormEvent<HTMLFormElement>) { event.preventDefault(); router.get('/master/tindakan', { search, kategori: category }, { preserveState: true, preserveScroll: true, replace: true }); }
    function create() { setEditing(null); setProcedureQuery(''); form.clearErrors(); form.setData({ ...empty }); setOpen(true); }
    function edit(item: Treatment) { setEditing(item); setProcedureQuery(''); form.clearErrors(); form.setData({ kode: item.code, kode_icd9: item.icd9Code ?? '', nama: item.name, kategori: item.category, poliklinik_id: String(item.clinicId ?? ''), is_active: item.active }); setOpen(true); }
    function save(event: FormEvent<HTMLFormElement>) { event.preventDefault(); const options = { preserveScroll: true, onSuccess: () => setOpen(false) }; if (editing) form.put(`/master/tindakan/${editing.id}`, options); else form.post('/master/tindakan', options); }
    function remove(item: Treatment) { confirmAction(`Hapus tindakan ${item.name}?`, () => { router.delete(`/master/tindakan/${item.id}`, { preserveScroll: true }); }, 'Hapus'); }
    return <>
        <Head title="Tindakan Medis" />
        <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Katalog pelayanan klinis</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Tindakan medis</h2><p className="mt-1 text-sm text-neutral-500">Kelola layanan klinis dan pemakaian bahan habis pakai tiap tindakan.</p></div><Button onClick={create}><Plus className="size-4" />Tambah tindakan</Button></div>
            <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><HeartPulse className="size-5" /></span><div><CardTitle>Daftar tindakan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(treatments.total)} tindakan tercatat.</CardDescription></div></div><form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_13rem_auto]" onSubmit={filter}><Input aria-label="Cari tindakan" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode…" value={search} /><Select aria-label="Kategori tindakan" onChange={(event) => setCategory(event.target.value)} value={category}><option value="">Semua kategori</option>{categories.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select><Button type="submit"><Search className="size-4" />Filter</Button></form></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[1050px]"><TableHeader><tr><TableHead>Kode / ICD-9</TableHead><TableHead>Nama tindakan</TableHead><TableHead>Poliklinik</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{treatments.data.length ? treatments.data.map((item) => <TableRow key={item.id}><TableCell className="font-mono text-xs font-semibold">{item.code}{item.icd9Code && <span className="mt-1 block font-normal text-neutral-500">ICD-9: {item.icd9Code}</span>}</TableCell><TableCell className="font-medium text-neutral-900">{item.name}<span className="mt-1 block text-xs capitalize text-neutral-500">{item.category}</span></TableCell><TableCell>{item.clinic ?? 'Semua poli'}</TableCell><TableCell>{item.active ? 'Aktif' : 'Nonaktif'}</TableCell><TableCell><div className="flex justify-end gap-1"><Button asChild size="sm" variant="ghost"><Link href={`/master/tindakan/${item.id}`}>Atur BHP</Link></Button><Button onClick={() => edit(item)} size="sm" variant="secondary">Edit</Button><Button onClick={() => remove(item)} size="sm" variant="destructive">Hapus</Button></div></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada tindakan sesuai filter." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={treatments} /></CardContent></Card></div>
        <Dialog onOpenChange={setOpen} open={open}><DialogContent className="max-w-2xl"><DialogHeader><DialogTitle>{editing ? 'Edit tindakan medis' : 'Tambah tindakan medis'}</DialogTitle><DialogDescription>Catat tindakan klinis dan kode prosedur ICD-9-CM bila tersedia. Kode lokal dan nama tindakan tetap dapat diisi manual.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={save}>
            <div className="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50 p-3">
                {procedureCatalogAvailable ? <>
                    <Field htmlFor="procedure-search" label="Cari prosedur ICD-9-CM pada katalog"><Input id="procedure-search" placeholder="Kode atau nama prosedur, minimal 2 karakter" value={procedureQuery} onChange={(event) => setProcedureQuery(event.target.value)} /></Field>
                    {procedureResults.length > 0 && <div className="max-h-40 space-y-1 overflow-y-auto">{procedureResults.map((procedure) => <Button className="h-auto w-full justify-start whitespace-normal text-left" key={procedure.code} type="button" variant="ghost" onClick={() => { form.setData({ ...form.data, kode_icd9: procedure.code, nama: procedure.display }); setProcedureQuery(''); }}>{procedure.code + ' · ' + procedure.display + (procedure.release ? ' (' + procedure.release + ')' : '')}</Button>)}</div>}
                </> : <p className="text-sm text-neutral-600">Katalog prosedur belum diimpor. Admin tetap dapat mengisi kode dan nama tindakan secara manual melalui formulir ini.</p>}
            </div><div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.kode} htmlFor="treatment-code" label="Kode lokal" required><Input id="treatment-code" onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field><Field error={form.errors.kode_icd9} htmlFor="treatment-icd9" label="Kode prosedur ICD-9-CM"><Input id="treatment-icd9" placeholder="Contoh: 86.59" onChange={(event) => form.setData('kode_icd9', event.target.value)} value={form.data.kode_icd9} /></Field></div><Field error={form.errors.nama} htmlFor="treatment-name" label="Nama tindakan" required><Input id="treatment-name" onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field><div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.kategori} htmlFor="treatment-category" label="Kategori" required><Select id="treatment-category" onChange={(event) => form.setData('kategori', event.target.value)} value={form.data.kategori}>{categories.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field><Field error={form.errors.poliklinik_id} htmlFor="treatment-clinic" label="Poliklinik"><Select id="treatment-clinic" onChange={(event) => form.setData('poliklinik_id', event.target.value)} value={form.data.poliklinik_id}><option value="">Semua poli</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select></Field></div><Label className="flex items-center gap-2 text-sm text-neutral-700"><Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', (checked === true))} />Tindakan aktif</Label><div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan tindakan'}</Button></div></form></DialogContent></Dialog>
    </>;
}
