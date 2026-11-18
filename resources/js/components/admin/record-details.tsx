import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export function RecordDetails({ title, description, entries, open, onOpenChange }: {
    title: string;
    description: string;
    entries: [string, string | null | undefined][];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return <Dialog open={open} onOpenChange={onOpenChange}><DialogContent className="max-h-[90vh] overflow-y-auto"><DialogHeader><DialogTitle>{title}</DialogTitle><DialogDescription>{description}</DialogDescription></DialogHeader>
        <dl className="grid gap-4 sm:grid-cols-2">{entries.map(([label, value]) => <div className="min-w-0" key={label}><dt className="text-xs text-muted-foreground">{label}</dt><dd className="mt-1 break-words text-sm font-medium">{value || 'Belum diisi'}</dd></div>)}</dl>
    </DialogContent></Dialog>;
}
