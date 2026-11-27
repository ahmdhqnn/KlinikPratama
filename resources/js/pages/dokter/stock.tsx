import { Head, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, PackageSearch, Pill, XCircle } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Medicine {
    id: number;
    code: string;
    name: string;
    unit: string;
    stock: number;
    minimumStock: number;
    isLow: boolean;
}

interface Props {
    stats?: { total: number; available: number; lowStock: number; outOfStock: number };
    filters?: { search?: string; kondisi?: string };
    medicines: PaginationData & { data: Medicine[] };
}

function formatStock(value: number): string {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);
}

export default function DoctorStock({ stats, filters: initialFilters, medicines }: Props) {
    const [search, setSearch] = useState(initialFilters?.search ?? '');
    const [kondisi, setKondisi] = useState(initialFilters?.kondisi ?? '');

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/dokter/stok-obat', { search, kondisi }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Stok obat" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Informasi farmasi</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Stok obat</h2>
                    <p className="mt-1 text-sm text-neutral-500">Ketersediaan stok layak pakai per batch aktif pada unit poliklinik sebagai acuan peresepan dokter. Batch kedaluwarsa dan karantina dikecualikan.</p>
                </div>
                <SummaryStatGrid ariaLabel="Ringkasan ketersediaan obat" columns={4}>
                    <StatCard label="Total obat aktif" value={stats?.total ?? medicines.total} description="Formularium aktif depo poli" icon={<Pill className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Tersedia" value={stats?.available ?? medicines.data.filter((m) => m.stock > 0).length} description="Stok layak pakai > 0" icon={<CheckCircle2 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Stok menipis" value={stats?.lowStock ?? medicines.data.filter((m) => m.isLow && m.stock > 0).length} description="Pada atau di bawah minimum" icon={<AlertTriangle className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Stok habis" value={stats?.outOfStock ?? medicines.data.filter((m) => m.stock <= 0).length} description="Perlu resep luar / pengadaan" icon={<XCircle className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </SummaryStatGrid>
                <FilterPanel>
                    <form className="grid min-w-0 gap-4 sm:grid-cols-2" onSubmit={applyFilters}>
                        <Field htmlFor="doctor-stock-search" label="Cari obat / kode / zat aktif">
                            <Input id="doctor-stock-search" onChange={(event) => setSearch(event.target.value)} placeholder="Nama obat, kode, atau zat aktif" value={search} />
                        </Field>
                        <Field htmlFor="doctor-stock-condition" label="Kondisi stok">
                            <Select id="doctor-stock-condition" onChange={(event) => setKondisi(event.target.value)} value={kondisi}>
                                <option value="">Semua kondisi</option>
                                <option value="rendah">Stok menipis (≤ batas minimum)</option>
                                <option value="habis">Stok habis</option>
                            </Select>
                        </Field>
                        <FilterSubmit />
                    </form>
                </FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><PackageSearch className="size-5" /></span>
                            <div><CardTitle>Stok obat aktif</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(medicines.total)} obat sesuai filter. Stok pada atau di bawah batas minimum ditandai.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[680px]">
                                <TableHeader><TableRow><TableHead>Kode</TableHead><TableHead>Nama obat</TableHead><TableHead>Satuan</TableHead><TableHead className="text-center">Stok tersedia</TableHead><TableHead className="text-center">Stok minimum</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    {medicines.data.length > 0 ? medicines.data.map((medicine) => (
                                        <TableRow key={medicine.id}>
                                            <TableCell className="font-sans tabular-nums text-xs text-neutral-500">{medicine.code}</TableCell>
                                            <TableCell className="font-medium text-neutral-900">{medicine.name}</TableCell>
                                            <TableCell className="text-neutral-600">{medicine.unit}</TableCell>
                                            <TableCell className="text-center"><Badge variant={medicine.stock <= 0 ? 'cancelled' : medicine.isLow ? 'warning' : 'default'}>{formatStock(medicine.stock)}</Badge></TableCell>
                                            <TableCell className="text-center tabular-nums text-neutral-600">{formatStock(medicine.minimumStock)}</TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Tidak ada obat yang cocok dengan filter." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination pagination={medicines} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
