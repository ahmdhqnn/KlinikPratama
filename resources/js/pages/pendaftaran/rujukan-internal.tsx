import { InternalReferralTable, type InternalReferralPagination } from '@/components/kunjungan/internal-referral-table';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { DatePicker } from '@/components/ui/date-picker';
import { Field } from '@/components/ui/field';
import { Select } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function RujukanInternal({ search: appliedSearch, referrals, date: appliedDate = '', destinationClinicId = '', clinics = [] }: {
    search: string;
    referrals: InternalReferralPagination;
    date?: string;
    destinationClinicId?: string;
    clinics?: { id: number; nama: string }[];
}) {
    const [search, setSearch] = useState(appliedSearch);
    const [date, setDate] = useState(appliedDate);
    const [clinic, setClinic] = useState(destinationClinicId);

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
                    <form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" onSubmit={(event) => {
                        event.preventDefault();
                        router.get('/pendaftaran/rujukan-internal', { search: search.trim(), tanggal: date, ke_poli_id: clinic }, { preserveScroll: true, replace: true });
                    }}>
                        <Field htmlFor="referral-search" label="Cari rujukan internal">
                            <Input id="referral-search" placeholder="Nama, No. RM, No. rujukan, atau No. kunjungan" maxLength={100} value={search} onChange={(event) => setSearch(event.target.value)} />
                        </Field>
                        <Field htmlFor="referral-date" label="Tanggal rujukan">
                            <DatePicker id="referral-date" value={date} onChange={(event) => setDate(event.target.value)} />
                        </Field>
                        <Field htmlFor="referral-clinic" label="Poli tujuan">
                            <Select id="referral-clinic" value={clinic} onChange={(event) => setClinic(event.target.value)}>
                                <option value="">Semua poli</option>{clinics.map(({ id, nama }) => <option key={id} value={id}>{nama}</option>)}
                            </Select>
                        </Field>
                        <FilterSubmit />
                    </form>
                </FilterPanel>
                <InternalReferralTable referrals={referrals} canManage registration />
            </div>
        </>
    );
}
