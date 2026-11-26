import { MasterExportButton } from '@/components/admin/master-export-button';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { ActionButton } from '@/components/ui/action-button';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, DoorOpen, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { ReportTableCard } from '@/components/dashboard/report-table-card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StatCard } from '@/components/dashboard/stat-card';
import { Select } from '@/components/ui/select';

interface Props {
    clinic: { id: number; code: string; name: string; rooms: { id: number; name: string; active: boolean }[] };
}

export default function ClinicRooms({ clinic }: Props) {
    const url = usePage().url;
    const params = new URLSearchParams(url.split('?')[1]);
    const search = (params.get('search') ?? '').trim().toLocaleLowerCase('id-ID');
    const status = params.get('status') ?? '';
    const rooms = clinic.rooms.filter((room) => room.name.toLocaleLowerCase('id-ID').includes(search)
        && (!status || room.active === (status === 'aktif')));
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const form = useForm({ nama: '', is_active: true });

    function saveRoom(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        if (editingId !== null) form.put(`/master/poliklinik/ruang/${editingId}`, options);
        else form.post(`/master/poliklinik/${clinic.id}/ruang`, options);
    }

    function reset() {
        setOpen(false);
        setEditingId(null);
        form.reset();
        form.clearErrors();
    }

    function edit(room: Props['clinic']['rooms'][number]) {
        setEditingId(room.id);
        form.clearErrors();
        form.setData({ nama: room.name, is_active: room.active });
        setOpen(true);
    }

    return (
        <>
            <Head title={`Ruang Periksa · ${clinic.name}`} />
            <div className="space-y-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <Button asChild className="mb-3" size="sm" variant="ghost">
                            <Link href="/master/poliklinik"><ArrowLeft className="size-4" />Kembali ke poliklinik</Link>
                        </Button>
                        <p className="text-sm font-medium text-neutral-700">Ruang pemeriksaan</p>
                        <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{clinic.name}</h2>
                    </div>
                    <Button type="button" onClick={() => { reset(); setOpen(true); }}>
                        <Plus className="size-4" />Tambah ruang
                    </Button>
                </header>

                <section aria-label="Ringkasan ruang" className="grid gap-4 sm:grid-cols-2">
                    <StatCard label="Total ruang" value={clinic.rooms.length} description="Ruang terdaftar" icon={<DoorOpen className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Ruang aktif" value={clinic.rooms.filter((room) => room.active).length} description="Ruang yang tersedia" icon={<DoorOpen className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </section>

                <FilterPanel actions={<MasterExportButton href={'/master/export/ruang-poli?poliklinik_id=' + clinic.id} label="Ekspor daftar ruang" />}>
                    <form key={url} className="grid min-w-0 gap-4 sm:grid-cols-2" onSubmit={(event) => {
                        event.preventDefault();
                        const fields = new FormData(event.currentTarget);
                        router.get('/master/poliklinik/' + clinic.id, { search: String(fields.get('search') ?? '').trim(), status: (fields.get('status') === 'semua' ? '' : String(fields.get('status') ?? '')) }, { preserveScroll: true, replace: true });
                    }}>
                        <Field htmlFor="room-search" label="Cari ruang"><Input id="room-search" name="search" defaultValue={params.get('search') ?? ''} maxLength={100} placeholder="Nama ruang" /></Field>
                        <Field htmlFor="room-status" label="Status ruang"><Select id="room-status" name="status" defaultValue={status || 'semua'}><option value="semua">Semua status</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></Select></Field>
                        <FilterSubmit />
                    </form>
                </FilterPanel>
                <ReportTableCard title="Daftar ruang periksa" description={clinic.name} icon={<DoorOpen className="size-5" />}>
                    <Table className="min-w-[20rem]">
                        <TableHeader><TableRow><TableHead>Nama ruang</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                        <TableBody>
                            {rooms.length ? rooms.map((room) => (
                                <TableRow key={room.id}>
                                    <TableCell className="font-medium">{room.name}</TableCell>
                                    <TableCell><Badge>{room.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell>
                                    <TableCell className="text-center"><ActionButton type="button" onClick={() => edit(room)} action="edit" label={`Edit ruang ${room.name}`} /></TableCell>
                                </TableRow>
                            )) : <TableRow><TableCell colSpan={3}><Empty size="compact" title="Tidak ada ruang periksa yang sesuai." /></TableCell></TableRow>}
                        </TableBody>
                    </Table>
                </ReportTableCard>
            </div>

            <Dialog open={open} onOpenChange={(next) => { if (!next && !form.processing) reset(); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingId === null ? 'Tambah ruang periksa' : 'Edit ruang periksa'}</DialogTitle>
                        <DialogDescription>{clinic.name}</DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={saveRoom}>
                        <Field error={form.errors.nama} htmlFor="room-name" label="Nama ruang" required>
                            <Input id="room-name" maxLength={100} onChange={(event) => form.setData('nama', event.target.value)} placeholder="Contoh: Ruang Periksa 1" required value={form.data.nama} />
                        </Field>
                        {editingId !== null && (
                            <Field error={form.errors.is_active} htmlFor="room-status" label="Status">
                                <Select id="room-status" value={String(form.data.is_active)} onChange={(event) => form.setData('is_active', event.target.value === 'true')}>
                                    <option value="true">Aktif</option>
                                    <option value="false">Nonaktif</option>
                                </Select>
                            </Field>
                        )}
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button disabled={form.processing} type="button" variant="secondary" onClick={reset}>Batal</Button>
                            <Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan ruang'}</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
