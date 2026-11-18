import { InternalReferralTable, type InternalReferralPagination } from '@/components/kunjungan/internal-referral-table';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { Input } from '@/components/ui/input';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function RujukanInternal({ search: appliedSearch, referrals }: { search: string; referrals: InternalReferralPagination }) {
    const [search, setSearch] = useState(appliedSearch);

    return (
        <>
            <Head title="Rujukan internal" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pendaftaran</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Rujukan internal</h2>
                    <p className="mt-1 text-sm text-neutral-500">Kelola rujukan antarpoliklinik yang diterbitkan dokter.</p>
                </div>

                <FilterPanel>
                    <form className="flex flex-col gap-3 sm:flex-row" onSubmit={(event) => {
                        event.preventDefault();
                        router.get('/pendaftaran/rujukan-internal', { search: search.trim() }, { preserveScroll: true, replace: true });
                    }}>
                        <Input aria-label="Cari rujukan internal" placeholder="Cari nama, No. RM, nomor rujukan, atau nomor kunjungan" maxLength={100} value={search} onChange={(event) => setSearch(event.target.value)} />
                        <FilterSubmit />
                    </form>
                </FilterPanel>
                <InternalReferralTable referrals={referrals} canManage registration />
            </div>
        </>
    );
}
