import { Head } from '@inertiajs/react';
import { PackageSearch } from 'lucide-react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
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
    medicines: PaginationData & { data: Medicine[] };
}

function formatStock(value: number): string {
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(value);
}

export default function DoctorStock({ medicines }: Props) {
    return (
        <>
            <Head title="Stok Obat" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Informasi farmasi</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ketersediaan obat</h2>
                    <p className="mt-1 text-sm text-neutral-500">Stok dari batch layak pakai untuk membantu pertimbangan terapi dan peresepan. Batch kedaluwarsa dan karantina dikecualikan.</p>
                </div>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><PackageSearch className="size-5" /></span>
                            <div><CardTitle>Stok obat aktif</CardTitle><CardDescription className="mt-1">Stok pada atau di bawah batas minimum ditandai.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[680px]">
                                <TableHeader><tr><TableHead>Kode</TableHead><TableHead>Nama obat</TableHead><TableHead>Satuan</TableHead><TableHead className="text-right">Stok tersedia</TableHead><TableHead className="text-right">Stok minimum</TableHead></tr></TableHeader>
                                <TableBody>
                                    {medicines.data.length > 0 ? medicines.data.map((medicine) => (
                                        <TableRow key={medicine.id}>
                                            <TableCell className="font-mono text-xs text-neutral-500">{medicine.code}</TableCell>
                                            <TableCell className="font-medium text-neutral-900">{medicine.name}</TableCell>
                                            <TableCell className="text-neutral-600">{medicine.unit}</TableCell>
                                            <TableCell className="text-right"><Badge variant={medicine.isLow ? 'cancelled' : 'complete'}>{formatStock(medicine.stock)}</Badge></TableCell>
                                            <TableCell className="text-right tabular-nums text-neutral-600">{formatStock(medicine.minimumStock)}</TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Belum ada data obat aktif." /></TableCell></TableRow>}
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
