import { Checkbox } from '@/components/ui/checkbox';
type Option = [string, string];

export function ChoiceGroup({ label, name, options, selected, onChange, single = false, compact = false, required = false, error }: { label: string; name: string; options: Option[]; selected: string[]; onChange: (values: string[]) => void; single?: boolean; compact?: boolean; required?: boolean; error?: string }) {
    function toggle(value: string, checked: boolean) {
        if (single) { onChange(checked ? [value] : []); return; }
        const exclusive = ['tidak_ada', 'belum_diketahui', 'none', 'unknown'];
        onChange(!checked ? selected.filter((item) => item !== value) : exclusive.includes(value) ? [value] : [...selected.filter((item) => !exclusive.includes(item)), value]);
    }
    return <fieldset className="min-w-0 space-y-2" aria-describedby={error ? `${name}-error` : undefined}>
        <legend className="text-sm font-medium text-neutral-800">{label}{required && <span className="ml-1 text-red-600">*</span>}</legend>
        <div className="flex min-w-0 flex-wrap gap-x-3 gap-y-2">
            {options.map(([value, text]) => <label key={value} htmlFor={`${name}-${value}`} className={`flex min-w-0 max-w-full cursor-pointer items-start gap-2 text-sm leading-5 transition-colors ${compact ? 'min-h-8 py-1.5' : `min-h-10 rounded-lg border px-3 py-2 ${selected.includes(value) ? 'border-neutral-600 bg-neutral-100 text-neutral-950' : 'border-neutral-200 text-neutral-700 hover:bg-neutral-50'}`}`}>
                <Checkbox className="mt-0.5 size-4 shrink-0" id={`${name}-${value}`} checked={selected.includes(value)} onCheckedChange={(checked) => toggle(value, checked === true)} />
                <span className="min-w-0 whitespace-normal break-words">{text}</span>
            </label>)}
        </div>
        {error && <p id={`${name}-error`} role="alert" className="text-xs text-red-600">{error}</p>}
    </fieldset>;
}
