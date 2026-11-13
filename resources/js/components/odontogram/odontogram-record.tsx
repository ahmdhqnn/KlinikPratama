import type { ReactNode } from 'react';
import { OdontogramFdi, type DentalFinding } from './odontogram-fdi';

export function OdontogramRecord({ findings, children, compact = false }: { findings: DentalFinding[]; children: ReactNode; compact?: boolean }) {
    return <div className={`grid min-w-0 items-stretch xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] ${compact ? 'gap-4' : 'gap-5'}`}>
        <div className="min-w-0 space-y-4 rounded-xl bg-neutral-50/50 p-4 sm:p-5">{(['adult', 'primary'] as const).filter(dentition => (!findings.length && dentition === 'adult') || findings.some(finding => dentition === 'primary' ? Number(finding.toothFdi) >= 50 : Number(finding.toothFdi) < 50)).map(dentition => <OdontogramFdi key={dentition} dentition={dentition} findings={findings} selectedTooth="" disabled recordView onSelect={() => undefined} />)}</div>
        <section className={`min-w-0 space-y-3 rounded-xl border border-neutral-200 bg-surface p-4 sm:p-5`}><h4 className="text-sm font-semibold text-neutral-900">Temuan pemeriksaan ({findings.length})</h4>{children}</section>
    </div>;
}
