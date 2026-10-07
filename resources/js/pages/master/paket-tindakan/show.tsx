import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency } from '@/lib/format';

interface Service { id: number; name: string; tariff: number }
interface PackageItem { id: number; type: string; name: string; tariff: number }
interface Props {
    package: { id: number; code: string; name: string; description: string | null; tariff: number; active: boolean; items: PackageItem[]; standardTariffTotal: number };
    treatments: Service[];
    laboratories: Service[];
}

export default function PackageShow({ package: servicePackage, treatments, laboratories }: Props) {
    const form = useForm({ jenis: 'tindakan', tindakan_id: '', laboratorium_id: '' });
    function add(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/master/paket-tindakan/${servicePackage.id}/item`, { preserveScroll: true, onSuccess: () => form.reset('tindakan_id', 'laboratorium_id') });
    }
    function remove(item: PackageItem) {
        if (window.confirm(`Hapus ${item.name} dari paket ini?`)) {
            router.delete(`/master/paket-tindakan/item/${item.id}`, { preserveScroll: true });
        }
    }
    const savings = Math.max(0, servicePackage.standardTariffTotal - servicePackage.tariff);

    return <>
        <Head title={`Paket ${servicePackage.name}`} />
        <div className="space-y-6">
            <div><Button asChild className="mb-3" size="sm" variant="ghost"><Link href="/master/paket-tindakan"><ArrowLeft className="size-4" />Kembali ke paket</Link></Button><p className="text-sm font-medium text-violet-700">Komposisi paket layanan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">{servicePackage.name} <span className="font-mono text-base text-slate-500">({servicePackage.code})</span></h2><p className="mt-1 text-sm text-slate-500">Tarif paket <strong className="text-slate-800">{formatCurrency(servicePackage.tariff)}</strong> · {servicePackage.active ? 'Aktif' : 'Nonaktif'}{servicePackage.description ? ` · ${servicePackage.description}` : ''}</p></div>
            <Card><CardHeader><CardTitle>Tambah layanan</CardTitle><CardDescription>Pilih tindakan medis atau pemeriksaan laboratorium yang termasuk dalam paket.</CardDescription></CardHeader><CardContent><form className="grid gap-4 sm:grid-cols-[12rem_minmax(0,1fr)_auto] sm:items-end" onSubmit={add}>
                <Field error={form.errors.jenis} htmlFor="package-service-type" label="Jenis layanan" required><NativeSelect id="package-service-type" onChange={(event) => form.setData('jenis', event.target.value)} value={form.data.jenis}><option value="tindakan">Tindakan medis</option><option value="lab">Laboratorium</option></NativeSelect></Field>
                {form.data.jenis === 'tindakan' ? <Field error={form.errors.tindakan_id} htmlFor="package-treatment" label="Tindakan" required><NativeSelect id="package-treatment" onChange={(event) => form.setData('tindakan_id', event.target.value)} required value={form.data.tindakan_id}><option value="">Pilih tindakan</option>{treatments.map((item) => <option key={item.id} value={item.id}>{item.name} · {formatCurrency(item.tariff)}</option>)}</NativeSelect></Field> : <Field error={form.errors.laboratorium_id} htmlFor="package-laboratory" label="Pemeriksaan laboratorium" required><NativeSelect id="package-laboratory" onChange={(event) => form.setData('laboratorium_id', event.target.value)} required value={form.data.laboratorium_id}><option value="">Pilih pemeriksaan</option>{laboratories.map((item) => <option key={item.id} value={item.id}>{item.name} · {formatCurrency(item.tariff)}</option>)}</NativeSelect></Field>}
                <Button disabled={form.processing} type="submit"><Plus className="size-4" />Tambah layanan</Button>
            </form></CardContent></Card>
            <Card className="overflow-hidden"><CardHeader className="border-b border-slate-100"><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><ClipboardList className="size-5" /></span><div><CardTitle>Layanan dalam paket</CardTitle><CardDescription className="mt-1">{servicePackage.items.length} layanan · tarif satuan {formatCurrency(servicePackage.standardTariffTotal)}</CardDescription></div></div></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Jenis layanan</TableHead><TableHead>Nama layanan</TableHead><TableHead className="text-right">Tarif standar</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>
                {servicePackage.items.length ? servicePackage.items.map((item) => <TableRow key={item.id}><TableCell>{item.type === 'tindakan' ? 'Tindakan medis' : 'Laboratorium'}</TableCell><TableCell className="font-medium text-slate-900">{item.name}</TableCell><TableCell className="text-right font-mono">{formatCurrency(item.tariff)}</TableCell><TableCell className="text-right"><Button aria-label={`Hapus ${item.name}`} onClick={() => remove(item)} size="icon" variant="destructive"><Trash2 className="size-4" /></Button></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-slate-500" colSpan={4}>Belum ada layanan dalam paket ini.</TableCell></TableRow>}
            </TableBody></Table></div>{servicePackage.items.length > 0 && <div className="flex flex-wrap justify-between gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 text-sm"><span className="text-slate-600">Penghematan dari tarif satuan</span><strong className="text-emerald-700">{formatCurrency(savings)}</strong></div>}</CardContent></Card>
        </div>
    </>;
}
