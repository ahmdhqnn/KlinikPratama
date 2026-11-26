import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Search, SlidersHorizontal, X } from 'lucide-react';
import { useEffect, useId, useState, type ComponentProps, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function FilterSubmit({ className, disabled }: Pick<ComponentProps<typeof Button>, 'className' | 'disabled'>) {
    return (
        <Button className={cn('col-span-full w-full sm:ml-auto sm:w-auto sm:justify-self-end', className)} disabled={disabled} type="submit">
            <Search aria-hidden="true" className="size-4" />
            Terapkan filter
        </Button>
    );
}

export function FilterPanel({
    children,
    leading,
    actions,
    actionsAlign = 'end',
    contextKeys = [],
    filterKeys,
    ariaLabel = 'Filter data',
}: {
    children: ReactNode;
    leading?: ReactNode;
    actions?: ReactNode;
    actionsAlign?: 'start' | 'end';
    contextKeys?: readonly string[];
    filterKeys?: readonly string[];
    ariaLabel?: string;
}) {
    const { url, props } = usePage();
    const [path, query = ''] = url.split('#', 1)[0].split('?');
    const params = new URLSearchParams(query);
    const isContext = (key: string) => key === 'scope' || key === 'tab' || contextKeys.includes(key);
    const isTrackedKey = (key: string) => (filterKeys ? filterKeys.includes(key) : !isContext(key) && !key.endsWith('page'));
    const active = [...params].some(([key, value]) => value !== '' && (value !== '0' || key === 'search' || key === 'q') && isTrackedKey(key));
    const preserved = new URLSearchParams(
        [...params].filter(([key]) => (filterKeys ? !filterKeys.includes(key) && !key.endsWith('page') : isContext(key))),
    );
    const resetHref = preserved.size ? path + '?' + preserved.toString() : path;
    const [open, setOpen] = useState(active);
    const panelId = useId();
    const errors = Object.values(props.errors ?? {});

    useEffect(() => { setOpen(active || (!filterKeys && errors.length > 0)); }, [url, active, errors.length, filterKeys]);

    if (params.get('scope') === 'today') {
        if (!leading && !actions) {
            return null;
        }

        return (
            <div className={`flex flex-wrap items-center gap-3 ${leading ? 'justify-between' : actionsAlign === 'start' ? 'justify-start' : 'justify-end'}`}>
                {leading && <div className="flex flex-wrap items-center gap-2">{leading}</div>}
                {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
            </div>
        );
    }

    return <section aria-label={ariaLabel} className="space-y-3">
        <div className={`flex flex-wrap items-center gap-3 ${leading ? 'justify-between' : actionsAlign === 'start' ? 'justify-start' : 'justify-end'}`}>
            {leading && <div className="flex flex-wrap items-center gap-2">{leading}</div>}
            <div className={`flex flex-wrap items-center gap-2 ${leading ? 'ml-auto justify-end' : actionsAlign === 'start' ? 'justify-start' : 'justify-end'}`}>
                {actions}
                {active && <Button asChild size="sm" variant="ghost"><Link href={resetHref} preserveScroll replace><X className="size-4" />Reset filter</Link></Button>}
                <Button aria-controls={panelId} aria-expanded={open} onClick={() => setOpen(!open)} size="sm" type="button" variant="secondary">
                    <SlidersHorizontal className="size-4" />Filter{active && <span className="size-2 rounded-full bg-neutral-500" aria-label="Filter aktif" />}<ChevronDown className={`size-4 transition-transform ${open ? 'rotate-180' : ''}`} />
                </Button>
            </div>
        </div>
        <CardContent className="rounded-xl bg-surface p-4 sm:p-6 sm:[&_form]:items-end sm:[&_form>div:has(>button[type=submit])]:col-span-full sm:[&_form>div:has(>button[type=submit])]:ml-auto sm:[&_form>div:has(>button[type=submit])]:flex sm:[&_form>div:has(>button[type=submit])]:w-full sm:[&_form>div:has(>button[type=submit])]:justify-end" hidden={!open} id={panelId}>
            {children}
            {!filterKeys && errors.length > 0 && <div className="mt-3 space-y-1 text-sm text-red-600" role="alert">{errors.map((error, index) => <p key={index}>{String(error)}</p>)}</div>}
        </CardContent>
    </section>;
}
