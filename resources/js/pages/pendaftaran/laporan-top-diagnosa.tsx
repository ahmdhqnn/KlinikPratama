import { Head, router } from '@inertiajs/react';
import { Activity, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DateRangePicker } from '@/components/ui/date-range-picker';

interface Diagnosis {
    rank: number;
    code: string;
    name: string;
    count: number;
    percentage: number;
}

interface Props {
    filters: { tanggalMulai: string; tanggalSelesai: string };
    summary: { diagnosisCount: number; caseCount: number };
    diagnoses: Diagnosis[];
}

export default function RegistrationTopDiagnoses({ filters: initialFilters, summary, diagnoses }: Props) {
    const [filters, setFilters] = useState(initialFilters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/pendaftaran/laporan-top-diagnosa', {
            tanggal_mulai: filters.tanggalMulai,
            tanggal_selesai: filters.tanggalSelesai,
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Laporan Top Diagnosis" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Analisis rekam medis</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Laporan top diagnosis</h2>
                    <p className="mt-1 text-sm text-neutral-500">Ringkasan diagnosis pasien untuk periode yang dipilih.</p>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start" onSubmit={applyFilters}>
                            <Field htmlFor="registration-diagnoses-period" label="Periode diagnosis" required>
                                <DateRangePicker id="registration-diagnoses-period" from={filters.tanggalMulai} onChange={(range) => setFilters({ ...filters, tanggalMulai: range.from, tanggalSelesai: range.to })} required to={filters.tanggalSelesai} />
                            </Field>
                            <Button className="sm:mt-7" type="submit"><Search className="size-4" />Tampilkan laporan</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden border-neutral-200 bg-inverse text-on-inverse">
                    <CardContent className="flex flex-col justify-between gap-5 p-6 sm:flex-row sm:items-end sm:p-8">
                        <div>
                            <p className="text-sm font-medium text-on-inverse/70">Periode laporan</p>
                            <p className="mt-1 text-xl font-semibold tracking-tight">{formatDate(filters.tanggalMulai)} – {formatDate(filters.tanggalSelesai)}</p>
                        </div>
                        <div className="flex gap-8">
                            <div><p className="text-sm text-on-inverse/70">Jenis diagnosis</p><p className="mt-1 text-3xl font-semibold">{summary.diagnosisCount}</p></div>
                            <div><p className="text-sm text-on-inverse/70">Total kasus</p><p className="mt-1 text-3xl font-semibold">{new Intl.NumberFormat('id-ID').format(summary.caseCount)}</p></div>
                        </div>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><Activity className="size-5" /></span>
                            <div><CardTitle>Diagnosis terbanyak</CardTitle><CardDescription className="mt-1">Urut berdasarkan jumlah kasus pada periode laporan.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[760px]">
                                <TableHeader><tr><TableHead className="w-24 text-center">Peringkat</TableHead><TableHead>Kode ICD-10</TableHead><TableHead>Nama diagnosis</TableHead><TableHead className="text-right">Kasus</TableHead><TableHead>Persentase</TableHead></tr></TableHeader>
                                <TableBody>
                                    {diagnoses.length > 0 ? diagnoses.map((diagnosis) => (
                                        <TableRow key={`${diagnosis.code}-${diagnosis.name}`}>
                                            <TableCell className="text-center"><span className={`inline-flex size-8 items-center justify-center rounded-full text-xs font-bold ${diagnosis.rank <= 3 ? 'bg-neutral-100 text-neutral-800' : 'bg-neutral-100 text-neutral-600'}`}>{diagnosis.rank}</span></TableCell>
                                            <TableCell className="font-mono text-xs font-semibold text-neutral-700">{diagnosis.code}</TableCell>
                                            <TableCell className="font-medium text-neutral-800">{diagnosis.name}</TableCell>
                                            <TableCell className="text-right font-semibold tabular-nums text-neutral-900">{new Intl.NumberFormat('id-ID').format(diagnosis.count)}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <div aria-label={`${diagnosis.percentage}%`} className="h-2 w-28 overflow-hidden rounded-full bg-neutral-100">
                                                        <div className="h-full rounded-full bg-neutral-600" style={{ width: `${Math.min(100, diagnosis.percentage)}%` }} />
                                                    </div>
                                                    <span className="text-xs font-medium tabular-nums text-neutral-600">{diagnosis.percentage.toLocaleString('id-ID', { maximumFractionDigits: 1 })}%</span>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={5}><Empty size="compact" title="Belum ada diagnosis untuk periode ini." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

function formatDate(date: string): string {
    const [year, month, day] = date.split('-').map(Number);
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(new Date(year, month - 1, day));
}
