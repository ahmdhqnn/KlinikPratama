import { Link, usePage } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';

type ReportKind = 'screening' | 'examinations' | 'procedures' | 'handover' | 'direct' | 'external' | 'pharmacy-history' | 'stock' | 'visits' | 'letters';

export function ServiceReportLinks({ kind, size = 'default' }: { kind: ReportKind; size?: 'sm' | 'default' }) {
    const { auth } = usePage().props as { auth?: { user?: { role: string } | null } };
    const role = auth?.user?.role;
    const admin = role === 'admin';
    let links: { href: string; label: string }[] = [];

    if (kind === 'screening' && (role === 'perawat')) links = [{ href: '/perawat/laporan-pengkajian', label: 'Laporan pengkajian' }];
    if (kind === 'examinations' && (role === 'dokter')) links = [{ href: '/dokter/laporan-pemeriksaan', label: 'Laporan pemeriksaan' }];
    if (kind === 'procedures' && (role === 'perawat')) links = [{ href: '/perawat/laporan-tindakan', label: 'Laporan tindakan' }];
    if (['handover', 'direct', 'external', 'pharmacy-history'].includes(kind) && (role === 'farmasi')) {
        if (kind === 'handover' || kind === 'pharmacy-history') links.push({ href: '/farmasi/laporan/penyerahan-obat', label: 'Laporan penyerahan obat' });
        if (kind === 'direct' || kind === 'pharmacy-history') links.push({ href: '/farmasi/laporan/pemberian-langsung', label: 'Laporan obat langsung' });
        if (kind === 'external') links.push({ href: '/farmasi/laporan/resep-luar', label: 'Laporan resep luar' });
    }
    if (kind === 'stock' && (admin || role === 'manajemen')) links = [{ href: '/laporan/stok', label: 'Laporan stok' }];
    if ((kind === 'visits' || kind === 'letters') && admin) links = [{ href: '/laporan/kunjungan', label: 'Laporan kunjungan' }];
    if (kind === 'visits' && role === 'pendaftaran') links = [{ href: '/pendaftaran/laporan-kunjungan', label: 'Laporan kunjungan' }];

    if (!links.length) return null;
    return <div className="flex flex-wrap justify-end gap-2">{links.map(({ href, label }) => <Button asChild key={href} size={size} variant="secondary"><Link href={href}><FileText aria-hidden="true" className="size-4" />{label}</Link></Button>)}</div>;
}
