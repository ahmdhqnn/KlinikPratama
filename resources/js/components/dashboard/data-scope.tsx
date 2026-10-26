import { Link, usePage } from '@inertiajs/react';
import { CalendarDays, List } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function DataScope({ todayLabel }: { todayLabel: string }) {
    const { url } = usePage();
    const [path, query = ''] = url.split('?');
    const today = new URLSearchParams(query).get('scope') === 'today';

    return <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <nav aria-label="Cakupan data" className="flex flex-wrap gap-2">
            <Button asChild variant={today ? 'secondary' : 'default'}><Link aria-current={!today ? 'page' : undefined} href={path}><List className="size-4" />Daftar kunjungan</Link></Button>
            <Button asChild variant={today ? 'default' : 'secondary'}><Link aria-current={today ? 'page' : undefined} href={`${path}?scope=today`}><CalendarDays className="size-4" />{todayLabel}</Link></Button>
        </nav>
    </div>;
}
