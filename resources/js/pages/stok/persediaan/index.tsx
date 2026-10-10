import { Head, Link, router } from '@inertiajs/react';
import { Package, Search } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { formatNumber } from '@/lib/format';

interface Medicine { id: number; name: string; code: string; type: string; stock: number; usable: number; minimum: number; unit: string; url: string }
export default function Inventory({ medicines, filters }: { medicines: PaginationData & { data: Medicine[] }; filters: { search?: string; jenis?: string } }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.jenis ?? '');
    return <><Head title="Persediaan Obat & BHP" /><div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Farmasi & logistik medis</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Persediaan obat & BHP</h2><p className="mt-1 text-sm text-neutral-500">Kelola batch, kedaluwarsa, penerimaan, dan stok opname. Stok layak pakai mengecualikan batch karantina dan kedaluwarsa.</p></div><Button asChild variant="secondary"><Link href="/stok/purchase-order">Pengadaan</Link></Button></div>
        <Card className="overflow-hidden">
            <CardHeader className="border-b border-neutral-100">
                <div className="flex items-start gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Package className="size-5" /></span>
                    <div>
                        <CardTitle>Daftar persediaan</CardTitle>
                        <CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(medicines.total)} item obat dan BHP tercatat.</CardDescription>
                    </div>
                </div>
                <form className="grid gap-3 pt-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-center" onSubmit={(event) => { event.preventDefault(); router.get('/stok/persediaan', { search, jenis: type }, { preserveState: true, replace: true }); }}>
                    <Input aria-label="Cari obat atau BHP" placeholder="Cari nama atau kode…" value={search} onChange={(event) => setSearch(event.target.value)} />
                    <Select className="w-full" aria-label="Jenis persediaan" value={type} onChange={(event) => setType(event.target.value)}><option value="">Obat & BHP</option><option value="obat">Obat</option><option value="bhp">BHP</option></Select>
                    <Button className="w-full sm:w-auto" type="submit" variant="secondary"><Search className="size-4" />Cari</Button>
                </form>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto"><Table className="min-w-[800px]"><TableHeader><TableRow><TableHead>Item persediaan</TableHead><TableHead>Jenis</TableHead><TableHead>Total fisik</TableHead><TableHead>Layak pakai</TableHead><TableHead>Batas minimum</TableHead><TableHead>Aksi</TableHead></TableRow></TableHeader><TableBody>{medicines.data.length ? medicines.data.map((medicine) => <TableRow key={medicine.id}><TableCell><p className="font-medium">{medicine.name}</p><p className="font-mono text-xs text-neutral-500">{medicine.code}</p></TableCell><TableCell className="uppercase">{medicine.type}</TableCell><TableCell>{formatNumber(medicine.stock)} {medicine.unit}</TableCell><TableCell><Badge variant={medicine.usable <= medicine.minimum ? 'waiting' : 'complete'}>{formatNumber(medicine.usable)} {medicine.unit}</Badge></TableCell><TableCell>{formatNumber(medicine.minimum)} {medicine.unit}</TableCell><TableCell><Button asChild size="sm" variant="secondary"><Link href={medicine.url}>Batch & kartu stok</Link></Button></TableCell></TableRow>) : <TableRow><TableCell colSpan={6}><Empty title="Persediaan belum tersedia" size="compact" /></TableCell></TableRow>}</TableBody></Table></div>
                <Pagination pagination={medicines} />
            </CardContent>
        </Card>
    </div></>;
}
