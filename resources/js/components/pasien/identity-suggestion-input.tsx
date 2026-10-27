import { useEffect, useId, useState, type ComponentProps } from 'react';
import { Input } from '@/components/ui/input';

interface Props extends ComponentProps<typeof Input> {
    field: 'tempat_lahir' | 'kelurahan' | 'kecamatan';
    district?: string;
}

export function IdentitySuggestionInput({ field, district, value, ...props }: Props) {
    const listId = useId();
    const [suggestions, setSuggestions] = useState<string[]>([]);

    useEffect(() => {
        const query = String(value ?? '').trim();
        setSuggestions([]);
        if (query.length < 2) return;
        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            const params = new URLSearchParams({ field, q: query });
            if (field === 'kelurahan' && district?.trim()) params.set('kecamatan', district.trim());
            try {
                const response = await fetch(`/pendaftaran/sugesti-identitas?${params}`, {
                    headers: { Accept: 'application/json' }, signal: controller.signal,
                });
                if (response.ok) {
                    const values: string[] = await response.json();
                    if (!controller.signal.aborted) setSuggestions(values);
                }
            } catch {
                if (!controller.signal.aborted) setSuggestions([]);
            }
        }, 250);
        return () => { window.clearTimeout(timeout); controller.abort(); };
    }, [field, district, value]);

    return <>
        <Input {...props} autoComplete="off" list={listId} maxLength={100} value={value} />
        <datalist id={listId}>{suggestions.map((suggestion) => <option key={suggestion} value={suggestion} />)}</datalist>
    </>;
}
