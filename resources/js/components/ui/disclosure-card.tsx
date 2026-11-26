import { ChevronDown } from 'lucide-react';
import { useId, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export function DisclosureCard({ title, description, icon, expanded, onToggle, children }: {
    title: string;
    description: string;
    icon: ReactNode;
    expanded: boolean;
    onToggle: () => void;
    children: ReactNode;
}) {
    const panelId = useId();

    return <Card className="min-w-0 overflow-hidden">
        <CardHeader className={expanded ? 'border-b border-neutral-100' : undefined}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex min-w-0 items-start gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">{icon}</span>
                    <div className="min-w-0"><CardTitle>{title}</CardTitle><CardDescription className="mt-1">{description}</CardDescription></div>
                </div>
                <Button aria-controls={panelId} aria-expanded={expanded} onClick={onToggle} size="sm" type="button" variant="secondary">
                    {expanded ? 'Tutup' : 'Buka'}<ChevronDown aria-hidden="true" className={`size-4 transition-transform ${expanded ? 'rotate-180' : ''}`} />
                </Button>
            </div>
        </CardHeader>
        <div hidden={!expanded} id={panelId}>{children}</div>
    </Card>;
}
