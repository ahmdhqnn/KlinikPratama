import { ActionButton } from '@/components/ui/action-button';
import type { ComponentProps } from 'react';
import { Link } from '@inertiajs/react';
import { PriorityBadge } from '@/components/dashboard/priority-badge';
import { StatusBadge } from '@/components/dashboard/status-badge';
import { Empty } from '@/components/ui/empty';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

export interface DashboardVisit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    status: string;
    examinationStarted?: boolean;
    treatmentStarted?: boolean;
    pendingReservation?: boolean;
    isReservation?: boolean;
    triage?: string | null;
    priority?: string | null;
    actionUrl: string;
    detailUrl?: string | null;
    recordUrl?: string | null;
    letterUrl?: string | null;
    showLetterAction?: boolean;
    actionLabel: string;
    actionType: 'screening' | 'examination' | 'pharmacy' | 'procedure' | 'detail';
    actionDisabled?: boolean;
    actionDisabledReason?: string;
}

export function VisitTable({ visits, nursing = false }: { visits: DashboardVisit[]; nursing?: boolean }) {
    return (
        <div className="overflow-x-auto">
            <Table className="min-w-[760px]">
                <TableHeader><TableRow>
                    <TableHead className="text-center">No. kunjungan</TableHead>
                    <TableHead className="text-center">Pasien</TableHead>
                    <TableHead className="text-center">Poliklinik</TableHead>
                    <TableHead className="text-center">Prioritas</TableHead>
                    <TableHead className="text-center">Status</TableHead>
                    <TableHead className="text-center">Aksi</TableHead>
                </TableRow></TableHeader>
                <TableBody>
                    {visits.length > 0 ? visits.map((visit) => {
                        const numberUrl = nursing ? visit.detailUrl : visit.actionDisabled ? visit.recordUrl : visit.actionUrl;
                        return <TableRow key={visit.id}>
                            <TableCell className="text-center whitespace-nowrap font-sans tabular-nums text-xs text-neutral-600">
                                {numberUrl ? <Link className="font-medium text-neutral-900 underline-offset-4 hover:underline" href={numberUrl}>{visit.number}</Link> : visit.number}
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
                                    <StatusBadge examinationStarted={visit.examinationStarted} treatmentStarted={visit.treatmentStarted} context={visit.actionType === 'pharmacy' ? 'pharmacy' : (nursing || ['screening', 'procedure'].includes(visit.actionType)) ? 'nurse' : 'visit'} reservation={visit.isReservation} pendingReservation={visit.pendingReservation} status={visit.status} />
                                </div>
                            </TableCell>
                            <TableCell className="w-px px-4 text-center">
                                <div className="inline-flex w-max items-center justify-center gap-2">
                                    {nursing ? <>
                                        <DashboardAction action="detail" href={visit.detailUrl} label="Detail kunjungan" />
                                        <DashboardAction action="screening" href={visit.actionType === 'screening' ? visit.actionUrl : null} label={visit.actionType === 'screening' ? visit.actionLabel : 'Pengkajian awal'} disabledReason={visit.pendingReservation ? 'Reservasi masih menunggu konfirmasi pendaftaran.' : 'Tidak ada pengkajian awal yang menunggu atau sedang dikerjakan.'} />
                                        <DashboardAction action="procedure" href={visit.actionType === 'procedure' ? visit.actionUrl : null} label={visit.actionType === 'procedure' ? visit.actionLabel : 'Tindakan perawat'} disabledReason="Tidak ada tindakan perawat yang siap dilaksanakan." />
                                    </> : <DashboardAction action={visit.actionLabel === 'Lihat RME' ? 'rme' : visit.actionType} href={visit.actionDisabled ? null : visit.actionUrl} label={visit.actionLabel} disabledReason={visit.actionDisabledReason} />}
                                    {(nursing || visit.showLetterAction || (visit.recordUrl && visit.actionLabel !== 'Lihat RME')) && <DashboardAction action="rme" href={visit.recordUrl} label={`Lihat RME ${visit.patient}`} disabledReason="Rekam medis belum dapat diakses oleh akun Anda." />}
                                    {visit.showLetterAction && <DashboardAction action="issue" href={visit.letterUrl} label={`Terbitkan surat untuk ${visit.patient}`} disabledReason="Surat dapat diterbitkan setelah pemeriksaan difinalisasi dan diverifikasi." />}
                                </div>
                            </TableCell>
                        </TableRow>;
                    }) : (
                        <TableRow className="hover:bg-transparent">
                            <TableCell className="text-center" colSpan={6}><Empty description="Kunjungan yang sesuai akan tampil di daftar ini." size="compact" title="Belum ada kunjungan" /></TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}

function DashboardAction({ action, href, label, disabledReason }: { action: ComponentProps<typeof ActionButton>['action']; href?: string | null; label: string; disabledReason?: string }) {
    return href
        ? <ActionButton asChild action={action} label={label}><Link href={href} /></ActionButton>
        : <ActionButton action={action} label={label} disabled disabledReason={disabledReason} />;
}
