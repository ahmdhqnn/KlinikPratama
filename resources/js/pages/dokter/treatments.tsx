import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { StatCard } from '@/components/dashboard/stat-card';
import { VisitActionButtons } from '@/components/dashboard/visit-action-buttons';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarDays, Check, ClipboardCheck, Search, Stethoscope } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface ActionRow {
    id: number;
    date: string;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    name: string;
    code: string | null;
    quantity: number;
    executionRole: string | null;
    status: string | null;
    statusLabel: string;
    performedBy: string | null;
    performedAt: string | null;
    detailUrl: string;
    letterUrl: string | null;
    recordUrl: string | null;
}

interface Props {
    filters: { dari: string; sampai: string; search: string; status: string };
    stats: { total: number; visits: number; pending: number; completed: number };
    actions: PaginationData & { data: ActionRow[] };
}

export default function DoctorTreatments({ filters, stats, actions }: Props) {
    const [values, setValues] = useState(filters);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/dokter/tindakan', values, { preserveState: true, preserveScroll: true, replace: true });
    }

    return (
        <>
            <Head title="Tindakan dokter" />
            <div className="space-y-6">
                <header>
                    <p className="text-sm font-medium text-neutral-700">Pelayanan dokter</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Tindakan</h2>
                    <p className="mt-1 max-w-3xl text-sm text-neutral-500">Pantau tindakan dari pemeriksaan Anda. Buka pemeriksaan terkait untuk meninjau catatan dan melanjutkan pelayanan.</p>
                </header>

                <section aria-label="Ringkasan tindakan" className="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Total tindakan" value={stats.total} description="Sesuai filter tindakan" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Kunjungan terkait" value={stats.visits} description="Kunjungan dengan tindakan tercatat" icon={<Stethoscope className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Menunggu tindakan" value={stats.pending} description="Belum selesai dilaksanakan" icon={<CalendarDays className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Tindakan selesai" value={stats.completed} description="Sudah dilaksanakan" icon={<Check className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </section>

                <FilterPanel>
                    <form className="grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(12rem,1fr)_minmax(18rem,1.2fr)_12rem_auto]" onSubmit={applyFilters}>
                        <Field htmlFor="doctor-treatment-search" label="Cari pasien atau tindakan">
                            <Input id="doctor-treatment-search" onChange={(event) => setValues({ ...values, search: event.target.value })} placeholder="Nama, No. RM, kunjungan" value={values.search} />
                        </Field>
                        <Field htmlFor="doctor-treatment-period" label="Periode kunjungan">
                            <DateRangePicker from={values.dari} id="doctor-treatment-period" onChange={(range) => setValues({ ...values, dari: range.from, sampai: range.to })} to={values.sampai} />
                        </Field>
                        <Field htmlFor="doctor-treatment-status" label="Status tindakan">
                            <Select id="doctor-treatment-status" onChange={(event) => setValues({ ...values, status: event.target.value })} value={values.status}>
                                <option value="all">Semua status</option>
                                <option value="pending">Menunggu</option>
                                <option value="completed">Selesai</option>
                            </Select>
                        </Field>
                        <div className="flex items-end gap-2">
                            <Button type="submit"><Search className="size-4" />Terapkan</Button>
                            <Button asChild variant="secondary"><Link href="/dokter/tindakan">Reset</Link></Button>
                        </div>
                    </form>
                </FilterPanel>

                <Card className="overflow-hidden">
                    <CardHeader className="border-b border-neutral-100">
                        <div className="flex items-start gap-3">
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700"><ClipboardCheck className="size-5" /></span>
                            <div><CardTitle>Daftar tindakan</CardTitle><CardDescription className="mt-1">{actions.total.toLocaleString('id-ID')} catatan sesuai pilihan.</CardDescription></div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <Table className="min-w-[1050px]">
                                <TableHeader><TableRow>
                                    <TableHead className="text-center">Tanggal</TableHead>
                                    <TableHead className="text-center">No. kunjungan</TableHead>
                                    <TableHead className="text-center">Pasien</TableHead>
                                    <TableHead className="text-center">Poliklinik</TableHead>
                                    <TableHead className="text-center">Tindakan</TableHead>
                                    <TableHead className="text-center">Jumlah</TableHead>
                                    <TableHead className="text-center">Pelaksana</TableHead>
                                    <TableHead className="text-center">Status</TableHead>
                                    <TableHead className="text-center">Aksi</TableHead>
                                </TableRow></TableHeader>
                                <TableBody>
                                    {actions.data.length ? actions.data.map((action) => (
                                        <TableRow key={action.id}>
                                            <TableCell className="whitespace-nowrap text-center">{action.date}</TableCell>
                                            <TableCell className="whitespace-nowrap text-center font-mono text-xs">{action.number}</TableCell>
                                            <TableCell className="text-center"><p className="font-medium">{action.patient}</p><p className="text-xs text-neutral-500">{action.medicalRecordNumber}</p></TableCell>
                                            <TableCell className="text-center">{action.clinic}</TableCell>
                                            <TableCell className="max-w-56 text-center"><span className="line-clamp-2">{action.name}</span>{action.code && <span className="mt-1 block text-xs text-neutral-500">{action.code}</span>}</TableCell>
                                            <TableCell className="text-center tabular-nums">{action.quantity}</TableCell>
                                            <TableCell className="text-center">{action.executionRole === 'perawat' ? 'Perawat' : 'Dokter'}{action.performedBy && <span className="mt-1 block text-xs text-neutral-500">{action.performedBy}</span>}</TableCell>
                                            <TableCell className="text-center"><Badge className="whitespace-nowrap">{action.statusLabel}</Badge></TableCell>
                                            <TableCell className="text-center"><VisitActionButtons letterUrl={action.letterUrl} patient={action.patient} primary={{ href: action.detailUrl, label: `Buka pemeriksaan ${action.number}`, type: 'examination' }} recordUrl={action.recordUrl} /></TableCell>
                                        </TableRow>
                                    )) : <TableRow><TableCell className="text-center" colSpan={9}><Empty size="compact" title="Belum ada tindakan pada pilihan ini." description="Tindakan akan tampil setelah dicatat pada pemeriksaan." /></TableCell></TableRow>}
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination pagination={actions} />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

