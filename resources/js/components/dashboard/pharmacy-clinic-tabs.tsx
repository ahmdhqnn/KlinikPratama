import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Link, usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

const clinicOptions = [
    ['', 'Semua poli'],
    ['umum', 'Poli Umum'],
    ['gigi', 'Poli Gigi'],
] as const;

export function PharmacyClinicTabs({ clinicType }: { clinicType?: string | null }) {
    const page = usePage<SharedProps>();
    const user = page.props.auth?.user;

    if (user?.role === 'farmasi' && user.clinicType) {
        return <div aria-label="Unit penugasan farmasi" className="flex items-center gap-2">
            <Badge className="px-3 py-1 text-xs font-medium">Unit penugasan: {user.clinicName ?? (user.clinicType === 'gigi' ? 'Poli Gigi' : 'Poli Umum')}</Badge>
        </div>;
    }

    const { url } = page;
    const [path, query] = url.split('?');
    const params = new URLSearchParams(query);
    for (const key of [...params.keys()]) {
        if (key === 'page' || key.endsWith('_page')) params.delete(key);
    }

    return <nav aria-label="Poliklinik farmasi" className="flex flex-wrap gap-2">
        {clinicOptions.map(([value, label]) => {
            const active = (clinicType ?? '') === value;
            const target = new URLSearchParams(params);
            if (value) target.set('poli', value);
            else target.delete('poli');

            return <Button asChild key={value} size="sm" variant={active ? 'default' : 'secondary'}>
                <Link aria-current={active ? 'page' : undefined} href={target.size ? path + '?' + target : path} preserveScroll>{label}</Link>
            </Button>;
        })}
    </nav>;
}
