import { useEffect, useId, useState, type ComponentProps } from 'react';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

interface Props extends ComponentProps<typeof Input> {
    field: 'tempat_lahir' | 'kelurahan' | 'kecamatan';
    district?: string;
}

export function IdentitySuggestionInput({ field, district, value, className, ...props }: Props) {
    const listId = useId();
    const [suggestions, setSuggestions] = useState<string[]>([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const query = String(value ?? '').trim();
        setSuggestions([]);
        setLoading(false);
        if (query.length < 2) return;
        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setLoading(true);
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
            } finally {
                if (!controller.signal.aborted) setLoading(false);
            }
        }, 250);
        return () => { window.clearTimeout(timeout); controller.abort(); };
    }, [field, district, value]);

    return <div className="relative">
        <Input {...props} aria-busy={loading} autoComplete="off" className={cn(loading && 'pr-9', className)} list={listId} maxLength={100} value={value} />
        {loading && <Skeleton className="pointer-events-none absolute right-3 top-3.5 size-3" />}
        <datalist id={listId}>{suggestions.map((suggestion) => <option key={suggestion} value={suggestion} />)}</datalist>
    </div>;
}
