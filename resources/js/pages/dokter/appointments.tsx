import { FilterPanel } from '@/components/ui/filter-panel';
import { Head, router } from '@inertiajs/react';
import { CalendarDays, ClipboardCheck, Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { PriorityBadge } from '@/components/dashboard/priority-badge';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { DateRangePicker } from '@/components/ui/date-range-picker';

interface Visit {
    id: number;
    date: string;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    status: string;
    isReservation?: boolean;
    triage?: string | null;
    priority?: string | null;
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
            <Head title="Kunjungan Pasien Dokter" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Praktik dokter</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Daftar kunjungan pasien</h2>
                    <p className="mt-1 text-sm text-neutral-500">Kunjungan pasien yang terdaftar untuk pelayanan dokter.</p>
                </div>
                <FilterPanel>
                    <form className="flex flex-col gap-4 sm:flex-row sm:items-end" onSubmit={applyFilters}>
                        <Field htmlFor="doctor-appointments-period" label="Periode kunjungan">
                            <DateRangePicker id="doctor-appointments-period" from={dates.dari} onChange={(range) => setDates({ dari: range.from, sampai: range.to })} to={dates.sampai} />
                        </Field>
                        <Button type="submit"><Search className="size-4" />Terapkan filter</Button>
                    </form>
                </FilterPanel>
                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-neutral-50 text-neutral-700"><CalendarDays className="size-5" /></span>
                            <div><CardTitle>Daftar kunjungan</CardTitle><CardDescription className="mt-1">{visits.total} kunjungan dalam hasil pencarian.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[850px]">
                                <TableHeader>
                                    <tr>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead>Pasien</TableHead>
                                        <TableHead>Poliklinik</TableHead>
                                        <TableHead className="text-center">Status</TableHead>
                                        <TableHead className="text-center">Prioritas</TableHead>
                                        <TableHead className="text-center">Tindakan</TableHead>
                                    </tr>
                                </TableHeader>
                                <TableBody>
                                    {visits.data.length > 0 ? visits.data.map((visit) => (
                                        <TableRow key={visit.id}>
                                            <TableCell className="whitespace-nowrap text-neutral-600">{visit.date}</TableCell>
                                            <TableCell><p className="font-medium text-neutral-900">{visit.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber} · {visit.number}</p></TableCell>
                                            <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                                            <TableCell className="text-center"><StatusBadge reservation={visit.isReservation} status={visit.status} /></TableCell>
                                            <TableCell className="text-center"><PriorityBadge triage={visit.triage} priority={visit.priority} /></TableCell>
                                            <TableCell className="text-center"><Button asChild size="sm" variant="secondary"><a href={visit.examinationUrl}><ClipboardCheck className="size-4" />Pemeriksaan</a></Button></TableCell>
                                        </TableRow>
                                    )) : <TableRow className="hover:bg-transparent"><TableCell colSpan={6}><Empty size="compact" title="Belum ada kunjungan pada rentang tanggal ini." /></TableCell></TableRow>}
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
