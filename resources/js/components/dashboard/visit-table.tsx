import { Link } from '@inertiajs/react';
import { ClipboardCheck, Eye, Pill, Stethoscope } from 'lucide-react';
import { PriorityBadge } from '@/components/dashboard/priority-badge';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Button } from '@/components/ui/button';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader } from '@/components/ui/table';

export interface DashboardVisit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    status: string;
    isReservation?: boolean;
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
                    <TableHead className="text-center">No. kunjungan</TableHead>
                    <TableHead className="text-center">Pasien</TableHead>
                    <TableHead className="text-center">Poliklinik</TableHead>
                    <TableHead className="text-center">Prioritas</TableHead>
                    <TableHead className="text-center">Status</TableHead>
                    <TableHead className="text-center">Aksi cepat</TableHead>
                </tr></TableHeader>
                <TableBody>
                    {visits.length > 0 ? visits.map((visit) => (
                        <tr className="transition-colors hover:bg-neutral-50/80 dark:hover:bg-neutral-800/50" key={visit.id}>
                            <TableCell className="text-center whitespace-nowrap font-mono text-xs text-neutral-600 dark:text-neutral-400">
                                <Link className="font-medium text-neutral-900 underline-offset-4 hover:underline dark:text-neutral-100" href={visit.actionUrl}>{visit.number}</Link>
                            </TableCell>
                            <TableCell className="text-center">
                                <p className="font-medium text-neutral-900 dark:text-neutral-100">{visit.patient}</p>
                                <p className="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{visit.medicalRecordNumber}</p>
                            </TableCell>
                            <TableCell className="text-center text-neutral-600 dark:text-neutral-400">{visit.clinic}</TableCell>
                            <TableCell className="text-center">
                                <PriorityBadge triage={visit.triage} priority={visit.priority} />
                            </TableCell>
                            <TableCell className="text-center">
                                <div className="flex flex-col items-center gap-1.5">
                                    <StatusBadge context={visit.actionType === 'pharmacy' ? 'pharmacy' : 'visit'} reservation={visit.isReservation} status={visit.status} />
                                </div>
                            </TableCell>
                            <TableCell className="text-center">
                                <Button asChild size="sm" variant="secondary">
                                    <Link href={visit.actionUrl}><ActionIcon type={visit.actionType} />{visit.actionLabel}</Link>
                                </Button>
                            </TableCell>
                        </tr>
                    )) : (
                        <tr>
                            <TableCell className="text-center" colSpan={6}><Empty description="Kunjungan yang sesuai akan tampil di daftar ini." size="compact" title="Belum ada kunjungan" /></TableCell>
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
