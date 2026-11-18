import { Badge } from '@/components/ui/badge';

const labels: Record<string, string> = {
    menunggu: 'Menunggu pendaftaran',
    terdaftar: 'Terdaftar di poli tujuan',
    dilayani: 'Pelayanan berlangsung',
    diterima: 'Diterima',
    selesai: 'Pelayanan selesai',
    batal: 'Kunjungan tujuan batal',
};

export function InternalReferralStatus({ status }: { status: string }) {
    return <Badge className="whitespace-normal text-center" variant={status === 'selesai' ? 'complete' : status === 'batal' ? 'cancelled' : 'default'}>
        {labels[status] ?? status}
    </Badge>;
}
