import { Head, router } from '@inertiajs/react';
import { ClipboardList, Download, Pill, UsersRound, Wallet } from 'lucide-react';
import { useState } from 'react';
import { StatCard } from '@/components/dashboard/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field } from '@/components/ui/field';
import { DateRangePicker } from '@/components/ui/date-range-picker';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Empty } from '@/components/ui/empty';
import { formatNumber, formatCurrency } from '@/lib/format';

export interface UtilizationReport {
    stats: { visits: number; patients: number; completed: number; prescriptions: number; medicineCost: number; supplyCost: number; lowStock: number; expiredBatches: number; nearExpiryBatches: number; quarantinedBatches: number };
    units: { unit: string; costCenter: string; visits: number }[];
    costCenters: { unit: string; costCenter: string; cost: number }[];
    clinics: { name: string; visits: number }[];
    diagnoses: { code: string; name: string; count: number }[];
    usage: { id: number; name: string; type: string; unit: string; quantity: number; cost: number }[];
    lowStock: { name: string; stock: number; minimum: number; unit: string }[];
}

export function UtilizationDetails({ report }: { report: UtilizationReport }) {
    return <div className="space-y-6">
        <div className="grid gap-6 xl:grid-cols-2">
            <Card><CardHeader><CardTitle>Top 10 diagnosis</CardTitle><CardDescription>Diagnosis pada pemeriksaan yang telah difinalisasi dalam periode terpilih.</CardDescription></CardHeader><CardContent>{report.diagnoses.length ? <div className="space-y-4">{report.diagnoses.map((diagnosis, index) => <div key={diagnosis.code + diagnosis.name} className="flex items-start justify-between gap-3"><div className="flex gap-3"><span className="text-sm font-semibold text-neutral-400">{index + 1}.</span><div><p className="text-sm font-medium">{diagnosis.name}</p><p className="font-mono text-xs text-neutral-500">{diagnosis.code}</p></div></div><span className="shrink-0 text-sm font-semibold">{formatNumber(diagnosis.count)} kunjungan</span></div>)}</div> : <Empty title="Belum ada diagnosis final" size="compact" />}</CardContent></Card>
            <Card><CardHeader><CardTitle>Persediaan perlu perhatian</CardTitle><CardDescription>Kondisi stok saat ini, termasuk batch kedaluwarsa dan karantina.</CardDescription></CardHeader><CardContent><div className="mb-4 grid grid-cols-3 gap-3 text-center">{[{ label: 'Kedaluwarsa', value: report.stats.expiredBatches }, { label: '≤ 90 hari', value: report.stats.nearExpiryBatches }, { label: 'Karantina', value: report.stats.quarantinedBatches }].map((item) => <div className="rounded-lg bg-neutral-50 p-3" key={item.label}><p className="text-xl font-semibold">{formatNumber(item.value)}</p><p className="mt-1 text-xs text-neutral-500">{item.label}</p></div>)}</div>{report.lowStock.length ? <div className="max-h-72 space-y-3 overflow-y-auto">{report.lowStock.map((item) => <div key={item.name} className="flex justify-between gap-3 text-sm"><span>{item.name}</span><span className="shrink-0 text-amber-700">{formatNumber(item.stock)} / min. {formatNumber(item.minimum)} {item.unit}</span></div>)}</div> : <p className="text-sm text-neutral-500">Stok layak pakai berada di atas batas minimum.</p>}</CardContent></Card>
        </div>
        <Card className="overflow-hidden"><CardHeader><CardTitle>Pemakaian obat & BHP</CardTitle><CardDescription>Pengeluaran aktual berdasarkan resep dan tindakan, dikurangi retur. Nilai memakai biaya perolehan batch per satuan stok.</CardDescription></CardHeader><div className="overflow-x-auto"><Table className="min-w-[650px]"><TableHeader><TableRow><TableHead>Item</TableHead><TableHead>Jenis</TableHead><TableHead>Pemakaian bersih</TableHead><TableHead>Nilai anggaran digunakan</TableHead></TableRow></TableHeader><TableBody>{report.usage.length ? report.usage.map((item) => <TableRow key={item.id}><TableCell className="font-medium">{item.name}</TableCell><TableCell className="uppercase">{item.type}</TableCell><TableCell>{formatNumber(item.quantity)} {item.unit}</TableCell><TableCell>{formatCurrency(item.cost)}</TableCell></TableRow>) : <TableRow><TableCell colSpan={4}><Empty title="Belum ada pemakaian dalam periode ini" size="compact" /></TableCell></TableRow>}</TableBody></Table></div></Card>
    </div>;
}

export default function Utilization({ report, filters, exportUrl }: { report: UtilizationReport; filters: { from: string; to: string }; exportUrl: string }) {
    const [from, setFrom] = useState(filters.from); const [to, setTo] = useState(filters.to);
    return <><Head title="Utilisasi & Anggaran Internal" /><div className="space-y-6">
        <div><p className="text-sm font-medium text-neutral-700">Pertanggungjawaban instansi</p><h2 className="mt-1 text-2xl font-semibold tracking-tight">Utilisasi layanan & logistik</h2><p className="mt-1 text-sm text-neutral-500">Ringkasan layanan, pemakaian sumber daya, dan kebutuhan pengadaan berdasarkan anggaran instansi.</p></div>
        <Card><CardContent className="p-5"><form className="flex flex-col gap-4 sm:flex-row sm:items-end" onSubmit={(event) => { event.preventDefault(); router.get('/laporan/utilisasi', { dari: from, sampai: to }, { preserveState: true, replace: true }); }}><Field label="Periode layanan & pemakaian" htmlFor="utilization-period"><DateRangePicker id="utilization-period" from={from} to={to} onChange={(range) => { setFrom(range.from); setTo(range.to); }} required /></Field><Button type="submit">Terapkan</Button><Button asChild variant="secondary"><a href={exportUrl}><Download className="size-4" />Ekspor Excel</a></Button></form></CardContent></Card>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><StatCard iconClassName="bg-neutral-50 text-neutral-700" label="Kunjungan" value={report.stats.visits} description={`${report.stats.completed} kunjungan selesai; pembatalan dikecualikan`} icon={<ClipboardList className="size-5" />} /><StatCard iconClassName="bg-neutral-50 text-neutral-700" label="Pasien unik" value={report.stats.patients} description="Pasien yang berkunjung dalam periode" icon={<UsersRound className="size-5" />} /><StatCard iconClassName="bg-neutral-50 text-neutral-700" label="Resep diserahkan" value={report.stats.prescriptions} description="Penyerahan obat telah selesai" icon={<Pill className="size-5" />} /><StatCard iconClassName="bg-neutral-50 text-neutral-700" label="Nilai pemakaian" value={formatCurrency(report.stats.medicineCost + report.stats.supplyCost)} description="Biaya perolehan obat & BHP, bersih setelah retur" icon={<Wallet className="size-5" />} /></div>
        <Card className="overflow-hidden"><CardHeader><CardTitle>Kunjungan per unit kerja & cost center</CardTitle><CardDescription>Unit dan cost center mengikuti hasil verifikasi saat kunjungan didaftarkan.</CardDescription></CardHeader><div className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>Unit kerja</TableHead><TableHead>Cost center</TableHead><TableHead>Kunjungan</TableHead></TableRow></TableHeader><TableBody>{report.units.length ? report.units.map((unit) => <TableRow key={unit.unit + unit.costCenter}><TableCell>{unit.unit}</TableCell><TableCell>{unit.costCenter}</TableCell><TableCell>{formatNumber(unit.visits)}</TableCell></TableRow>) : <TableRow><TableCell colSpan={3}><Empty title="Belum ada utilisasi unit kerja" size="compact" /></TableCell></TableRow>}</TableBody></Table></div></Card>
        <Card className="overflow-hidden"><CardHeader><CardTitle>Nilai pemakaian per cost center</CardTitle><CardDescription>Biaya perolehan pengeluaran aktual dan retur dalam periode ini.</CardDescription></CardHeader><div className="overflow-x-auto"><Table><TableHeader><TableRow><TableHead>Unit kerja</TableHead><TableHead>Cost center</TableHead><TableHead>Nilai pemakaian bersih</TableHead></TableRow></TableHeader><TableBody>{report.costCenters.length ? report.costCenters.map((item) => <TableRow key={item.unit + item.costCenter}><TableCell>{item.unit}</TableCell><TableCell>{item.costCenter}</TableCell><TableCell>{formatCurrency(item.cost)}</TableCell></TableRow>) : <TableRow><TableCell colSpan={3}><Empty title="Belum ada pemakaian per cost center" size="compact" /></TableCell></TableRow>}</TableBody></Table></div></Card>
        <UtilizationDetails report={report} />
    </div></>;
}
