import { Badge } from '@/components/ui/badge';

const labels: Record<string, string> = {
    menunggu: 'Menunggu',
    screening: 'Skrining',
    pemeriksaan: 'Pemeriksaan',
    farmasi: 'Farmasi',
    kasir: 'Perlu penyelesaian',
    selesai: 'Selesai',
    batal: 'Dibatalkan',
};

const variants: Record<string, 'waiting' | 'screening' | 'examination' | 'pharmacy' | 'complete' | 'cancelled' | 'default'> = {
    menunggu: 'waiting',
    screening: 'screening',
    pemeriksaan: 'examination',
    farmasi: 'pharmacy',
    kasir: 'waiting',
    selesai: 'complete',
    batal: 'cancelled',
};

export function StatusBadge({ status }: { status: string }) {
    return <Badge variant={variants[status] ?? 'default'}>{labels[status] ?? status}</Badge>;
}
