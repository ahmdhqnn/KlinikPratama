import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, FlaskConical, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface Indicator { id: number; name: string; unit: string | null; referenceMin: string | null; referenceMax: string | null; format: string; choices: string[] }
interface Supply { id: number; medicine: string; unit: string; quantity: number }
interface Props {
    laboratory: { id: number; code: string; name: string; clinic: string; tariff: number; indicators: Indicator[]; supplies: Supply[] };
    medicines: { id: number; name: string; stock: number; unit: string | null }[];
}

export default function LaboratoryShow({ laboratory, medicines }: Props) {
    const indicatorForm = useForm({ nama: '', satuan: '', nilai_rujukan_min: '', nilai_rujukan_max: '', format_input: 'number', pilihan: '' });
    const supplyForm = useForm({ obat_id: '', jumlah: '1' });
    function addIndicator(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        indicatorForm.post(`/master/laboratorium/${laboratory.id}/indikator`, { preserveScroll: true, onSuccess: () => indicatorForm.reset() });
    }
    function addSupply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        supplyForm.post(`/master/laboratorium/${laboratory.id}/bhp`, { preserveScroll: true, onSuccess: () => supplyForm.reset('jumlah') });
    }
    function removeIndicator(indicator: Indicator) {
        confirmAction(`Hapus indikator ${indicator.name}?`, () => { router.delete(`/master/laboratorium/indikator/${indicator.id}`, { preserveScroll: true }); }, 'Hapus');
    }
    function removeSupply(supply: Supply) {
        confirmAction(`Hapus ${supply.medicine} dari BHP laboratorium?`, () => { router.delete(`/master/laboratorium/bhp/${supply.id}`, { preserveScroll: true }); }, 'Hapus');
    }

    return <>
        <Head title={`Laboratorium ${laboratory.name}`} />
        <div className="space-y-6"><div><Button asChild className="mb-3" size="sm" variant="ghost"><Link href="/master/laboratorium"><ArrowLeft className="size-4" />Kembali ke laboratorium</Link></Button><p className="text-sm font-medium text-neutral-700">Pengaturan pemeriksaan</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{laboratory.name} <span className="font-mono text-base text-neutral-500">({laboratory.code})</span></h2><p className="mt-1 text-sm text-neutral-500">Poliklinik: {laboratory.clinic} · Tarif Rp {new Intl.NumberFormat('id-ID').format(laboratory.tariff)}</p></div>
            <Card><CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><FlaskConical className="size-5" /></span><div><CardTitle>Indikator hasil</CardTitle><CardDescription className="mt-1">Tentukan format input dan rentang nilai rujukan untuk hasil pemeriksaan.</CardDescription></div></div></CardHeader><CardContent className="space-y-5"><form className="space-y-4" onSubmit={addIndicator}><div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><Field error={indicatorForm.errors.nama} htmlFor="indicator-name" label="Nama indikator" required><Input id="indicator-name" maxLength={100} onChange={(event) => indicatorForm.setData('nama', event.target.value)} required value={indicatorForm.data.nama} /></Field><Field error={indicatorForm.errors.satuan} htmlFor="indicator-unit" label="Satuan"><Input id="indicator-unit" maxLength={50} onChange={(event) => indicatorForm.setData('satuan', event.target.value)} placeholder="g/dL, %, mg/dL" value={indicatorForm.data.satuan} /></Field><Field error={indicatorForm.errors.format_input} htmlFor="indicator-format" label="Format input" required><Select id="indicator-format" onChange={(event) => indicatorForm.setData('format_input', event.target.value)} value={indicatorForm.data.format_input}><option value="number">Angka</option><option value="text">Teks bebas</option><option value="select">Pilihan</option></Select></Field><Field error={indicatorForm.errors.nilai_rujukan_min} htmlFor="indicator-min" label="Nilai rujukan minimum"><Input id="indicator-min" onChange={(event) => indicatorForm.setData('nilai_rujukan_min', event.target.value)} value={indicatorForm.data.nilai_rujukan_min} /></Field><Field error={indicatorForm.errors.nilai_rujukan_max} htmlFor="indicator-max" label="Nilai rujukan maksimum"><Input id="indicator-max" onChange={(event) => indicatorForm.setData('nilai_rujukan_max', event.target.value)} value={indicatorForm.data.nilai_rujukan_max} /></Field>{indicatorForm.data.format_input === 'select' && <Field error={indicatorForm.errors.pilihan} htmlFor="indicator-choices" label="Pilihan (pisahkan dengan koma)" required><Input id="indicator-choices" onChange={(event) => indicatorForm.setData('pilihan', event.target.value)} placeholder="Positif, Negatif" value={indicatorForm.data.pilihan} /></Field>}</div><Button disabled={indicatorForm.processing} type="submit"><Plus className="size-4" />Tambah indikator</Button></form>
                <div className="overflow-x-auto border-t border-neutral-100 pt-4"><Table><TableHeader><tr><TableHead>Nama indikator</TableHead><TableHead>Satuan</TableHead><TableHead>Nilai rujukan</TableHead><TableHead>Format / pilihan</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{laboratory.indicators.length ? laboratory.indicators.map((item) => <TableRow key={item.id}><TableCell className="font-medium text-neutral-900">{item.name}</TableCell><TableCell>{item.unit ?? '—'}</TableCell><TableCell>{item.referenceMin || item.referenceMax ? `${item.referenceMin ?? '—'} – ${item.referenceMax ?? '—'}` : '—'}</TableCell><TableCell className="capitalize">{item.format}{item.choices.length > 0 && <span className="mt-1 block text-xs text-neutral-500">{item.choices.join(', ')}</span>}</TableCell><TableCell className="text-right"><Button aria-label={`Hapus indikator ${item.name}`} onClick={() => removeIndicator(item)} size="icon" variant="destructive"><Trash2 className="size-4" /></Button></TableCell></TableRow>) : <TableRow><TableCell colSpan={5}><Empty size="compact" title="Belum ada indikator yang diatur." /></TableCell></TableRow>}</TableBody></Table></div>
            </CardContent></Card>
            <Card><CardHeader><CardTitle>Bahan habis pakai laboratorium</CardTitle><CardDescription>Item berikut akan dipotong dari stok saat pemeriksaan dijalankan.</CardDescription></CardHeader><CardContent className="space-y-5"><form className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end" onSubmit={addSupply}><Field error={supplyForm.errors.obat_id} htmlFor="lab-supply" label="Obat / alkes" required><Select id="lab-supply" onChange={(event) => supplyForm.setData('obat_id', event.target.value)} required value={supplyForm.data.obat_id}><option value="">Pilih item stok</option>{medicines.map((item) => <option key={item.id} value={item.id}>{item.name} · stok {item.stock} {item.unit ?? ''}</option>)}</Select></Field><Field error={supplyForm.errors.jumlah} htmlFor="lab-supply-quantity" label="Jumlah" required><Input id="lab-supply-quantity" min="0.01" onChange={(event) => supplyForm.setData('jumlah', event.target.value)} required step="0.01" type="number" value={supplyForm.data.jumlah} /></Field><Button disabled={supplyForm.processing} type="submit"><Plus className="size-4" />Tambah BHP</Button></form><div className="overflow-x-auto border-t border-neutral-100 pt-4"><Table><TableHeader><tr><TableHead>Nama item</TableHead><TableHead className="text-right">Jumlah</TableHead><TableHead>Satuan</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{laboratory.supplies.length ? laboratory.supplies.map((item) => <TableRow key={item.id}><TableCell className="font-medium text-neutral-900">{item.medicine}</TableCell><TableCell className="text-right font-mono">{item.quantity}</TableCell><TableCell>{item.unit}</TableCell><TableCell className="text-right"><Button aria-label={`Hapus ${item.medicine}`} onClick={() => removeSupply(item)} size="icon" variant="destructive"><Trash2 className="size-4" /></Button></TableCell></TableRow>) : <TableRow><TableCell colSpan={4}><Empty size="compact" title="Belum ada BHP laboratorium." /></TableCell></TableRow>}</TableBody></Table></div></CardContent></Card>
        </div>
    </>;
}
