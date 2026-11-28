import { MasterExportButton } from '@/components/admin/master-export-button';
import { StatCard } from '@/components/dashboard/stat-card';
import { RecordDetails } from '@/components/admin/record-details';
import { identityPlaceholders } from '@/lib/form-placeholders';
import { ActionButton } from '@/components/ui/action-button';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, ShieldCheck, UserRoundCog, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableScroll } from '@/components/ui/table';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

interface UserRecord {
    id: number;
    name: string;
    email: string;
    role: string;
    roleLabel: string;
    active: boolean;
    retiredRole?: boolean;
    hasSigningPin?: boolean;
    mustSetupSigningPin?: boolean;
    nakes: { id: number; name: string; code: string; clinicId: number | null; clinic: string | null } | null;
    isCurrentUser: boolean;
}

interface Props {
    users: PaginationData & { data: UserRecord[] };
    filters: { search: string; role: string; status?: string };
    roles: { value: string; label: string }[];
    occupiedSingleRoles?: string[];
    doctors: { id: number; name: string; code: string; linked: boolean }[];
    staffProfiles?: { id: number; name: string; code: string; role: string | null; userId: number | null; clinicId: number | null }[];
    clinics: { id: number; name: string }[];
}

const roleStyles: Record<string, string> = {
    admin: 'bg-neutral-100 text-neutral-700',
    dokter: 'bg-neutral-100 text-neutral-700',
    perawat: 'bg-neutral-100 text-neutral-700',
    farmasi: 'bg-neutral-100 text-neutral-700',
    pendaftaran: 'bg-neutral-100 text-neutral-700',
};

const emptyUser = { name: '', email: '', password: '', role: 'perawat', is_active: true, nakes_id: '', poliklinik_id: '' };

export default function UserManagement({ users, filters, roles, clinics, staffProfiles = [], occupiedSingleRoles = [] }: Props) {
    const [detailUser, setDetailUser] = useState<UserRecord | null>(null);
    const [search, setSearch] = useState(filters.search);
    const [role, setRole] = useState(filters.role);
    const [status, setStatus] = useState(filters.status ?? '');
    const [editingUser, setEditingUser] = useState<UserRecord | null>(null);
    const [userDialogOpen, setUserDialogOpen] = useState(false);
    const [resetUser, setResetUser] = useState<UserRecord | null>(null);
    const [resetDialogOpen, setResetDialogOpen] = useState(false);
    const form = useForm(emptyUser);
    const resetForm = useForm({ password: '' });
    const requiresProfile = ['dokter', 'perawat', 'farmasi'].includes(form.data.role)
        && (!editingUser || (form.data.is_active && (form.data.role !== editingUser.role || !editingUser.nakes)));

    function filterUsers(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/users', { search, role, status }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function openCreate() {
        setEditingUser(null);
        form.reset();
        form.clearErrors();
        form.setData({ ...emptyUser });
        setUserDialogOpen(true);
    }

    function openEdit(user: UserRecord) {
        setEditingUser(user);
        form.clearErrors();
        form.setData({ name: user.name, email: user.email, password: '', role: user.retiredRole ? '' : user.role, is_active: user.active, nakes_id: String(user.nakes?.id ?? ''), poliklinik_id: String(user.nakes?.clinicId ?? '') });
        setUserDialogOpen(true);
    }

    function saveUser(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setUserDialogOpen(false) };
        if (editingUser) {
            form.put(`/users/${editingUser.id}`, options);
        } else {
            form.post('/users', options);
        }
    }

    function openReset(user: UserRecord) {
        setResetUser(user);
        resetForm.reset();
        resetForm.clearErrors();
        setResetDialogOpen(true);
    }

    function resetPassword(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!resetUser) return;
        resetForm.post(`/users/${resetUser.id}/reset-password`, { preserveScroll: true, onSuccess: () => setResetDialogOpen(false) });
    }


    return (
        <>
            <Head title="Pengguna & hak akses" />
            <div className="space-y-6">

                <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Akses dan akun aplikasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Pengguna & hak akses</h2><p className="mt-1 max-w-2xl text-sm text-neutral-500">Kelola peran, status akses, profil tenaga kesehatan, dan kata sandi.</p></div><Button onClick={openCreate}><Plus className="size-4" />Tambah pengguna</Button></div>
                <div className="balanced-stats">
                    <StatCard label="Pengguna ditemukan" value={users.total} description="Akun yang sesuai dengan filter" icon={<UsersRound className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Peran yang dikelola" value={roles.length} description="Jenis hak akses aplikasi" icon={<ShieldCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Profil petugas tersedia" value={staffProfiles.filter((profile) => profile.userId === null && profile.role !== null).length} description="Profil aktif yang belum terhubung ke akun" icon={<UserRoundCog className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </div>

                <FilterPanel actions={<><Button asChild size="sm" variant="secondary"><Link href="/master/nakes"><UsersRound className="size-4" />Profil tenaga kerja</Link></Button><MasterExportButton catalog="pengguna" /></>}><form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" onSubmit={filterUsers}><Field htmlFor="user-search" label="Cari nama atau email"><Input id="user-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau email" value={search} /></Field><Field htmlFor="user-role" label="Peran"><Select id="user-role" onChange={(event) => setRole(event.target.value)} value={role}><option value="">Semua peran</option>{roles.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field>
                    <Field htmlFor="user-status" label="Status akses"><Select id="user-status" onChange={(event) => setStatus(event.target.value)} value={status}>
                        <option value="">Semua status</option><option value="aktif">Aktif</option><option value="nonaktif">Nonaktif</option><option value="pensiun">Peran dihentikan</option>
                    </Select></Field><FilterSubmit /></form></FilterPanel>

                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><CardTitle>Daftar pengguna</CardTitle><CardDescription>Temukan akun menurut nama, email, peran, atau status akses.</CardDescription></CardHeader>
                    <CardContent className="p-0"><TableScroll className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><TableRow><TableHead>Nama</TableHead><TableHead>Email akun</TableHead><TableHead>Peran</TableHead><TableHead>Status</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{users.data.length ? users.data.map((user) => <TableRow key={user.id}><TableCell><p className="font-medium text-neutral-900">{user.name}</p>{user.nakes && <p className="mt-0.5 text-xs text-neutral-500">{user.nakes.name} · {user.nakes.code}{['dokter', 'perawat', 'farmasi'].includes(user.role) ? ` · ${user.nakes.clinic ?? 'Belum ditugaskan'}` : ''}</p>}</TableCell><TableCell className="font-sans tabular-nums text-xs">{user.email}</TableCell><TableCell><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${roleStyles[user.role] ?? 'bg-neutral-100 text-neutral-700'}`}>{user.roleLabel}</span></TableCell><TableCell><Badge className="gap-1.5"><span className={`size-2 rounded-full ${user.active && !user.retiredRole ? 'bg-neutral-500' : 'bg-neutral-300'}`} />{user.retiredRole ? 'Peran dihentikan' : user.active ? 'Aktif' : 'Nonaktif'}</Badge></TableCell><TableCell><div className="flex flex-wrap items-center justify-center gap-2"><ActionButton onClick={() => setDetailUser(user)} action="detail" label="Detail pengguna" /><ActionButton disabled={user.retiredRole} onClick={() => openReset(user)} action="resetPassword" label="Atur ulang kata sandi" /><ActionButton onClick={() => openEdit(user)} action="edit" label="Edit" /><><ActionButton disabledReason="Akun yang sedang digunakan tidak dapat dinonaktifkan." disabled={user.isCurrentUser || (user.retiredRole && !user.active)} onClick={() => router.post(`/users/${user.id}/toggle-active`, {}, { preserveScroll: true })} action={user.active ? 'deactivate' : 'activate'} label={user.active ? 'Nonaktifkan pengguna' : 'Aktifkan pengguna'} /></></div></TableCell></TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Belum ada pengguna yang sesuai filter." /></TableCell></TableRow>}</TableBody></Table></TableScroll><Pagination pagination={users} /></CardContent>
                </Card>
            </div>

            <RecordDetails title="Detail pengguna" description="Identitas akun, akses aplikasi, dan profil petugas yang terhubung." open={detailUser !== null} onOpenChange={(open) => { if (!open) setDetailUser(null); }} entries={detailUser ? [['Nama akun', detailUser.name], ['Email', detailUser.email], ['Peran', detailUser.roleLabel], ['Status akses', detailUser.retiredRole ? 'Peran dihentikan' : detailUser.active ? 'Aktif' : 'Nonaktif'], ['Profil petugas', detailUser.nakes?.name], ['Kode petugas', detailUser.nakes?.code], ['Poliklinik', detailUser.nakes?.clinic]] : []} />
            <Dialog onOpenChange={setUserDialogOpen} open={userDialogOpen}><DialogContent><DialogHeader><DialogTitle>{editingUser ? 'Edit pengguna' : 'Tambah pengguna baru'}</DialogTitle><DialogDescription>Atur identitas, peran, dan status akun.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={saveUser}><Field error={form.errors.name} htmlFor="user-name" label="Nama pengguna" required><Input placeholder={identityPlaceholders.nama} autoComplete="name" id="user-name" onChange={(event) => form.setData('name', event.target.value)} required value={form.data.name} /></Field><Field error={form.errors.email} htmlFor="user-email" label="Email akun" required><Input placeholder={identityPlaceholders.email} autoComplete="email" id="user-email" onChange={(event) => form.setData('email', event.target.value)} required type="email" value={form.data.email} /></Field>{!editingUser && <Field error={form.errors.password} htmlFor="user-password" label="Kata sandi" required><Input placeholder="Minimal 8 karakter" autoComplete="new-password" id="user-password" minLength={8} onChange={(event) => form.setData('password', event.target.value)} required type="password" value={form.data.password} /></Field>}<Field error={form.errors.role} htmlFor="user-role-select" label="Peran hak akses" required><Select disabled={editingUser?.isCurrentUser} id="user-role-select" onChange={(event) => { form.setData('role', event.target.value); form.setData('nakes_id', ''); if (!['perawat', 'farmasi'].includes(event.target.value)) form.setData('poliklinik_id', ''); }} value={form.data.role}><option value="">Pilih peran</option>{roles.map((item) => {
                    const occupied = occupiedSingleRoles.includes(item.value) && editingUser?.role !== item.value;
                    return <option disabled={occupied} key={item.value} value={item.value}>{item.label}{occupied ? ' (akun aktif tersedia)' : ''}</option>;
                })}</Select></Field>{['dokter', 'perawat', 'farmasi', 'pendaftaran'].includes(form.data.role) && <Field error={form.errors.nakes_id} htmlFor="user-doctor" label="Profil petugas" required={requiresProfile}><Select id="user-doctor" onChange={(event) => { const profile = staffProfiles.find((item) => String(item.id) === event.target.value); form.setData('nakes_id', event.target.value); if (profile) { form.setData('name', profile.name); if (['perawat', 'farmasi'].includes(form.data.role)) form.setData('poliklinik_id', String(profile.clinicId ?? '')); } }} required={requiresProfile} value={form.data.nakes_id}><option value="">{requiresProfile ? 'Pilih profil tenaga kerja' : 'Pilih profil bila diperlukan'}</option>{staffProfiles.filter((item) => item.role === form.data.role && (item.userId === null || item.userId === editingUser?.id)).map((item) => <option key={item.id} value={item.id}>{item.name} ({item.code})</option>)}</Select><p className="text-xs text-neutral-500">Gunakan profil yang sudah ada atau tambahkan melalui Tenaga kesehatan &amp; karyawan.</p></Field>}{['perawat', 'farmasi'].includes(form.data.role) && <Field error={form.errors.poliklinik_id} htmlFor="user-nurse-clinic" label="Poliklinik penugasan" required><Select id="user-nurse-clinic" onChange={(event) => form.setData('poliklinik_id', event.target.value)} required value={form.data.poliklinik_id}><option value="">Pilih poli akun ini</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}</Select><p className="text-xs text-neutral-500">{form.data.role === 'farmasi' ? 'Akun farmasi akan memproses resep, obat langsung, dan stok pada poli penugasannya.' : 'Akun perawat akan melihat layanan pada poli penugasannya.'}</p></Field>}<Label className="flex items-center gap-2 text-sm text-neutral-700"><Checkbox aria-label="Akun aktif" checked={form.data.is_active} disabled={editingUser?.isCurrentUser} onCheckedChange={(checked) => form.setData('is_active', (checked === true))} />Akun aktif</Label>{editingUser?.isCurrentUser && <p className="text-xs text-muted-foreground">Peran dan status akun sendiri tidak dapat diubah.</p>}<div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setUserDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : editingUser ? 'Simpan perubahan' : 'Buat pengguna'}</Button></div></form></DialogContent></Dialog>

            <Dialog onOpenChange={setResetDialogOpen} open={resetDialogOpen}><DialogContent className="max-w-md"><DialogHeader><DialogTitle>Atur ulang kata sandi</DialogTitle><DialogDescription>Atur kata sandi baru untuk {resetUser?.name ?? 'pengguna'}.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={resetPassword}><Field error={resetForm.errors.password} htmlFor="reset-password" label="Kata sandi baru" required><Input placeholder="Minimal 8 karakter" autoComplete="new-password" id="reset-password" minLength={8} onChange={(event) => resetForm.setData('password', event.target.value)} required type="password" value={resetForm.data.password} /></Field>{resetUser && ['dokter', 'perawat', 'farmasi', 'admin'].includes(resetUser.role) && <div className="rounded-xl border border-neutral-200 bg-neutral-50 p-3 text-xs text-neutral-600"><p className="font-medium text-neutral-900">PIN Tanda Tangan Klinis Terpisah</p><p className="mt-1">Reset kata sandi login tidak mengubah PIN pengesahan pribadi petugas ({resetUser.hasSigningPin ? 'PIN aktif' : resetUser.mustSetupSigningPin ? 'Menunggu penyetelan PIN' : 'Belum disetel'}). Jika petugas lupa PIN pengesahan, reset status PIN agar petugas menyetel sendiri di Profil saya.</p><Button className="mt-2 w-full" onClick={() => router.post(`/users/${resetUser.id}/reset-signing-pin`, {}, { preserveScroll: true, onSuccess: () => setResetDialogOpen(false) })} size="sm" type="button" variant="secondary">Reset status PIN tanda tangan</Button></div>}<div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setResetDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={resetForm.processing} type="submit">{resetForm.processing ? 'Menyimpan…' : 'Simpan kata sandi'}</Button></div></form></DialogContent></Dialog>
        </>
    );
}
