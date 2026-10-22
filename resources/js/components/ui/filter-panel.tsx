import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useId, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { CardContent } from '@/components/ui/card';

export function FilterPanel({ children, actions }: { children: ReactNode; actions?: ReactNode }) {
    const { url, props } = usePage();
    const [path, query = ''] = url.split('?');
    const params = new URLSearchParams(query);
    const active = [...params].some(([key, value]) => value !== '' && value !== '0' && key !== 'scope' && key !== 'tab' && !key.endsWith('page'));
    const [open, setOpen] = useState(active);
    const panelId = useId();
    const errors = Object.values(props.errors ?? {});

    useEffect(() => { setOpen(active || errors.length > 0); }, [url, active, errors.length]);

    if (params.get('scope') === 'today') {
        return null;
    }

    return <section aria-label="Filter data" className="space-y-3">
        <div className="flex flex-wrap items-center justify-end gap-3">
            <div className="flex flex-wrap items-center gap-2">
                {actions}
                {active && <Button asChild size="sm" variant="ghost"><Link href={path} preserveScroll replace><X className="size-4" />Reset filter</Link></Button>}
                <Button aria-controls={panelId} aria-expanded={open} onClick={() => setOpen(!open)} size="sm" type="button" variant="secondary">
                    <SlidersHorizontal className="size-4" />Filter{active && <span className="size-2 rounded-full bg-neutral-500" aria-label="Filter aktif" />}<ChevronDown className={`size-4 transition-transform ${open ? 'rotate-180' : ''}`} />
                </Button>
            </div>
        </div>
        <CardContent className="rounded-xl border border-neutral-200 bg-surface p-5 sm:p-6 [&_form]:items-end [&_form>button]:w-full sm:[&_form>button]:w-auto sm:[&_form>button]:justify-self-start" hidden={!open} id={panelId}>
            {children}
            {errors.length > 0 && <div className="mt-3 space-y-1 text-sm text-red-600" role="alert">{errors.map((error, index) => <p key={index}>{String(error)}</p>)}</div>}
        </CardContent>
    </section>;
}
