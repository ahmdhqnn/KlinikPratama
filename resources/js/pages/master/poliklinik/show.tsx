import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, DoorOpen, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface Props {
    clinic: { id: number; code: string; name: string; rooms: { id: number; name: string; active: boolean }[] };
}

export default function ClinicRooms({ clinic }: Props) {
    const form = useForm({ nama: '' });

    function addRoom(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/master/poliklinik/${clinic.id}/ruang`, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    function deleteRoom(room: Props['clinic']['rooms'][number]) {
        confirmAction(`Hapus ruang ${room.name}?`, () => { router.delete(`/master/poliklinik/ruang/${room.id}`, { preserveScroll: true }); }, 'Hapus');
    }

    return (
        <>
            <Head title={`Ruang Poli ${clinic.name}`} />
            <div className="space-y-6"><div className="flex flex-wrap items-end justify-between gap-4"><div><Button asChild className="mb-3" size="sm" variant="ghost"><Link href="/master/poliklinik"><ArrowLeft className="size-4" />Kembali ke poliklinik</Link></Button><p className="text-sm font-medium text-neutral-700">Pengaturan ruang pemeriksaan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{clinic.name} <span className="font-mono text-base text-neutral-500">({clinic.code})</span></h2></div></div>
                <Card><CardHeader><CardTitle>Tambah ruang periksa</CardTitle><CardDescription>Tambahkan ruang yang tersedia di unit ini.</CardDescription></CardHeader><CardContent><form className="flex flex-col gap-4 sm:flex-row sm:items-end" onSubmit={addRoom}><div className="flex-1"><Field error={form.errors.nama} htmlFor="room-name" label="Nama ruang" required><Input autoFocus id="room-name" maxLength={100} onChange={(event) => form.setData('nama', event.target.value)} placeholder="Contoh: Ruang Poli 1" required value={form.data.nama} /></Field></div><Button disabled={form.processing} type="submit"><Plus className="size-4" />Tambah ruang</Button></form></CardContent></Card>
                <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><DoorOpen className="size-5" /></span><div><CardTitle>Daftar ruang periksa</CardTitle><CardDescription className="mt-1">{clinic.rooms.length} ruang terdaftar.</CardDescription></div></div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Nama ruang</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{clinic.rooms.length ? clinic.rooms.map((room) => <TableRow key={room.id}><TableCell className="font-medium text-neutral-900">{room.name}</TableCell><TableCell>{room.active ? 'Aktif' : 'Nonaktif'}</TableCell><TableCell className="text-right"><Button aria-label={`Hapus ruang ${room.name}`} onClick={() => deleteRoom(room)} size="sm" variant="destructive"><Trash2 className="size-4" />Hapus</Button></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-neutral-500" colSpan={3}>Belum ada ruang periksa di poliklinik ini.</TableCell></TableRow>}</TableBody></Table></div></CardContent></Card>
            </div>
        </>
    );
}
