import { StatusBadge } from '@/components/dashboard/status-badge';

export interface DashboardVisit {
    id: number;
    number: string;
    patient: string;
    medicalRecordNumber: string;
    clinic: string;
    status: string;
    triage?: string | null;
    priority?: string | null;
}

export function VisitTable({ visits }: { visits: DashboardVisit[] }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[680px] text-left text-sm">
                <thead className="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th className="px-5 py-3.5">No. kunjungan</th>
                        <th className="px-5 py-3.5">Pasien</th>
                        <th className="px-5 py-3.5">Poliklinik</th>
                        <th className="px-5 py-3.5">Status</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {visits.length > 0 ? visits.map((visit) => (
                        <tr className="transition-colors hover:bg-slate-50/80" key={visit.id}>
                            <td className="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{visit.number}</td>
                            <td className="px-5 py-4">
                                <p className="font-medium text-slate-900">{visit.patient}</p>
                                <p className="mt-0.5 text-xs text-slate-500">{visit.medicalRecordNumber}</p>
                            </td>
                            <td className="px-5 py-4 text-slate-600">{visit.clinic}</td>
                            <td className="px-5 py-4">
                                <div className="flex flex-col items-start gap-1.5">
                                    <StatusBadge status={visit.status} />
                                    {visit.triage && (
                                        <span className={`text-xs font-medium capitalize ${visit.triage === 'merah' ? 'text-red-700' : 'text-slate-500'}`}>
                                            Triase {visit.triage}{visit.priority ? ` · ${visit.priority}` : ''}
                                        </span>
                                    )}
                                </div>
                            </td>
                        </tr>
                    )) : (
                        <tr>
                            <td className="px-5 py-10 text-center text-sm text-slate-500" colSpan={4}>
                                Belum ada kunjungan untuk ditampilkan.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
