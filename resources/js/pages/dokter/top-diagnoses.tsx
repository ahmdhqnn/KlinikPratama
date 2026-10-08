import { Head, router } from '@inertiajs/react';
import { Activity, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DateRangePicker } from '@/components/ui/date-range-picker';

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
                    <p className="text-sm font-medium text-neutral-700">Analisis pelayanan</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Diagnosis terbanyak</h2>
                    <p className="mt-1 text-sm text-neutral-500">Ringkasan diagnosis dari kunjungan yang ditangani selama periode terpilih.</p>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end" onSubmit={applyFilters}>
                            <Field htmlFor="doctor-diagnoses-period" label="Periode diagnosis" required>
                                <DateRangePicker id="doctor-diagnoses-period" from={dates.dari} onChange={(range) => setDates({ dari: range.from, sampai: range.to })} required to={dates.sampai} />
                            </Field>
                            <Button type="submit"><Search className="size-4" />Tampilkan</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Activity className="size-5" /></span>
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
                                            <TableCell><span className="flex size-8 items-center justify-center rounded-lg bg-neutral-100 text-xs font-semibold text-neutral-600">{(diagnoses.from ?? 1) + index}</span></TableCell>
                                            <TableCell className="font-mono text-xs font-semibold text-neutral-700">{diagnosis.code}</TableCell>
                                            <TableCell className="font-medium text-neutral-800">{diagnosis.name}</TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums text-neutral-900">{new Intl.NumberFormat('id-ID').format(diagnosis.count)}</TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={4}><Empty size="compact" title="Belum ada diagnosis pada periode ini." /></TableCell></TableRow>}
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
