import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export function setFindingSign(narrative: string, term: string, sign: '+' | '-'): string {
    const name = term.trim().replace(/[.,;]+$/, '');
    if (!name) return narrative;
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const pattern = new RegExp(`(^|[,.!?;]\\s*)${escaped}\\s*\\([+-]\\)`, 'i');
    if (pattern.test(narrative)) return narrative.replace(pattern, (_, start) => `${start}${name} (${sign})`);
    return `${narrative.trim().replace(/[,;]$/, '')}${narrative.trim() ? /[.!?]$/.test(narrative.trim()) ? ' ' : ', ' : ''}${name} (${sign})`;
}
export function ClinicalFindingSigns({ value, onChange, suggestions, disabled, label }: { value: string; onChange: (value: string) => void; suggestions: string[]; disabled?: boolean; label: string }) {
    const [term, setTerm] = useState('');
    const [otherOpen, setOtherOpen] = useState(false);
    return <div className="space-y-3 rounded-lg border border-neutral-200 p-3" aria-label={label}>
        <p className="text-xs text-neutral-500">Tambahkan hasil (+) atau (−) setelah dinilai. Pilihan kosong berarti belum dicatat.</p>
        <div className="flex flex-wrap gap-2">{suggestions.map(name => <div key={name} className="flex items-center gap-1 rounded-lg border border-neutral-200 px-2 py-1"><span className="mr-1 text-xs">{name}</span>{(['+', '-'] as const).map(sign => <Button key={sign} type="button" size="sm" variant="ghost" disabled={disabled} aria-label={`${name} (${sign})`} onClick={() => onChange(setFindingSign(value, name, sign))}>{sign === '-' ? '−' : '+'}</Button>)}</div>)}</div>
        <Button type="button" size="sm" variant="ghost" disabled={disabled} aria-expanded={otherOpen} onClick={() => setOtherOpen(!otherOpen)}>Lainnya</Button>
        {otherOpen && <div className="flex flex-wrap gap-2"><Input className="min-w-40 flex-1" aria-label={`${label} lainnya`} placeholder="Temuan atau gejala lain, misalnya nyeri lepas" maxLength={150} disabled={disabled} value={term} onChange={event => setTerm(event.target.value)} />{(['+', '-'] as const).map(sign => <Button key={sign} type="button" size="sm" variant="secondary" disabled={disabled || !term.trim()} onClick={() => { onChange(setFindingSign(value, term, sign)); setTerm(''); }}>Tambahkan ({sign === '-' ? '−' : '+'})</Button>)}</div>}
    </div>;
}
