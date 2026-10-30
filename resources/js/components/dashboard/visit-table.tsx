import { ActionButton } from '@/components/ui/action-button';
import { Link } from '@inertiajs/react';
import { PriorityBadge } from '@/components/dashboard/priority-badge';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader } from '@/components/ui/table';

export interface DashboardVisit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    status: string;
    examinationStarted?: boolean;
    treatmentStarted?: boolean;
    isReservation?: boolean;
    triage?: string | null;
    priority?: string | null;
    actionUrl: string;
    recordUrl?: string | null;
    letterUrl?: string | null;
    showLetterAction?: boolean;
    actionLabel: string;
    actionType: 'screening' | 'examination' | 'pharmacy' | 'procedure' | 'detail';
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
                    <TableHead className="text-center">Aksi</TableHead>
                </tr></TableHeader>
                <TableBody>
                    {visits.length > 0 ? visits.map((visit) => (
                        <tr className="transition-colors hover:bg-neutral-50/80" key={visit.id}>
                            <TableCell className="text-center whitespace-nowrap font-mono text-xs text-neutral-600">
                                <Link className="font-medium text-neutral-900 underline-offset-4 hover:underline" href={visit.actionUrl}>{visit.number}</Link>
                            </TableCell>
                            <TableCell className="text-center">
                                <p className="font-medium text-neutral-900">{visit.patient}</p>
                                <p className="mt-0.5 text-xs text-neutral-500">{visit.medicalRecordNumber}</p>
                            </TableCell>
                            <TableCell className="text-center text-neutral-600">{visit.clinic}</TableCell>
                            <TableCell className="text-center">
                                <PriorityBadge triage={visit.triage} priority={visit.priority} />
                            </TableCell>
                            <TableCell className="text-center">
                                <div className="flex flex-col items-center gap-1.5">
                                    <StatusBadge examinationStarted={visit.examinationStarted} treatmentStarted={visit.treatmentStarted} context={visit.actionType === 'pharmacy' ? 'pharmacy' : ['screening', 'procedure'].includes(visit.actionType) ? 'nurse' : 'visit'} reservation={visit.isReservation} status={visit.status} />
                                </div>
                            </TableCell>
                            <TableCell className="text-center">
                                <div className="flex items-center justify-center gap-2">
                                    <ActionButton asChild action={visit.actionLabel === 'Lihat RME' ? 'rme' : visit.actionType} label={visit.actionLabel}><Link href={visit.actionUrl} /></ActionButton>
                                    {visit.actionLabel !== 'Lihat RME' && visit.recordUrl && <ActionButton asChild action="rme" label={`Lihat RME ${visit.patient}`}><Link href={visit.recordUrl} /></ActionButton>}
                                    {visit.showLetterAction && (visit.letterUrl ? <ActionButton asChild action="issue" label={`Terbitkan surat untuk ${visit.patient}`}><Link href={visit.letterUrl} /></ActionButton> : <ActionButton action="issue" disabled label={`Surat belum dapat diterbitkan untuk ${visit.patient}`} title="Surat dapat diterbitkan setelah pemeriksaan difinalisasi dan diverifikasi." />)}
                                </div>
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
