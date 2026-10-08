import { Head, Link, router, useForm } from '@inertiajs/react';
import { Building2, Plus, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
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

interface Clinic {
    id: number;
    code: string;
    name: string;
    type: string;
    depotId: number | null;
    depotName: string | null;
    active: boolean;
}

interface Props {
    clinics: PaginationData & { data: Clinic[] };
    filters: { search: string };
    depots: { id: number; name: string; code: string }[];
    clinicTypes: { value: string; label: string }[];
}

const emptyClinic = { kode: '', nama: '', jenis: 'umum', depo_obat_id: '', is_active: true };

export default function ClinicIndex({ clinics, filters, depots, clinicTypes }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [editingClinic, setEditingClinic] = useState<Clinic | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm(emptyClinic);

    function applyFilter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/master/poliklinik', { search }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function createClinic() {
        setEditingClinic(null);
        form.clearErrors();
        form.setData({ ...emptyClinic });
        setDialogOpen(true);
    }

    function editClinic(clinic: Clinic) {
        setEditingClinic(clinic);
        form.clearErrors();
        form.setData({ kode: clinic.code, nama: clinic.name, jenis: clinic.type, depo_obat_id: String(clinic.depotId ?? ''), is_active: clinic.active });
        setDialogOpen(true);
    }

    function saveClinic(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        if (editingClinic) form.put(`/master/poliklinik/${editingClinic.id}`, options);
        else form.post('/master/poliklinik', options);
    }

    function deleteClinic(clinic: Clinic) {
        confirmAction(`Hapus poliklinik ${clinic.name}?`, () => { router.delete(`/master/poliklinik/${clinic.id}`, { preserveScroll: true }); }, 'Hapus');
    }

    return (
        <>
            <Head title="Poliklinik" />
            <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Konfigurasi layanan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Poliklinik</h2><p className="mt-1 text-sm text-neutral-500">Kelola unit pelayanan, depo stok, dan ruang pemeriksaan.</p></div><Button onClick={createClinic}><Plus className="size-4" />Tambah poliklinik</Button></div>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Building2 className="size-5" /></span><div><CardTitle>Daftar poliklinik</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(clinics.total)} unit terdaftar.</CardDescription></div></div><form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_auto]" onSubmit={applyFilter}><Label className="sr-only" htmlFor="clinic-search">Cari nama atau kode poli</Label><Input id="clinic-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode poli…" value={search} /><Button type="submit"><Search className="size-4" />Cari</Button></form></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>Kode</TableHead><TableHead>Nama poliklinik</TableHead><TableHead>Jenis unit</TableHead><TableHead>Depo stok</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{clinics.data.length ? clinics.data.map((clinic) => <TableRow key={clinic.id}><TableCell className="font-mono text-xs font-semibold">{clinic.code}</TableCell><TableCell className="font-medium text-neutral-900">{clinic.name}</TableCell><TableCell className="capitalize">{clinic.type}</TableCell><TableCell>{clinic.depotName ?? '—'}</TableCell><TableCell><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${clinic.active ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-600'}`}>{clinic.active ? 'Aktif' : 'Nonaktif'}</span></TableCell><TableCell><div className="flex justify-end gap-1"><Button asChild size="sm" variant="ghost"><Link href={`/master/poliklinik/${clinic.id}`}>Ruang poli</Link></Button><Button onClick={() => editClinic(clinic)} size="sm" variant="secondary">Edit</Button><Button onClick={() => deleteClinic(clinic)} size="sm" variant="destructive">Hapus</Button></div></TableCell></TableRow>) : <TableRow><TableCell colSpan={6}><Empty size="compact" title="Belum ada poliklinik yang sesuai." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={clinics} /></CardContent></Card>
            </div>
            <Dialog onOpenChange={setDialogOpen} open={dialogOpen}><DialogContent><DialogHeader><DialogTitle>{editingClinic ? 'Edit poliklinik' : 'Tambah poliklinik'}</DialogTitle><DialogDescription>Atur identitas unit pelayanan dan sumber stoknya.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={saveClinic}><Field error={form.errors.kode} htmlFor="clinic-code" label="Kode" required><Input id="clinic-code" maxLength={20} onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field><Field error={form.errors.nama} htmlFor="clinic-name" label="Nama poliklinik" required><Input id="clinic-name" maxLength={100} onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field><Field error={form.errors.jenis} htmlFor="clinic-type" label="Jenis unit" required><Select id="clinic-type" onChange={(event) => form.setData('jenis', event.target.value)} value={form.data.jenis}>{clinicTypes.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}</Select></Field><Field error={form.errors.depo_obat_id} htmlFor="clinic-depot" label="Depo stok obat"><Select id="clinic-depot" onChange={(event) => form.setData('depo_obat_id', event.target.value)} value={form.data.depo_obat_id}><option value="">Tanpa depo khusus</option>{depots.map((depot) => <option key={depot.id} value={depot.id}>{depot.name} ({depot.code})</option>)}</Select></Field><Label className="flex items-center gap-2 text-sm text-neutral-700"><Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', (checked === true))} />Poliklinik aktif</Label><div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan poliklinik'}</Button></div></form></DialogContent></Dialog>
        </>
    );
}
