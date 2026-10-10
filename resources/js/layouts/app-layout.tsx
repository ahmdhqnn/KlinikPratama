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
    FileText,
    HeartPulse,
    LayoutDashboard,
    LogOut,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    Pill,
    Search,
    Settings,
    ShieldCheck,
    ShoppingBag,
    Stethoscope,
    UserRound,
    UserRoundPlus,
    UsersRound,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Breadcrumb, type BreadcrumbItem } from '@/components/ui/breadcrumb';
import { Drawer, DrawerContent, DrawerTitle } from '@/components/ui/drawer';
import { Empty } from '@/components/ui/empty';
import { Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import type { PageProps } from '@inertiajs/core';

interface AppUser {
    id: number;
    name: string;
    email: string;
    role: string;
    roleLabel: string;
    photoUrl: string | null;
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
                { label: 'Persuratan', href: '/persuratan', icon: FileText },
                { label: 'Jadwal Praktik', href: '/pendaftaran/jadwal-praktik', icon: CalendarDays },
                { label: 'Kepesertaan & hak layanan', href: '/kepesertaan', icon: ShieldCheck },
                { label: 'Terminologi klinis', href: '/master/terminologi-klinis', icon: ClipboardList },
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
                { label: 'Alat kesehatan', href: '/master/alkes', icon: HeartPulse },
                { label: 'Tenaga kesehatan', href: '/master/nakes', icon: UsersRound },
            ],
        },
        {
            label: 'Pengelolaan',
            items: [
                { label: 'Persediaan & batch', href: '/stok/persediaan', icon: Boxes },
                { label: 'Pengadaan', href: '/stok/purchase-order', icon: ShoppingBag },
                { label: 'Pengaturan', href: '/setting', icon: Settings },
                { label: 'Pengguna', href: '/users', icon: UsersRound },
            ],
        },
        {
            label: 'Laporan',
            items: [
                { label: 'Kunjungan', href: '/laporan/kunjungan', icon: ClipboardList },
                { label: 'Utilisasi & anggaran', href: '/laporan/utilisasi', icon: ChartNoAxesCombined },
                { label: 'Stok', href: '/laporan/stok', icon: Boxes },
                { label: 'Laboratorium', href: '/laporan/laboratorium', icon: Activity },
            ],
        },
    ],
    pendaftaran: [
        {
            label: 'Pendaftaran',
            items: [
                { label: 'Dashboard', href: '/pendaftaran', icon: LayoutDashboard },
                { label: 'Kepesertaan & hak layanan', href: '/kepesertaan', icon: ShieldCheck },
                { label: 'Pasien Baru', href: '/pendaftaran/pendaftaran-baru', icon: UserRoundPlus },
                { label: 'Pasien Lama', href: '/pendaftaran/pendaftaran-lama', icon: UserRound },
                { label: 'Database Pasien', href: '/pendaftaran/database-pasien', icon: UsersRound },
                { label: 'Kunjungan Per Poli', href: '/pendaftaran/kunjungan-per-poli', icon: BedDouble },
                { label: 'Laporan Kunjungan', href: '/pendaftaran/laporan-kunjungan', icon: BarChart3 },
                { label: 'Top Diagnosis', href: '/pendaftaran/laporan-top-diagnosa', icon: ChartNoAxesCombined },
                { label: 'Jadwal Praktik', href: '/pendaftaran/jadwal-praktik', icon: CalendarDays },
            ],
        },
    ],
    perawat: [
        {
            label: 'Pelayanan',
            items: [
                { label: 'Dashboard', href: '/perawat/dashboard', icon: LayoutDashboard },
                { label: 'Pasien', href: '/pelayanan/pasien', icon: UsersRound },
                { label: 'Skrining', href: '/pelayanan/screening', icon: HeartPulse },
            ],
        },
        {
            label: 'Pemantauan',
            items: [
                { label: 'Kunjungan Per Poli', href: '/pendaftaran/kunjungan-per-poli', icon: Activity },
                { label: 'Top Diagnosis', href: '/pendaftaran/laporan-top-diagnosa', icon: ChartNoAxesCombined },
                { label: 'Jadwal Praktik', href: '/pendaftaran/jadwal-praktik', icon: CalendarDays },
            ],
        },
    ],
    dokter: [
        {
            label: 'Praktik Dokter',
            items: [
                { label: 'Dashboard', href: '/dokter', icon: LayoutDashboard },
                { label: 'Kunjungan', href: '/dokter/kunjungan', icon: ClipboardList },
                { label: 'Pasien', href: '/dokter/pasien', icon: UsersRound },
                { label: 'Stok Obat', href: '/dokter/stok-obat', icon: Pill },
                { label: 'Top Diagnosis', href: '/dokter/laporan-top-diagnosa', icon: ChartNoAxesCombined },
                { label: 'Pemeriksaan', href: '/pelayanan/pemeriksaan', icon: Stethoscope },
                { label: 'Persuratan', href: '/persuratan', icon: FileText },
            ],
        },
    ],
    farmasi: [
        { label: 'Farmasi', items: [{ label: 'Dashboard', href: '/farmasi/dashboard', icon: LayoutDashboard }, { label: 'Antrian Farmasi', href: '/pelayanan/farmasi', icon: Pill }, { label: 'Persediaan & batch', href: '/stok/persediaan', icon: Boxes }, { label: 'Pengadaan', href: '/stok/purchase-order', icon: ShoppingBag }] },
    ],
    manajemen: [
        { label: 'Manajemen', items: [{ label: 'Dashboard', href: '/', icon: LayoutDashboard }, { label: 'Utilisasi & anggaran', href: '/laporan/utilisasi', icon: ChartNoAxesCombined }] },
    ],
};

function getPageTitle(component: string): string {
    const titles: Record<string, string> = {
        'dashboard/index': 'Dashboard',
        'kepesertaan/index': 'Kepesertaan & Hak Layanan',
        'laporan/utilisasi': 'Utilisasi & Anggaran Internal',
        'stok/persediaan/index': 'Persediaan Obat & BHP',
        'stok/persediaan/show': 'Batch & Kartu Stok',
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
        'persuratan/index': 'Persuratan',
        'persuratan/cetak': 'Cetak Dokumen',
        'pelayanan/farmasi/index': 'Antrean Farmasi',
        'pelayanan/farmasi/show': 'Dispensing Obat',
        'farmasi/dashboard': 'Dashboard Farmasi',
        'pelayanan/kasir/index': 'Antrean Kasir',
        'pelayanan/kasir/show': 'Pembayaran Tagihan',
        'pelayanan/kasir/kuitansi': 'Kuitansi Pembayaran',
        'laporan/kunjungan': 'Laporan Kunjungan',
        'laporan/pendapatan': 'Laporan Pendapatan',
        'laporan/stok': 'Laporan Stok',
        'laporan/laboratorium': 'Laporan Laboratorium',
        'users/index': 'Manajemen Pengguna',
        'profile/show': 'Profil Saya',
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

const breadcrumbLabels: Record<string, string> = {
    dokter: 'Dokter',
    laporan: 'Laporan',
    master: 'Data master',
    pendaftaran: 'Pendaftaran',
    pelayanan: 'Pelayanan',
    stok: 'Stok & pengadaan',
    setting: 'Pengaturan',
    users: 'Pengguna',
    profile: 'Profil Saya',
};

function getBreadcrumbItems(pathname: string, title: string, validHrefs: Set<string>): BreadcrumbItem[] {
    const segments = pathname.split('/').filter(Boolean);

    if (segments.length === 0) {
        return [{ label: title }];
    }

    return segments.map((segment, index) => {
        const current = index === segments.length - 1;

        return {
            label: current ? title : breadcrumbLabels[segment] ?? segment.replaceAll('-', ' ').replace(/\b\w/g, (character) => character.toUpperCase()),
            href: current ? undefined : `/${segments.slice(0, index + 1).join('/')}`,
        };
    }).map((item) => item.href && !validHrefs.has(item.href) ? { label: item.label } : item);
}

function SidebarContents({
    groups,
    pathname,
    user,
    onNavigate,
    headerClassName,
    isCollapsed = false,
}: {
    groups: NavigationGroup[];
    pathname: string;
    user: AppUser | null;
    onNavigate: () => void;
    headerClassName?: string;
    isCollapsed?: boolean;
}) {
    return (
        <>
            <SidebarHeader className={`${isCollapsed ? 'justify-center px-2' : ''} ${headerClassName ?? ''}`}>
                <Link aria-label="Klinik Pratama — beranda" className={`flex min-w-0 items-center gap-3 ${isCollapsed ? 'justify-center' : ''}`} href="/" onClick={onNavigate}>
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-900 text-neutral-50 shadow-sm shadow-inverse/20">
                        <BriefcaseMedical aria-hidden="true" className="size-5" />
                    </span>
                    {!isCollapsed && <span className="min-w-0">
                        <span className="block truncate text-sm font-bold tracking-tight text-neutral-950">Klinik Pratama</span>
                        <span className="block truncate text-xs text-neutral-500">Rekam Medis Elektronik</span>
                    </span>}
                </Link>
            </SidebarHeader>

            <SidebarContent aria-label="Navigasi utama" className={isCollapsed ? 'space-y-4 px-2 py-4' : undefined}>
                {groups.map((group) => (
                    <SidebarGroup key={group.label}>
                        <SidebarGroupLabel className={isCollapsed ? 'sr-only' : undefined}>{group.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {group.items.map(({ href, icon: Icon, label }) => {
                                const active = href === '/'
                                    ? pathname === '/'
                                    : pathname === href || pathname.startsWith(`${href}/`);

                                return (
                                    <SidebarMenuItem key={`${href}-${label}`}>
                                        <SidebarMenuButton asChild className={isCollapsed ? 'justify-center px-2' : undefined} isActive={active}>
                                            <Link aria-label={label} href={href} onClick={onNavigate} title={isCollapsed ? label : undefined}>
                                                <Icon aria-hidden="true" className="size-[18px] shrink-0" />
                                                <span className={isCollapsed ? 'sr-only' : undefined}>{label}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </SidebarGroup>
                ))}
            </SidebarContent>

            <SidebarFooter>
                <AccountMenu isCollapsed={isCollapsed} isSidebar user={user} />
            </SidebarFooter>
        </>
    );
}

function AccountMenu({ user, isSidebar = false, isCollapsed = false }: { user: AppUser | null; isSidebar?: boolean; isCollapsed?: boolean }) {
    const name = user?.name ?? 'Pengguna';

    return (
        <DropdownMenu.Root>
            <DropdownMenu.Trigger asChild>
                {isSidebar ? (
                    <button aria-label={`Menu akun ${name}`} className={`flex w-full min-w-0 items-center gap-3 rounded-xl bg-neutral-50 p-3 text-left outline-none transition hover:bg-neutral-100 focus-visible:ring-2 focus-visible:ring-neutral-400 ${isCollapsed ? 'flex-col px-1.5' : ''}`} type="button">
                        <AccountAvatar user={user} />
                        {!isCollapsed && <span className="min-w-0 flex-1">
                            <span className="block truncate text-sm font-semibold text-neutral-900">{name}</span>
                            <span className="block truncate text-xs text-neutral-500">{user?.roleLabel ?? ''}</span>
                        </span>}
                    </button>
                ) : (
                    <Button aria-label={`Menu akun ${name}`} className="size-10 rounded-full p-0" size="icon" variant="ghost">
                        <AccountAvatar user={user} />
                    </Button>
                )}
            </DropdownMenu.Trigger>
            <DropdownMenu.Portal>
                <DropdownMenu.Content align="end" className="z-50 min-w-56 rounded-xl border border-neutral-200 bg-surface p-1.5 shadow-xl shadow-inverse/10" side={isSidebar ? 'right' : 'bottom'} sideOffset={8}>
                    <div className="px-3 py-2">
                        <p className="truncate text-sm font-semibold text-neutral-900">{name}</p>
                        <p className="truncate text-xs text-neutral-500">{user?.email ?? ''}</p>
                        <p className="truncate text-xs text-neutral-500">{user?.roleLabel ?? ''}</p>
                    </div>
                    <DropdownMenu.Separator className="my-1 h-px bg-neutral-200" />
                    <DropdownMenu.Item asChild className="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm text-neutral-700 outline-none transition-colors hover:bg-neutral-100 focus:bg-neutral-100">
                        <Link href="/profile"><UserRound aria-hidden="true" className="size-4" />Profil Saya</Link>
                    </DropdownMenu.Item>
                    <DropdownMenu.Item className="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm text-neutral-700 outline-none transition-colors hover:bg-neutral-100 focus:bg-neutral-100" onSelect={() => router.post('/logout')}>
                        <LogOut aria-hidden="true" className="size-4" />Keluar dari aplikasi
                    </DropdownMenu.Item>
                </DropdownMenu.Content>
            </DropdownMenu.Portal>
        </DropdownMenu.Root>
    );
}

function AccountAvatar({ user }: { user: AppUser | null }) {
    return <Avatar aria-label={user?.name ?? 'Pengguna'} className="size-9 border border-neutral-200 bg-neutral-100 text-neutral-800">
        {user?.photoUrl && <AvatarImage alt="" src={user.photoUrl} />}
        <AvatarFallback>{user?.name.slice(0, 1).toLocaleUpperCase() ?? 'U'}</AvatarFallback>
    </Avatar>;
}

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, flash } = usePage<SharedPageProps>().props;
    const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchOpen, setSearchOpen] = useState(false);
    const user = auth.user;
    const groups: NavigationGroup[] = [
        ...(navigationByRole[user?.role ?? ''] ?? []),
        { label: 'Akun', items: [{ label: 'Profil Saya', href: '/profile', icon: UserRound }] },
    ];
    const pathname = window.location.pathname;
    const pageTitle = getPageTitle(usePage().component);
    const validHrefs = new Set([...Object.values(navigationByRole).flatMap((roleGroups) => roleGroups.flatMap((group) => group.items.map((item) => item.href))), '/profile']);
    const breadcrumbs = getBreadcrumbItems(pathname, pageTitle, validHrefs);
    const searchResults = groups.flatMap((group) => group.items
        .filter((item) => `${item.label} ${group.label}`.toLocaleLowerCase().includes(searchQuery.trim().toLocaleLowerCase()))
        .map((item) => ({ ...item, group: group.label })))
        .slice(0, 6);

    useEffect(() => {
        const handleSearchShortcut = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLocaleLowerCase() === 'k') {
                event.preventDefault();
                setSearchOpen(true);
                window.requestAnimationFrame(() => document.getElementById('global-navigation-search')?.focus());
            }
        };

        window.addEventListener('keydown', handleSearchShortcut);

        return () => window.removeEventListener('keydown', handleSearchShortcut);
    }, []);

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.error, flash.success]);

    return (
        <div className="min-h-screen bg-canvas text-neutral-900">
            <Sidebar className={`fixed inset-y-4 left-4 z-40 hidden h-[calc(100vh-2rem)] overflow-hidden rounded-2xl border border-neutral-200 shadow-lg shadow-inverse/[0.06] print:hidden transition-[width] duration-200 lg:flex ${sidebarCollapsed ? 'w-[4.5rem]' : 'w-[17rem]'}`}>
                <SidebarContents groups={groups} isCollapsed={sidebarCollapsed} onNavigate={() => setMobileNavigationOpen(false)} pathname={pathname} user={user} />
            </Sidebar>

            <Drawer onOpenChange={setMobileNavigationOpen} open={mobileNavigationOpen}>
                <DrawerContent className="p-0 lg:hidden" side="left">
                    <DrawerTitle className="sr-only">Navigasi utama</DrawerTitle>
                    <Sidebar className="h-full w-full">
                        <SidebarContents groups={groups} headerClassName="pr-14" onNavigate={() => setMobileNavigationOpen(false)} pathname={pathname} user={user} />
                    </Sidebar>
                </DrawerContent>
            </Drawer>

            <div className={`min-h-screen transition-[padding] duration-200 ${sidebarCollapsed ? 'lg:pl-[6.5rem]' : 'lg:pl-[19rem]'}`}>
                <header className="sticky top-0 z-30 flex h-16 items-center gap-2 bg-canvas px-3 print:hidden sm:gap-3 sm:px-6 lg:top-4 lg:mt-4 lg:h-[4.5rem] lg:px-8">
                    <Button
                        aria-label="Buka navigasi"
                        className="lg:hidden"
                        onClick={() => setMobileNavigationOpen(true)}
                        size="icon"
                        variant="ghost"
                    >
                        <Menu className="size-5" />
                    </Button>
                    <Button
                        aria-expanded={!sidebarCollapsed}
                        aria-label={sidebarCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}
                        className="hidden lg:inline-flex"
                        onClick={() => setSidebarCollapsed((collapsed) => !collapsed)}
                        size="icon"
                        title={sidebarCollapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}
                        variant="ghost"
                    >
                        {sidebarCollapsed ? <PanelLeftOpen aria-hidden="true" className="size-4" /> : <PanelLeftClose aria-hidden="true" className="size-4" />}
                    </Button>
                    <span aria-hidden="true" className="h-6 w-px shrink-0 bg-neutral-200" />
                    <div className="min-w-0 flex-1">
                        <h1 className="sr-only">{pageTitle}</h1>
                        <Breadcrumb items={breadcrumbs} />
                    </div>
                    <div className="relative flex min-w-0 items-center">
                        <Search aria-hidden="true" className="pointer-events-none absolute left-3 size-4 text-neutral-400" />
                        <input
                            aria-label="Cari menu"
                            aria-controls="global-search-results"
                            aria-expanded={searchOpen && searchQuery.trim().length > 0}
                            autoComplete="off"
                            className="h-10 w-32 rounded-lg border border-neutral-200 bg-surface pl-9 pr-3 text-sm text-neutral-900 shadow-sm outline-none transition placeholder:text-neutral-400 focus-visible:border-neutral-400 focus-visible:ring-4 focus-visible:ring-neutral-500/10 sm:w-56 sm:pr-12 lg:w-64"
                            id="global-navigation-search"
                            onBlur={() => setSearchOpen(false)}
                            onChange={(event) => {
                                setSearchQuery(event.target.value);
                                setSearchOpen(true);
                            }}
                            onFocus={() => setSearchOpen(true)}
                            onKeyDown={(event) => {
                                if (event.key === 'Escape') {
                                    setSearchOpen(false);
                                    event.currentTarget.blur();
                                }

                                if (event.key === 'Enter' && searchResults[0]) {
                                    router.visit(searchResults[0].href);
                                    setSearchOpen(false);
                                    setSearchQuery('');
                                }
                            }}
                            placeholder="Cari menu…"
                            role="combobox"
                            value={searchQuery}
                        />
                        <kbd aria-hidden="true" className="pointer-events-none absolute right-2 hidden rounded border border-neutral-200 bg-neutral-50 px-1.5 py-0.5 text-[10px] font-medium text-neutral-500 lg:block">Ctrl K</kbd>
                        {searchOpen && searchQuery.trim().length > 0 && (
                            <ul aria-label={searchResults.length > 0 ? 'Hasil pencarian menu' : 'Status pencarian menu'} className="absolute right-0 top-full z-50 mt-2 max-h-80 w-[min(20rem,calc(100vw-2rem))] overflow-y-auto rounded-xl border border-neutral-200 bg-surface p-1.5 shadow-xl shadow-inverse/10" id="global-search-results" role={searchResults.length > 0 ? 'listbox' : 'list'}>
                                {searchResults.length > 0 ? searchResults.map((result) => (
                                    <li key={result.href} role="option" aria-selected="false">
                                        <Link
                                            className="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-neutral-700 transition-colors hover:bg-neutral-100 hover:text-neutral-950 focus-visible:bg-neutral-100 focus-visible:outline-none"
                                            href={result.href}
                                            onMouseDown={(event) => event.preventDefault()}
                                            onClick={() => {
                                                setSearchOpen(false);
                                                setSearchQuery('');
                                            }}
                                        >
                                            <result.icon aria-hidden="true" className="size-4 shrink-0 text-neutral-500" />
                                            <span className="min-w-0 flex-1 truncate">{result.label}</span>
                                            <span className="shrink-0 text-xs text-neutral-400">{result.group}</span>
                                        </Link>
                                    </li>
                                )) : <li><Empty className="items-start gap-1 px-3 py-3 text-left" description="Coba kata kunci lain." eyebrow={null} icon={null} size="compact" title="Menu tidak ditemukan" /></li>}
                            </ul>
                        )}
                    </div>
                    <ThemeToggle className="shrink-0" />
                    <AccountMenu user={user} />
                </header>

                <main className="mx-auto w-full max-w-[1600px] p-4 print:max-w-none print:p-0 sm:p-6 lg:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
