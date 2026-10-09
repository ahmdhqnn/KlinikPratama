import { Head, router, useForm } from '@inertiajs/react';
import { KeyRound, Plus, Search, ShieldCheck, UserRoundCog, UsersRound } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface UserRecord {
    id: number;
    name: string;
    email: string;
    role: string;
    roleLabel: string;
    active: boolean;
    nakes: { id: number; name: string; code: string } | null;
    isCurrentUser: boolean;
}

interface Props {
    users: PaginationData & { data: UserRecord[] };
    filters: { search: string; role: string };
    roles: { value: string; label: string }[];
    doctors: { id: number; name: string; code: string; linked: boolean }[];
}

const roleStyles: Record<string, string> = {
    admin: 'bg-neutral-50 text-neutral-700',
    dokter: 'bg-neutral-50 text-neutral-700',
    perawat: 'bg-neutral-50 text-neutral-700',
    farmasi: 'bg-neutral-50 text-neutral-700',
    manajemen: 'bg-neutral-50 text-neutral-700',
    pendaftaran: 'bg-neutral-50 text-neutral-700',
};

const emptyUser = { name: '', email: '', password: '', role: 'perawat', is_active: true, nakes_id: '' };

export default function UserManagement({ users, filters, roles, doctors }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [role, setRole] = useState(filters.role);
    const [editingUser, setEditingUser] = useState<UserRecord | null>(null);
    const [userDialogOpen, setUserDialogOpen] = useState(false);
    const [resetUser, setResetUser] = useState<UserRecord | null>(null);
    const [resetDialogOpen, setResetDialogOpen] = useState(false);
    const form = useForm(emptyUser);
    const resetForm = useForm({ password: '' });

    function filterUsers(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/users', { search, role }, { preserveState: true, preserveScroll: true, replace: true });
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
        form.setData({ name: user.name, email: user.email, password: '', role: user.role, is_active: user.active, nakes_id: String(user.nakes?.id ?? '') });
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

    function deleteUser(user: UserRecord) {
        confirmAction(`Hapus akun ${user.name}?`, () => { router.delete(`/users/${user.id}`, { preserveScroll: true }); }, 'Hapus');
    }

    return (
        <>
            <Head title="Manajemen Pengguna" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Akses dan akun aplikasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Manajemen pengguna</h2><p className="mt-1 max-w-2xl text-sm text-neutral-500">Kelola peran, status akses, profil tenaga kesehatan, dan reset kata sandi.</p></div><Button onClick={openCreate}><Plus className="size-4" />Tambah pengguna</Button></div>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardContent className="flex min-h-[5.5rem] items-center gap-4 p-4 sm:p-5">
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-neutral-900 text-neutral-50"><UsersRound aria-hidden="true" className="size-5" /></span>
                            <div className="min-w-0">
                                <p className="text-xs font-medium text-neutral-500">Pengguna ditemukan</p>
                                <p className="mt-1 text-lg font-semibold leading-tight tracking-tight text-neutral-950">{new Intl.NumberFormat('id-ID').format(users.total)}</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex min-h-[5.5rem] items-center gap-4 p-4 sm:p-5">
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-neutral-900 text-neutral-50"><ShieldCheck aria-hidden="true" className="size-5" /></span>
                            <div className="min-w-0">
                                <p className="text-xs font-medium text-neutral-500">Peran yang dikelola</p>
                                <p className="mt-1 text-lg font-semibold leading-tight tracking-tight text-neutral-950">{roles.length} jenis akses</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="flex min-h-[5.5rem] items-center gap-4 p-4 sm:p-5">
                            <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-neutral-900 text-neutral-50"><UserRoundCog aria-hidden="true" className="size-5" /></span>
                            <div className="min-w-0">
                                <p className="text-xs font-medium text-neutral-500">Profil dokter tersedia</p>
                                <p className="mt-1 text-base font-semibold leading-tight tracking-tight text-neutral-950">{doctors.filter((doctor) => !doctor.linked).length} belum terhubung</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><CardTitle>Daftar pengguna</CardTitle><CardDescription>Temukan akun menurut nama, email, atau peran.</CardDescription><form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_14rem_auto]" onSubmit={filterUsers}><Label className="sr-only" htmlFor="user-search">Cari nama atau email</Label><Input id="user-search" onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama atau email…" value={search} /><Label className="sr-only" htmlFor="user-role">Filter peran</Label><Select id="user-role" onChange={(event) => setRole(event.target.value)} value={role}><option value="">Semua peran</option>{roles.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select><Button type="submit"><Search className="size-4" />Cari</Button></form></CardHeader>
                    <CardContent className="p-0"><div className="overflow-x-auto"><Table className="min-w-[850px]"><TableHeader><tr><TableHead>Nama</TableHead><TableHead>Email login</TableHead><TableHead>Peran</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{users.data.length ? users.data.map((user) => <TableRow key={user.id}><TableCell><p className="font-medium text-neutral-900">{user.name}</p>{user.nakes && <p className="mt-0.5 text-xs text-neutral-500">{user.nakes.name} · {user.nakes.code}</p>}</TableCell><TableCell className="font-mono text-xs">{user.email}</TableCell><TableCell><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${roleStyles[user.role] ?? 'bg-neutral-100 text-neutral-700'}`}>{user.roleLabel}</span></TableCell><TableCell><span className={`inline-flex items-center gap-1.5 text-xs font-medium ${user.active ? 'text-emerald-700' : 'text-neutral-500'}`}><span className={`size-2 rounded-full ${user.active ? 'bg-emerald-500' : 'bg-neutral-400'}`} />{user.active ? 'Aktif' : 'Nonaktif'}</span></TableCell><TableCell><div className="flex justify-end gap-1"><Button onClick={() => openReset(user)} size="sm" variant="ghost"><KeyRound className="size-4" /><span className="sr-only sm:not-sr-only">Reset password</span></Button><Button onClick={() => openEdit(user)} size="sm" variant="ghost">Edit</Button>{!user.isCurrentUser && <><Button onClick={() => router.post(`/users/${user.id}/toggle-active`, {}, { preserveScroll: true })} size="sm" variant="secondary">{user.active ? 'Nonaktifkan' : 'Aktifkan'}</Button><Button onClick={() => deleteUser(user)} size="sm" variant="destructive">Hapus</Button></>}</div></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada pengguna yang sesuai filter." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={users} /></CardContent>
                </Card>
            </div>

            <Dialog onOpenChange={setUserDialogOpen} open={userDialogOpen}><DialogContent><DialogHeader><DialogTitle>{editingUser ? 'Edit pengguna' : 'Tambah pengguna baru'}</DialogTitle><DialogDescription>Atur identitas, peran, dan status akun.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={saveUser}><Field error={form.errors.name} htmlFor="user-name" label="Nama pengguna" required><Input autoComplete="name" id="user-name" onChange={(event) => form.setData('name', event.target.value)} required value={form.data.name} /></Field><Field error={form.errors.email} htmlFor="user-email" label="Email login" required><Input autoComplete="email" id="user-email" onChange={(event) => form.setData('email', event.target.value)} required type="email" value={form.data.email} /></Field>{!editingUser && <Field error={form.errors.password} htmlFor="user-password" label="Password" required><Input autoComplete="new-password" id="user-password" minLength={6} onChange={(event) => form.setData('password', event.target.value)} required type="password" value={form.data.password} /></Field>}<Field error={form.errors.role} htmlFor="user-role-select" label="Peran hak akses" required><Select id="user-role-select" onChange={(event) => { form.setData('role', event.target.value); if (event.target.value !== 'dokter') form.setData('nakes_id', ''); }} value={form.data.role}>{roles.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}</Select></Field>{form.data.role === 'dokter' && <Field error={form.errors.nakes_id} htmlFor="user-doctor" label="Profil dokter" required><Select id="user-doctor" onChange={(event) => form.setData('nakes_id', event.target.value)} required value={form.data.nakes_id}><option value="">Pilih dokter</option>{doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.name} ({doctor.code}){doctor.linked && doctor.id !== editingUser?.nakes?.id ? ' · sudah terhubung' : ''}</option>)}</Select><p className="text-xs text-neutral-500">Kunjungan dan rekam medis dokter mengikuti profil ini.</p></Field>}<Label className="flex items-center gap-2 text-sm text-neutral-700"><Checkbox checked={form.data.is_active} onCheckedChange={(checked) => form.setData('is_active', (checked === true))} />Akun aktif</Label><div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setUserDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={form.processing} type="submit">{form.processing ? 'Menyimpan…' : editingUser ? 'Simpan perubahan' : 'Buat pengguna'}</Button></div></form></DialogContent></Dialog>

            <Dialog onOpenChange={setResetDialogOpen} open={resetDialogOpen}><DialogContent className="max-w-md"><DialogHeader><DialogTitle>Reset password</DialogTitle><DialogDescription>Atur kata sandi baru untuk {resetUser?.name ?? 'pengguna'}.</DialogDescription></DialogHeader><form className="space-y-4" onSubmit={resetPassword}><Field error={resetForm.errors.password} htmlFor="reset-password" label="Password baru" required><Input autoComplete="new-password" id="reset-password" minLength={6} onChange={(event) => resetForm.setData('password', event.target.value)} required type="password" value={resetForm.data.password} /></Field><div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button onClick={() => setResetDialogOpen(false)} type="button" variant="secondary">Batal</Button><Button disabled={resetForm.processing} type="submit">Simpan password</Button></div></form></DialogContent></Dialog>
        </>
    );
}
