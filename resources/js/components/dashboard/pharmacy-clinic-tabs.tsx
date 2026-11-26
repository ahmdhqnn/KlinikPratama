import { Button } from '@/components/ui/button';
import { Link, usePage } from '@inertiajs/react';

const clinicOptions = [
    ['', 'Semua poli'],
    ['umum', 'Poli Umum'],
    ['gigi', 'Poli Gigi'],
] as const;

export function PharmacyClinicTabs({ clinicType }: { clinicType?: string | null }) {
    const { url } = usePage();
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
