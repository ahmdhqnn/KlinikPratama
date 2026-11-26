import { MasterExportButton } from '@/components/admin/master-export-button';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { ActionButton } from '@/components/ui/action-button';
import { Badge } from '@/components/ui/badge';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { DoorOpen, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { ReportTableCard } from '@/components/dashboard/report-table-card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FormActionFooter } from '@/components/ui/form-action-footer';
import { Input } from '@/components/ui/input';
import { PageHeaderWithActions } from '@/components/ui/page-header';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { StatCard } from '@/components/dashboard/stat-card';
import { Select } from '@/components/ui/select';

interface RoomItem {
    id: number;
    code?: string | null;
    name: string;
    satusehatLocationId?: string | null;
    physicalTypeCode?: string | null;
    active: boolean;
}

interface Props {
    clinic: { id: number; code: string; name: string; rooms: RoomItem[] };
}

export default function ClinicRooms({ clinic }: Props) {
    const url = usePage().url;
    const params = new URLSearchParams(url.split('?')[1]);
    const search = (params.get('search') ?? '').trim().toLocaleLowerCase('id-ID');
    const status = params.get('status') ?? '';
    const rooms = clinic.rooms.filter((room) => (room.name.toLocaleLowerCase('id-ID').includes(search) || (room.code ?? '').toLocaleLowerCase('id-ID').includes(search))
        && (!status || room.active === (status === 'aktif')));
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const form = useForm({ kode: '', nama: '', satusehat_location_id: '', physical_type_code: 'ro', is_active: true });

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

    function edit(room: RoomItem) {
        setEditingId(room.id);
        form.clearErrors();
        form.setData({
            kode: room.code ?? '',
            nama: room.name,
            satusehat_location_id: room.satusehatLocationId ?? '',
            physical_type_code: room.physicalTypeCode ?? 'ro',
            is_active: room.active,
        });
        setOpen(true);
    }

    return (
        <>
            <Head title={`Ruang Periksa · ${clinic.name}`} />
            <div className="space-y-6">
                <PageHeaderWithActions
                    actions={
                        <Button onClick={() => { reset(); setOpen(true); }} size="sm" type="button">
                            <Plus className="size-4" />Tambah ruang
                        </Button>
                    }
                    backHref="/master/poliklinik"
                    backLabel="Kembali ke poliklinik"
                    eyebrow="Ruang pemeriksaan"
                    subtitle={`Kode poli ${clinic.code} · Kelola ruang periksa dan Location ID SATUSEHAT`}
                    title={clinic.name}
                />

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
                        <Field htmlFor="room-search" label="Cari ruang"><Input id="room-search" name="search" defaultValue={params.get('search') ?? ''} maxLength={100} placeholder="Nama atau kode ruang" /></Field>
                        <Field htmlFor="room-status" label="Status ruang"><Select id="room-status" name="status" defaultValue={status || 'semua'}><option value="semua">Semua status</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></Select></Field>
                        <FilterSubmit />
                    </form>
                </FilterPanel>
                <ReportTableCard title="Daftar ruang periksa" description={clinic.name} icon={<DoorOpen className="size-5" />}>
                    <Table className="min-w-[34rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Kode</TableHead>
                                <TableHead>Nama ruang</TableHead>
                                <TableHead>Location ID SATUSEHAT</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rooms.length ? rooms.map((room) => (
                                <TableRow key={room.id}>
                                    <TableCell className="text-xs font-semibold tabular-nums text-neutral-700">{room.code || '—'}</TableCell>
                                    <TableCell className="font-medium text-neutral-950">{room.name}</TableCell>
                                    <TableCell className="text-xs tabular-nums text-neutral-600">{room.satusehatLocationId || 'Belum ditautkan'}</TableCell>
                                    <TableCell><Badge variant={room.active ? 'success' : 'secondary'}>{room.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell>
                                    <TableCell className="text-center"><ActionButton type="button" onClick={() => edit(room)} action="edit" label={`Edit ruang ${room.name}`} /></TableCell>
                                </TableRow>
                            )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Tidak ada ruang periksa yang sesuai." /></TableCell></TableRow>}
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
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field error={form.errors.kode} htmlFor="room-code" label="Kode ruang">
                                <Input id="room-code" maxLength={32} onChange={(event) => form.setData('kode', event.target.value)} placeholder="Contoh: PU-01" value={form.data.kode} />
                            </Field>
                            <Field error={form.errors.physical_type_code} htmlFor="room-physical-type" label="Tipe lokasi FHIR">
                                <Select id="room-physical-type" onChange={(event) => form.setData('physical_type_code', event.target.value)} value={form.data.physical_type_code}>
                                    <option value="ro">Room (ro)</option>
                                    <option value="wa">Ward / Ruang Perawatan (wa)</option>
                                    <option value="area">Area (area)</option>
                                </Select>
                            </Field>
                        </div>
                        <Field error={form.errors.nama} htmlFor="room-name" label="Nama ruang" required>
                            <Input id="room-name" maxLength={100} onChange={(event) => form.setData('nama', event.target.value)} placeholder="Contoh: Ruang Periksa 1" required value={form.data.nama} />
                        </Field>
                        <Field error={form.errors.satusehat_location_id} htmlFor="room-satusehat-id" label="Location ID SATUSEHAT (opsional)">
                            <Input id="room-satusehat-id" maxLength={64} onChange={(event) => form.setData('satusehat_location_id', event.target.value)} placeholder="UUID Location dari SATUSEHAT" value={form.data.satusehat_location_id} />
                        </Field>
                        {editingId !== null && (
                            <Field error={form.errors.is_active} htmlFor="room-status-edit" label="Status">
                                <Select id="room-status-edit" value={String(form.data.is_active)} onChange={(event) => form.setData('is_active', event.target.value === 'true')}>
                                    <option value="true">Aktif</option>
                                    <option value="false">Nonaktif</option>
                                </Select>
                            </Field>
                        )}
                        <FormActionFooter className="pt-3">
                            <Button disabled={form.processing} type="button" variant="secondary" onClick={reset}>Batal</Button>
                            <Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan ruang'}</Button>
                        </FormActionFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
