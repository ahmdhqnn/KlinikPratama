import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function MasterExportButton({ catalog, href, label = 'Ekspor semua data' }: { catalog: string; href?: never; label?: string } | { href: string; catalog?: never; label?: string }) {
    return <Button asChild size="sm" variant="secondary">
        <a href={href ?? '/master/export/' + catalog}><Download aria-hidden="true" className="size-4" />{label}</a>
    </Button>;
}
