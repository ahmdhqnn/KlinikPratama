import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useMemo, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency, formatNumber } from '@/lib/format';

interface Medicine { id: number; name: string; unit: string | null; stock: number; price: number }
interface Props { medicines: Medicine[] }
interface SaleItem { obat_id: string; jumlah: string }
interface SaleForm { nama_pembeli: string; metode_bayar: 'tunai' | 'transfer' | 'qris'; bayar: string; items: SaleItem[] }

export default function CreateDirectSale({ medicines }: Props) {
    const form = useForm<SaleForm>({ nama_pembeli: '', metode_bayar: 'tunai', bayar: '', items: [{ obat_id: '', jumlah: '1' }] });
    const total = useMemo(() => form.data.items.reduce((sum, item) => {
        const medicine = medicines.find((entry) => String(entry.id) === item.obat_id);
        return sum + Number(item.jumlah || 0) * (medicine?.price ?? 0);
    }, 0), [form.data.items, medicines]);
    const change = Math.max(0, Number(form.data.bayar || 0) - total);
    const errors = form.errors as Record<string, string | undefined>;

    function updateItem(index: number, key: keyof SaleItem, value: string) {
        const items = [...form.data.items];
        items[index] = { ...items[index], [key]: value };
        form.setData('items', items);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/stok/penjualan-langsung', { preserveScroll: true });
    }

    return <><Head title="Transaksi Penjualan" /><div className="mx-auto max-w-5xl space-y-5"><Button asChild size="sm" variant="ghost"><Link href="/stok/penjualan-langsung"><ArrowLeft className="size-4" />Kembali ke riwayat</Link></Button><div><p className="text-sm font-medium text-neutral-700">Kasir farmasi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Transaksi penjualan baru</h2><p className="mt-1 text-sm text-neutral-500">Harga mengikuti data obat dan stok dikurangi setelah pembayaran valid.</p></div>
        <Card><CardHeader><CardTitle>Rincian transaksi</CardTitle><CardDescription>Harga satuan terkunci mengikuti harga jual aktif di data obat.</CardDescription></CardHeader><CardContent><form className="space-y-6" onSubmit={submit}><div className="grid gap-4 md:grid-cols-2"><Field error={form.errors.nama_pembeli} htmlFor="sale-buyer" label="Nama pembeli"><Input id="sale-buyer" maxLength={200} onChange={(event) => form.setData('nama_pembeli', event.target.value)} placeholder="Kosongkan untuk pembeli umum" value={form.data.nama_pembeli} /></Field><Field error={form.errors.metode_bayar} htmlFor="sale-payment-method" label="Metode pembayaran" required><Select id="sale-payment-method" onChange={(event) => form.setData('metode_bayar', event.target.value as SaleForm['metode_bayar'])} value={form.data.metode_bayar}><option value="tunai">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></Select></Field></div>
            <div className="space-y-3"><div><h3 className="text-base font-semibold text-neutral-900">Obat yang dijual</h3><p className="text-sm text-neutral-500">Pilih obat aktif dengan stok tersedia.</p></div><div className="overflow-x-auto rounded-xl border border-neutral-200"><Table className="min-w-[780px]"><TableHeader><tr><TableHead>Obat</TableHead><TableHead className="w-32">Jumlah</TableHead><TableHead className="text-right">Harga satuan</TableHead><TableHead className="text-right">Subtotal</TableHead><TableHead className="w-12" /></tr></TableHeader><TableBody>{form.data.items.map((item, index) => { const selected = medicines.find((medicine) => String(medicine.id) === item.obat_id); const quantity = Number(item.jumlah || 0); return <TableRow key={index}><TableCell><Select aria-label={`Obat baris ${index + 1}`} onChange={(event) => updateItem(index, 'obat_id', event.target.value)} value={item.obat_id}><option value="">Pilih obat</option>{medicines.map((medicine) => <option disabled={form.data.items.some((other, otherIndex) => otherIndex !== index && other.obat_id === String(medicine.id))} key={medicine.id} value={medicine.id}>{medicine.name} · stok {formatNumber(medicine.stock)} {medicine.unit ?? ''}</option>)}</Select>{errors[`items.${index}.obat_id`] && <p className="mt-1 text-xs text-red-600">{errors[`items.${index}.obat_id`]}</p>}</TableCell><TableCell><Input aria-label={`Jumlah ${selected?.name ?? `baris ${index + 1}`}`} max={selected?.stock} min="1" onChange={(event) => updateItem(index, 'jumlah', event.target.value)} step="1" type="number" value={item.jumlah} />{selected && <p className="mt-1 text-xs text-neutral-500">Tersedia {formatNumber(selected.stock)} {selected.unit ?? ''}</p>}</TableCell><TableCell className="text-right">{formatCurrency(selected?.price ?? 0)}</TableCell><TableCell className="text-right font-semibold">{formatCurrency(quantity * (selected?.price ?? 0))}</TableCell><TableCell><Button aria-label={`Hapus baris ${index + 1}`} disabled={form.data.items.length <= 1} onClick={() => form.setData('items', form.data.items.filter((_, itemIndex) => itemIndex !== index))} size="icon" variant="ghost"><Trash2 className="size-4" /></Button></TableCell></TableRow>; })}</TableBody></Table></div><div className="flex flex-wrap items-center justify-between gap-3"><Button onClick={() => form.setData('items', [...form.data.items, { obat_id: '', jumlah: '1' }])} type="button" variant="secondary"><Plus className="size-4" />Tambah obat</Button><p className="text-lg font-bold text-neutral-800">Total tagihan: {formatCurrency(total)}</p></div>{form.errors.items && <p className="text-sm text-red-600">{form.errors.items}</p>}</div>
            <div className="grid gap-4 rounded-xl border border-neutral-100 bg-neutral-50 p-4 sm:grid-cols-2"><Field error={form.errors.bayar} htmlFor="sale-paid" label="Nominal dibayar (Rp)" required><Input id="sale-paid" min={total} onChange={(event) => form.setData('bayar', event.target.value)} required step="0.01" type="number" value={form.data.bayar} /><Button className="mt-1 h-auto px-0 py-1 text-xs font-medium text-neutral-700 hover:bg-transparent hover:underline" onClick={() => form.setData('bayar', String(total))} type="button" variant="link">Isi sesuai total</Button></Field><div className="flex flex-col justify-center"><p className="text-xs font-medium uppercase tracking-wide text-neutral-500">Kembalian</p><p className="mt-1 text-2xl font-bold text-neutral-800">{formatCurrency(change)}</p></div></div><div className="flex justify-end gap-2 border-t border-neutral-100 pt-4"><Button asChild variant="secondary"><Link href="/stok/penjualan-langsung">Batal</Link></Button><Button disabled={form.processing || medicines.length === 0 || total <= 0} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan dan buka nota'}</Button></div>{medicines.length === 0 && <p className="text-sm text-amber-700">Tidak ada obat aktif dengan stok tersedia.</p>}</form></CardContent></Card>
    </div></>;
}
