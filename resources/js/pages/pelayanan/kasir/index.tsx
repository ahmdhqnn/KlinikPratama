import { Head, Link, router } from '@inertiajs/react';
import { CreditCard, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import { DatePicker } from '@/components/ui/date-picker';

interface Visit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    payer: string;
    billingStatus: string | null;
    receiptUrl: string | null;
}

interface Props {
    visits: PaginationData & { data: Visit[] };
    filters: { search: string; date: string };
}

export default function CashierQueue({ visits, filters }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [date, setDate] = useState(filters.date);

    function filterQueue(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pelayanan/kasir', { search, tanggal: date }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Antrean Kasir" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Administrasi pembayaran</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Antrean kasir</h2>
                    <p className="mt-1 text-sm text-neutral-500">Tinjau tagihan kunjungan dan buka kembali kuitansi pembayaran.</p>
                </div>

                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 md:grid-cols-[minmax(0,1fr)_14rem_auto] md:items-end" onSubmit={filterQueue}>
                            <Label className="space-y-1.5 text-sm font-medium text-neutral-700">
                                <span>Cari pasien atau No. RM</span>
                                <Input onChange={(event) => setSearch(event.target.value)} placeholder="Nama pasien atau No. RM" value={search} />
                            </Label>
                            <Label className="space-y-1.5 text-sm font-medium text-neutral-700">
                                <span>Tanggal kunjungan</span>
                                <DatePicker onChange={(event) => setDate(event.target.value)}  value={date} />
                            </Label>
                            <Button type="submit"><Search className="size-4" />Filter antrean</Button>
                        </form>
                    </CardContent>
                </Card>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><CreditCard className="size-5" /></span>
                            <div><CardTitle>Tagihan kunjungan</CardTitle><CardDescription className="mt-1">{new Intl.NumberFormat('id-ID').format(visits.total)} kunjungan ditemukan.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[780px]">
                                <TableHeader><tr><TableHead>No. kunjungan</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Penjamin</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Aksi</TableHead></tr></TableHeader>
                                <TableBody>
                                    {visits.data.length ? visits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="font-mono text-xs font-semibold text-neutral-700">{visit.number}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="font-mono text-xs text-neutral-500">{visit.medicalRecordNumber}</p></TableCell>
                                            <TableCell>{visit.clinic}</TableCell>
                                            <TableCell><StatusBadge status={visit.payer} /></TableCell>
                                            <TableCell><StatusBadge status={visit.billingStatus === 'lunas' ? 'selesai' : 'kasir'} /></TableCell>
                                            <TableCell className="text-right">
                                                {visit.receiptUrl
                                                    ? <Button asChild size="sm" variant="secondary"><Link href={visit.receiptUrl}>Buka kuitansi</Link></Button>
                                                    : <Button asChild size="sm"><Link href={`/pelayanan/kasir/${visit.id}`}>Proses pembayaran</Link></Button>}
                                            </TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-neutral-500" colSpan={6}>Tidak ada kunjungan kasir pada filter ini.</TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination pagination={visits} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
