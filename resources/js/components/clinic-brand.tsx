export const clinicBrand = {
    name: 'Klinik Pratama Internal',
    ministry: 'Kementerian Pariwisata RI',
    logo: '/logo_kemenpar.webp',
};

export function ClinicBrand({ iconOnly = false, inverse = false }: { iconOnly?: boolean; inverse?: boolean }) {
    return (
        <span className="flex min-w-0 items-center gap-3">
            <img alt="" className="size-10 shrink-0 object-contain" height={40} src={clinicBrand.logo} width={40} />
            {!iconOnly && (
                <span className="min-w-0">
                    <span className={`block text-sm leading-tight font-bold tracking-tight ${inverse ? 'text-on-inverse' : 'text-neutral-950'}`}>{clinicBrand.name}</span>
                    <span className={`block text-xs leading-tight ${inverse ? 'text-on-inverse/70' : 'text-neutral-600'}`}>{clinicBrand.ministry}</span>
                </span>
            )}
        </span>
    );
}
