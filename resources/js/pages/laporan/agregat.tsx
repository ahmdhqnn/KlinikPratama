import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Head, router } from '@inertiajs/react';
import { ClipboardList, Download } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { ReportTableCard } from '@/components/dashboard/report-table-card';
import { StatCard } from '@/components/dashboard/stat-card';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Empty } from '@/components/ui/empty';
import { Field } from '@/components/ui/field';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { visitStatusFilterLabels, StatusBadge } from '@/components/dashboard/status-badge';

const groups = {
    status: 'Status kunjungan',
    unit: 'Unit kerja',
    kepesertaan: 'Kategori peserta',
    pengkajian: 'Pengkajian terkirim',
    pemeriksaan: 'Pemeriksaan ditandatangani',
    tindakan: 'Tindakan terlaksana',
    tindakan_perawat: 'Tindakan perawat',
    tindakan_dokter: 'Tindakan dokter',
    obat: 'Penyerahan obat',
    rujukan: 'Surat rujukan keluar',
};

interface Props {
    filters: { from: string; to: string; group: keyof typeof groups; clinic: string; status: string; unit: string; category: string; employment: string };
    rows: { clinic: string; status: string; total: number }[];
    exportUrl: string;
    canChooseGroup: boolean;
}

export default function AggregateReport({ filters, rows, exportUrl, canChooseGroup }: Props) {
    const [draft, setDraft] = useState(filters);
    const isGroupedByMembership = filters.group === 'unit' || filters.group === 'kepesertaan';
    const total = rows.reduce((sum, row) => sum + row.total, 0);
    const clinicCount = new Set(rows.map((row) => row.clinic)).size;

    function filter(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/laporan/kunjungan', {
            dari: draft.from,
            sampai: draft.to,
            kelompok: draft.group,
            poli: draft.clinic,
            status: draft.status,
            unit_kerja: draft.unit,
            kategori: draft.category,
            status_kepegawaian: draft.employment,
        }, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Ringkasan pelayanan" />
            <div className="space-y-6">
                <header>
                    <p className="text-sm font-medium text-neutral-700">Laporan & analitik</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Ringkasan pelayanan</h2>
                    <p className="mt-1 text-sm text-neutral-500">Pantau volume layanan dan ekspor rekap sesuai filter.</p>
                </header>
                <section aria-label="Total laporan" className="grid gap-4 sm:grid-cols-2">
                    <StatCard label="Total kunjungan" value={total} description="Kunjungan sesuai filter" icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Poliklinik tercakup" value={clinicCount} description="Poliklinik dengan data sesuai filter" icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </section>
                <FilterPanel actions={
                    <Button asChild size="sm" variant="secondary">
                        <a href={exportUrl}><Download className="size-4" />Ekspor Excel</a>
                    </Button>
                }>
                    <form className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" onSubmit={filter}>
                        <Field htmlFor="aggregate-period" label={draft.group === 'rujukan' ? 'Periode surat rujukan' : 'Periode kunjungan'}>
                            <DateRangePicker id="aggregate-period" from={draft.from} to={draft.to} onChange={(dates) => setDraft({ ...draft, ...dates })} />
                        </Field>
                        {canChooseGroup && (
                            <Field htmlFor="aggregate-group" label="Jenis rekap">
                                <Select id="aggregate-group" value={draft.group} onChange={(event) => setDraft({ ...draft, group: event.target.value as Props['filters']['group'] })}>
                                    {Object.entries(groups).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                                </Select>
                            </Field>
                        )}
                        <Field htmlFor="aggregate-clinic" label="Poliklinik">
                            <Select id="aggregate-clinic" value={draft.clinic} onChange={(event) => setDraft({ ...draft, clinic: event.target.value })}>
                                <option value="">Semua poliklinik</option>
                                <option value="umum">Poli Umum</option>
                                <option value="gigi">Poli Gigi</option>
                            </Select>
                        </Field>
                        <Field htmlFor="aggregate-status" label="Status kunjungan">
                            <Select id="aggregate-status" value={draft.status} onChange={(event) => setDraft({ ...draft, status: event.target.value })}>
                                <option value="">Semua status</option>
                                {Object.entries(visitStatusFilterLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
                            </Select>
                        </Field>
                        {canChooseGroup && <>
                            <Field htmlFor="aggregate-unit" label="Unit kerja">
                                <Input id="aggregate-unit" value={draft.unit} maxLength={255} onChange={(event) => setDraft({ ...draft, unit: event.target.value })} placeholder="Nama unit sesuai data kunjungan" />
                            </Field>
                            <Field htmlFor="aggregate-category" label="Kategori peserta">
                                <Select id="aggregate-category" value={draft.category} onChange={(event) => setDraft({ ...draft, category: event.target.value })}>
                                    <option value="">Semua kategori</option>
                                    <option value="asn">ASN</option>
                                    <option value="lainnya">Lainnya</option>
                                </Select>
                            </Field>
                            <Field htmlFor="aggregate-employment" label="Status kepegawaian tercatat">
                                <Select id="aggregate-employment" value={draft.employment} onChange={(event) => setDraft({ ...draft, employment: event.target.value })}>
                                    <option value="">Semua status</option>
                                    <option value="aktif">Aktif</option>
                                    <option value="nonaktif">Nonaktif</option>
                                </Select>
                            </Field>
                        </>}
                        <FilterSubmit />
                    </form>
                </FilterPanel>
                <ReportTableCard title="Rekap kunjungan" description={groups[filters.group]} icon={<ClipboardList className="size-5" />}>
                    <Table className="min-w-[28rem]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Poliklinik</TableHead>
                                <TableHead>{isGroupedByMembership ? groups[filters.group] : 'Status kunjungan'}</TableHead>
                                <TableHead className="text-right">Jumlah</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.length ? rows.map((row) => (
                                <TableRow key={JSON.stringify([row.clinic, row.status])}>
                                    <TableCell>{row.clinic}</TableCell>
                                    <TableCell>{isGroupedByMembership ? row.status : <StatusBadge status={row.status} />}</TableCell>
                                    <TableCell className="text-right tabular-nums">{row.total.toLocaleString('id-ID')}</TableCell>
                                </TableRow>
                            )) : (
                                <TableRow><TableCell colSpan={3}><Empty size="compact" title="Tidak ada kunjungan sesuai filter." /></TableCell></TableRow>
                            )}
                        </TableBody>
                    </Table>
                </ReportTableCard>
            </div>
        </>
    );
}
