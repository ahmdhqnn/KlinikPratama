import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useMemo, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/format';

interface Depot { id: number; nama: string }
interface Medicine { id: number; nama: string; satuan_kecil: string | null; harga_beli: number }
interface OrderItem { obat_id: string; jumlah: string; harga: string }
interface FormData { depo_id: string; supplier: string; tanggal: string; tanggal_kirim: string; catatan: string; items: OrderItem[] }
interface Props { depots: Depot[]; medicines: Medicine[]; today: string }

const newItem = (): OrderItem => ({ obat_id: '', jumlah: '10', harga: '0' });

export default function CreatePurchaseOrder({ depots, medicines, today }: Props) {
    const form = useForm<FormData>({ depo_id: depots[0] ? String(depots[0].id) : '', supplier: '', tanggal: today, tanggal_kirim: '', catatan: '', items: [newItem()] });
    const total = useMemo(() => form.data.items.reduce((sum, item) => sum + Number(item.jumlah || 0) * Number(item.harga || 0), 0), [form.data.items]);

    function updateItem(index: number, key: keyof OrderItem, value: string) {
        const items = [...form.data.items];
        items[index] = { ...items[index], [key]: value };
        if (key === 'obat_id') items[index].harga = String(medicines.find((medicine) => String(medicine.id) === value)?.harga_beli ?? 0);
        form.setData('items', items);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/stok/purchase-order', { preserveScroll: true });
    }

    const errors = form.errors as Record<string, string | undefined>;
    return <><Head title="Buat Pesanan Pembelian" /><div className="mx-auto max-w-5xl space-y-5"><Button asChild size="sm" variant="ghost"><Link href="/stok/purchase-order"><ArrowLeft className="size-4" />Kembali ke daftar PO</Link></Button>
        <div><p className="text-sm font-medium text-blue-700">Pengadaan obat</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Buat purchase order</h2><p className="mt-1 text-sm text-slate-500">Isi supplier, tujuan penyimpanan, dan obat yang akan dipesan.</p></div>
        <Card><CardHeader><CardTitle>Informasi pesanan</CardTitle><CardDescription>Nomor PO dibuat otomatis saat pesanan disimpan.</CardDescription></CardHeader><CardContent><form className="space-y-6" onSubmit={submit}><div className="grid gap-4 md:grid-cols-2"><Field error={form.errors.supplier} htmlFor="po-supplier" label="Supplier / PBF" required><Input id="po-supplier" maxLength={200} onChange={(event) => form.setData('supplier', event.target.value)} placeholder="Nama supplier" required value={form.data.supplier} /></Field><Field error={form.errors.depo_id} htmlFor="po-depot" label="Depo tujuan" required><NativeSelect id="po-depot" onChange={(event) => form.setData('depo_id', event.target.value)} required value={form.data.depo_id}><option value="">Pilih depo</option>{depots.map((depot) => <option key={depot.id} value={depot.id}>{depot.nama}</option>)}</NativeSelect></Field><Field error={form.errors.tanggal} htmlFor="po-date" label="Tanggal PO" required><Input id="po-date" onChange={(event) => form.setData('tanggal', event.target.value)} required type="date" value={form.data.tanggal} /></Field><Field error={form.errors.tanggal_kirim} htmlFor="po-delivery-date" label="Perkiraan tanggal kirim"><Input id="po-delivery-date" min={form.data.tanggal} onChange={(event) => form.setData('tanggal_kirim', event.target.value)} type="date" value={form.data.tanggal_kirim} /></Field></div>
            <div className="space-y-3"><div><h3 className="text-base font-semibold text-slate-900">Barang yang dipesan</h3><p className="text-sm text-slate-500">Harga beli awal akan terisi otomatis dan masih bisa disesuaikan untuk PO ini.</p></div><div className="overflow-x-auto rounded-xl border border-slate-200"><Table className="min-w-[760px]"><TableHeader><tr><TableHead>Obat</TableHead><TableHead className="w-32">Jumlah</TableHead><TableHead className="w-44">Harga satuan</TableHead><TableHead className="text-right">Subtotal</TableHead><TableHead className="w-12" /></tr></TableHeader><TableBody>{form.data.items.map((item, index) => <TableRow key={index}><TableCell><NativeSelect aria-label={`Obat baris ${index + 1}`} onChange={(event) => updateItem(index, 'obat_id', event.target.value)} value={item.obat_id}><option value="">Pilih obat</option>{medicines.map((medicine) => <option key={medicine.id} value={medicine.id}>{medicine.nama}{medicine.satuan_kecil ? ` · ${medicine.satuan_kecil}` : ''}</option>)}</NativeSelect>{errors[`items.${index}.obat_id`] && <p className="mt-1 text-xs text-red-600">{errors[`items.${index}.obat_id`]}</p>}</TableCell><TableCell><Input aria-label={`Jumlah baris ${index + 1}`} min="1" onChange={(event) => updateItem(index, 'jumlah', event.target.value)} step="1" type="number" value={item.jumlah} /></TableCell><TableCell><Input aria-label={`Harga baris ${index + 1}`} min="0" onChange={(event) => updateItem(index, 'harga', event.target.value)} step="0.01" type="number" value={item.harga} /></TableCell><TableCell className="text-right font-semibold">{formatCurrency(Number(item.jumlah || 0) * Number(item.harga || 0))}</TableCell><TableCell><Button aria-label={`Hapus baris ${index + 1}`} disabled={form.data.items.length <= 1} onClick={() => form.setData('items', form.data.items.filter((_, itemIndex) => itemIndex !== index))} size="icon" variant="ghost"><Trash2 className="size-4" /></Button></TableCell></TableRow>)}</TableBody></Table></div><div className="flex flex-wrap items-center justify-between gap-3"><Button onClick={() => form.setData('items', [...form.data.items, newItem()])} type="button" variant="secondary"><Plus className="size-4" />Tambah baris</Button><p className="text-lg font-bold text-blue-800">Total {formatCurrency(total)}</p></div>{form.errors.items && <p className="text-sm text-red-600">{form.errors.items}</p>}</div>
            <Field error={form.errors.catatan} htmlFor="po-note" label="Catatan"><Textarea id="po-note" maxLength={2000} onChange={(event) => form.setData('catatan', event.target.value)} placeholder="Catatan untuk supplier atau instruksi pengiriman" value={form.data.catatan} /></Field><div className="flex justify-end gap-2 border-t border-slate-100 pt-4"><Button asChild variant="secondary"><Link href="/stok/purchase-order">Batal</Link></Button><Button disabled={form.processing || depots.length === 0 || medicines.length === 0} type="submit">{form.processing ? 'Menyimpan…' : 'Simpan purchase order'}</Button></div>{depots.length === 0 && <p className="text-sm text-amber-700">Tambahkan depo aktif sebelum membuat pesanan.</p>}{medicines.length === 0 && <p className="text-sm text-amber-700">Belum ada obat aktif untuk dipesan.</p>}</form></CardContent></Card>
    </div></>;
}
