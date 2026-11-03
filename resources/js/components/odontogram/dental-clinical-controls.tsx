import { Button } from '@/components/ui/button';

export const clinicalTests: Record<string, { label: string; options: Record<string, string> }> = {
    vitality: { label: 'Termal / sensibilitas pulpa', options: { positive: '+ Respons', negative: '− Tidak respons', unknown: 'Belum dinilai' } },
    percussion: { label: 'Perkusi', options: { positive: '+', negative: '−', unknown: 'Belum dinilai' } },
    palpation: { label: 'Palpasi', options: { positive: '+', negative: '−', unknown: 'Belum dinilai' } },
    mobility: { label: 'Mobilitas', options: { M0: 'M0', M1: 'M1', M2: 'M2', M3: 'M3', unknown: 'Belum dinilai' } },
};

export function clinicalTestSummary(details: Record<string, string>): string {
    return Object.entries(clinicalTests).flatMap(([key, test]) => {
        const value = details[key];
        if (!value || value === 'unknown') return [];
        return [`${test.label} (${key === 'mobility' ? value : value === 'positive' ? '+' : '−'})`];
    }).join(', ');
}

export function updateClinicalTest(details: Record<string, string>, key: string, value: string): Record<string, string> {
    const oldSummary = clinicalTestSummary(details);
    const next = { ...details, [key]: details[key] === value ? '' : value };
    const summary = clinicalTestSummary(next);
    const manual = (details.assessment ?? '').replace(oldSummary ? `Tes klinis: ${oldSummary}.` : '\u0000', '').trim();
    return { ...next, assessment: [summary ? `Tes klinis: ${summary}.` : '', manual].filter(Boolean).join('\n') };
}

export function DentalClinicalControls({ tooth, surface, selectedSurfaces = [], finding, details, disabled, onSurface, onChange, mode = 'all' }: {
    mode?: 'all' | 'surface' | 'tests';
    tooth: string; surface: string; selectedSurfaces?: string[]; finding: string; details: Record<string, string>; disabled: boolean;
    onSurface: (surface: string) => void; onChange: (details: Record<string, string>) => void;
}) {
    const surfaces = [{ code: 'M', name: 'Mesial', points: '10,10 34,34 34,66 10,90' }, { code: 'D', name: 'Distal', points: '90,10 66,34 66,66 90,90' }, { code: 'V', name: 'Bukal / labial', points: '10,10 90,10 66,34 34,34' }, { code: 'L', name: 'Lingual / palatal', points: '10,90 90,90 66,66 34,66' }, { code: 'O', name: 'Oklusal / insisal', points: '34,34 66,34 66,66 34,66' }];
    return <section className="w-full min-w-0 space-y-4 rounded-xl border border-neutral-200 p-4" aria-label="Permukaan dan tes klinis gigi aktif">
        {mode !== 'tests' && <div className="flex flex-wrap items-center gap-4">
            <svg aria-label={`Permukaan gigi ${tooth || 'belum dipilih'}`} viewBox="0 0 100 100" className="size-32 shrink-0">
                {surfaces.map((part) => <polygon key={part.code} points={part.points} role="button" aria-label={`Pilih ${part.name} gigi ${tooth}`} aria-pressed={(selectedSurfaces.length ? selectedSurfaces : [surface]).includes(part.code)} aria-disabled={disabled} tabIndex={disabled ? -1 : 0} onClick={() => !disabled && onSurface(part.code)} onKeyDown={(event) => { if (!disabled && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onSurface(part.code); } }} className={`cursor-pointer stroke-neutral-400 stroke-1 focus:outline-none focus:stroke-neutral-900 focus:stroke-2 ${(selectedSurfaces.length ? selectedSurfaces : [surface]).includes(part.code) ? finding === 'caries' ? 'fill-red-400' : finding === 'restored' ? 'fill-neutral-500' : 'fill-neutral-400' : 'fill-neutral-100 hover:fill-neutral-200'}`}><title>{part.name}</title></polygon>)}
                {surfaces.map((part, index) => <text key={part.code} x={[22, 78, 50, 50, 50][index]} y={[54, 54, 26, 82, 54][index]} textAnchor="middle" className="pointer-events-none fill-neutral-900 text-[10px] font-semibold">{part.code}</text>)}
            </svg>
            <p className="min-w-0 flex-1 text-sm text-neutral-600">Klik permukaan yang diperiksa. Beberapa permukaan dapat dipilih bersama, misalnya M–O–D.</p>
        </div>}
        {mode !== 'surface' && <div className="grid min-w-0 items-start gap-4 sm:grid-cols-2">{Object.entries(clinicalTests).map(([key, test]) => <div key={key} className="space-y-2"><p className="text-sm font-medium">{test.label}</p><div className="flex flex-wrap gap-2">{Object.entries(test.options).map(([value, label]) => <Button key={value} type="button" size="sm" disabled={disabled} aria-pressed={details[key] === value} variant={details[key] === value ? 'default' : 'secondary'} onClick={() => onChange(updateClinicalTest(details, key, value))}>{label}</Button>)}</div></div>)}</div>}
        {mode !== 'surface' && <p className="text-xs text-neutral-500">Hasil tes yang dipilih akan ditambahkan ke catatan pemeriksaan.</p>}
    </section>;
}
