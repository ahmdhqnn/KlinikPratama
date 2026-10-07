import { Head, router } from '@inertiajs/react';
import { Activity, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Diagnosis {
    code: string;
    name: string;
    count: number;
}

interface Props {
    filters: { dari: string; sampai: string };
    diagnoses: PaginationData & { data: Diagnosis[] };
}

export default function DoctorTopDiagnoses({ filters, diagnoses }: Props) {
    const [dates, setDates] = useState(filters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/dokter/laporan-top-diagnosa', dates, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Laporan Top Diagnosa" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-blue-700">Analisis pelayanan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Diagnosis terbanyak</h2>
                    <p className="mt-1 text-sm text-slate-500">Ringkasan diagnosis dari kunjungan yang ditangani selama periode terpilih.</p>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" onSubmit={applyFilters}>
                            <label className="space-y-1.5 text-sm font-medium text-slate-700">
                                <span>Dari tanggal</span>
                                <Input onChange={(event) => setDates({ ...dates, dari: event.target.value })} required type="date" value={dates.dari} />
                            </label>
                            <label className="space-y-1.5 text-sm font-medium text-slate-700">
                                <span>Sampai tanggal</span>
                                <Input min={dates.dari} onChange={(event) => setDates({ ...dates, sampai: event.target.value })} required type="date" value={dates.sampai} />
                            </label>
                            <Button type="submit"><Search className="size-4" />Tampilkan</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-slate-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700"><Activity className="size-5" /></span>
                            <div><CardTitle>Daftar diagnosis</CardTitle><CardDescription className="mt-1">Urut berdasarkan jumlah kasus terbanyak.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[620px]">
                                <TableHeader><tr><TableHead className="w-24">Peringkat</TableHead><TableHead>ICD-10</TableHead><TableHead>Diagnosis</TableHead><TableHead className="text-right">Jumlah kasus</TableHead></tr></TableHeader>
                                <TableBody>
                                    {diagnoses.data.length > 0 ? diagnoses.data.map((diagnosis, index) => (
                                        <TableRow key={`${diagnosis.code}-${diagnosis.name}`}>
                                            <TableCell><span className="flex size-8 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-600">{(diagnoses.from ?? 1) + index}</span></TableCell>
                                            <TableCell className="font-mono text-xs font-semibold text-violet-700">{diagnosis.code}</TableCell>
                                            <TableCell className="font-medium text-slate-800">{diagnosis.name}</TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums text-slate-900">{new Intl.NumberFormat('id-ID').format(diagnosis.count)}</TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-slate-500" colSpan={4}>Belum ada diagnosis pada periode ini.</TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination pagination={diagnoses} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
