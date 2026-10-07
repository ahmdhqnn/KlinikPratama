import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency } from '@/lib/format';
import { confirmAction } from '@/components/ui/confirm-dialog';

interface Price { id: number; medicineId: number | null; medicine: string; regularPrice: number; specialPrice: number }
interface Props { insurance: { id: number; code: string; name: string; prices: Price[] }; medicines: { id: number; name: string; regularPrice: number }[] }

export default function InsurancePrices({ insurance, medicines }: Props) {
    const form = useForm({ obat_id: '', harga_khusus: '' });
    function save(event: FormEvent<HTMLFormElement>) { event.preventDefault(); form.post(`/master/asuransi/${insurance.id}/harga`, { preserveScroll: true, onSuccess: () => form.reset('harga_khusus') }); }
    function remove(price: Price) { confirmAction(`Hapus harga khusus untuk ${price.medicine}?`, () => { router.delete(`/master/asuransi/harga/${price.id}`, { preserveScroll: true }); }, 'Hapus'); }
    return <>
        <Head title={`Harga Khusus ${insurance.name}`} />
        <div className="space-y-6"><div><Button asChild className="mb-3" size="sm" variant="ghost"><Link href="/master/asuransi"><ArrowLeft className="size-4" />Kembali ke penjamin</Link></Button><p className="text-sm font-medium text-neutral-700">Pengaturan harga obat</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{insurance.name} <span className="font-mono text-base text-neutral-500">({insurance.code})</span></h2><p className="mt-1 text-sm text-neutral-500">Harga khusus diterapkan pada obat yang dipilih untuk penjamin ini.</p></div>
            <Card><CardHeader><div className="flex items-start gap-3"><span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><ShieldCheck className="size-5" /></span><div><CardTitle>Tambah atau ubah harga khusus</CardTitle><CardDescription className="mt-1">Menyimpan harga obat yang sama akan memperbarui harga yang sudah terdaftar.</CardDescription></div></div></CardHeader><CardContent><form className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem_auto] sm:items-end" onSubmit={save}><Field error={form.errors.obat_id} htmlFor="special-price-medicine" label="Obat" required><Select id="special-price-medicine" onChange={(event) => form.setData('obat_id', event.target.value)} required value={form.data.obat_id}><option value="">Pilih obat</option>{medicines.map((medicine) => <option key={medicine.id} value={medicine.id}>{medicine.name} · harga normal {formatCurrency(medicine.regularPrice)}</option>)}</Select></Field><Field error={form.errors.harga_khusus} htmlFor="special-price-amount" label="Harga khusus (Rp)" required><Input id="special-price-amount" min="0" onChange={(event) => form.setData('harga_khusus', event.target.value)} required type="number" value={form.data.harga_khusus} /></Field><Button disabled={form.processing} type="submit"><Plus className="size-4" />Simpan harga</Button></form></CardContent></Card>
            <Card className="overflow-hidden"><CardHeader className="border-b border-neutral-100"><CardTitle>Harga terdaftar</CardTitle><CardDescription>{insurance.prices.length} harga khusus sudah diatur.</CardDescription></CardHeader><CardContent className="p-0"><div className="overflow-x-auto"><Table><TableHeader><tr><TableHead>Nama obat</TableHead><TableHead className="text-right">Harga normal</TableHead><TableHead className="text-right">Harga khusus</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader><TableBody>{insurance.prices.length ? insurance.prices.map((price) => <TableRow key={price.id}><TableCell className="font-medium text-neutral-900">{price.medicine}</TableCell><TableCell className="text-right text-neutral-500 line-through">{formatCurrency(price.regularPrice)}</TableCell><TableCell className="text-right font-semibold text-neutral-800">{formatCurrency(price.specialPrice)}</TableCell><TableCell className="text-right"><Button aria-label={`Hapus harga ${price.medicine}`} onClick={() => remove(price)} size="icon" variant="destructive"><Trash2 className="size-4" /></Button></TableCell></TableRow>) : <TableRow><TableCell className="py-10 text-center text-neutral-500" colSpan={4}>Belum ada harga khusus untuk penjamin ini.</TableCell></TableRow>}</TableBody></Table></div></CardContent></Card>
        </div>
    </>;
}
