import { clinicBrand } from '@/components/clinic-brand';

export interface ClinicProfile {
    name?: string;
    license?: string | null;
    facilityCode?: string | null;
    address?: string | null;
    phone?: string | null;
    email?: string | null;
    logoUrl?: string | null;
}

export function ClinicalDocumentHeader({ clinic = {} }: { clinic?: ClinicProfile }) {
    const defaultAddress = !clinic.address || clinic.address === 'Jl. Medan Merdeka Barat No. 17, Gambir, Jakarta Pusat 10110';
    const rawName = clinic.name && !/ekonomi\s+kreatif/i.test(clinic.name) ? clinic.name : 'Klinik Pratama';
    const headline = rawName.replace(/\s*Kementerian Pariwisata.*$/i, '').trim() || 'Klinik Pratama';

    return <header className="clinical-document-head">
        <img className="clinical-logo" src={clinic.logoUrl || clinicBrand.logo} alt={clinicBrand.ministry} />
        <div className="clinical-head-copy">
            <strong>{headline}</strong>
            <strong>{clinicBrand.ministry.toUpperCase()}</strong>
            <p className="clinical-license">Izin Operasional Klinik Nomor: {clinic.license || '6/B.6.1/31.09.86/e/2023'}</p>
            <p>{defaultAddress ? 'Gedung Sapta Pesona, Jl. Medan Merdeka Barat No. 17' : clinic.address}</p>
            <p>{defaultAddress ? 'Jakarta Pusat 10110 · ' : ''}Telp. {clinic.phone && clinic.phone !== '021 3838142' ? clinic.phone : '(021) 3838142'}</p>
        </div>
        <img className="clinical-logo" src="/logo_wonderful.png" alt="Wonderful Indonesia" />
    </header>;
}
