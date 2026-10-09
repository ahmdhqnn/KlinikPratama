import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet, HeartPulse, Plus, Search, ShieldCheck, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DateOfBirthPicker, DatePicker } from '@/components/ui/date-picker';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';

interface Patient {
    id: number;
    medicalRecordNumber: string;
    name: string;
    nik: string | null;
    gender: string | null;
    age: number | null;
    phone: string | null;
    address: string | null;
    insurance: string;
    insuranceType: string | null;
    canVerify: boolean;
    verificationUrl: string;
    verificationData: {
        name: string;
        nik: string | null;
        birthPlace: string | null;
        birthDate: string | null;
        gender: string | null;
        bloodType: string | null;
        religion: string | null;
        address: string | null;
        rt: string | null;
        rw: string | null;
        village: string | null;
        district: string | null;
    } | null;
}

interface VerificationFormData {
    nama: string;
    nik: string;
    tempat_lahir: string;
    tanggal_lahir: string;
    jenis_kelamin: string;
    golongan_darah: string;
    agama: string;
    alamat: string;
    rt: string;
    rw: string;
    kelurahan: string;
    kecamatan: string;
    unit_kerja: string;
    cost_center: string;
    berlaku_mulai: string;
    berlaku_sampai: string;
    referensi_bukti: string;
    hak_layanan: boolean;
}

interface Props {
    permissions: { viewRme: boolean; managePatients: boolean };
    filters: { search: string; kategori: string };
    patients: PaginationData & { data: Patient[] };
    categories: Record<string, string>;
    today: string;
}

export default function PatientsIndex({ filters: initialFilters, patients, categories, permissions, today }: Props) {
    const [filters, setFilters] = useState(initialFilters);
    const [mergeOpen, setMergeOpen] = useState(false);
    const [verificationPatient, setVerificationPatient] = useState<Patient | null>(null);
    const [verificationOpen, setVerificationOpen] = useState(false);
    const mergeForm = useForm({ pasien_utama_id: '', pasien_hapus_id: '' });
    const verificationForm = useForm<VerificationFormData>({
        nama: '', nik: '', tempat_lahir: '', tanggal_lahir: '', jenis_kelamin: '', golongan_darah: '', agama: '',
        alamat: '', rt: '', rw: '', kelurahan: '', kecamatan: '', unit_kerja: '', cost_center: '',
        berlaku_mulai: today, berlaku_sampai: '', referensi_bukti: '', hak_layanan: false,
    });

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/pasien', filters, { preserveState: true, preserveScroll: true, replace: true });
    }


    function submitMerge(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        confirmAction('Gabungkan rekam medis dan pindahkan seluruh kunjungan ke pasien utama?', () => { mergeForm.post('/pelayanan/pasien/gabung', { onSuccess: () => setMergeOpen(false) }); }, 'Lanjutkan');
    }

    function openVerification(patient: Patient) {
        const identity = patient.verificationData;
        verificationForm.clearErrors();
        verificationForm.setData({
            nama: identity?.name ?? patient.name, nik: identity?.nik ?? patient.nik ?? '',
            tempat_lahir: identity?.birthPlace ?? '', tanggal_lahir: identity?.birthDate ?? '',
            jenis_kelamin: identity?.gender ?? '', golongan_darah: identity?.bloodType ?? '', agama: identity?.religion ?? '',
            alamat: identity?.address ?? patient.address ?? '', rt: identity?.rt ?? '', rw: identity?.rw ?? '',
            kelurahan: identity?.village ?? '', kecamatan: identity?.district ?? '', unit_kerja: '', cost_center: '',
            berlaku_mulai: today, berlaku_sampai: '', referensi_bukti: '', hak_layanan: false,
        });
        setVerificationPatient(patient);
        setVerificationOpen(true);
    }

    function submitVerification(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!verificationPatient) return;
        verificationForm.post(verificationPatient.verificationUrl, {
            preserveScroll: true,
            onSuccess: () => setVerificationOpen(false),
        });
    }

    return (
        <>
            <Head title="Database Pasien" />
            <div className="space-y-6">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div><p className="text-sm font-medium text-neutral-700">Data induk pasien</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Database pasien</h2><p className="mt-1 text-sm text-neutral-500">Kelola identitas pasien yang terhubung dengan kepesertaan internal.</p></div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.managePatients && <>

                            <Button asChild size="sm" variant="secondary"><Link href="/kepesertaan"><FileSpreadsheet className="size-4" />Impor kepesertaan</Link></Button>
                            <Button asChild size="sm" variant="secondary"><a href="/pelayanan/pasien-export"><Download className="size-4" />Export</a></Button>
                            <Button onClick={() => setMergeOpen(true)} size="sm" variant="secondary">Gabung RM</Button>
                            <Button asChild size="sm"><Link href="/pelayanan/pasien/create"><Plus className="size-4" />Pasien baru</Link></Button>
                        </>}
                    </div>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 md:grid-cols-[minmax(0,1fr)_16rem_auto] md:items-start" onSubmit={applyFilters}>
                            <Field htmlFor="patient-search" label="Cari pasien"><Input id="patient-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama, No. RM, NIK, atau telepon" value={filters.search} /></Field>
                            <Field htmlFor="patient-insurance-filter" label="Kategori peserta"><Select id="patient-insurance-filter" onChange={(event) => setFilters({ ...filters, kategori: event.target.value })} value={filters.kategori}><option value="">Semua kategori</option>{Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>
                            <Button className="md:mt-7" type="submit"><Search className="size-4" />Cari pasien</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><UsersRound className="size-5" /></span><div><CardTitle>Pasien terdaftar</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(patients.total)} pasien ditemukan.</CardDescription></div></div></CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto"><Table className="min-w-[1120px]"><TableHeader><tr><TableHead>No. RM</TableHead><TableHead>Pasien</TableHead><TableHead>JK / Umur</TableHead><TableHead>Kontak</TableHead><TableHead>Alamat</TableHead><TableHead>Kepesertaan</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                            <TableBody>{patients.data.length ? patients.data.map((patient) => <TableRow key={patient.id}>
                                <TableCell><Link className="font-mono text-xs font-semibold text-neutral-700 hover:underline" href={`/pelayanan/pasien/${patient.id}`}>{patient.medicalRecordNumber}</Link></TableCell>
                                <TableCell><div className="font-medium text-neutral-900">{patient.name}</div>{patient.nik && <div className="font-mono text-xs text-neutral-400">NIK: {patient.nik}</div>}</TableCell>
                                <TableCell className="whitespace-nowrap text-neutral-600">{patient.gender ?? '—'} / {patient.age === null ? '—' : `${patient.age} th`}</TableCell>
                                <TableCell className="text-neutral-600">{patient.phone ?? '—'}</TableCell>
                                <TableCell className="max-w-56 truncate text-neutral-500">{patient.address ?? '—'}</TableCell>
                                <TableCell><span className="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-700">{patient.insurance}</span></TableCell>
                                <TableCell><div className="flex justify-end gap-2">{permissions.managePatients && patient.canVerify && <Button onClick={() => openVerification(patient)} size="sm" variant="secondary"><ShieldCheck className="size-4" />Verifikasi</Button>}<Button asChild size="sm" variant="secondary"><Link href={`/pelayanan/pasien/${patient.id}`}>Detail</Link></Button>{permissions.viewRme && <Button asChild size="sm" variant="ghost"><Link href={`/pelayanan/pasien/${patient.id}/rekam-medis`}><HeartPulse className="size-4" />RME</Link></Button>}</div></TableCell>
                            </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty description="Ubah kata kunci atau kategori, lalu coba lagi." size="compact" title="Tidak ada pasien yang cocok" /></TableCell></TableRow>}</TableBody>
                        </Table></div>
                        <Pagination pagination={patients} />
                    </CardContent>
                </Card>
            </div>
            <Dialog onOpenChange={setMergeOpen} open={mergeOpen}><DialogContent><DialogHeader><DialogTitle>Gabungkan rekam medis</DialogTitle><DialogDescription>Riwayat kunjungan pasien duplikat akan dipindahkan ke pasien utama.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={submitMerge}><Field error={mergeForm.errors.pasien_utama_id} htmlFor="merge-primary-patient" label="ID pasien utama" required><Input id="merge-primary-patient" min={1} onChange={(event) => mergeForm.setData('pasien_utama_id', event.target.value)} required type="number" value={mergeForm.data.pasien_utama_id} /></Field><Field error={mergeForm.errors.pasien_hapus_id} htmlFor="merge-duplicate-patient" label="ID pasien duplikat" required><Input id="merge-duplicate-patient" min={1} onChange={(event) => mergeForm.setData('pasien_hapus_id', event.target.value)} required type="number" value={mergeForm.data.pasien_hapus_id} /></Field><div className="flex justify-end gap-2"><Button onClick={() => setMergeOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={mergeForm.processing} type="submit">Gabungkan</Button></div></form></DialogContent></Dialog>
            <Dialog onOpenChange={setVerificationOpen} open={verificationOpen}><DialogContent className="max-w-3xl"><DialogHeader><DialogTitle>Verifikasi hak layanan khusus</DialogTitle><DialogDescription>Periksa identitas dan catat dasar persetujuan serta masa berlaku. Hak layanan aktif setelah data ini tersimpan.</DialogDescription></DialogHeader><form className="space-y-5" onSubmit={submitVerification}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field error={verificationForm.errors.nama} htmlFor="verify-patient-name" label="Nama pasien" required><Input id="verify-patient-name" onChange={(event) => verificationForm.setData('nama', event.target.value)} required value={verificationForm.data.nama} /></Field>
                    <Field error={verificationForm.errors.nik} htmlFor="verify-patient-nik" label="NIK" required><Input id="verify-patient-nik" inputMode="numeric" maxLength={16} onChange={(event) => verificationForm.setData('nik', event.target.value.replace(/\D/g, '').slice(0, 16))} required value={verificationForm.data.nik} /></Field>
                    <Field error={verificationForm.errors.tempat_lahir} htmlFor="verify-birth-place" label="Tempat lahir" required><Input id="verify-birth-place" onChange={(event) => verificationForm.setData('tempat_lahir', event.target.value)} required value={verificationForm.data.tempat_lahir} /></Field>
                    <Field error={verificationForm.errors.tanggal_lahir} htmlFor="verify-birth-date" label="Tanggal lahir" required><DateOfBirthPicker id="verify-birth-date" max={today} onChange={(event) => verificationForm.setData('tanggal_lahir', event.target.value)} required value={verificationForm.data.tanggal_lahir} /></Field>
                    <Field error={verificationForm.errors.jenis_kelamin} htmlFor="verify-gender" label="Jenis kelamin" required><Select id="verify-gender" onChange={(event) => verificationForm.setData('jenis_kelamin', event.target.value)} required value={verificationForm.data.jenis_kelamin}><option value="">Pilih jenis kelamin</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></Select></Field>
                    <Field error={verificationForm.errors.golongan_darah} htmlFor="verify-blood-type" label="Golongan darah"><Select id="verify-blood-type" onChange={(event) => verificationForm.setData('golongan_darah', event.target.value)} value={verificationForm.data.golongan_darah}><option value="">Tidak diketahui</option>{['A', 'B', 'AB', 'O'].map((type) => <option key={type} value={type}>{type}</option>)}</Select></Field>
                    <Field error={verificationForm.errors.agama} htmlFor="verify-religion" label="Agama" required><Input id="verify-religion" onChange={(event) => verificationForm.setData('agama', event.target.value)} required value={verificationForm.data.agama} /></Field>
                    <div className="grid grid-cols-2 gap-4"><Field error={verificationForm.errors.rt} htmlFor="verify-rt" label="RT" required><Input id="verify-rt" maxLength={5} onChange={(event) => verificationForm.setData('rt', event.target.value)} required value={verificationForm.data.rt} /></Field><Field error={verificationForm.errors.rw} htmlFor="verify-rw" label="RW" required><Input id="verify-rw" maxLength={5} onChange={(event) => verificationForm.setData('rw', event.target.value)} required value={verificationForm.data.rw} /></Field></div>
                    <Field error={verificationForm.errors.kelurahan} htmlFor="verify-village" label="Kelurahan / desa" required><Input id="verify-village" onChange={(event) => verificationForm.setData('kelurahan', event.target.value)} required value={verificationForm.data.kelurahan} /></Field>
                    <Field error={verificationForm.errors.kecamatan} htmlFor="verify-district" label="Kecamatan" required><Input id="verify-district" onChange={(event) => verificationForm.setData('kecamatan', event.target.value)} required value={verificationForm.data.kecamatan} /></Field>
                    <div className="sm:col-span-2"><Field error={verificationForm.errors.alamat} htmlFor="verify-address" label="Alamat" required><Textarea id="verify-address" onChange={(event) => verificationForm.setData('alamat', event.target.value)} required value={verificationForm.data.alamat} /></Field></div>
                </div>
                <div className="grid gap-4 rounded-xl border border-neutral-200 bg-neutral-50/70 p-4 sm:grid-cols-2">
                    <Field error={verificationForm.errors.unit_kerja} htmlFor="verify-unit" label="Unit kerja / instansi penanggung" required><Input id="verify-unit" maxLength={200} onChange={(event) => verificationForm.setData('unit_kerja', event.target.value)} required value={verificationForm.data.unit_kerja} /></Field>
                    <Field error={verificationForm.errors.cost_center} htmlFor="verify-cost-center" label="Unit / cost center" required><Input id="verify-cost-center" maxLength={100} onChange={(event) => verificationForm.setData('cost_center', event.target.value)} required value={verificationForm.data.cost_center} /></Field>
                    <Field error={verificationForm.errors.berlaku_mulai} htmlFor="verify-valid-from" label="Hak berlaku mulai" required><DatePicker id="verify-valid-from" min={today} onChange={(event) => verificationForm.setData('berlaku_mulai', event.target.value)} required value={verificationForm.data.berlaku_mulai} /></Field>
                    <Field error={verificationForm.errors.berlaku_sampai} htmlFor="verify-valid-until" label="Hak berlaku sampai" required><DatePicker id="verify-valid-until" min={verificationForm.data.berlaku_mulai || today} onChange={(event) => verificationForm.setData('berlaku_sampai', event.target.value)} required value={verificationForm.data.berlaku_sampai} /></Field>
                    <div className="sm:col-span-2"><Field error={verificationForm.errors.referensi_bukti} htmlFor="verify-evidence-reference" label="Referensi bukti verifikasi" required><Input id="verify-evidence-reference" maxLength={255} onChange={(event) => verificationForm.setData('referensi_bukti', event.target.value)} placeholder="Nomor surat, dasar penugasan, atau dokumen persetujuan" required value={verificationForm.data.referensi_bukti} /></Field></div>
                    <div className="sm:col-span-2"><label className="flex items-start gap-3 text-sm text-neutral-700"><Checkbox checked={verificationForm.data.hak_layanan} onCheckedChange={(checked) => verificationForm.setData('hak_layanan', checked === true)} /><span>Saya telah memeriksa bukti dan menyetujui aktivasi hak layanan untuk masa berlaku di atas.<span className="ml-1 text-red-600">*</span>{verificationForm.errors.hak_layanan && <span className="mt-1 block text-xs font-medium text-red-600">{verificationForm.errors.hak_layanan}</span>}</span></label></div>
                </div>
                <div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setVerificationOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={verificationForm.processing} type="submit"><ShieldCheck className="size-4" />{verificationForm.processing ? 'Memverifikasi…' : 'Verifikasi hak layanan'}</Button></div>
            </form></DialogContent></Dialog>
        </>
    );
}
