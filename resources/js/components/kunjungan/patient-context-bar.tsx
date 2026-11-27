import { AlertTriangle, Baby, HeartPulse, ShieldAlert } from 'lucide-react';
import { isReportedAllergy } from '@/lib/allergy-status';
import { cn } from '@/lib/utils';

export interface PatientContextBarProps {
    patientName: string;
    medicalRecordNumber: string;
    visitNumber?: string | null;
    gender?: string | null;
    age?: number | null;
    weight?: number | string | null;
    bloodPressure?: string | null;
    pulse?: number | string | null;
    temperature?: number | string | null;
    oxygenSaturation?: number | string | null;
    allergyText?: string | null;
    maternalSummary?: string | null;
    maternalRisk?: boolean;
    triage?: string | null;
    unsavedCount?: number;
    draftStatus?: string | null;
    className?: string;
}

export function PatientContextBar({
    patientName,
    medicalRecordNumber,
    visitNumber,
    gender,
    age,
    weight,
    bloodPressure,
    pulse,
    temperature,
    oxygenSaturation,
    allergyText,
    maternalSummary,
    maternalRisk = false,
    triage,
    unsavedCount = 0,
    draftStatus,
    className,
}: PatientContextBarProps) {
    const normalizedAllergy = typeof allergyText === 'string' ? allergyText.trim() : '';
    const hasAllergy = isReportedAllergy(normalizedAllergy);
    const vitals = [
        bloodPressure ? `TD ${bloodPressure}` : null,
        pulse !== null && pulse !== undefined && String(pulse).trim() !== '' ? `N ${pulse}` : null,
        temperature !== null && temperature !== undefined && String(temperature).trim() !== '' ? `S ${temperature}°C` : null,
        oxygenSaturation !== null && oxygenSaturation !== undefined && String(oxygenSaturation).trim() !== '' ? `SpO₂ ${oxygenSaturation}%` : null,
    ].filter((item): item is string => Boolean(item));

    const genderShort = gender === 'L' ? 'L' : gender === 'P' ? 'P' : null;
    const demographics = [
        genderShort,
        age !== null && age !== undefined ? `${age} th` : null,
        weight !== null && weight !== undefined && String(weight).trim() !== '' ? `BB ${weight} kg` : null,
    ].filter(Boolean).join(' · ');

    return (
        <aside
            aria-label="Konteks klinis pasien"
            className={cn(
                'no-print sticky top-[4.25rem] z-20 rounded-xl border border-neutral-200 bg-surface px-5 py-3.5 sm:top-[4.5rem] lg:top-[5.75rem]',
                className,
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-x-5 gap-y-2.5 text-sm">
                <div className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1.5">
                    <span className="truncate text-base font-semibold text-neutral-950">{patientName}</span>
                    <span className="rounded-md bg-neutral-100 px-2.5 py-1 font-sans text-xs font-semibold tabular-nums text-neutral-800">
                        {medicalRecordNumber}
                    </span>
                    {visitNumber && <span className="font-sans text-xs font-medium tabular-nums text-neutral-500">{visitNumber}</span>}
                    {demographics && <span className="text-xs font-medium text-neutral-600">{demographics}</span>}
                </div>

                <div className="flex flex-wrap items-center gap-2 text-xs">
                    {vitals.length > 0 && (
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 font-sans font-medium tabular-nums text-neutral-700">
                            <HeartPulse aria-hidden="true" className="size-3.5 text-neutral-500" />
                            {vitals.join(' · ')}
                        </span>
                    )}

                    {triage && ['merah', 'kuning', 'hijau'].includes(triage) && (
                        <span
                            className={cn(
                                'inline-flex items-center rounded-full border px-3 py-1 font-semibold capitalize',
                                triage === 'merah'
                                    ? 'border-red-200 bg-red-50 text-red-900'
                                    : triage === 'kuning'
                                        ? 'border-amber-200 bg-amber-50 text-amber-900'
                                        : 'border-emerald-200 bg-emerald-50 text-emerald-900',
                            )}
                        >
                            Triase {triage}
                        </span>
                    )}

                    {hasAllergy ? (
                        <span className="inline-flex max-w-xs items-center gap-1.5 truncate rounded-full border border-red-200 bg-red-50 px-3 py-1 font-semibold text-red-900" title={`Alergi: ${normalizedAllergy}`}>
                            <ShieldAlert aria-hidden="true" className="size-3.5 shrink-0" />
                            <span className="truncate">Alergi: {normalizedAllergy}</span>
                        </span>
                    ) : (
                        <span className="inline-flex items-center rounded-full border border-neutral-200 bg-neutral-50 px-3 py-1 font-medium text-neutral-600">
                            Alergi: {normalizedAllergy ? normalizedAllergy : 'Belum ada laporan'}
                        </span>
                    )}

                    {maternalSummary && maternalRisk && (
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 font-semibold text-amber-900">
                            <Baby aria-hidden="true" className="size-3.5 shrink-0" />
                            {maternalSummary}
                        </span>
                    )}

                    {unsavedCount > 0 ? (
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 font-medium text-amber-900">
                            <AlertTriangle aria-hidden="true" className="size-3.5 shrink-0" />
                            {unsavedCount} bagian belum disimpan
                        </span>
                    ) : draftStatus ? (
                        <span className="font-medium text-neutral-500">{draftStatus}</span>
                    ) : null}
                </div>
            </div>
        </aside>
    );
}
