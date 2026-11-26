import { DateRangePicker } from '@/components/ui/date-range-picker';
import { ActionButton } from '@/components/ui/action-button';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, ClipboardCheck, Clock3, Download } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { type PaginationData } from '@/components/dashboard/pagination';
import { PriorityBadge } from '@/components/dashboard/priority-badge';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { Button } from '@/components/ui/button';
import { ReportTableCard } from '@/components/dashboard/report-table-card';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface ScreeningRecord {
    id: number;
    date: string;
    visitNumber: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    staff: string;
    triage: string | null;
    priority: string | null;
    visitUrl: string | null;
    printUrl: string | null;
    editUrl: string | null;
    isDraft: boolean;
}

interface Props {
    isReport: boolean;
    baseUrl?: string;
    serviceName: string;
    exportUrl: string | null;
    stats?: { total: number; submitted: number; draft: number; highPriority: number };
    filters: { search: string; tanggal: string; dari?: string; sampai?: string; status?: string; prioritas?: string; poliklinikId: number | '' };
    canSelectClinic: boolean;
    clinics: { id: number; name: string }[];
    screenings: PaginationData & { data: ScreeningRecord[] };
}

export default function ScreeningHistory({ filters: initialFilters, canSelectClinic, clinics, screenings, isReport, serviceName, exportUrl, stats, baseUrl = '/perawat/laporan-pengkajian' }: Props) {
    const [filters, setFilters] = useState({
        search: initialFilters.search ?? '',
        dari: initialFilters.dari ?? initialFilters.tanggal ?? '',
        sampai: initialFilters.sampai ?? initialFilters.tanggal ?? '',
        status: initialFilters.status ?? '',
        prioritas: initialFilters.prioritas ?? '',
        poliklinikId: initialFilters.poliklinikId ?? '',
    });
    const title = isReport ? 'Laporan pengkajian awal' : 'Pengkajian saya';
    const pageUrl = isReport ? baseUrl : '/pelayanan/screening/riwayat';

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(pageUrl, {
            search: filters.search,
            dari: filters.dari,
            sampai: filters.sampai,
            status: filters.status,
            prioritas: filters.prioritas,
            ...(canSelectClinic ? { poliklinik_id: filters.poliklinikId } : {}),
        }, { preserveState: true, preserveScroll: true, replace: true });
    }

    return <>
        <Head title={title} />
        <div className="space-y-6">
            <div>
                <p className="text-sm font-medium text-neutral-700">Pelayanan keperawatan</p>
                <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">{title}</h2>
                <p className="mt-1 text-sm text-neutral-500">{canSelectClinic ? 'Riwayat pengkajian awal lintas poliklinik, termasuk draf dan pengkajian terkirim.' : 'Riwayat pengkajian yang Anda tangani di ' + serviceName + ', termasuk draf dan pengkajian terkirim.'}</p>
            </div>
            {stats && (
                <SummaryStatGrid ariaLabel="Ringkasan pengkajian awal" columns={4}>
                    <StatCard description="Pengkajian sesuai filter aktif" icon={<ClipboardCheck className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Total pengkajian" value={stats.total} />
                    <StatCard description="Pengkajian selesai dan terkirim ke dokter" icon={<CheckCircle2 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Selesai / terkirim" value={stats.submitted} />
                    <StatCard description="Pengkajian masih berupa draf" icon={<Clock3 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" label="Draf / proses" value={stats.draft} />
                    <StatCard description="Prioritas darurat atau segera" icon={<AlertTriangle className="size-5" />} iconClassName="bg-red-50 text-red-700" label="Triase merah & kuning" value={stats.highPriority} />
                </SummaryStatGrid>
            )}
            <FilterPanel actions={exportUrl && <Button asChild size="sm" variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button>}>
                <form className={canSelectClinic ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-5' : 'grid gap-4 sm:grid-cols-2 xl:grid-cols-4'} onSubmit={applyFilters}>
                    <Field htmlFor="screening-history-search" label="Cari pasien atau kunjungan">
                        <Input id="screening-history-search" onChange={(event) => setFilters({ ...filters, search: event.target.value })} placeholder="Nama, No. RM, atau No. kunjungan" value={filters.search} />
                    </Field>
                    <Field htmlFor="screening-history-date" label="Periode kunjungan">
                        <DateRangePicker from={filters.dari} id="screening-history-date" onChange={(range) => setFilters({ ...filters, dari: range.from, sampai: range.to })} to={filters.sampai} />
                    </Field>
                    {canSelectClinic && <Field htmlFor="screening-history-clinic" label="Poliklinik">
                        <Select id="screening-history-clinic" onChange={(event) => setFilters({ ...filters, poliklinikId: event.target.value ? Number(event.target.value) : '' })} value={filters.poliklinikId}>
                            <option value="">Semua poliklinik</option>{clinics.map((clinic) => <option key={clinic.id} value={clinic.id}>{clinic.name}</option>)}
                        </Select>
                    </Field>}
                    <Field htmlFor="screening-history-status" label="Status pengkajian">
                        <Select id="screening-history-status" onChange={(event) => setFilters({ ...filters, status: event.target.value })} value={filters.status}>
                            <option value="">Semua status</option>
                            <option value="submitted">Terkirim</option>
                            <option value="draft">Draf / proses</option>
                        </Select>
                    </Field>
                    <Field htmlFor="screening-history-priority" label="Prioritas layanan">
                        <Select id="screening-history-priority" onChange={(event) => setFilters({ ...filters, prioritas: event.target.value })} value={filters.prioritas}>
                            <option value="">Semua prioritas</option><option value="normal">Rutin</option><option value="segera">Segera</option><option value="darurat">Darurat</option>
                        </Select>
                    </Field>
                    <FilterSubmit />
                </form>
            </FilterPanel>
            <ReportTableCard title="Daftar pengkajian awal" description={`${new Intl.NumberFormat('id-ID').format(screenings.total)} pengkajian tercatat.`} icon={<ClipboardCheck className="size-5" />} pagination={screenings}>
                <Table className="min-w-[850px]">
                    <TableHeader><TableRow><TableHead>No. kunjungan</TableHead><TableHead>Tanggal</TableHead><TableHead>Pasien</TableHead><TableHead>Poliklinik</TableHead><TableHead>Petugas</TableHead><TableHead className="text-center">Prioritas</TableHead><TableHead className="text-center">Aksi</TableHead></TableRow></TableHeader>
                    <TableBody>
                        {screenings.data.length ? screenings.data.map((record) => <TableRow key={record.id}>
                            <TableCell className="font-sans tabular-nums text-xs">{record.visitNumber}</TableCell>
                            <TableCell>{record.date}</TableCell>
                            <TableCell><p className="font-medium text-neutral-900">{record.patient}</p><p className="mt-0.5 text-xs text-neutral-500">{record.medicalRecordNumber}</p></TableCell>
                            <TableCell>{record.clinic}</TableCell>
                            <TableCell>{record.staff}<p className="mt-1 text-xs text-neutral-500">{record.isDraft ? 'Draf pengkajian' : 'Pengkajian terkirim'}</p></TableCell>
                            <TableCell className="text-center"><PriorityBadge triage={record.triage} priority={record.priority} /></TableCell>
                            <TableCell className="text-center"><div className="flex flex-wrap justify-center gap-2"><ActionButton disabledReason="Data ini belum dapat diubah pada tahap pelayanan saat ini." disabled={!record.editUrl} asChild action="screening" label="Perbarui pengkajian"><Link href={record.editUrl ?? ''} /></ActionButton><ActionButton disabledReason="Dokumen belum tersedia untuk dicetak." disabled={!record.printUrl} asChild action="print" label="Cetak pengkajian"><a href={record.printUrl ?? ''} /></ActionButton><ActionButton disabledReason="Detail kunjungan belum tersedia." disabled={!record.visitUrl} asChild action="detail" label="Lihat kunjungan"><Link href={record.visitUrl ?? ''} /></ActionButton></div></TableCell>
                        </TableRow>) : <TableRow className="hover:bg-transparent"><TableCell colSpan={7}><Empty size="compact" title="Belum ada catatan pengkajian pada filter ini." /></TableCell></TableRow>}
                    </TableBody>
                </Table>
            </ReportTableCard>
        </div>
    </>;
}
