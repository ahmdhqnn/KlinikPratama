import type { ReactNode } from 'react';
import { OdontogramFdi, type DentalFinding } from './odontogram-fdi';

export function OdontogramRecord({ findings, children }: { findings: DentalFinding[]; children: ReactNode }) {
    return <div className="grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <div className="min-w-0 space-y-4">{(['adult', 'primary'] as const).filter(dentition => (!findings.length && dentition === 'adult') || findings.some(finding => dentition === 'primary' ? Number(finding.toothFdi) >= 50 : Number(finding.toothFdi) < 50)).map(dentition => <OdontogramFdi key={dentition} dentition={dentition} findings={findings} selectedTooth="" disabled recordView onSelect={() => undefined} />)}</div>
        <section className="min-w-0 space-y-3 rounded-xl border border-neutral-200 bg-surface p-4 sm:p-5 xl:mt-14"><h4 className="text-sm font-semibold text-neutral-900">Temuan pemeriksaan ({findings.length})</h4>{children}</section>
    </div>;
}
