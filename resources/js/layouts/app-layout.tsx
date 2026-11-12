import { ClinicBrand, clinicBrand } from '@/components/clinic-brand';
import { Link, router, usePage } from '@inertiajs/react';
import {
    Activity,
    BarChart3,
    BedDouble,
    Boxes,
    CalendarDays,
    ChartNoAxesCombined,
    ClipboardList,
    ClipboardCheck,
    FilePlus2,
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
    Stethoscope,
    UserRound,
    UserRoundPlus,
    UsersRound,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import * as DropdownMenu from '@radix-ui/react-dropdown-menu';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Breadcrumb, type BreadcrumbItem } from '@/components/ui/breadcrumb';
import { Drawer, DrawerContent, DrawerTitle } from '@/components/ui/drawer';
import { Empty } from '@/components/ui/empty';
import { Sidebar, SidebarContent, SidebarFooter, SidebarGroup, SidebarGroupLabel, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import type { PageProps } from '@inertiajs/core';
import { activeNavigationHref, type NavigationTarget } from '@/lib/navigation';

interface AppUser {
    id: number;
    name: string;
    email: string;
    role: string;
    roleLabel: string;
    clinicType?: string | null;
    photoUrl: string | null;
}

interface SharedPageProps extends PageProps {
    modules?: Partial<Record<'laboratory' | 'medicine_depots' | 'medical_equipment' | 'medical_treatments', boolean>>;
    nurseQueues?: { assessment: number; treatments: number } | null;
    adminQueues?: { assessment: number; treatments: number; examinations: number; handovers: number } | null;
    doctorQueues?: { examinations: number } | null;
    pharmacyQueues?: { handovers: number } | null;
    auth: { user: AppUser | null };
    flash: { success?: string | null; error?: string | null };
}

interface NavigationItem extends NavigationTarget {
    label: string;
    href: string;
    icon: typeof LayoutDashboard;
    badge?: number;
    module?: 'laboratory' | 'medicine_depots' | 'medical_equipment' | 'medical_treatments';
}

interface NavigationGroup {
    label: string;
    items: NavigationItem[];
}

const navigationByRole: Record<string, NavigationGroup[]> = {
    admin: [
        { label: 'Ringkasan', items: [{ label: 'Dasbor', href: '/', icon: LayoutDashboard, exact: true }] },
        {
            label: 'Pendaftaran & kunjungan',
            items: [
                { label: 'Antrean pasien', href: '/pelayanan/antrian', icon: Activity },
                { label: 'Pendaftaran pasien', href: '/pendaftaran/pendaftaran-baru', activePaths: ['/pendaftaran/pendaftaran-lama'], icon: UserRoundPlus },
                { label: 'Kunjungan poli', href: '/pendaftaran/kunjungan-per-poli', activePaths: ['/pendaftaran/kunjungan'], icon: BedDouble },
                { label: 'Daftar kunjungan', href: '/pelayanan/kunjungan', icon: ClipboardList },
                { label: 'Data pasien', href: '/pelayanan/pasien', activePaths: ['/pendaftaran/database-pasien'], icon: UsersRound },
                { label: 'Kepesertaan', href: '/kepesertaan', icon: ShieldCheck },
                { label: 'Jadwal praktik', href: '/pendaftaran/jadwal-praktik', icon: CalendarDays },
            ],
        },
        {
            label: 'Pelayanan klinis',
            items: [
                { label: 'Pengkajian awal', href: '/pelayanan/screening', icon: HeartPulse },
                { label: 'Pemeriksaan dokter', href: '/pelayanan/pemeriksaan', icon: Stethoscope },
                { label: 'Tindakan perawat', href: '/pelayanan/tindakan-perawat', icon: ClipboardCheck },
                { label: 'Persuratan', href: '/persuratan', icon: FileText },
            ],
        },
        {
            label: 'Farmasi & persediaan',
            items: [
                { label: 'Penyerahan obat', href: '/pelayanan/farmasi', icon: Pill },
                { label: 'Obat langsung', href: '/farmasi/pemberian-obat', icon: FilePlus2 },
                { label: 'Resep luar', href: '/farmasi/resep-luar', icon: FileText },
                { label: 'Riwayat farmasi pasien', href: '/farmasi/pasien', icon: UsersRound },
                { label: 'Persediaan & batch', href: '/stok/persediaan', icon: Boxes },
            ],
        },
        {
            label: 'Laporan',
            items: [{
                label: 'Pusat laporan', href: '/admin/laporan', icon: BarChart3,
                activePaths: ['/laporan', '/farmasi/laporan', '/pendaftaran/laporan-kunjungan', '/pendaftaran/laporan-top-diagnosa'],
            }],
        },
        {
            label: 'Data master',
            items: [
                { label: 'Poliklinik', href: '/master/poliklinik', icon: Boxes },
                { label: 'Tenaga kesehatan & karyawan', href: '/master/nakes', icon: UsersRound },
                { label: 'Obat & BHP', href: '/master/obat', icon: Pill },
                { label: 'Depo obat', href: '/master/depo-obat', module: 'medicine_depots', icon: Pill },
                { label: 'Alat kesehatan', href: '/master/alkes', module: 'medical_equipment', icon: HeartPulse },
                { label: 'Tindakan medis', href: '/master/tindakan', module: 'medical_treatments', icon: HeartPulse },
                { label: 'Terminologi klinis', href: '/master/terminologi-klinis', icon: ClipboardList },
                { label: 'Laboratorium', href: '/master/laboratorium', module: 'laboratory', icon: Activity },
            ],
        },
        {
            label: 'Administrasi',
            items: [
                { label: 'Manajemen pengguna', href: '/users', icon: UsersRound },
                { label: 'Pengaturan klinik', href: '/setting', icon: Settings },
                { label: 'Audit bantuan layanan', href: '/admin/audit-bantuan', icon: ShieldCheck },
            ],
        },
    ],
    pendaftaran: [
        { label: 'Ringkasan', items: [{ label: 'Dasbor', href: '/pendaftaran', icon: LayoutDashboard, exact: true }] },
        {
            label: 'Operasional',
            items: [
                { label: 'Pendaftaran pasien', href: '/pendaftaran/pendaftaran-baru', activePaths: ['/pendaftaran/pendaftaran-lama'], icon: UserRoundPlus },
                { label: 'Kunjungan poli', href: '/pendaftaran/kunjungan-per-poli', activePaths: ['/pendaftaran/kunjungan'], icon: BedDouble },
                { label: 'Pasien', href: '/pendaftaran/database-pasien', activePaths: ['/pelayanan/pasien'], icon: UsersRound },
            ],
        },
        {
            label: 'Pengelolaan',
            items: [
                { label: 'Jadwal praktik', href: '/pendaftaran/jadwal-praktik', icon: CalendarDays },
                { label: 'Kepesertaan', href: '/kepesertaan', icon: ShieldCheck },
            ],
        },
        {
            label: 'Laporan',
            items: [
                { label: 'Rincian kunjungan pendaftaran', href: '/pendaftaran/laporan-kunjungan', icon: BarChart3 },
                { label: 'Diagnosis terbanyak', href: '/pendaftaran/laporan-top-diagnosa', icon: ChartNoAxesCombined },
            ],
        },
    ],
    perawat: [
        { label: 'Ringkasan', items: [{ label: 'Dasbor', href: '/perawat/dashboard', icon: LayoutDashboard, exact: true }] },
        {
            label: 'Operasional',
            items: [
                { label: 'Pengkajian awal', href: '/pelayanan/screening', activePaths: ['/pelayanan/screening/riwayat'], icon: HeartPulse },
                { label: 'Tindakan', href: '/pelayanan/tindakan-perawat', icon: ClipboardCheck },
                { label: 'Pasien', href: '/pelayanan/pasien', icon: UsersRound },
            ],
        },
        {
            label: 'Koordinasi layanan',
            items: [
                { label: 'Kunjungan poli', href: '/pendaftaran/kunjungan-per-poli', activePaths: ['/pelayanan/kunjungan'], icon: Activity },
                { label: 'Jadwal praktik', href: '/pendaftaran/jadwal-praktik', icon: CalendarDays },
            ],
        },
        { label: 'Laporan', items: [{ label: 'Laporan pengkajian awal', href: '/perawat/laporan-pengkajian', icon: ClipboardCheck }, { label: 'Laporan tindakan', href: '/perawat/laporan-tindakan', icon: ClipboardCheck }, { label: 'Diagnosis terbanyak', href: '/pendaftaran/laporan-top-diagnosa', icon: ChartNoAxesCombined }] },
    ],
    dokter: [
        { label: 'Ringkasan', items: [{ label: 'Dasbor', href: '/dokter', icon: LayoutDashboard, exact: true }] },
        {
            label: 'Operasional',
            items: [
                { label: 'Pemeriksaan', href: '/pelayanan/pemeriksaan', icon: Stethoscope },
                { label: 'Tindakan', href: '/dokter/tindakan', icon: ClipboardCheck },
                { label: 'Persuratan', href: '/persuratan', icon: FileText },
                { label: 'Pasien', href: '/dokter/pasien', activePaths: ['/pelayanan/pasien'], icon: UsersRound },
            ],
        },
        {
            label: 'Penunjang',
            items: [
                { label: 'Kunjungan', href: '/dokter/kunjungan', icon: ClipboardList },
                { label: 'Jadwal praktik', href: '/pendaftaran/jadwal-praktik', icon: CalendarDays },
                { label: 'Stok obat', href: '/dokter/stok-obat', icon: Pill },
            ],
        },
        { label: 'Laporan', items: [
            { label: 'Laporan pemeriksaan', href: '/dokter/laporan-pemeriksaan', icon: ClipboardCheck },
            { label: 'Laporan tindakan', href: '/dokter/laporan/tindakan', icon: ClipboardCheck },
            { label: 'Laporan obat', href: '/dokter/laporan/obat', icon: Pill },
            { label: 'Diagnosis terbanyak', href: '/dokter/laporan-top-diagnosa', icon: ChartNoAxesCombined },
        ] },
    ],
    farmasi: [
        { label: 'Ringkasan', items: [{ label: 'Dasbor', href: '/farmasi/dashboard', icon: LayoutDashboard, exact: true }] },
        { label: 'Operasional', items: [
            { label: 'Penyerahan obat', href: '/pelayanan/farmasi', icon: Pill },
            { label: 'Obat langsung', href: '/farmasi/pemberian-obat', icon: FilePlus2 },
            { label: 'Resep luar', href: '/farmasi/resep-luar', icon: FileText },
            { label: 'Riwayat farmasi pasien', href: '/farmasi/pasien', icon: UsersRound },
        ] },
        { label: 'Pengelolaan obat', items: [
            { label: 'Persediaan & batch', href: '/stok/persediaan', icon: Boxes },
            { label: 'Data pasien', href: '/pelayanan/pasien', icon: UsersRound },
        ] },
        { label: 'Laporan', items: [
            { label: 'Laporan penyerahan obat', href: '/farmasi/laporan/penyerahan-obat', icon: ClipboardCheck },
            { label: 'Laporan obat langsung', href: '/farmasi/laporan/pemberian-langsung', icon: FileText },
            { label: 'Laporan resep luar', href: '/farmasi/laporan/resep-luar', icon: ClipboardList },
        ] },
    ],
    manajemen: [
        { label: 'Ringkasan', items: [{ label: 'Dasbor', href: '/', icon: LayoutDashboard, exact: true }] },
        {
            label: 'Laporan',
            items: [
                { label: 'Laporan kunjungan', href: '/laporan/kunjungan', icon: ClipboardList },
                { label: 'Laporan stok', href: '/laporan/stok', icon: Boxes },
                { label: 'Laporan laboratorium', href: '/laporan/laboratorium', module: 'laboratory', icon: Activity },
            ],
        },
    ],
};

function getPageTitle(component: string): string {
    const titles: Record<string, string> = {
        'admin/audit-bantuan': 'Audit bantuan layanan',
        'admin/laporan': 'Pusat laporan',
        'auth/login': 'Masuk',
        'dashboard/index': 'Dasbor',
        'kepesertaan/index': 'Kepesertaan',
        'kepesertaan/show': 'Detail kepesertaan',
        'stok/persediaan/index': 'Persediaan & batch',
        'stok/persediaan/show': 'Batch & kartu stok',
        'pendaftaran/dashboard': 'Dasbor pendaftaran',
        'pendaftaran/pendaftaran-baru': 'Pendaftaran pasien',
        'pendaftaran/pendaftaran-lama': 'Pendaftaran pasien',
        'pendaftaran/laporan-kunjungan': 'Rincian kunjungan pendaftaran',
        'pendaftaran/database-pasien': 'Pasien',
        'pendaftaran/kunjungan-per-poli': 'Kunjungan poli',
        'pendaftaran/laporan-top-diagnosa': 'Laporan diagnosis terbanyak',
        'pendaftaran/jadwal-praktik': 'Jadwal praktik dokter',
        'pendaftaran/edit-kunjungan': 'Edit kunjungan',
        'pendaftaran/cetak-antrian': 'Cetak antrean',
        'perawat/dashboard': 'Dasbor perawat',
        'dokter/dashboard': 'Dasbor dokter',
        'dokter/appointments': 'Kunjungan dokter',
        'dokter/treatments': 'Tindakan dokter',
        'dokter/patients': 'Pasien dokter',
        'dokter/stock': 'Stok obat',
        'dokter/top-diagnoses': 'Diagnosis terbanyak',
        'dokter/reports': 'Laporan dokter',
        'pelayanan/screening/index': 'Pengkajian awal',
        'pelayanan/screening/show': 'Pengkajian awal',
        'pelayanan/screening/history': 'Pengkajian saya',
        'pelayanan/screening/print': 'Cetak pengkajian awal',
        'pelayanan/pasien/index': 'Data pasien',
        'pelayanan/pasien/form': 'Data pasien',
        'pelayanan/pasien/show': 'Profil pasien',
        'pelayanan/pasien/rekam-medis': 'Rekam medis pasien',
        'pelayanan/kunjungan/index': 'Daftar kunjungan',
        'pelayanan/kunjungan/form': 'Data kunjungan',
        'pelayanan/kunjungan/show': 'Detail kunjungan',
        'pelayanan/kunjungan/antrian': 'Antrean pasien',
        'pelayanan/pemeriksaan/index': 'Pemeriksaan dokter',
        'pelayanan/pemeriksaan/show': 'Pemeriksaan klinis',
        'persuratan/index': 'Persuratan',
        'persuratan/terbitkan': 'Terbitkan dokumen',
        'persuratan/cetak': 'Cetak dokumen',
        'pelayanan/tindakan/index': 'Tindakan perawat',
        'pelayanan/tindakan/show': 'Pelaksanaan tindakan',
        'pelayanan/tindakan/report': 'Laporan tindakan perawat',
        'pelayanan/farmasi/index': 'Penyerahan obat',
        'pelayanan/farmasi/show': 'Penyerahan obat',
        'farmasi/dashboard': 'Dasbor farmasi',
        'farmasi/resep-luar': 'Resep luar',
        'farmasi/pasien': 'Riwayat farmasi pasien',
        'farmasi/pasien-kunjungan': 'Detail layanan pasien',
        'farmasi/pemberian-obat/form': 'Pemberian obat tanpa pemeriksaan',
        'farmasi/pemberian-obat/index': 'Obat langsung',
        'farmasi/pemberian-obat/cetak': 'Bukti pemberian obat',
        'farmasi/laporan': 'Laporan obat langsung',
        'farmasi/laporan-penyerahan': 'Laporan penyerahan obat',
        'farmasi/laporan-resep-luar': 'Laporan resep luar',
        'laporan/kunjungan': 'Laporan kunjungan',
        'laporan/stok': 'Laporan stok',
        'laporan/laboratorium': 'Laporan laboratorium',
        'users/index': 'Manajemen pengguna',
        'profile/show': 'Profil saya',
        'setting/index': 'Pengaturan klinik',
        'master/terminologi/index': 'Terminologi klinis',
        'master/terminologi/show': 'Detail terminologi klinis',
        'master/poliklinik/index': 'Poliklinik',
        'master/poliklinik/show': 'Ruang poliklinik',
        'master/depo-obat/index': 'Depo obat',
        'master/alkes/index': 'Alat kesehatan',
        'master/tindakan/index': 'Tindakan medis',
        'master/tindakan/show': 'BHP tindakan',
        'master/laboratorium/index': 'Laboratorium',
        'master/laboratorium/show': 'Pengaturan laboratorium',
        'master/nakes/index': 'Tenaga kesehatan & karyawan',
        'master/obat/index': 'Obat & BHP',
        'master/obat/stok': 'Kartu stok obat',
    };

    return titles[component] ?? 'Klinik Pratama';
}

const breadcrumbLabels: Record<string, string> = {
    admin: 'Administrasi',
    screening: 'Pengkajian awal',
    pemeriksaan: 'Pemeriksaan dokter',
    'tindakan-perawat': 'Tindakan perawat',
    'database-pasien': 'Data pasien',
    'kunjungan-per-poli': 'Kunjungan poli',
    'jadwal-praktik': 'Jadwal praktik',
    'terminologi-klinis': 'Terminologi klinis',
    'pemberian-obat': 'Obat langsung',
    'resep-luar': 'Resep luar',
    'rekam-medis': 'Rekam medis',
    'depo-obat': 'Depo obat',
    nakes: 'Tenaga kesehatan',
    alkes: 'Alat kesehatan',
    dokter: 'Dokter',
    laporan: 'Laporan',
    master: 'Data master',
    pendaftaran: 'Pendaftaran',
    pelayanan: 'Pelayanan',
    stok: 'Persediaan',
    setting: 'Pengaturan',
    users: 'Pengguna',
    profile: 'Profil saya',
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
    const activeHref = activeNavigationHref(pathname, groups.flatMap((group) => group.items));

    return (
        <>
            <SidebarHeader className={`${isCollapsed ? 'justify-center px-2' : ''} ${headerClassName ?? ''}`}>
                <Link aria-label={`${clinicBrand.name} — beranda`} className={`flex min-w-0 items-center gap-3 ${isCollapsed ? 'justify-center' : ''}`} href="/" onClick={onNavigate}>
                    <ClinicBrand iconOnly={isCollapsed} />
                </Link>
            </SidebarHeader>

            <SidebarContent aria-label="Navigasi utama" className={isCollapsed ? 'space-y-4 px-2 py-4' : undefined}>
                {groups.map((group) => (
                    <SidebarGroup key={group.label}>
                        <SidebarGroupLabel className={isCollapsed ? 'sr-only' : undefined}>{group.label}</SidebarGroupLabel>
                        <SidebarMenu>
                            {group.items.map(({ href, icon: Icon, label, badge }) => {
                                const active = href === activeHref;

                                return (
                                    <SidebarMenuItem key={`${href}-${label}`}>
                                        <SidebarMenuButton asChild className={isCollapsed ? 'justify-center px-2' : undefined} isActive={active}>
                                            <Link aria-current={active ? 'page' : undefined} aria-label={badge ? `${label}, ${badge} memerlukan layanan` : label} className="relative" href={href} onClick={onNavigate} title={isCollapsed ? label : undefined}>
                                                <Icon aria-hidden="true" className="size-[18px] shrink-0" />
                                                {isCollapsed ? <span className="sr-only">{label}</span> : <span className="flex min-w-0 items-center gap-1.5"><span className="truncate">{label}</span>{badge ? <Badge aria-hidden="true" variant="notification">{badge > 99 ? '99+' : badge}</Badge> : null}</span>}
                                                {isCollapsed && badge ? <Badge aria-hidden="true" className="absolute right-0.5 top-0.5" variant="notification">{badge > 99 ? '99+' : badge}</Badge> : null}
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
                    <button aria-label={`Menu akun ${name}`} className={`flex w-full min-w-0 items-center gap-3 rounded-xl bg-transparent p-3 text-left outline-none transition hover:bg-neutral-100/40 focus-visible:ring-2 focus-visible:ring-neutral-400 ${isCollapsed ? 'flex-col px-1.5' : ''}`} type="button">
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
                <DropdownMenu.Content align="end" className="z-50 min-w-56 rounded-xl border border-neutral-200 bg-surface p-1.5" side={isSidebar ? 'right' : 'bottom'} sideOffset={8}>
                    <div className="px-3 py-2">
                        <p className="truncate text-sm font-semibold text-neutral-900">{name}</p>
                        <p className="truncate text-xs text-neutral-500">{user?.email ?? ''}</p>
                        <p className="truncate text-xs text-neutral-500">{user?.roleLabel ?? ''}</p>
                    </div>
                    <DropdownMenu.Separator className="my-1 h-px bg-neutral-200" />
                    <DropdownMenu.Item asChild className="flex cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-sm text-neutral-700 outline-none transition-colors hover:bg-neutral-100 focus:bg-neutral-100">
                        <Link href="/profile"><UserRound aria-hidden="true" className="size-4" />Profil saya</Link>
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
    const page = usePage<SharedPageProps>();
    useEffect(() => router.on('error', event => {
        const messages = Object.values(event.detail.errors).flat().filter(value => typeof value === 'string');
        if (messages.length) toast.error(messages[0]);
    }), []);

    const { auth, flash, modules, adminQueues, nurseQueues, doctorQueues, pharmacyQueues } = page.props;
    const adminBadges: Record<string, number | undefined> = {
        '/pelayanan/screening': adminQueues?.assessment,
        '/pelayanan/pemeriksaan': adminQueues?.examinations,
        '/pelayanan/tindakan-perawat': adminQueues?.treatments,
        '/pelayanan/farmasi': adminQueues?.handovers,
    };
    const [mobileNavigationOpen, setMobileNavigationOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [searchOpen, setSearchOpen] = useState(false);
    const user = auth.user;
    const groups: NavigationGroup[] = [
        ...(navigationByRole[user?.role ?? ''] ?? []).map((group) => ({
            ...group,
            items: group.items.filter((item) => !item.module || modules?.[item.module] === true).map((item) => {
                const label = user?.role === 'dokter' && item.href === '/pelayanan/pemeriksaan'
                    ? user.clinicType === 'gigi' ? 'Pemeriksaan Gigi & Mulut' : user.clinicType === 'umum' ? 'Pemeriksaan Umum' : item.label
                    : item.label;
                const badge = user?.role === 'perawat'
                    ? item.href === '/pelayanan/screening' ? nurseQueues?.assessment : item.href === '/pelayanan/tindakan-perawat' ? nurseQueues?.treatments : undefined
                    : user?.role === 'dokter' && item.href === '/pelayanan/pemeriksaan'
                        ? doctorQueues?.examinations
                        : user?.role === 'farmasi' && item.href === '/pelayanan/farmasi'
                            ? pharmacyQueues?.handovers
                            : undefined;

                return { ...item, label, badge: (user?.role === 'admin' ? adminBadges[item.href] : badge) ?? undefined };
            }),
        })).filter((group) => group.items.length > 0),
        { label: 'Akun', items: [{ label: 'Profil saya', href: '/profile', icon: UserRound }] },
    ];
    const pathname = page.url.split(/[?#]/, 1)[0];
    const reportTitles: Record<string, string> = {
        '/admin/laporan/pemeriksaan': 'Laporan pemeriksaan dokter',
        '/admin/laporan/pengkajian': 'Laporan pengkajian awal',
        '/admin/laporan/dokter/tindakan': 'Laporan tindakan dokter',
        '/admin/laporan/dokter/obat': 'Laporan resep dokter',
        '/dokter/laporan-pemeriksaan': 'Laporan pemeriksaan',
        '/perawat/laporan-pengkajian': 'Laporan pengkajian awal',
        '/dokter/laporan/tindakan': 'Laporan tindakan',
        '/dokter/laporan/obat': 'Laporan obat',
    };
    const pageTitle = reportTitles[pathname] ?? getPageTitle(page.component);
    const validHrefs = new Set(groups.flatMap((group) => group.items.map((item) => item.href)));
    const activeHref = activeNavigationHref(pathname, groups.flatMap((group) => group.items));
    const breadcrumbs = activeHref === '/admin/laporan' && pathname !== activeHref
        ? [{ label: 'Pusat laporan', href: activeHref }, { label: pageTitle }]
        : getBreadcrumbItems(pathname, pageTitle, validHrefs);
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
        const queueProps: Record<string, string> = { admin: 'adminQueues', perawat: 'nurseQueues', dokter: 'doctorQueues', farmasi: 'pharmacyQueues' };
        const queueProp = queueProps[user?.role ?? ''];
        if (!queueProp) {
            return;
        }

        const refreshQueues = () => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: [queueProp], async: true });
            }
        };
        const interval = window.setInterval(refreshQueues, 30_000);
        window.addEventListener('focus', refreshQueues);

        return () => {
            window.clearInterval(interval);
            window.removeEventListener('focus', refreshQueues);
        };
    }, [user?.role]);

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.error, flash.success]);

    return (
        <div className="clinic-app min-h-screen bg-canvas text-neutral-900">
            <Sidebar className={`fixed inset-y-4 left-4 z-40 hidden h-[calc(100vh-2rem)] overflow-hidden rounded-2xl bg-surface print:hidden transition-[width] duration-200 lg:flex ${sidebarCollapsed ? 'w-[4.5rem]' : 'w-[17rem]'}`}>
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

            <div className={`min-h-screen transition-[padding] duration-200 print:min-h-0 print:!pl-0 ${sidebarCollapsed ? 'lg:pl-[6.5rem]' : 'lg:pl-[19rem]'}`}>
                <header className="sticky top-0 z-30 flex min-h-16 flex-wrap items-center gap-2 bg-canvas px-3 py-3 print:hidden sm:h-16 sm:flex-nowrap sm:gap-3 sm:px-6 sm:py-0 lg:top-4 lg:mt-4 lg:h-[4.5rem] lg:px-8 lg:before:pointer-events-none lg:before:absolute lg:before:inset-x-0 lg:before:-top-4 lg:before:h-4 lg:before:bg-canvas lg:before:content-['']">
                    <div aria-hidden="true" className="pointer-events-none absolute inset-x-0 top-full -z-10 h-4 bg-linear-to-b from-canvas to-transparent sm:h-6 lg:h-8" />
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
                    <div className="relative order-last flex w-full min-w-0 items-center sm:order-none sm:w-auto">
                        <Search aria-hidden="true" className="pointer-events-none absolute left-3 size-4 text-neutral-400" />
                        <input
                            aria-label="Cari menu"
                            aria-controls="global-search-results"
                            aria-expanded={searchOpen && searchQuery.trim().length > 0}
                            autoComplete="off"
                            className="h-10 w-full rounded-lg bg-surface pl-9 pr-3 text-sm text-neutral-900 outline-none transition placeholder:text-neutral-400 focus-visible:ring-4 focus-visible:ring-neutral-500/10 sm:w-56 sm:pr-12 lg:w-64"
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
                            placeholder="Cari menu"
                            role="combobox"
                            value={searchQuery}
                        />
                        <kbd aria-hidden="true" className="pointer-events-none absolute right-2 hidden rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-500 lg:block">Ctrl K</kbd>
                        {searchOpen && searchQuery.trim().length > 0 && (
                            <ul aria-label={searchResults.length > 0 ? 'Hasil pencarian menu' : 'Status pencarian menu'} className="absolute right-0 top-full z-50 mt-2 max-h-80 w-[min(20rem,calc(100vw-2rem))] overflow-y-auto rounded-xl border border-neutral-200 bg-surface p-1.5" id="global-search-results" role={searchResults.length > 0 ? 'listbox' : 'list'}>
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
                    {!['persuratan/cetak', 'pelayanan/screening/print', 'pendaftaran/cetak-antrian', 'farmasi/pemberian-obat/cetak'].includes(page.component) && <ThemeToggle className="shrink-0" />}
                    <AccountMenu user={user} />
                </header>

                <main className={`mx-auto min-w-0 w-full p-4 print:max-w-none print:p-0 sm:p-6 lg:p-8 ${['pelayanan/pasien/rekam-medis', 'pelayanan/pemeriksaan/show'].includes(page.component) ? 'max-w-none' : 'max-w-[1600px]'}`}>
                    {children}
                </main>
            </div>
        </div>
    );
}
