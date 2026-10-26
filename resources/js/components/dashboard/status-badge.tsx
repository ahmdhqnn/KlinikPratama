import { Badge } from '@/components/ui/badge';

export const visitStatusLabels: Record<string, string> = {
    menunggu: 'Menunggu pengkajian awal',
    screening: 'Pengkajian awal berlangsung',
    pemeriksaan: 'Menunggu pemeriksaan',
    farmasi: 'Pelayanan farmasi',
    kasir: 'Perlu penyelesaian kunjungan',
    selesai: 'Kunjungan selesai',
    batal: 'Kunjungan dibatalkan',
};

const pharmacyStatusLabels: Record<string, string> = {
    menunggu: 'Menunggu penyiapan obat',
    farmasi: 'Menunggu penyiapan obat',
    diproses: 'Penyiapan obat berlangsung',
    selesai: 'Penyerahan obat selesai',
    batal: 'Pelayanan farmasi dibatalkan',
};

const laboratoryStatusLabels: Record<string, string> = {
    menunggu: 'Menunggu pemeriksaan lab',
    selesai: 'Pemeriksaan lab selesai',
};

const variants: Record<string, 'waiting' | 'screening' | 'examination' | 'pharmacy' | 'complete' | 'cancelled' | 'default'> = {
    menunggu: 'waiting',
    screening: 'screening',
    pemeriksaan: 'examination',
    farmasi: 'pharmacy',
    diproses: 'pharmacy',
    kasir: 'waiting',
    selesai: 'complete',
    batal: 'cancelled',
};

export const visitStatusFilterLabels: Record<string, string> = {
    ...visitStatusLabels,
    pemeriksaan: 'Menunggu / sedang diperiksa',
};

export function StatusBadge({ status, reservation = false, context = 'visit', examinationStarted = false }: { status: string; examinationStarted?: boolean; reservation?: boolean; context?: 'visit' | 'pharmacy' | 'laboratory' }) {
    if (reservation) {
        return <Badge variant="default">Reservasi</Badge>;
    }

    if (context === 'visit' && status === 'pemeriksaan' && examinationStarted) {
        return <Badge className="justify-center text-center leading-4" variant="examination">Sedang diperiksa</Badge>;
    }

    const labels = { visit: visitStatusLabels, pharmacy: pharmacyStatusLabels, laboratory: laboratoryStatusLabels }[context];

    return <Badge className="justify-center text-center leading-4" variant={variants[status] ?? 'default'}>{labels[status] ?? status}</Badge>;
}
