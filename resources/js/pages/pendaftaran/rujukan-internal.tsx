import { InternalReferralTable, type InternalReferralPagination } from '@/components/kunjungan/internal-referral-table';
import { StatCard } from '@/components/dashboard/stat-card';
import { SummaryStatGrid } from '@/components/dashboard/summary-stat-grid';
import { FilterPanel, FilterSubmit } from '@/components/ui/filter-panel';
import { DatePicker } from '@/components/ui/date-picker';
import { Field } from '@/components/ui/field';
import { Select } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Head, router } from '@inertiajs/react';
import { ArrowRightLeft, CheckCircle2, ClipboardList, Clock3 } from 'lucide-react';
import { useState } from 'react';

export default function RujukanInternal({
    search: appliedSearch,
    referrals,
    date: appliedDate = '',
    destinationClinicId = '',
    status: appliedStatus = '',
    clinics = [],
    stats,
}: {
    search: string;
    referrals: InternalReferralPagination;
    date?: string;
    destinationClinicId?: string;
    status?: string;
    clinics?: { id: number; nama: string }[];
    stats?: { total: number; waiting: number; registered: number; completed: number };
}) {
    const [search, setSearch] = useState(appliedSearch);
    const [date, setDate] = useState(appliedDate);
    const [clinic, setClinic] = useState(destinationClinicId);
    const [status, setStatus] = useState(appliedStatus);

    return (
        <>
            <Head title="Rujukan internal" />
            <div className="space-y-6">
                <div>
                    <p className="text-sm font-medium text-neutral-700">Pendaftaran</p>
                    <h2 className="mt-1 text-2xl font-semibold tracking-tight text-neutral-950">Rujukan internal</h2>
                    <p className="mt-1 text-sm text-neutral-500">Kelola rujukan antarpoliklinik yang diterbitkan dokter.</p>
                </div>
                <SummaryStatGrid ariaLabel="Ringkasan rujukan internal" columns={4}>
                    <StatCard label="Total rujukan" value={stats?.total ?? referrals.total} description="Seluruh rujukan antarpoli" icon={<ArrowRightLeft className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Menunggu daftar" value={stats?.waiting ?? referrals.data.filter((r) => r.status === 'menunggu').length} description="Perlu didaftarkan ke poli tujuan" icon={<Clock3 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Terdaftar di tujuan" value={stats?.registered ?? referrals.data.filter((r) => r.status === 'terdaftar').length} description="Sedang dalam layanan poli tujuan" icon={<ClipboardList className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                    <StatCard label="Selesai" value={stats?.completed ?? referrals.data.filter((r) => r.status === 'selesai').length} description="Pelayanan rujukan selesai" icon={<CheckCircle2 className="size-5" />} iconClassName="bg-neutral-100 text-neutral-700" />
                </SummaryStatGrid>

                <FilterPanel>
                    <form className="grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-4" onSubmit={(event) => {
                        event.preventDefault();
                        router.get('/pendaftaran/rujukan-internal', { search: search.trim(), tanggal: date, ke_poli_id: clinic, status }, { preserveScroll: true, replace: true });
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
                        <Field htmlFor="referral-status" label="Status rujukan">
                            <Select id="referral-status" value={status} onChange={(event) => setStatus(event.target.value)}>
                                <option value="">Semua status</option>
                                <option value="menunggu">Menunggu pendaftaran</option>
                                <option value="terdaftar">Terdaftar di poli tujuan</option>
                                <option value="dilayani">Sedang dilayani</option>
                                <option value="selesai">Selesai</option>
                                <option value="batal">Batal</option>
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
