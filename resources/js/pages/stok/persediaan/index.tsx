import { ActionButton } from '@/components/ui/action-button';
import { StatCard } from '@/components/dashboard/stat-card';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { Boxes, Package, Search, TriangleAlert } from 'lucide-react';
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
export default function Inventory({ medicines, filters, stats }: { medicines: PaginationData & { data: Medicine[] }; filters: { search?: string; jenis?: string }; stats: { medicines: number; supplies: number; lowStock: number } }) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [type, setType] = useState(filters.jenis ?? '');
    return <><Head title="Persediaan Obat & BHP" /><div className="space-y-6">
        <div className="flex flex-wrap items-end justify-between gap-4"><div><p className="text-sm font-medium text-neutral-700">Pengelolaan obat</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Obat & BHP</h2><p className="mt-1 text-sm text-neutral-500">Pantau ketersediaan obat dan bahan habis pakai, lalu periksa batch atau catat penerimaan stok.</p></div></div>
        <section aria-label="Ringkasan persediaan" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"><StatCard label="Jenis obat" value={stats.medicines} description="Obat yang tercatat" icon={<Package className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" /><StatCard label="Jenis BHP" value={stats.supplies} description="Bahan habis pakai yang tercatat" icon={<Boxes className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" /><StatCard label="Perlu perhatian" value={stats.lowStock} description="Stok layak pakai mencapai batas minimum" icon={<TriangleAlert className="size-5" />} iconClassName={stats.lowStock ? 'bg-amber-50 text-amber-800' : 'bg-neutral-100 text-neutral-700'} /></section>
        <FilterPanel><form className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 sm:items-center" onSubmit={(event) => { event.preventDefault(); router.get('/stok/persediaan', { search, jenis: type }, { preserveState: true, replace: true }); }}>
            <Input aria-label="Cari obat atau BHP" placeholder="Cari nama atau kode" value={search} onChange={(event) => setSearch(event.target.value)} />
            <Select className="w-full" aria-label="Jenis persediaan" value={type} onChange={(event) => setType(event.target.value)}><option value="">Obat & BHP</option><option value="obat">Obat</option><option value="bhp">BHP</option></Select>
            <Button className="w-full sm:w-auto" type="submit" variant="secondary"><Search className="size-4" />Cari</Button>
        </form></FilterPanel>
        <Card className="overflow-hidden">
            <CardHeader className="border-b border-neutral-100">
                <div className="flex items-start gap-3">
                    <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Package className="size-5" /></span>
                    <div>
                        <CardTitle>Daftar persediaan</CardTitle>
                        <CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(medicines.total)} item obat dan BHP tercatat.</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto"><Table className="min-w-[800px]"><TableHeader><TableRow><TableHead className="text-center">Item persediaan</TableHead><TableHead className="text-center">Jenis</TableHead><TableHead className="text-center">Total fisik</TableHead><TableHead className="text-center">Layak pakai</TableHead><TableHead className="text-center">Batas minimum</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader><TableBody>{medicines.data.length ? medicines.data.map((medicine) => <TableRow key={medicine.id}><TableCell className="text-center"><p className="font-medium">{medicine.name}</p><p className="font-mono text-xs text-neutral-500">{medicine.code}</p></TableCell><TableCell className="text-center uppercase">{medicine.type}</TableCell><TableCell className="text-center">{formatNumber(medicine.stock)} {medicine.unit}</TableCell><TableCell className="text-center"><Badge variant={medicine.usable <= medicine.minimum ? 'warning' : 'default'}>{formatNumber(medicine.usable)} {medicine.unit}</Badge></TableCell><TableCell className="text-center">{formatNumber(medicine.minimum)} {medicine.unit}</TableCell><TableCell className="text-center"><ActionButton asChild action="stock" label="Batch & kartu stok"><Link href={medicine.url} /></ActionButton></TableCell></TableRow>) : <TableRow><TableCell className="text-center" colSpan={6}><Empty title="Persediaan belum tersedia" size="compact" /></TableCell></TableRow>}</TableBody></Table></div>
                <Pagination pagination={medicines} />
            </CardContent>
        </Card>
    </div></>;
}
