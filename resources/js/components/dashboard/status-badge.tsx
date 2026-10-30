import { Badge } from '@/components/ui/badge';

export const visitStatusLabels: Record<string, string> = {
    menunggu: 'Menunggu pengkajian awal',
    screening: 'Pengkajian awal berlangsung',
    pemeriksaan: 'Menunggu pemeriksaan',
    tindakan: 'Menunggu tindakan perawat',
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

const nurseStatusLabels: Record<string, string> = {
    ...visitStatusLabels,
    tindakan: 'Menunggu tindakan',
};

const variants: Record<string, 'cancelled' | 'default'> = {
    batal: 'cancelled',
};

export const visitStatusFilterLabels: Record<string, string> = {
    ...visitStatusLabels,
    pemeriksaan: 'Menunggu / sedang diperiksa',
};

export function StatusBadge({ status, reservation = false, context = 'visit', examinationStarted = false, treatmentStarted = false }: { status: string; examinationStarted?: boolean; treatmentStarted?: boolean; reservation?: boolean; context?: 'visit' | 'nurse' | 'pharmacy' | 'laboratory' }) {
    if (reservation) {
        return <Badge variant="default">Reservasi</Badge>;
    }

    if (context === 'visit' && status === 'pemeriksaan' && examinationStarted) {
        return <Badge className="justify-center text-center leading-4">Sedang diperiksa</Badge>;
    }

    if (context === 'nurse' && status === 'pemeriksaan' && examinationStarted) {
        return <Badge className="justify-center text-center leading-4">Sedang diperiksa dokter</Badge>;
    }

    if (context === 'nurse' && status === 'tindakan' && treatmentStarted) {
        return <Badge className="justify-center text-center leading-4">Tindakan berlangsung</Badge>;
    }

    const labels = { visit: visitStatusLabels, nurse: nurseStatusLabels, pharmacy: pharmacyStatusLabels, laboratory: laboratoryStatusLabels }[context];

    return <Badge className="justify-center text-center leading-4" variant={variants[status] ?? 'default'}>{labels[status] ?? status}</Badge>;
}
