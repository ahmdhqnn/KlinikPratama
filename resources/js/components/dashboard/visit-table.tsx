import { Link } from '@inertiajs/react';
import { ClipboardCheck, Eye, Pill, Stethoscope } from 'lucide-react';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export interface DashboardVisit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    status: string;
    triage?: string | null;
    priority?: string | null;
    actionUrl: string;
    actionLabel: string;
    actionType: 'screening' | 'examination' | 'pharmacy' | 'detail';
}

export function VisitTable({ visits }: { visits: DashboardVisit[] }) {
    return (
        <div className="overflow-x-auto">
            <Table className="min-w-[760px]">
                <TableHeader><tr>
                    <TableHead>No. kunjungan</TableHead>
                    <TableHead>Pasien</TableHead>
                    <TableHead>Poliklinik</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Aksi cepat</TableHead>
                </tr></TableHeader>
                <TableBody>
                    {visits.length > 0 ? visits.map((visit) => (
                        <tr className="transition-colors hover:bg-neutral-50/80" key={visit.id}>
                            <TableCell className="whitespace-nowrap font-mono text-xs text-neutral-600"><Link className="font-medium text-neutral-900 underline-offset-4 hover:underline" href={visit.actionUrl}>{visit.number}</Link></TableCell>
                            <TableCell>
                                <p className="font-medium text-neutral-900">{visit.patient}</p>
                                <p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p>
                            </TableCell>
                            <TableCell className="text-neutral-600">{visit.clinic}</TableCell>
                            <TableCell>
                                <div className="flex flex-col items-start gap-1.5">
                                    <StatusBadge status={visit.status} />
                                    {visit.triage && (
                                        <span className={`text-xs font-medium capitalize ${visit.triage === 'merah' ? 'text-red-700' : 'text-neutral-500'}`}>
                                            Triase {visit.triage}{visit.priority ? ` · ${visit.priority}` : ''}
                                        </span>
                                    )}
                                </div>
                            </TableCell>
                            <TableCell className="text-right"><Button asChild size="sm" variant="secondary"><Link href={visit.actionUrl}><ActionIcon type={visit.actionType} />{visit.actionLabel}</Link></Button></TableCell>
                        </tr>
                    )) : (
                        <tr>
                            <TableCell colSpan={5}><Empty description="Kunjungan yang sesuai akan tampil di daftar ini." size="compact" title="Belum ada kunjungan" /></TableCell>
                        </tr>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}

function ActionIcon({ type }: { type: DashboardVisit['actionType'] }) {
    const icons = {
        screening: ClipboardCheck,
        examination: Stethoscope,
        pharmacy: Pill,
        detail: Eye,
    };
    const Icon = icons[type];

    return <Icon className="size-4" />;
}
