import { clinicBrand } from '@/components/clinic-brand';

export interface ClinicProfile {
    name?: string; address?: string | null; phone?: string | null; email?: string | null;
}

export function ClinicalDocumentHeader({ clinic = {} }: { clinic?: ClinicProfile }) {
    const defaultAddress = !clinic.address || clinic.address === 'Jl. Medan Merdeka Barat No. 17, Gambir, Jakarta Pusat 10110';
    return <header className="clinical-document-head">
        <img className="clinical-logo" src={clinicBrand.logo} alt={clinicBrand.ministry} />
        <div className="clinical-head-copy"><strong>{(clinic.name || 'Klinik Pratama').replace(/\s*Kementerian Pariwisata.*$/i, '') || 'Klinik Pratama'}</strong><strong>{clinicBrand.ministry.toUpperCase()}</strong>
            <p className="clinical-license">Izin Operasional Klinik Nomor: 6/B.6.1/31.09.86/e/2023</p>
            <p>{defaultAddress ? 'Jl. Medan Merdeka Barat No. 17' : clinic.address}</p>
            <p>{defaultAddress ? 'Jakarta 10110 ' : ''}Telp. {clinic.phone && clinic.phone !== '021 3838142' ? clinic.phone : '3838142'}</p>
        </div>
        <img className="clinical-logo" src="/logo_wonderful.png" alt="Wonderful Indonesia" />
    </header>;
}
