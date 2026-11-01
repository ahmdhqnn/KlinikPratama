import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardCheck, Pill, Search, UserRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Empty } from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

interface Patient { id: number; name: string; medicalRecordNumber: string; allergies: string | null }
interface Medicine { id: number; name: string; unit: string; stock: number }
interface Data { pasien_id: string; obat_id: string; jumlah: string; aturan_pakai: string; dasar_pemberian: string; catatan: string }

export default function DirectDispensing({ search, patients, medicines }: { search: string; patients: Patient[]; medicines: Medicine[] }) {
    const [patientMode, setPatientMode] = useState<'list' | 'search'>(search ? 'search' : 'list');
    const form = useForm<Data>({ pasien_id: '', obat_id: '', jumlah: '1', aturan_pakai: '', dasar_pemberian: '', catatan: '' });
    const visiblePatients = patientMode === 'search' && !search ? [] : patients;
    const patient = visiblePatients.find((item) => String(item.id) === form.data.pasien_id);
    const medicine = medicines.find((item) => String(item.id) === form.data.obat_id);
    const availableMedicines = medicines.filter((item) => item.stock > 0);

    function searchPatients(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.setData('pasien_id', '');
        const data = new FormData(event.currentTarget);
        router.get('/farmasi/pemberian-obat/baru', { search: data.get('search') }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/farmasi/pemberian-obat');
    }

    return <><Head title="Catat pemberian obat langsung" /><div className="mx-auto max-w-5xl space-y-6">
        <header className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end"><div><p className="text-sm font-medium text-neutral-700">Pelayanan farmasi</p><h1 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Catat pemberian obat langsung</h1><p className="mt-1 text-sm text-neutral-500">Pastikan pasien, riwayat alergi, obat, dan aturan pakai sebelum diserahkan.</p></div><Button asChild variant="secondary"><Link href="/farmasi/pemberian-obat"><ArrowLeft className="size-4" />Kembali ke obat langsung</Link></Button></header>
        <Tabs className="space-y-5" onValueChange={(value) => { setPatientMode(value as 'list' | 'search'); form.setData('pasien_id', ''); if (value === 'list' && search) router.get('/farmasi/pemberian-obat/baru', {}, { preserveState: true, replace: true }); }} value={patientMode}><TabsList aria-label="Pilih pasien" className="h-auto flex-wrap"><TabsTrigger value="list"><UserRound className="size-4" />Pilih dari daftar</TabsTrigger><TabsTrigger value="search"><Search className="size-4" />Cari pasien</TabsTrigger></TabsList>
        <Card><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><UserRound className="size-5" /></span><div><CardTitle>Pilih pasien</CardTitle><CardDescription className="mt-1">Pilih pasien yang menerima obat langsung.</CardDescription></div></div></CardHeader><CardContent className="space-y-5 pt-5 sm:pt-6">
            <TabsContent className="space-y-4" value="list">
                <Field error={form.errors.pasien_id} htmlFor="direct-patient-list" label="Pasien terdaftar" required><Select id="direct-patient-list" onChange={(event) => form.setData('pasien_id', event.target.value)} required value={patientMode === 'list' ? form.data.pasien_id : ''}><option value="">{visiblePatients.length ? 'Pilih pasien' : 'Pasien tidak ditemukan'}</option>{visiblePatients.map((item) => <option key={item.id} value={item.id}>{item.medicalRecordNumber} · {item.name}</option>)}</Select></Field>
            </TabsContent>
            <TabsContent className="space-y-4" value="search">
            <form className="flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={searchPatients}><Field className="w-full sm:max-w-lg" htmlFor="direct-patient-search" label="Cari pasien"><Input autoComplete="off" defaultValue={search} id="direct-patient-search" name="search" placeholder="Cari nama, No. RM, atau NIK" required /></Field><Button type="submit" variant="secondary"><Search className="size-4" />Cari pasien</Button></form>
            {search && (visiblePatients.length ? <ul aria-label="Hasil pencarian pasien" className="max-h-72 divide-y divide-neutral-100 overflow-y-auto rounded-xl border border-neutral-200">{visiblePatients.map((item) => <li key={item.id}><Button className="h-auto w-full justify-between rounded-none px-4 py-3 text-left font-normal hover:bg-neutral-50/60 hover:text-neutral-900 focus-visible:bg-neutral-50/60" onClick={() => form.setData('pasien_id', String(item.id))} type="button" variant="ghost"><span className="min-w-0"><span className="block truncate text-sm font-semibold text-neutral-900">{item.name}</span><span className="mt-0.5 block text-xs text-neutral-500">{item.medicalRecordNumber}</span></span><span className="shrink-0 text-xs font-medium text-neutral-700">{form.data.pasien_id === String(item.id) ? 'Dipilih' : 'Pilih pasien →'}</span></Button></li>)}</ul> : <Empty className="min-h-0 py-6" title="Pasien tidak ditemukan" description="Periksa kembali nama, No. RM, atau NIK yang Anda masukkan." />)}
            </TabsContent>
            {patient && <div className="rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-sm text-neutral-700"><p className="font-semibold text-neutral-900">{patient.name} · {patient.medicalRecordNumber}</p><p className="mt-1"><strong>Riwayat alergi:</strong> {patient.allergies || 'Belum ada informasi alergi pada data pasien. Pastikan kembali sebelum obat diberikan.'}</p></div>}
        </CardContent></Card>
        <Card><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Pill className="size-5" /></span><div><CardTitle>Obat dan aturan pakai</CardTitle><CardDescription className="mt-1">Obat yang dapat dipilih sudah disetujui untuk pemberian langsung dan tersedia di klinik.</CardDescription></div></div></CardHeader><CardContent className="pt-5 sm:pt-6"><form className="space-y-6" onSubmit={submit}>
            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"><Field className="md:col-span-2" error={form.errors.obat_id} htmlFor="direct-medicine" label="Obat dari stok klinik" required><Select id="direct-medicine" onChange={(event) => form.setData('obat_id', event.target.value)} required value={form.data.obat_id}><option value="">Pilih obat</option>{medicines.map((item) => <option disabled={item.stock <= 0} key={item.id} value={item.id}>{item.name} · {item.stock > 0 ? `stok layak pakai ${item.stock} ${item.unit}` : 'tidak ada stok layak pakai'}</option>)}</Select></Field><Field error={form.errors.jumlah} htmlFor="direct-quantity" label="Jumlah" required><Input id="direct-quantity" min="1" max={medicine?.stock} onChange={(event) => form.setData('jumlah', event.target.value)} required type="number" value={form.data.jumlah} /></Field><Field htmlFor="direct-unit" label="Satuan"><Input id="direct-unit" readOnly placeholder="Terisi dari obat" value={medicine?.unit ?? ''} /></Field></div>
            <Field error={form.errors.aturan_pakai} htmlFor="direct-instruction" label="Aturan pakai" required><Input id="direct-instruction" maxLength={255} onChange={(event) => form.setData('aturan_pakai', event.target.value)} placeholder="Dosis, frekuensi, dan waktu penggunaan" required value={form.data.aturan_pakai} /></Field>
            <Field error={form.errors.catatan} htmlFor="direct-notes" label="Catatan tambahan"><Input id="direct-notes" maxLength={2000} onChange={(event) => form.setData('catatan', event.target.value)} placeholder="Informasi tambahan saat penyerahan obat" value={form.data.catatan} /></Field>
            <Field error={form.errors.dasar_pemberian} htmlFor="direct-basis" label="Alasan pemberian langsung" required><Textarea id="direct-basis" maxLength={2000} onChange={(event) => form.setData('dasar_pemberian', event.target.value)} placeholder="Catat permintaan atau keluhan pasien dan alasan pemberian obat." required rows={4} value={form.data.dasar_pemberian} /></Field>
            {availableMedicines.length === 0 && <div role="status" className="rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 text-sm text-neutral-700">{medicines.length === 0 ? 'Belum ada obat aktif yang disetujui untuk pemberian langsung tanpa pemeriksaan. Minta pengelola master obat menetapkan obat yang sesuai sebelum layanan ini digunakan.' : <>Obat yang disetujui sudah tercantum tetapi belum memiliki batch layak pakai. Periksa tanggal kedaluwarsa, status, dan stok pada <Link className="font-medium underline underline-offset-2" href="/stok/persediaan">Obat & BHP</Link>.</>}</div>}
            <div className="flex justify-end border-t border-neutral-100 pt-5"><Button disabled={form.processing || availableMedicines.length === 0 || !form.data.pasien_id} type="submit"><ClipboardCheck className="size-4" />Catat dan serahkan obat</Button></div>
        </form></CardContent></Card></Tabs>
    </div></>;
}
