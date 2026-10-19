import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, FileSpreadsheet, Plus, Search, ShieldCheck } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { DateOfBirthPicker } from '@/components/ui/date-picker';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Empty } from '@/components/ui/empty';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';

interface Member {
    id: number; nama: string; nik: string | null; nip: string | null; kategori: string; jenis_lainnya: string | null; status_kepegawaian: string;
    unit_kerja: string; pegawai_penanggung_id: number | null; hubungan_keluarga: string | null;
    hak_layanan: boolean; referensi_bukti: string;
    tempat_lahir: string | null; tanggal_lahir: string | null; jenis_kelamin: string | null; agama: string | null; golongan_darah: string | null;
    alamat: string | null; rt: string | null; rw: string | null; kelurahan: string | null; kecamatan: string | null;
    verified_at: string | null; reason: string | null; detail_url: string;
}
interface Props { members: PaginationData & { data: Member[] }; search: string; categories: Record<string, string>; otherTypes: Record<string, string>; canAssignSpecial: boolean; sponsors: { id: number; nama: string; nip: string | null }[]; urls: { index: string; import: string; template: string } }

export default function MembershipDirectory({ members, search, categories, otherTypes, canAssignSpecial, sponsors, urls }: Props) {
    const [query, setQuery] = useState(search);
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<number | null>(null);
    const upload = useForm<{ file: File | null; referensi_bukti: string }>({ file: null, referensi_bukti: '' });
    const form = useForm({
        nama: '', nik: '', nip: '', kategori: 'asn', jenis_lainnya: '', status_kepegawaian: 'aktif', unit_kerja: '',
        tempat_lahir: '', tanggal_lahir: '', jenis_kelamin: '', agama: '', golongan_darah: '', alamat: '', rt: '', rw: '', kelurahan: '', kecamatan: '',
        pegawai_penanggung_id: '', hubungan_keluarga: '', hak_layanan: true, referensi_bukti: '',
    });
    function show(member?: Member) {
        form.clearErrors(); setEditing(member?.id ?? null);
        form.setData({
            nama: member?.nama ?? '', nik: member?.nik ?? '', nip: member?.nip ?? '', kategori: member?.kategori ?? 'asn', jenis_lainnya: member?.jenis_lainnya ?? '',
            status_kepegawaian: member?.status_kepegawaian ?? 'aktif', unit_kerja: member?.unit_kerja ?? '',
            tempat_lahir: member?.tempat_lahir ?? '', tanggal_lahir: member?.tanggal_lahir?.slice(0, 10) ?? '',
            jenis_kelamin: member?.jenis_kelamin ?? '', agama: member?.agama ?? '', golongan_darah: member?.golongan_darah ?? '',
            alamat: member?.alamat ?? '', rt: member?.rt ?? '', rw: member?.rw ?? '', kelurahan: member?.kelurahan ?? '', kecamatan: member?.kecamatan ?? '',
            pegawai_penanggung_id: member?.pegawai_penanggung_id?.toString() ?? '', hubungan_keluarga: member?.hubungan_keluarga ?? '',
            hak_layanan: member?.hak_layanan ?? true, referensi_bukti: member?.referensi_bukti ?? '',
        }); setOpen(true);
    }
    function save(event: FormEvent) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setOpen(false) };
        if (editing) form.put(`/kepesertaan/${editing}`, options); else form.post(urls.index, options);
    }

    return <><Head title="Kepesertaan & Hak Layanan" /><div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Direktori instansi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Kepesertaan & hak layanan</h2><p className="mt-1 text-sm text-neutral-500">Verifikasi identitas dan status hak layanan peserta klinik internal.</p></div><Button onClick={() => show()}><Plus className="size-4" />Verifikasi peserta</Button></div>
        <Card><CardHeader><CardTitle className="flex items-center gap-2"><FileSpreadsheet className="size-5" />Impor data kepegawaian</CardTitle><CardDescription>Gunakan template Excel. NIP dan NIK harus bertipe teks. Semua baris diperiksa; impor dibatalkan jika ada kesalahan. Pengunggah bertanggung jawab memverifikasi sumber daftar.</CardDescription></CardHeader><CardContent>
            <form className="grid gap-4 lg:grid-cols-[1fr_1fr_auto_auto] lg:items-end" onSubmit={(event) => { event.preventDefault(); upload.post(urls.import, { forceFormData: true, preserveScroll: true, onSuccess: () => upload.reset() }); }}>
                <Field label="Berkas Excel (.xlsx)" htmlFor="membership-file" required error={upload.errors.file}><Input id="membership-file" type="file" accept=".xlsx" required onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)} /></Field>
                <Field label="Referensi bukti / daftar instansi" htmlFor="import-reference" required error={upload.errors.referensi_bukti}><Input id="import-reference" placeholder="Nomor surat / versi daftar kepegawaian" required value={upload.data.referensi_bukti} onChange={(event) => upload.setData('referensi_bukti', event.target.value)} /></Field>
                <Button disabled={upload.processing || !upload.data.file} type="submit">{upload.processing ? 'Memeriksa…' : 'Impor & verifikasi'}</Button>
                <Button asChild variant="secondary"><a href={urls.template}><Download className="size-4" />Template & contoh</a></Button>
            </form><p className="mt-3 text-xs text-neutral-500">Contoh bersifat sintetis. Ganti seluruh data contoh dengan daftar resmi sebelum dipakai untuk pelayanan. Keluarga, magang, dan staf non-ASN dicatat dalam kategori Lainnya.</p>
        </CardContent></Card>
        <Card className="overflow-hidden"><CardHeader><form className="flex gap-3" onSubmit={(event) => { event.preventDefault(); router.get(urls.index, { search: query }, { preserveState: true, replace: true }); }}><Input aria-label="Cari peserta" placeholder="Cari nama, NIP, NIK, atau unit kerja…" value={query} onChange={(event) => setQuery(event.target.value)} /><Button type="submit" variant="secondary"><Search className="size-4" />Cari</Button></form></CardHeader><div className="overflow-x-auto"><Table className="min-w-[900px]"><TableHeader><TableRow><TableHead>Peserta</TableHead><TableHead>Kategori</TableHead><TableHead>Unit kerja</TableHead><TableHead>Status kepegawaian</TableHead><TableHead>Hak layanan</TableHead><TableHead>Aksi</TableHead></TableRow></TableHeader><TableBody>
            {members.data.length ? members.data.map((member) => <TableRow key={member.id}><TableCell><p className="font-semibold">{member.nama}</p><p className="mt-1 font-mono text-xs text-neutral-500">NIP {member.nip ?? 'Belum diisi'}<br />NIK {member.nik ?? 'Belum diisi'}</p></TableCell><TableCell>{categories[member.kategori]}{member.jenis_lainnya && <p className="text-xs text-neutral-500">{otherTypes[member.jenis_lainnya]}</p>}</TableCell><TableCell>{member.unit_kerja}</TableCell><TableCell><Badge variant={member.status_kepegawaian === 'nonaktif' ? 'cancelled' : 'default'}>{({ aktif: 'Aktif', pensiun: 'Pensiun', nonaktif: 'Nonaktif' } as Record<string, string>)[member.status_kepegawaian] ?? member.status_kepegawaian}</Badge></TableCell><TableCell><Badge variant={member.reason ? 'cancelled' : 'complete'}>{member.reason ? 'Tidak berhak' : 'Berhak'}</Badge>{member.reason && <p className="mt-1 max-w-64 text-xs text-red-700">{member.reason}</p>}</TableCell><TableCell><div className="flex flex-wrap gap-2"><Button asChild size="sm" variant="secondary"><Link href={member.detail_url}>Detail</Link></Button><Button size="sm" variant="ghost" onClick={() => show(member)}>Perbarui</Button></div></TableCell></TableRow>) : <TableRow><TableCell colSpan={6}><Empty title="Belum ada peserta" description="Impor daftar kepegawaian atau verifikasi peserta pertama." size="compact" /></TableCell></TableRow>}
        </TableBody></Table></div><Pagination pagination={members} /></Card>
    </div>
    <Dialog open={open} onOpenChange={setOpen}><DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl"><DialogHeader><DialogTitle>{editing ? 'Perbarui verifikasi kepesertaan' : 'Verifikasi peserta instansi'}</DialogTitle><DialogDescription>Catat sumber resmi dan hak fasilitas yang telah diverifikasi.</DialogDescription></DialogHeader>
        <form onSubmit={save} className="space-y-5"><div className="space-y-5"><section className="space-y-3"><h3 className="text-sm font-semibold text-neutral-800">Identitas peserta</h3><div className="grid gap-4 sm:grid-cols-2">
            {(['nama', 'nik', 'nip', 'unit_kerja'] as const).map((key) => <Field key={key} label={{ nama: 'Nama lengkap', nik: 'NIK (16 digit)', nip: 'NIP (8–30 digit)', unit_kerja: 'Unit kerja' }[key]} htmlFor={'member-' + key} error={form.errors[key]} required={['nama', 'unit_kerja'].includes(key)}><Input id={'member-' + key} inputMode={key === 'nik' || key === 'nip' ? 'numeric' : undefined} maxLength={key === 'nik' ? 16 : key === 'nip' ? 30 : undefined} value={form.data[key]} onChange={(event) => form.setData(key, key === 'nik' || key === 'nip' ? event.target.value.replace(/\D/g, '') : event.target.value)} /></Field>)}
            <Field label="Tempat lahir" htmlFor="member-tempat-lahir" error={form.errors.tempat_lahir} required><Input id="member-tempat-lahir" value={form.data.tempat_lahir} onChange={(event) => form.setData('tempat_lahir', event.target.value)} required /></Field>
            <Field label="Tanggal lahir" htmlFor="member-tanggal-lahir" error={form.errors.tanggal_lahir} required><DateOfBirthPicker id="member-tanggal-lahir" value={form.data.tanggal_lahir} onChange={(event) => form.setData('tanggal_lahir', event.target.value)} required /></Field>
            <Field label="Jenis kelamin" htmlFor="member-jenis-kelamin" error={form.errors.jenis_kelamin} required><Select id="member-jenis-kelamin" value={form.data.jenis_kelamin} onChange={(event) => form.setData('jenis_kelamin', event.target.value)} required><option value="">Pilih jenis kelamin</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></Select></Field>
            <Field label="Agama" htmlFor="member-agama" error={form.errors.agama} required><Input id="member-agama" value={form.data.agama} onChange={(event) => form.setData('agama', event.target.value)} required /></Field>
            <Field label="Golongan darah" htmlFor="member-golongan-darah" error={form.errors.golongan_darah}><Select id="member-golongan-darah" value={form.data.golongan_darah} onChange={(event) => form.setData('golongan_darah', event.target.value)}><option value="">Belum diketahui</option>{['A', 'B', 'AB', 'O'].map((bloodType) => <option key={bloodType}>{bloodType}</option>)}</Select></Field>
            <Field label="RT" htmlFor="member-rt" error={form.errors.rt} required><Input id="member-rt" value={form.data.rt} onChange={(event) => form.setData('rt', event.target.value)} required /></Field>
            <Field label="RW" htmlFor="member-rw" error={form.errors.rw} required><Input id="member-rw" value={form.data.rw} onChange={(event) => form.setData('rw', event.target.value)} required /></Field>
            <Field label="Kelurahan/desa" htmlFor="member-kelurahan" error={form.errors.kelurahan} required><Input id="member-kelurahan" value={form.data.kelurahan} onChange={(event) => form.setData('kelurahan', event.target.value)} required /></Field>
            <Field label="Kecamatan" htmlFor="member-kecamatan" error={form.errors.kecamatan} required><Input id="member-kecamatan" value={form.data.kecamatan} onChange={(event) => form.setData('kecamatan', event.target.value)} required /></Field>
            <div className="sm:col-span-2"><Field label="Alamat sesuai KTP" htmlFor="member-alamat" error={form.errors.alamat} required><Textarea id="member-alamat" rows={2} value={form.data.alamat} onChange={(event) => form.setData('alamat', event.target.value)} required /></Field></div>
        </div></section><section className="space-y-3"><h3 className="text-sm font-semibold text-neutral-800">Hak layanan dan unit kerja</h3><div className="grid gap-4 sm:grid-cols-2">
            <Field label="Kategori peserta" htmlFor="member-category" error={form.errors.kategori} required><Select id="member-category" value={form.data.kategori} onChange={(event) => form.setData('kategori', event.target.value)}>{Object.entries(categories).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>
            <Field label="Status kepegawaian" htmlFor="member-status" error={form.errors.status_kepegawaian} required><Select id="member-status" value={form.data.status_kepegawaian} onChange={(event) => form.setData('status_kepegawaian', event.target.value)}><option value="aktif">Aktif</option><option value="pensiun">Pensiun</option><option value="nonaktif">Nonaktif</option></Select></Field>
            {form.data.kategori === 'lainnya' && <Field label="Jenis peserta lainnya" htmlFor="member-other-type" error={form.errors.jenis_lainnya} required><Select id="member-other-type" value={form.data.jenis_lainnya} onChange={(event) => form.setData('jenis_lainnya', event.target.value)} required><option value="">Pilih jenis</option>{Object.entries(otherTypes).filter(([value]) => canAssignSpecial || value !== 'khusus' || form.data.jenis_lainnya === 'khusus').map(([value, label]) => <option key={value} value={value}>{label}</option>)}</Select></Field>}
            {form.data.kategori === 'lainnya' && form.data.jenis_lainnya === 'keluarga' && <><Field label="ASN terkait keluarga" htmlFor="member-sponsor" error={form.errors.pegawai_penanggung_id} required><Select id="member-sponsor" value={form.data.pegawai_penanggung_id} onChange={(event) => form.setData('pegawai_penanggung_id', event.target.value)}><option value="">Pilih ASN terkait</option>{sponsors.filter((sponsor) => sponsor.id !== editing).map((sponsor) => <option key={sponsor.id} value={sponsor.id}>{sponsor.nama} · {sponsor.nip ?? 'Belum diisi'}</option>)}</Select></Field><Field label="Hubungan keluarga" htmlFor="member-relation" error={form.errors.hubungan_keluarga} required><Select id="member-relation" value={form.data.hubungan_keluarga} onChange={(event) => form.setData('hubungan_keluarga', event.target.value)}><option value="">Pilih hubungan</option><option value="suami">Suami</option><option value="istri">Istri</option><option value="anak">Anak</option></Select></Field></>}
        </div></section></div><Field label="Referensi bukti verifikasi" htmlFor="member-evidence" error={form.errors.referensi_bukti} required><Input id="member-evidence" value={form.data.referensi_bukti} onChange={(event) => form.setData('referensi_bukti', event.target.value)} required /></Field>
        <Field htmlFor="membership-entitlement" label="Hak fasilitas" error={form.errors.hak_layanan}><label className="flex items-center gap-3 text-sm"><Checkbox id="membership-entitlement" checked={form.data.hak_layanan} onCheckedChange={(value) => form.setData('hak_layanan', value === true)} />Berhak atas layanan klinik internal sesuai bukti instansi</label></Field>
        <div className="flex justify-end gap-2"><Button type="button" variant="secondary" onClick={() => setOpen(false)}>Batal</Button><Button disabled={form.processing} type="submit"><ShieldCheck className="size-4" />{form.processing ? 'Menyimpan…' : 'Simpan verifikasi'}</Button></div></form>
    </DialogContent></Dialog></>;
}
