import { Head, router } from '@inertiajs/react';
import { AlertTriangle, Boxes, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatCurrency, formatNumber } from '@/lib/format';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

interface Medicine {
    id: number;
    code: string;
    name: string;
    type: string;
    stock: number;
    unit: string;
    minimumStock: number;
    purchasePrice: number;
    assetValue: number;
}

interface Props {
    filters: { type: string; lowStockOnly: boolean };
    stats: { totalItems: number; lowStockItems: number };
    medicines: PaginationData & { data: Medicine[] };
}

export default function StockReport({ filters, stats, medicines }: Props) {
    const [type, setType] = useState(filters.type);
    const [lowStockOnly, setLowStockOnly] = useState(filters.lowStockOnly);

    function filterReport(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/laporan/stok', { jenis: type, stok_rendah: lowStockOnly ? 1 : undefined }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Laporan Stok" />
            <div className="space-y-6"><div><p className="text-sm font-medium text-neutral-700">Persediaan klinik</p><h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laporan stok dan opname</h2><p className="mt-1 text-sm text-neutral-500">Pantau kuantitas fisik, batas minimum, dan nilai aset obat serta BHP.</p></div>
                <div className="grid gap-4 md:grid-cols-2"><StatCard description="Seluruh obat dan BHP terdaftar" icon={<Boxes className="size-5" />} iconClassName="bg-neutral-50 text-neutral-700" label="Total item terdaftar" value={`${formatNumber(stats.totalItems)} item`} /><StatCard description="Stok pada atau di bawah batas minimum" icon={<AlertTriangle className="size-5" />} iconClassName="bg-red-50 text-red-700" label="Item menipis / kritis" value={`${formatNumber(stats.lowStockItems)} item`} /></div>
                <Card><CardContent className="p-5 sm:p-6"><form className="flex flex-wrap items-center gap-4" onSubmit={filterReport}><Label className="sr-only" htmlFor="medicine-type">Jenis item</Label><Select className="max-w-xs" id="medicine-type" onChange={(event) => setType(event.target.value)} value={type}><option value="">Semua jenis</option><option value="obat">Obat</option><option value="bhp">BHP</option></Select><Label className="flex items-center gap-2 text-sm text-neutral-700"><Checkbox checked={lowStockOnly} onCheckedChange={(checked) => setLowStockOnly((checked === true))} />Hanya stok kritis</Label><Button type="submit"><Search className="size-4" />Filter stok</Button></form></CardContent></Card>
                <Card className="overflow-hidden"><div className="overflow-x-auto"><Table className="min-w-[1000px]"><TableHeader><tr><TableHead>Kode</TableHead><TableHead>Nama item</TableHead><TableHead>Jenis</TableHead><TableHead className="text-right">Stok fisik</TableHead><TableHead className="text-right">Batas minimum</TableHead><TableHead className="text-right">Harga beli</TableHead><TableHead className="text-right">Nilai aset</TableHead><TableHead>Status</TableHead></tr></TableHeader><TableBody>{medicines.data.length ? medicines.data.map((medicine) => {const critical = medicine.stock <= medicine.minimumStock; const empty = medicine.stock <= 0; return <TableRow key={medicine.id}><TableCell className="font-mono text-xs font-semibold">{medicine.code}</TableCell><TableCell className="font-medium text-neutral-900">{medicine.name}</TableCell><TableCell><Badge className="bg-neutral-100 text-neutral-700">{medicine.type.toUpperCase()}</Badge></TableCell><TableCell className="text-right font-mono font-semibold">{formatNumber(medicine.stock)} {medicine.unit}</TableCell><TableCell className="text-right font-mono">{formatNumber(medicine.minimumStock)}</TableCell><TableCell className="text-right">{formatCurrency(medicine.purchasePrice)}</TableCell><TableCell className="text-right font-semibold">{formatCurrency(medicine.assetValue)}</TableCell><TableCell><Badge className={empty ? 'bg-red-50 text-red-700' : critical ? 'bg-amber-50 text-amber-700' : 'bg-emerald-50 text-emerald-700'}>{empty ? 'Habis' : critical ? 'Kritis' : 'Aman'}</Badge></TableCell></TableRow>;}) : <TableRow><TableCell colSpan={8}><Empty size="compact" title="Tidak ada item yang cocok dengan filter stok." /></TableCell></TableRow>}</TableBody></Table></div><Pagination pagination={medicines} /></Card>
            </div>
        </>
    );
}
