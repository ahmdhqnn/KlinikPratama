import { MasterExportButton } from '@/components/admin/master-export-button';
import { RecordDetails } from '@/components/admin/record-details';
import { identityPlaceholders } from '@/lib/form-placeholders';
import { ActionButton } from '@/components/ui/action-button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AlertTriangle, Plus, ShieldCheck, UserCheck, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Checkbox } from '@/components/ui/checkbox';
import { DateOfBirthPicker, DatePicker } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface Staff {
    id: number;
    code: string;
    name: string;
    nik?: string | null;
    ihsNumber?: string | null;
    birthDate?: string | null;
    gender: string | null;
    category: string;
    position: string;
    clinicId: number | null;
    clinic: string | null;
    sip: string | null;
    sipExpiresAt?: string | null;
    str: string | null;
    strExpiresAt?: string | null;
    phone: string | null;
    email: string | null;
    accountActive?: boolean;
    active: boolean;
}
interface Option { value: string; label: string }
interface Props {
    missingClinicCount: number;
    stats?: { total: number; active: number; withAccount: number; missingClinic: number };
    loginPositions?: string[];
    staff: PaginationData & { data: Staff[] };
    filters: { search: string; category: string; position: string; clinicId?: string; status?: string; assignment?: string };
    categories: Option[];
    positions: Option[];
    clinics: { id: number; name: string }[];
}
const emptyForm = {
    kode: '',
    nama: '',
    nik: '',
    ihs_number: '',
    tanggal_lahir: '',
    jenis_kelamin: '',
    kategori: 'medis',
    jabatan: 'dokter',
    poliklinik_id: '',
    no_sip: '',
    sip_berlaku_sampai: '',
    no_str: '',
    str_berlaku_sampai: '',
    telepon: '',
    is_active: true,
    buat_akun: false,
    email: '',
    password: '',
};

export default function StaffIndex({ missingClinicCount, stats, staff, filters, categories, positions, clinics, loginPositions = [] }: Props) {
    const [detailStaff, setDetailStaff] = useState<Staff | null>(null);
    const [search, setSearch] = useState(filters.search);
    const [category, setCategory] = useState(filters.category);
    const [position, setPosition] = useState(filters.position);
    const [clinicId, setClinicId] = useState(filters.clinicId ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [editing, setEditing] = useState<Staff | null>(null);
    const [open, setOpen] = useState(false);
    const form = useForm(emptyForm);
    const canProvisionLogin = loginPositions.includes(form.data.jabatan) && form.data.is_active;
    function filter(event: FormEvent<HTMLFormElement>) { event.preventDefault(); router.get('/master/nakes', { search, kategori: category, jabatan: position, poliklinik_id: clinicId, status, penugasan: filters.assignment }, { preserveState: true, preserveScroll: true, replace: true }); }
    function create() { setEditing(null); form.clearErrors(); form.setData({ ...emptyForm }); setOpen(true); }
    function edit(item: Staff) { setEditing(item); form.clearErrors(); form.setData({ kode: item.code, nama: item.name, nik: item.nik ?? '', ihs_number: item.ihsNumber ?? '', tanggal_lahir: item.birthDate ?? '', jenis_kelamin: item.gender ?? '', kategori: item.category, jabatan: item.position, poliklinik_id: item.clinicId?.toString() ?? '', no_sip: item.sip ?? '', sip_berlaku_sampai: item.sipExpiresAt ?? '', no_str: item.str ?? '', str_berlaku_sampai: item.strExpiresAt ?? '', telepon: item.phone ?? '', is_active: item.active, buat_akun: false, email: '', password: '' }); setOpen(true); }
    function save(event: FormEvent<HTMLFormElement>) { event.preventDefault(); const options = { preserveScroll: true, onSuccess: () => setOpen(false) }; if (editing) { form.put(`/master/nakes/${editing.id}`, options); } else { form.post('/master/nakes', options); } }
    function remove(item: Staff) { confirmAction(`Hapus data ${item.name}?`, () => { router.delete(`/master/nakes/${item.id}`, { preserveScroll: true }); }, 'Hapus'); }

    return <>
        <Head title="Tenaga kesehatan & karyawan" />
        <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Manajemen SDM klinik</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Tenaga kesehatan & karyawan</h2><p className="mt-1 text-sm text-neutral-500">Kelola profil, kredensial profesi, status, dan akses login tenaga klinik.</p></div><Button onClick={create}><Plus className="size-4" />Tambah tenaga kerja</Button></div>
            <SummaryStatGrid ariaLabel="Ringkasan tenaga kesehatan dan karyawan" columns={4}>
                <StatCard label="Total SDM" value={stats?.total ?? staff.total} description="Seluruh profil terdaftar" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Profil aktif" value={stats?.active ?? staff.data.filter((s) => s.active).length} description="Aktif dalam pelayanan" icon={<UserCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Memiliki akun" value={stats?.withAccount ?? staff.data.filter((s) => Boolean(s.email)).length} description="Terhubung dengan login" icon={<ShieldCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                <StatCard label="Perlu penugasan poli" value={stats?.missingClinic ?? missingClinicCount} description="Petugas klinis tanpa poli" icon={<AlertTriangle className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
            </SummaryStatGrid>
            {missingClinicCount > 0 && (
                <Alert
                    action={filters.assignment === 'perlu-perhatian' ? (
                        <Button onClick={() => router.get('/master/nakes')} size="sm" type="button" variant="secondary">
                            Tampilkan semua
                        </Button>
                    ) : (
                        <Button onClick={() => router.get('/master/nakes', { penugasan: 'perlu-perhatian' })} size="sm" type="button" variant="secondary">
                            Tinjau penugasan
                        </Button>
                    )}
                    dismissible={false}
                    variant="default"
                >
                    <AlertTriangle aria-hidden="true" className="size-4" />
                    <AlertTitle>Penugasan poliklinik perlu ditinjau</AlertTitle>
                    <AlertDescription>
                        <strong>{missingClinicCount} petugas klinis aktif</strong> perlu penugasan ke poli aktif agar dapat menerima layanan sesuai penugasan.
                    </AlertDescription>
                </Alert>
            )}
            <FilterPanel actions={<><Button asChild size="sm" variant="secondary"><Link href="/users"><UsersRound className="size-4" />Kelola akun</Link></Button><MasterExportButton catalog="tenaga-kerja" /></>}><form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-5" onSubmit={filter}>
                <Field htmlFor="filter-master-nakes-index-1" label="Cari tenaga kerja"><Input id="filter-master-nakes-index-1" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau kode" value={search} /></Field>
                <Field htmlFor="filter-master-nakes-index-2" label="Kategori"><Select id="filter-master-nakes-index-2" onChange={(event) => setCategory(event.target.value)} value={category}><option value="">Semua kategori</option>{categories.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field>
                <Field htmlFor="filter-master-nakes-index-3" label="Jabatan"><Select id="filter-master-nakes-index-3" onChange={(event) => setPosition(event.target.value)} value={position}><option value="">Semua jabatan</option>{positions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field>
                <Field htmlFor="filter-master-nakes-index-4" label="Poliklinik"><Select id="filter-master-nakes-index-4" onChange={(event) => setClinicId(event.target.value)} value={clinicId}><option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={String(clinic.id)}>{clinic.name}</option>)}</Select></Field>
                <Field htmlFor="filter-master-nakes-index-5" label="Status"><Select id="filter-master-nakes-index-5" onChange={(event) => setStatus(event.target.value)} value={status}><option value="">Semua status</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option></Select></Field>
                <FilterSubmit />
            </form></FilterPanel>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><UsersRound className="size-5" /></span><div><CardTitle>Daftar tenaga kerja</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(staff.total)} profil sesuai filter.</CardDescription></div></div></CardHeader>
                <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[1150px]"><TableHeader><TableRow><TableHead>Kode</TableHead><TableHead>Nama</TableHead><TableHead>Kategori</TableHead><TableHead>Jabatan</TableHead><TableHead>Poliklinik</TableHead><TableHead>SIP / STR</TableHead><TableHead>Akun login</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{staff.data.length ? staff.data.map((item) => <TableRow key={item.id}><TableCell className="font-sans tabular-nums text-xs font-semibold">{item.code}</TableCell><TableCell className="font-medium text-neutral-900">{item.name}<span className="mt-1 block text-xs text-neutral-500">{item.phone ?? 'Tanpa nomor telepon'}</span></TableCell><TableCell>{item.category === 'medis' ? 'Medis' : 'Non-medis'}</TableCell><TableCell className="capitalize">{positions.find((entry) => entry.value === item.position)?.label ?? item.position}</TableCell><TableCell>{item.clinic ?? 'Belum ditugaskan'}</TableCell><TableCell className="font-sans tabular-nums text-xs">{item.sip ?? item.str ?? 'Belum diisi'}</TableCell><TableCell className="text-xs">{item.email ? <>{item.email}<span className="mt-1 block text-muted-foreground">{item.accountActive ? 'Aktif' : 'Nonaktif'}</span></> : <span className="text-neutral-500">Tanpa akun</span>}</TableCell><TableCell><Badge>{item.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell><TableCell><div className="flex flex-wrap items-center justify-center gap-2"><ActionButton onClick={() => setDetailStaff(item)} action="detail" label="Detail petugas" /><ActionButton onClick={() => edit(item)} action="edit" label="Edit" /><ActionButton onClick={() => remove(item)} action="delete" label="Hapus" /></div></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={9}><Empty size="compact" title="Belum ada tenaga kerja sesuai filter." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={staff} /></CardContent>
            </Card>
        </div>
        <RecordDetails title="Detail nakes & karyawan" description="Profil profesi, penugasan, kredensial SATUSEHAT, dan keterhubungan akun petugas." open={detailStaff !== null} onOpenChange={(open) => { if (!open) setDetailStaff(null); }} entries={detailStaff ? [['Nama', detailStaff.name], ['Kode SDM', detailStaff.code], ['NIK', detailStaff.nik ?? null], ['IHS Practitioner ID', detailStaff.ihsNumber ?? null], ['Tanggal lahir', detailStaff.birthDate ?? null], ['Jabatan', positions.find((item) => item.value === detailStaff.position)?.label ?? detailStaff.position], ['Poliklinik', detailStaff.clinic], ['Nomor SIP', detailStaff.sip], ['SIP berlaku sampai', detailStaff.sipExpiresAt ?? null], ['Nomor STR', detailStaff.str], ['STR berlaku sampai', detailStaff.strExpiresAt ?? null], ['Telepon', detailStaff.phone], ['Email akun', detailStaff.email], ['Status profil', detailStaff.active ? 'Aktif' : 'Nonaktif']] : []} />
        <Dialog onOpenChange={setOpen} open={open}><DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto"><DialogHeader><DialogTitle>{editing ? 'Edit profil tenaga kerja' : 'Tambah tenaga kerja'}</DialogTitle><DialogDescription>Data digunakan pada layanan, jadwal, dan akses klinik.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={save}>
            {editing && ['farmasi', 'apotek'].includes(editing.position) && (
                <div className="flex justify-end">
                    <Button asChild size="sm" variant="secondary">
                        <Link href={`/master/nakes/${editing.id}/kualifikasi-farmasi`}>
                            <ShieldCheck className="size-4" />
                            Verifikasi kualifikasi farmasi
                        </Link>
                    </Button>
                </div>
            )}
            <div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.kode} htmlFor="staff-code" label="Kode SDM" required><Input placeholder="Contoh: NK-001" id="staff-code" maxLength={30} onChange={(event) => form.setData('kode', event.target.value)} required value={form.data.kode} /></Field><Field error={form.errors.jenis_kelamin} htmlFor="staff-gender" label="Jenis kelamin"><Select id="staff-gender" onChange={(event) => form.setData('jenis_kelamin', event.target.value)} value={form.data.jenis_kelamin}><option value="">Belum dipilih</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></Select></Field></div>
            <Field error={form.errors.nama} htmlFor="staff-name" label="Nama lengkap" required><Input placeholder={identityPlaceholders.nama} id="staff-name" maxLength={200} onChange={(event) => form.setData('nama', event.target.value)} required value={form.data.nama} /></Field>
            <div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.kategori} htmlFor="staff-category" label="Kategori" required><Select id="staff-category" onChange={(event) => form.setData('kategori', event.target.value)} value={form.data.kategori}>{categories.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field><Field error={form.errors.jabatan} htmlFor="staff-position" label="Jabatan" required><Select id="staff-position" onChange={(event) => {
                const jabatan = event.target.value;
                form.setData({ ...form.data, jabatan, buat_akun: false, email: '', password: '' });
            }} value={form.data.jabatan}>{positions.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field></div>
            {form.data.jabatan === 'dokter' && <Field error={form.errors.poliklinik_id} htmlFor="staff-clinic" label={editing ? 'Poliklinik penugasan' : 'Poliklinik praktik awal'} required><Select id="staff-clinic" value={form.data.poliklinik_id} onChange={(event) => form.setData('poliklinik_id', event.target.value)} required><option value="">Pilih poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select>{!editing && <p className="text-xs leading-5 text-neutral-500">Jadwal ASN Senin–Jumat 08.00–16.00 dibuat otomatis. Tambahkan poli lain atau ubah jadwal di Jadwal praktik.</p>}</Field>}
            {['perawat', 'bidan', 'terapis_gigi', 'farmasi', 'apotek'].includes(form.data.jabatan) && <Field error={form.errors.poliklinik_id} htmlFor="staff-clinic" label="Poliklinik penugasan" required><Select id="staff-clinic" value={form.data.poliklinik_id} onChange={(event) => form.setData('poliklinik_id', event.target.value)} required><option value="">Pilih poli akun ini</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select><p className="text-xs leading-5 text-neutral-500">{['farmasi', 'apotek'].includes(form.data.jabatan) ? 'Akun farmasi hanya akan melihat dan memproses resep, obat langsung, dan stok pada poli penugasannya.' : 'Akun perawat hanya akan melihat dan melayani pasien pada poli penugasannya.'}</p></Field>}
            <div className="grid gap-4 sm:grid-cols-3"><Field error={form.errors.no_sip} htmlFor="staff-sip" label="Nomor SIP"><Input placeholder="Nomor sesuai dokumen SIP" id="staff-sip" maxLength={100} onChange={(event) => form.setData('no_sip', event.target.value)} value={form.data.no_sip} /></Field><Field error={form.errors.no_str} htmlFor="staff-str" label="Nomor STR"><Input placeholder="Nomor sesuai dokumen STR" id="staff-str" maxLength={100} onChange={(event) => form.setData('no_str', event.target.value)} value={form.data.no_str} /></Field><Field error={form.errors.telepon} htmlFor="staff-phone" label="Telepon"><Input placeholder={identityPlaceholders.telepon} id="staff-phone" maxLength={20} onChange={(event) => form.setData('telepon', event.target.value)} value={form.data.telepon} /></Field></div>
            <div className="grid gap-4 sm:grid-cols-3"><Field error={form.errors.nik} htmlFor="staff-nik" label="NIK (16 digit · SATUSEHAT)"><Input id="staff-nik" inputMode="numeric" maxLength={16} onChange={(event) => form.setData('nik', event.target.value.replace(/\D/g, ''))} placeholder={identityPlaceholders.nik} value={form.data.nik} /></Field><Field error={form.errors.ihs_number} htmlFor="staff-ihs" label="IHS Practitioner ID"><Input id="staff-ihs" maxLength={32} onChange={(event) => form.setData('ihs_number', event.target.value)} placeholder="Opsional (SATUSEHAT)" value={form.data.ihs_number} /></Field><Field error={form.errors.tanggal_lahir} htmlFor="staff-birth-date" label="Tanggal lahir"><DateOfBirthPicker id="staff-birth-date" onChange={(event) => form.setData('tanggal_lahir', event.target.value)} value={form.data.tanggal_lahir} /></Field></div>
            <div className="grid gap-4 sm:grid-cols-2"><Field error={form.errors.sip_berlaku_sampai} htmlFor="staff-sip-exp" label="SIP berlaku sampai"><DatePicker id="staff-sip-exp" onChange={(event) => form.setData('sip_berlaku_sampai', event.target.value)} value={form.data.sip_berlaku_sampai} /></Field><Field error={form.errors.str_berlaku_sampai} htmlFor="staff-str-exp" label="STR berlaku sampai"><DatePicker id="staff-str-exp" onChange={(event) => form.setData('str_berlaku_sampai', event.target.value)} value={form.data.str_berlaku_sampai} /></Field></div>
            {!editing && canProvisionLogin && <div className="space-y-3 rounded-xl border border-neutral-200 bg-neutral-50 p-4"><Label className="flex items-center gap-2 text-sm font-medium text-neutral-800"><Checkbox checked={form.data.buat_akun} onCheckedChange={(checked) => form.setData('buat_akun', (checked === true))} />Buat akun login untuk tenaga kerja ini</Label><p className="text-xs text-red-600" role={form.errors.buat_akun ? 'alert' : undefined}>{form.errors.buat_akun}</p>{form.data.buat_akun && <div className="grid gap-4 border-l-2 border-neutral-200 pl-4 sm:grid-cols-2"><Field error={form.errors.email} htmlFor="staff-email" label="Email akun" required><Input placeholder={identityPlaceholders.email} autoComplete="email" id="staff-email" onChange={(event) => form.setData('email', event.target.value)} required type="email" value={form.data.email} /></Field><Field error={form.errors.password} htmlFor="staff-password" label="Kata sandi awal" required><Input placeholder="Minimal 8 karakter" autoComplete="new-password" id="staff-password" minLength={8} onChange={(event) => form.setData('password', event.target.value)} required type="password" value={form.data.password} /></Field></div>}</div>}
            {!editing && !canProvisionLogin && <p className="text-sm text-muted-foreground">{form.data.jabatan === 'pendaftaran' && !loginPositions.includes('pendaftaran')
                ? 'Pendaftaran sudah memiliki akun aktif.'
                : 'Profil ini dapat disimpan tanpa akun login.'}</p>}
            {form.errors.buat_akun && !canProvisionLogin && <p className="text-sm text-red-600" role="alert">{form.errors.buat_akun}</p>}
            <Label className="flex items-center gap-2 text-sm text-neutral-700"><Checkbox checked={form.data.is_active} onCheckedChange={(checked) => {
                const isActive = checked === true;
                form.setData({ ...form.data, is_active: isActive, ...(!isActive && { buat_akun: false, email: '', password: '' }) });
            }} />Profil aktif</Label><div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan profil'}</Button></div>
        </form></DialogContent></Dialog>
    </>;
}
