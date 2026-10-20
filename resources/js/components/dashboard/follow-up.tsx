import { Link } from '@inertiajs/react';
import { CalendarClock, History } from 'lucide-react';
import { StatCard } from '@/components/dashboard/stat-card';

export interface FollowUpData {
    upcoming: number;
    overdue: number;
    href: string;
}

export function FollowUp({ data, showUpcoming = true }: { data: FollowUpData; showUpcoming?: boolean }) {
    return (
        <section aria-label="Tindak lanjut kunjungan" className="grid gap-4 sm:grid-cols-2">
            {[
                { key: 'upcoming', label: 'Reservasi mendatang', value: data.upcoming, detail: 'Kunjungan yang dijadwalkan berikutnya.', icon: CalendarClock },
                { key: 'overdue', label: 'Kunjungan tertunda', value: data.overdue, detail: 'Kunjungan lewat tanggal yang perlu ditinjau.', icon: History },
            ].filter((item) => showUpcoming || item.key !== 'upcoming').map(({ key, label, value, detail, icon: Icon }) => (
                <Link key={key} href={data.href} aria-label={`${label}: ${value}. Tinjau kunjungan`} className="rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500">
                    <StatCard label={label} value={value} description={detail} icon={<Icon className="size-5" />} iconClassName="bg-neutral-100 text-neutral-600" />
                </Link>
            ))}
        </section>
    );
}
