import { Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    BedDouble,
    Boxes,
    BriefcaseMedical,
    CalendarDays,
    ChartNoAxesCombined,
    ClipboardList,
    CreditCard,
    HeartPulse,
    LayoutDashboard,
    LogOut,
    Menu,
    Pill,
    Settings,
    ShieldCheck,
    ShoppingBag,
    Stethoscope,
    UserRound,
    UserRoundPlus,
    UsersRound,
    X,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import type { PageProps } from '@inertiajs/core';

interface AppUser {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface SharedPageProps extends PageProps {
    auth: { user: AppUser | null };
    flash: { success?: string | null; error?: string | null };
}

interface NavigationItem {
    label: string;
    href: string;
    icon: typeof LayoutDashboard;
}

interface NavigationGroup {
    label: string;
    items: NavigationItem[];
}

const navigationByRole: Record<string, NavigationGroup[]> = {
    admin: [
        { label: 'Ringkasan', items: [{ label: 'Dashboard', href: '/', icon: LayoutDashboard }] },
        {
            label: 'Operasional',
            items: [
                { label: 'Pasien', href: '/pelayanan/pasien', icon: UsersRound },
                { label: 'Kunjungan', href: '/pelayanan/kunjungan', icon: ClipboardList },
                { label: 'Antrian', href: '/pelayanan/antrian', icon: Activity },
                { label: 'Pemeriksaan', href: '/pelayanan/pemeriksaan', icon: Stethoscope },
                { label: 'Farmasi', href: '/pelayanan/farmasi', icon: Pill },
                { label: 'Kasir', href: '/pelayanan/kasir', icon: CreditCard },
            ],
        },
        {
            label: 'Data Master',
            items: [
                { label: 'Poliklinik', href: '/master/poliklinik', icon: Boxes },
                { label: 'Tindakan medis', href: '/master/tindakan', icon: HeartPulse },
                { label: 'Paket tindakan', href: '/master/paket-tindakan', icon: ClipboardList },
                { label: 'Laboratorium', href: '/master/laboratorium', icon: Activity },
                { label: 'Obat & BHP', href: '/master/obat', icon: Pill },
                { label: 'Depo obat', href: '/master/depo-obat', icon: Pill },
                { label: 'Biaya pendaftaran', href: '/master/biaya-pendaftaran', icon: CreditCard },
                { label: 'Biaya administrasi', href: '/master/biaya-admin', icon: CreditCard },
                { label: 'Alat kesehatan', href: '/master/alkes', icon: HeartPulse },
                { label: 'Tenaga kesehatan', href: '/master/nakes', icon: UsersRound },
                { label: 'Asuransi & penjamin', href: '/master/asuransi', icon: ShieldCheck },
            ],
        },
        {
            label: 'Pengelolaan',
            items: [
                { label: 'Stok & Pengadaan', href: '/stok/purchase-order', icon: ShoppingBag },
                { label: 'Penjualan langsung', href: '/stok/penjualan-langsung', icon: CreditCard },
                { label: 'Laporan', href: '/laporan/kunjungan', icon: ChartNoAxesCombined },
                { label: 'Pengaturan', href: '/setting', icon: Settings },
                { label: 'Pengguna', href: '/users', icon: UsersRound },
            ],
        },
    ],
    pendaftaran: [
        {
            label: 'Pendaftaran',
            items: [
                { label: 'Dashboard', href: '/pendaftaran', icon: LayoutDashboard },
                { label: 'Pasien Baru', href: '/pendaftaran/pendaftaran-baru', icon: UserRoundPlus },
                { label: 'Pasien Lama', href: '/pendaftaran/pendaftaran-lama', icon: UserRound },
                { label: 'Database Pasien', href: '/pendaftaran/database-pasien', icon: UsersRound },
                { label: 'Kunjungan Per Poli', href: '/pendaftaran/kunjungan-per-poli', icon: BedDouble },
                { label: 'Laporan Kunjungan', href: '/pendaftaran/laporan-kunjungan', icon: BarChart3 },
                { label: 'Jadwal Praktik', href: '/pendaftaran/jadwal-praktik', icon: CalendarDays },
            ],
        },
    ],
    perawat: [
        {
            label: 'Pelayanan',
            items: [
                { label: 'Dashboard', href: '/perawat/dashboard', icon: LayoutDashboard },
                { label: 'Daftar Kunjungan', href: '/pelayanan/screening', icon: ClipboardList },
                { label: 'Pasien', href: '/pelayanan/pasien', icon: UsersRound },
                { label: 'Skrining', href: '/pelayanan/screening', icon: HeartPulse },
            ],
        },
    ],
    dokter: [
        {
            label: 'Praktik Dokter',
            items: [
                { label: 'Dashboard', href: '/dokter', icon: LayoutDashboard },
                { label: 'Kunjungan', href: '/dokter/kunjungan', icon: ClipboardList },
                { label: 'Janji Kunjungan', href: '/dokter/janji-kunjungan', icon: CalendarDays },
                { label: 'Pasien', href: '/dokter/pasien', icon: UsersRound },
                { label: 'Stok Obat', href: '/dokter/stok-obat', icon: Pill },
                { label: 'Top Diagnosis', href: '/dokter/laporan-top-diagnosa', icon: ChartNoAxesCombined },
                { label: 'Pemeriksaan', href: '/pelayanan/pemeriksaan', icon: Stethoscope },
            ],
        },
    ],
    farmasi: [
        { label: 'Farmasi', items: [{ label: 'Antrian Farmasi', href: '/pelayanan/farmasi', icon: Pill }] },
    ],
    kasir: [
        { label: 'Kasir', items: [{ label: 'Antrian Kasir', href: '/pelayanan/kasir', icon: CreditCard }] },
    ],
};

function getPageTitle(component: string): string {
    const titles: Record<string, string> = {
        'dashboard/index': 'Dashboard',
        'pendaftaran/dashboard': 'Dashboard Pendaftaran',
        'pendaftaran/pendaftaran-baru': 'Pendaftaran Pasien Baru',
        'pendaftaran/pendaftaran-lama': 'Pendaftaran Pasien Lama',
        'pendaftaran/laporan-kunjungan': 'Laporan Kunjungan',
        'pendaftaran/database-pasien': 'Database Pasien',
        'pendaftaran/kunjungan-per-poli': 'Kunjungan Per Poli',
        'pendaftaran/laporan-top-diagnosa': 'Laporan Top Diagnosis',
        'pendaftaran/jadwal-praktik': 'Jadwal Praktik Dokter',
        'pendaftaran/edit-kunjungan': 'Edit Kunjungan',
        'pendaftaran/cetak-antrian': 'Cetak Antrean',
        'perawat/dashboard': 'Dashboard Perawat',
        'dokter/dashboard': 'Dashboard Dokter',
        'dokter/appointments': 'Kunjungan Dokter',
        'dokter/patients': 'Pasien Dokter',
        'dokter/stock': 'Stok Obat',
        'dokter/top-diagnoses': 'Top Diagnosis',
        'pelayanan/screening/index': 'Antrean Skrining',
        'pelayanan/pasien/index': 'Database Pasien',
        'pelayanan/pasien/form': 'Data Pasien',
        'pelayanan/pasien/show': 'Profil Pasien',
        'pelayanan/pasien/rekam-medis': 'Rekam Medis Pasien',
        'pelayanan/kunjungan/index': 'Daftar Kunjungan',
        'pelayanan/kunjungan/form': 'Data Kunjungan',
        'pelayanan/kunjungan/show': 'Detail Kunjungan',
        'pelayanan/kunjungan/antrian': 'Antrean Pasien',
        'pelayanan/pemeriksaan/index': 'Pemeriksaan Dokter',
        'pelayanan/pemeriksaan/show': 'Pemeriksaan Klinis',
        'pelayanan/farmasi/index': 'Antrean Farmasi',
        'pelayanan/farmasi/show': 'Dispensing Obat',
        'pelayanan/kasir/index': 'Antrean Kasir',
        'pelayanan/kasir/show': 'Pembayaran Tagihan',
        'pelayanan/kasir/kuitansi': 'Kuitansi Pembayaran',
        'laporan/kunjungan': 'Laporan Kunjungan',
        'laporan/pendapatan': 'Laporan Pendapatan',
        'laporan/stok': 'Laporan Stok',
        'laporan/laboratorium': 'Laporan Laboratorium',
        'users/index': 'Manajemen Pengguna',
        'setting/index': 'Pengaturan Klinik',
        'master/poliklinik/index': 'Poliklinik',
        'master/poliklinik/show': 'Ruang Poliklinik',
        'master/depo-obat/index': 'Depo Obat',
        'master/biaya-admin/index': 'Biaya Administrasi',
        'master/alkes/index': 'Alat Kesehatan',
        'stok/purchase-order/index': 'Pesanan Pembelian',
        'stok/purchase-order/create': 'Buat Pesanan Pembelian',
        'stok/purchase-order/show': 'Detail Pesanan Pembelian',
        'stok/purchase-order/terima': 'Penerimaan Barang',
        'stok/penjualan-langsung/index': 'Penjualan Langsung',
        'stok/penjualan-langsung/create': 'Transaksi Penjualan',
        'stok/penjualan-langsung/nota': 'Nota Penjualan',
        'master/biaya-pendaftaran/index': 'Biaya Pendaftaran',
        'master/tindakan/index': 'Tindakan Medis',
        'master/tindakan/show': 'BHP Tindakan',
        'master/paket-tindakan/index': 'Paket Tindakan',
        'master/paket-tindakan/show': 'Komposisi Paket',
        'master/laboratorium/index': 'Laboratorium',
        'master/laboratorium/show': 'Pengaturan Laboratorium',
        'master/nakes/index': 'Tenaga Kesehatan & Karyawan',
        'master/asuransi/index': 'Asuransi & Penjamin',
        'master/asuransi/show': 'Harga Khusus Penjamin',
        'master/obat/index': 'Master Obat & BHP',
        'master/obat/stok': 'Kartu Stok Obat',
    };

    return titles[component] ?? 'Klinik Pratama';
}

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage<SharedPageProps>().props;
    const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);
    const user = auth.user;
    const groups = navigationByRole[user?.role ?? ''] ?? navigationByRole.admin;
    const pathname = window.location.pathname;
    const pageTitle = getPageTitle(usePage().component);

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.error, flash.success]);

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            {mobileNavigationOpen && (
                <button
                    aria-label="Tutup navigasi"
                    className="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-[2px] lg:hidden"
                    onClick={() => setMobileNavigationOpen(false)}
                    type="button"
                />
            )}

            <aside
                className={`fixed inset-y-0 left-0 z-50 flex w-[17rem] flex-col border-r border-slate-200 bg-white transition-transform duration-200 print:hidden lg:translate-x-0 ${mobileNavigationOpen ? 'translate-x-0' : '-translate-x-full'}`}
            >
                <div className="flex h-[4.5rem] items-center justify-between border-b border-slate-100 px-5">
                    <Link className="flex min-w-0 items-center gap-3" href="/">
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm shadow-blue-600/20">
                            <BriefcaseMedical className="size-5" aria-hidden="true" />
                        </span>
                        <span className="min-w-0">
                            <span className="block truncate text-sm font-bold tracking-tight text-slate-950">Klinik Pratama</span>
                            <span className="block text-xs text-slate-500">Rekam Medis Elektronik</span>
                        </span>
                    </Link>
                    <Button
                        aria-label="Tutup menu"
                        className="lg:hidden"
                        onClick={() => setMobileNavigationOpen(false)}
                        size="icon"
                        variant="ghost"
                    >
                        <X className="size-4" />
                    </Button>
                </div>

                <nav aria-label="Navigasi utama" className="flex-1 space-y-7 overflow-y-auto px-3 py-5">
                    {groups.map((group) => (
                        <section key={group.label}>
                            <h2 className="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-400">
                                {group.label}
                            </h2>
                            <ul className="space-y-1">
                                {group.items.map(({ href, icon: Icon, label }) => {
                                    const active = href === '/'
                                        ? pathname === '/'
                                        : pathname === href || pathname.startsWith(`${href}/`);
                                    const className = `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${active ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'}`;

                                    return (
                                        <li key={`${href}-${label}`}>
                                            {href === '/' ? (
                                                <Link
                                                    className={className}
                                                    href={href}
                                                    onClick={() => setMobileNavigationOpen(false)}
                                                >
                                                    <Icon className="size-[18px] shrink-0" aria-hidden="true" />
                                                    <span>{label}</span>
                                                </Link>
                                            ) : (
                                                <a
                                                    className={className}
                                                    href={href}
                                                    onClick={() => setMobileNavigationOpen(false)}
                                                >
                                                    <Icon className="size-[18px] shrink-0" aria-hidden="true" />
                                                    <span>{label}</span>
                                                </a>
                                            )}
                                        </li>
                                    );
                                })}
                            </ul>
                        </section>
                    ))}
                </nav>

                <div className="border-t border-slate-100 p-3">
                    <div className="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700">
                            {user?.name.slice(0, 1).toLocaleUpperCase() ?? 'U'}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold text-slate-900">{user?.name ?? 'Pengguna'}</p>
                            <p className="truncate text-xs capitalize text-slate-500">{user?.role ?? ''}</p>
                        </div>
                        <Button
                            aria-label="Keluar dari aplikasi"
                            onClick={() => router.post('/logout')}
                            size="icon"
                            variant="ghost"
                        >
                            <LogOut className="size-4" />
                        </Button>
                    </div>
                </div>
            </aside>

            <div className="min-h-screen lg:pl-[17rem]">
                <header className="sticky top-0 z-30 flex h-[4.5rem] items-center gap-4 border-b border-slate-200 bg-white/90 px-4 backdrop-blur print:hidden sm:px-6 lg:px-8">
                    <Button
                        aria-label="Buka navigasi"
                        className="lg:hidden"
                        onClick={() => setMobileNavigationOpen(true)}
                        size="icon"
                        variant="ghost"
                    >
                        <Menu className="size-5" />
                    </Button>
                    <div className="min-w-0 flex-1">
                        <p className="text-xs font-medium text-slate-500">Sistem Informasi Klinik</p>
                        <h1 className="truncate text-lg font-semibold tracking-tight text-slate-950">{pageTitle}</h1>
                    </div>
                    <div className="hidden items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500 md:flex">
                        <CalendarDays className="size-4" aria-hidden="true" />
                        <span>{new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' }).format(new Date())}</span>
                    </div>
                </header>

                <main className="mx-auto w-full max-w-[1600px] p-4 print:max-w-none print:p-0 sm:p-6 lg:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
