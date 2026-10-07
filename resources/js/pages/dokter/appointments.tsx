import { Head, router } from '@inertiajs/react';
import { CalendarDays, ClipboardCheck, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { DatePicker } from '@/components/ui/date-picker';

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
                    <p className="text-sm font-medium text-neutral-700">Jadwal pasien</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Janji kunjungan</h2>
                    <p className="mt-1 text-sm text-neutral-500">Tinjau kunjungan yang ditugaskan kepada Anda berdasarkan rentang tanggal.</p>
                </div>
                <Card>
                    <CardContent className="p-5 sm:p-6">
                        <form className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" onSubmit={applyFilters}>
                            <Label className="space-y-1.5 text-sm font-medium text-neutral-700">
                                <span>Dari tanggal</span>
                                <DatePicker onChange={(event) => setDates({ ...dates, dari: event.target.value })} required  value={dates.dari} />
                            </Label>
                            <Label className="space-y-1.5 text-sm font-medium text-neutral-700">
                                <span>Sampai tanggal</span>
                                <DatePicker min={dates.dari} onChange={(event) => setDates({ ...dates, sampai: event.target.value })}  value={dates.sampai} />
                            </Label>
                            <Button type="submit"><Search className="size-4" />Terapkan filter</Button>
                        </form>
                    </CardContent>
                </Card>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><CalendarDays className="size-5" /></span>
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
                                            <TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber} · {visit.number}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                                            <TableCell><StatusBadge status={visit.status} /></TableCell>
                                            <TableCell className="text-right"><Button asChild size="sm" variant="secondary"><a href={visit.examinationUrl}><ClipboardCheck className="size-4" />Pemeriksaan</a></Button></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell className="py-12 text-center text-neutral-500" colSpan={5}>Belum ada kunjungan pada rentang tanggal ini.</TableCell></TableRow>}
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
