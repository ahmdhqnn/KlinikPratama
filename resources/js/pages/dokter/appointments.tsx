import { Head, router } from '@inertiajs/react';
import { CalendarDays, ClipboardCheck, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';

interface Visit {
    id: number;
    date: string;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    status: string;
    examinationUrl: string;
}

interface Props {
    filters: { dari: string; sampai: string };
    visits: PaginationData & { data: Visit[] };
}

export default function DoctorAppointments({ filters, visits }: Props) {
    const [dates, setDates] = useState(filters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/dokter/kunjungan', dates, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Janji Kunjungan" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-blue-700">Jadwal pasien</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-slate-950">Janji kunjungan</h2>
                    <p className="mt-1 text-sm text-slate-500">Tinjau kunjungan yang ditugaskan kepada Anda berdasarkan rentang tanggal.</p>
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
                                <Input min={dates.dari} onChange={(event) => setDates({ ...dates, sampai: event.target.value })} type="date" value={dates.sampai} />
                            </label>
                            <Button type="submit"><Search className="size-4" />Terapkan filter</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-slate-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><CalendarDays className="size-5" /></span>
                            <div><CardTitle>Daftar kunjungan</CardTitle><CardDescription className="mt-1">{visits.total} kunjungan dalam hasil pencarian.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[760px]">
                                <TableHeader><tr><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Status</TableHead><TableHead className="text-right">Tindakan</TableHead></tr></TableHeader>
                                <TableBody>
                                    {visits.data.length > 0 ? visits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="whitespace-nowrap text-slate-600">{visit.date}</TableCell>
                                            <TableCell><p className="font-medium text-slate-900">{visit.patient}</p><p className="mt-0.5 text-xs text-slate-500">{visit.medicalRecordNumber} · {visit.number}</p></TableCell>
                                            <TableCell className="text-slate-600">{visit.clinic}</TableCell>
                                            <TableCell><StatusBadge status={visit.status} /></TableCell>
                                            <TableCell className="text-right"><Button asChild size="sm" variant="secondary"><a href={visit.examinationUrl}><ClipboardCheck className="size-4" />Pemeriksaan</a></Button></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-slate-500" colSpan={5}>Belum ada kunjungan pada rentang tanggal ini.</TableCell></TableRow>}
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
