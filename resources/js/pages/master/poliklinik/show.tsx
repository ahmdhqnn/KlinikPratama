import { MasterExportButton } from '@/components/admin/master-export-button';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { ActionButton } from '@/components/ui/action-button';
import { Badge } from '@/components/ui/badge';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { DoorOpen, Plus, Stethoscope } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

interface ClinicTreatmentItem {
    id: number;
    code: string;
    icd9Code?: string | null;
    name: string;
    canDelegateToNurse: boolean;
    consentMode: 'general_consent' | 'oral' | 'written';
    consentModeLabel: string;
    active: boolean;
}

interface Props {
    clinic: { id: number; code: string; name: string; type?: string; rooms: RoomItem[]; treatments?: ClinicTreatmentItem[] };
    consentModes?: Record<string, string>;
}

const defaultConsentModes: Record<string, string> = {
    general_consent: 'Cukup General Consent',
    oral: 'Persetujuan lisan',
    written: 'Wajib tertulis (tanda tangan)',
};

export default function ClinicRooms({ clinic, consentModes = defaultConsentModes }: Props) {
    const url = usePage().url;
    const params = new URLSearchParams(url.split('?')[1]);
    const search = (params.get('search') ?? '').trim().toLocaleLowerCase('id-ID');
    const status = params.get('status') ?? '';
    const rooms = clinic.rooms.filter((room) => (room.name.toLocaleLowerCase('id-ID').includes(search) || (room.code ?? '').toLocaleLowerCase('id-ID').includes(search))
        && (!status || room.active === (status === 'aktif')));
    const treatments = clinic.treatments ?? [];
    const [open, setOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const form = useForm({ kode: '', nama: '', satusehat_location_id: '', physical_type_code: 'ro', is_active: true });

    const [treatmentOpen, setTreatmentOpen] = useState(false);
    const [editingTreatmentId, setEditingTreatmentId] = useState<number | null>(null);
    const treatmentForm = useForm({
        kode: '',
        kode_icd9: '',
        nama: '',
        can_delegate_to_nurse: true,
        consent_mode: 'general_consent' as 'general_consent' | 'oral' | 'written',
        is_active: true,
    });

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

    function resetTreatment() {
        setTreatmentOpen(false);
        setEditingTreatmentId(null);
        treatmentForm.reset();
        treatmentForm.clearErrors();
    }

    function editTreatment(item: ClinicTreatmentItem) {
        setEditingTreatmentId(item.id);
        treatmentForm.clearErrors();
        treatmentForm.setData({
            kode: item.code,
            kode_icd9: item.icd9Code ?? '',
            nama: item.name,
            can_delegate_to_nurse: item.canDelegateToNurse,
            consent_mode: item.consentMode,
            is_active: item.active,
        });
        setTreatmentOpen(true);
    }

    function saveTreatment(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: resetTreatment };
        if (editingTreatmentId !== null) treatmentForm.put(`/master/poliklinik/tindakan/${editingTreatmentId}`, options);
        else treatmentForm.post(`/master/poliklinik/${clinic.id}/tindakan`, options);
    }

    return (
        <>
            <Head title={`Ruang Periksa · ${clinic.name}`} />
            <div className="space-y-6">
                <PageHeaderWithActions
                    actions={
                        <div className="flex flex-wrap items-center gap-2">
                            <Button onClick={() => { resetTreatment(); setTreatmentOpen(true); }} size="sm" type="button" variant="secondary">
                                <Plus className="size-4" />Tambah tindakan
                            </Button>
                            <Button onClick={() => { reset(); setOpen(true); }} size="sm" type="button">
                                <Plus className="size-4" />Tambah ruang
                            </Button>
                        </div>
                    }
                    backHref="/master/poliklinik"
                    backLabel="Kembali ke poliklinik"
                    eyebrow="Ruang pemeriksaan & master tindakan"
                    subtitle={`Kode poli ${clinic.code} · Kelola ruang periksa, Location ID SATUSEHAT, dan klasifikasi persetujuan tindakan`}
                    title={clinic.name}
                />

                <section aria-label="Ringkasan ruang" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Total ruang" value={clinic.rooms.length} description="Ruang terdaftar" icon={<DoorOpen className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Ruang aktif" value={clinic.rooms.filter((room) => room.active).length} description="Ruang yang tersedia" icon={<DoorOpen className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Master tindakan" value={treatments.length} description="Tindakan medis pada poli ini" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Wajib Informed Consent" value={treatments.filter((item) => item.active && item.consentMode !== 'general_consent').length} description="Tindakan lisan atau tertulis aktif" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
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

                <ReportTableCard
                    title="Master tindakan & klasifikasi persetujuan"
                    description="Atur kewenangan delegasi ke perawat dan jenis persetujuan yang diwajibkan (terpadu dengan SOP Persetujuan Tindakan berbasis ICD-9-CM)."
                    icon={<Stethoscope className="size-5" />}
                >
                    <Table className="min-w-[46rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Kode</TableHead>
                                <TableHead>Nama tindakan</TableHead>
                                <TableHead>ICD-9-CM</TableHead>
                                <TableHead>Kewenangan delegasi</TableHead>
                                <TableHead>Klasifikasi persetujuan</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-center">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {treatments.length ? treatments.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="text-xs font-semibold tabular-nums text-neutral-700">{item.code}</TableCell>
                                    <TableCell className="font-medium text-neutral-950">{item.name}</TableCell>
                                    <TableCell className="text-xs tabular-nums text-neutral-600">{item.icd9Code || '—'}</TableCell>
                                    <TableCell>
                                        <Badge variant={item.canDelegateToNurse ? 'default' : 'warning'}>
                                            {item.canDelegateToNurse ? 'Dapat didelegasikan ke perawat' : 'Khusus dokter'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant={item.consentMode === 'written' ? 'warning' : item.consentMode === 'oral' ? 'default' : 'complete'}>
                                            {item.consentModeLabel}
                                        </Badge>
                                    </TableCell>
                                    <TableCell><Badge variant={item.active ? 'success' : 'secondary'}>{item.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell>
                                    <TableCell className="text-center">
                                        <ActionButton type="button" onClick={() => editTreatment(item)} action="edit" label={`Edit tindakan ${item.name}`} />
                                    </TableCell>
                                </TableRow>
                            )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Belum ada master tindakan pada poliklinik ini." /></TableCell></TableRow>}
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

            <Dialog open={treatmentOpen} onOpenChange={(next) => { if (!next && !treatmentForm.processing) resetTreatment(); }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editingTreatmentId === null ? 'Tambah master tindakan' : 'Edit master tindakan'}</DialogTitle>
                        <DialogDescription>{clinic.name} · Klasifikasi persetujuan otomatis dipadukan dengan SOP Persetujuan Tindakan ICD-9-CM.</DialogDescription>
                    </DialogHeader>
                    <form className="space-y-4" onSubmit={saveTreatment}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field error={treatmentForm.errors.kode} htmlFor="treatment-code" label="Kode tindakan" required>
                                <Input id="treatment-code" maxLength={32} required onChange={(event) => treatmentForm.setData('kode', event.target.value)} placeholder="Contoh: TDK-KP-05" value={treatmentForm.data.kode} />
                            </Field>
                            <Field error={treatmentForm.errors.kode_icd9} htmlFor="treatment-icd9" label="Kode ICD-9-CM (opsional)">
                                <Input id="treatment-icd9" maxLength={32} onChange={(event) => treatmentForm.setData('kode_icd9', event.target.value)} placeholder="Contoh: 93.57" value={treatmentForm.data.kode_icd9} />
                            </Field>
                        </div>
                        <Field error={treatmentForm.errors.nama} htmlFor="treatment-name" label="Nama tindakan" required>
                            <Input id="treatment-name" maxLength={180} required onChange={(event) => treatmentForm.setData('nama', event.target.value)} placeholder="Contoh: Perawatan Luka Steril" value={treatmentForm.data.nama} />
                        </Field>
                        <Field error={treatmentForm.errors.consent_mode} htmlFor="treatment-consent-mode" label="Klasifikasi persetujuan tindakan" required>
                            <Select
                                id="treatment-consent-mode"
                                value={treatmentForm.data.consent_mode}
                                onChange={(event) => treatmentForm.setData('consent_mode', event.target.value as 'general_consent' | 'oral' | 'written')}
                            >
                                {Object.entries(consentModes).map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </Select>
                            <p className="text-xs text-neutral-500">
                                Tindakan invasif/berisiko wajib disetel ke Wajib tertulis (tanda tangan). Tindakan rutin non-invasif cukup mengacu pada General Consent aktif.
                            </p>
                        </Field>
                        <Field error={treatmentForm.errors.can_delegate_to_nurse} htmlFor="treatment-delegate" label="Kewenangan delegasi">
                            <label className="flex cursor-pointer items-start gap-2.5 text-sm leading-6 text-neutral-700">
                                <Checkbox
                                    id="treatment-delegate"
                                    className="mt-1 shrink-0"
                                    checked={treatmentForm.data.can_delegate_to_nurse}
                                    onCheckedChange={(checked) => treatmentForm.setData('can_delegate_to_nurse', checked === true)}
                                />
                                <span>Tindakan ini dapat didelegasikan oleh dokter untuk dilaksanakan perawat sesuai SPO klinik.</span>
                            </label>
                        </Field>
                        {editingTreatmentId !== null && (
                            <Field error={treatmentForm.errors.is_active} htmlFor="treatment-status-edit" label="Status">
                                <Select id="treatment-status-edit" value={String(treatmentForm.data.is_active)} onChange={(event) => treatmentForm.setData('is_active', event.target.value === 'true')}>
                                    <option value="true">Aktif</option>
                                    <option value="false">Nonaktif</option>
                                </Select>
                            </Field>
                        )}
                        <FormActionFooter className="pt-3">
                            <Button disabled={treatmentForm.processing} type="button" variant="secondary" onClick={resetTreatment}>Batal</Button>
                            <Button disabled={treatmentForm.processing} type="submit">{treatmentForm.processing ? 'Menyimpan…' : 'Simpan tindakan'}</Button>
                        </FormActionFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
