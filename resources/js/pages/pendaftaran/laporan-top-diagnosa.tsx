import { StatCard } from '@/components/dashboard/stat-card';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, router } from '@inertiajs/react';
import { Activity, ClipboardList, Search, Download } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';

interface Diagnosis {
    rank: number;
    code: string | null;
    name: string;
    count: number;
    percentage: number;
}

interface Props {
    exportUrl: string | null;
    filters: { tanggalMulai: string; tanggalSelesai: string };
    summary: { diagnosisCount: number; caseCount: number };
    diagnoses: PaginationData & { data: Diagnosis[] };
}

export default function RegistrationTopDiagnoses({ filters: initialFilters, summary, diagnoses, exportUrl }: Props) {
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

                <FilterPanel actions={exportUrl && <Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}><form className="grid gap-4 sm:grid-cols-2 sm:items-end" onSubmit={applyFilters}>
                            <Field htmlFor="registration-diagnoses-period" label="Periode diagnosis">
                                <DateRangePicker id="registration-diagnoses-period" from={filters.tanggalMulai} onChange={(range) => setFilters({ ...filters, tanggalMulai: range.from, tanggalSelesai: range.to })} to={filters.tanggalSelesai} />
                            </Field>
                            <Button type="submit"><Search className="size-4" />Tampilkan laporan</Button>
                        </form></FilterPanel>
                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard label="Jenis diagnosis" value={summary.diagnosisCount} description="Diagnosis yang tercatat pada periode laporan." icon={<Activity className="size-5" />} iconClassName="bg-neutral-100 text-neutral-600" />
                    <StatCard label="Total kasus" value={summary.caseCount} description="Jumlah kasus dengan diagnosis tercatat." icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-100 text-neutral-600" />
                </div>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><Activity className="size-5" /></span>
                            <div><CardTitle>Diagnosis terbanyak</CardTitle><CardDescription className="mt-1">{!initialFilters.tanggalMulai && !initialFilters.tanggalSelesai ? 'Urut berdasarkan jumlah kasus terbanyak.' : `${formatDate(initialFilters.tanggalMulai)} – ${formatDate(initialFilters.tanggalSelesai)}`}</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[760px]">
                                <TableHeader><tr><TableHead className="w-24 text-center">Peringkat</TableHead><TableHead>Kode ICD-10</TableHead><TableHead>Nama diagnosis</TableHead><TableHead className="text-center">Kasus</TableHead><TableHead>Persentase</TableHead></tr></TableHeader>
                                <TableBody>
                                    {diagnoses.data.length > 0 ? diagnoses.data.map((diagnosis) => (
                                        <TableRow key={`${diagnosis.code}-${diagnosis.name}`}>
                                            <TableCell className="text-center"><span className={`inline-flex size-8 items-center justify-center rounded-full text-xs font-bold ${diagnosis.rank <= 3 ? 'bg-neutral-100 text-neutral-800' : 'bg-neutral-100 text-neutral-600'}`}>{diagnosis.rank}</span></TableCell>
                                            <TableCell className="font-mono text-xs font-semibold text-neutral-700">{diagnosis.code || 'Manual'}</TableCell>
                                            <TableCell className="font-medium text-neutral-800">{diagnosis.name}</TableCell>
                                            <TableCell className="text-center font-semibold tabular-nums text-neutral-900">{new Intl.NumberFormat('id-ID').format(diagnosis.count)}</TableCell>
                                            <TableCell>
                                                <div className="flex items-center justify-center gap-3">
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
                    <Pagination pagination={diagnoses} />
                </Card>
            </div>
        </>
    );
}

function formatDate(date: string): string {
    if (!date) return 'Tidak dibatasi';
    const [year, month, day] = date.split('-').map(Number);
    const value = new Date(year, month - 1, day);
    return Number.isNaN(value.getTime()) ? 'Tidak dibatasi' : new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(value);
}
