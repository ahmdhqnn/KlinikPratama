import { Head, router, useForm } from '@inertiajs/react';
import { ClipboardList, Plus } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency } from '@/lib/format';

interface Fee {
    id: number;
    clinicId: number | null;
    clinic: string | null;
    doctorId: number | null;
    doctor: string | null;
    patientType: string;
    tariff: number;
}

interface Props {
    fees: PaginationData & { data: Fee[] };
    clinics: { id: number; name: string }[];
    doctors: { id: number; name: string }[];
    patientTypes: { value: string; label: string }[];
}

const emptyFee = { poliklinik_id: '', dokter_id: '', jenis_pasien: 'baru', tarif: '0' };

export default function RegistrationFees({ fees, clinics, doctors, patientTypes }: Props) {
    const [editingFee, setEditingFee] = useState<Fee | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm(emptyFee);

    function createFee() {
        setEditingFee(null);
        form.clearErrors();
        form.setData({ ...emptyFee });
        setDialogOpen(true);
    }

    function editFee(fee: Fee) {
        setEditingFee(fee);
        form.clearErrors();
        form.setData({ poliklinik_id: String(fee.clinicId ?? ''), dokter_id: String(fee.doctorId ?? ''), jenis_pasien: fee.patientType, tarif: String(fee.tariff) });
        setDialogOpen(true);
    }

    function saveFee(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setDialogOpen(false) };
        if (editingFee) form.put(`/master/biaya-pendaftaran/${editingFee.id}`, options);
        else form.post('/master/biaya-pendaftaran', options);
    }

    function deleteFee(fee: Fee) {
        if (window.confirm(`Hapus tarif pendaftaran untuk pasien ${fee.patientType}?`)) router.delete(`/master/biaya-pendaftaran/${fee.id}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Biaya Pendaftaran" />
            <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-blue-700">Konfigurasi tarif awal layanan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Biaya pendaftaran</h2><p className="mt-1 text-sm text-slate-500">Atur tarif sesuai poliklinik, dokter, dan kategori pasien.</p></div><Button onClick={createFee}><Plus className="size-4" />Tambah tarif</Button></div>
                <Card className="overflow-hidden"><CardHeader className="border-b border-slate-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><ClipboardList className="size-5" /></span><div><CardTitle>Daftar tarif registrasi</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(fees.total)} tarif tersimpan.</CardDescription></div></div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[750px]"><TableHeader><tr><TableHead>Poliklinik</TableHead><TableHead>Dokter</TableHead><TableHead>Jenis pasien</TableHead><TableHead className="text-right">Tarif pendaftaran</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{fees.data.length ? fees.data.map((fee) => <TableRow key={fee.id}><TableCell className="font-medium text-slate-900">{fee.clinic ?? 'Semua poliklinik'}</TableCell><TableCell>{fee.doctor ?? 'Semua dokter'}</TableCell><TableCell><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${fee.patientType === 'baru' ? 'bg-blue-50 text-blue-700' : 'bg-violet-50 text-violet-700'}`}>Pasien {fee.patientType}</span></TableCell><TableCell className="text-right font-semibold">{formatCurrency(fee.tariff)}</TableCell><TableCell><div className="flex justify-end gap-1"><Button onClick={() => editFee(fee)} size="sm" variant="secondary">Edit</Button><Button onClick={() => deleteFee(fee)} size="sm" variant="destructive">Hapus</Button></div></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-slate-500" colSpan={5}>Belum ada tarif pendaftaran yang diatur.</TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={fees} /></CardContent></Card>
            </div>
            <Dialog onOpenChange={setDialogOpen} open={dialogOpen}><DialogContent><DialogHeader><DialogTitle>{editingFee ? 'Edit tarif pendaftaran' : 'Tambah tarif pendaftaran'}</DialogTitle><DialogDescription>Tarif dapat ditentukan khusus untuk poli dan dokter, atau berlaku umum.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={saveFee}><Field error={form.errors.poliklinik_id} htmlFor="registration-clinic" label="Poliklinik"><NativeSelect id="registration-clinic" onChange={(event) => form.setData('poliklinik_id', event.target.value)} value={form.data.poliklinik_id}><option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</NativeSelect></Field><Field error={form.errors.dokter_id} htmlFor="registration-doctor" label="Dokter (opsional)"><NativeSelect id="registration-doctor" onChange={(event) => form.setData('dokter_id', event.target.value)} value={form.data.dokter_id}><option value="">Semua dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name}</option>)}</NativeSelect></Field><Field error={form.errors.jenis_pasien} htmlFor="registration-patient-type" label="Jenis pasien" required><NativeSelect id="registration-patient-type" onChange={(event) => form.setData('jenis_pasien', event.target.value)} value={form.data.jenis_pasien}>{patientTypes.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}</NativeSelect></Field><Field error={form.errors.tarif} htmlFor="registration-fee" label="Tarif (Rp)" required><Input id="registration-fee" min="0" onChange={(event) => form.setData('tarif', event.target.value)} required type="number" value={form.data.tarif} /></Field><div className="flex justify-end gap-2 border-t border-slate-100 pt-4"><Button onClick={() => setDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan tarif'}</Button></div></form></DialogContent></Dialog>
        </>
    );
}
