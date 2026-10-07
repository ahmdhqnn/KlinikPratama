<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokterDashboardController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\Master\AlkesController;
use App\Http\Controllers\Master\AsuransiController;
use App\Http\Controllers\Master\BiayaAdminController;
use App\Http\Controllers\Master\BiayaPendaftaranController;
use App\Http\Controllers\Master\DepoObatController;
use App\Http\Controllers\Master\LaboratoriumController;
use App\Http\Controllers\Master\NakesController;
use App\Http\Controllers\Master\ObatController;
use App\Http\Controllers\Master\PaketTindakanController;
use App\Http\Controllers\Master\PoliklinikController;
use App\Http\Controllers\Master\TindakanController;
use App\Http\Controllers\Pelayanan\FarmasiController;
use App\Http\Controllers\Pelayanan\KasirController;
use App\Http\Controllers\Pelayanan\KunjunganController;
use App\Http\Controllers\Pelayanan\PasienController;
use App\Http\Controllers\Pelayanan\PemeriksaanController;
use App\Http\Controllers\Pelayanan\ScreeningController;
use App\Http\Controllers\Pendaftaran\RegistrationController;
use App\Http\Controllers\PerawatDashboardController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\Stok\PenjualanLangsungController;
use App\Http\Controllers\Stok\PurchaseOrderController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureAdminRole;
use App\Http\Middleware\EnsureRegistrationRole;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\RedirectRegistrationRole;
use Illuminate\Support\Facades\Route;

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware(['guest', 'throttle:login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Authenticated routes
Route::middleware(['auth', RedirectRegistrationRole::class])->group(function () {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/perawat/dashboard', PerawatDashboardController::class)
        ->middleware(EnsureRole::class.':admin,perawat')
        ->name('perawat.dashboard');
    Route::prefix('dokter')->name('dokter.')->middleware(EnsureRole::class.':dokter')->group(function (): void {
        Route::get('/', [DokterDashboardController::class, 'index'])->name('dashboard');
        Route::get('kunjungan', [DokterDashboardController::class, 'appointments'])->name('kunjungan');
        Route::get('janji-kunjungan', [DokterDashboardController::class, 'appointments'])->name('janji-kunjungan');
        Route::get('pasien', [DokterDashboardController::class, 'patients'])->name('pasien');
        Route::get('stok-obat', [DokterDashboardController::class, 'stock'])->name('stok-obat');
        Route::get('laporan-top-diagnosa', [DokterDashboardController::class, 'topDiagnoses'])->name('laporan-top-diagnosa');
    });

    // Master Data
    Route::prefix('master')->name('master.')->middleware(EnsureAdminRole::class)->group(function () {
        Route::resource('depo-obat', DepoObatController::class);
        Route::resource('poliklinik', PoliklinikController::class);
        Route::post('poliklinik/{poliklinik}/ruang', [PoliklinikController::class, 'storeRuang'])->name('poliklinik.ruang.store');
        Route::delete('poliklinik/ruang/{ruang}', [PoliklinikController::class, 'destroyRuang'])->name('poliklinik.ruang.destroy');

        Route::resource('tindakan', TindakanController::class);
        Route::post('tindakan/{tindakan}/bhp', [TindakanController::class, 'storeBhp'])->name('tindakan.bhp.store');
        Route::delete('tindakan/bhp/{bhp}', [TindakanController::class, 'destroyBhp'])->name('tindakan.bhp.destroy');

        Route::resource('laboratorium', LaboratoriumController::class);
        Route::post('laboratorium/{laboratorium}/indikator', [LaboratoriumController::class, 'storeIndikator'])->name('laboratorium.indikator.store');
        Route::delete('laboratorium/indikator/{indikator}', [LaboratoriumController::class, 'destroyIndikator'])->name('laboratorium.indikator.destroy');
        Route::post('laboratorium/{laboratorium}/bhp', [LaboratoriumController::class, 'storeBhp'])->name('laboratorium.bhp.store');
        Route::delete('laboratorium/bhp/{bhp}', [LaboratoriumController::class, 'destroyBhp'])->name('laboratorium.bhp.destroy');

        Route::resource('paket-tindakan', PaketTindakanController::class);
        Route::post('paket-tindakan/{paketTindakan}/item', [PaketTindakanController::class, 'storeItem'])->name('paket-tindakan.item.store');
        Route::delete('paket-tindakan/item/{item}', [PaketTindakanController::class, 'destroyItem'])->name('paket-tindakan.item.destroy');
        Route::resource('biaya-admin', BiayaAdminController::class);
        Route::resource('biaya-pendaftaran', BiayaPendaftaranController::class);
        Route::resource('nakes', NakesController::class);

        Route::resource('obat', ObatController::class);
        Route::get('obat-export', [ObatController::class, 'export'])->name('obat.export');
        Route::post('obat-import', [ObatController::class, 'import'])->name('obat.import');
        Route::get('obat-template', [ObatController::class, 'template'])->name('obat.template');
        Route::get('obat/{obat}/stok', [ObatController::class, 'stok'])->name('obat.stok');
        Route::post('obat/{obat}/tambah-stok', [ObatController::class, 'tambahStok'])->name('obat.tambah-stok');

        Route::resource('alkes', AlkesController::class);
        Route::resource('asuransi', AsuransiController::class);
        Route::post('asuransi/{asuransi}/harga', [AsuransiController::class, 'storeHarga'])->name('asuransi.harga.store');
        Route::delete('asuransi/harga/{harga}', [AsuransiController::class, 'destroyHarga'])->name('asuransi.harga.destroy');
    });

    // Pelayanan
    Route::prefix('pelayanan')->name('pelayanan.')->group(function () {
        Route::middleware(EnsureRole::class.':admin,perawat,pendaftaran,dokter')->group(function (): void {
            Route::resource('pasien', PasienController::class);
            Route::get('pasien/{pasien}/rekam-medis', [PasienController::class, 'rekamMedis'])->name('pasien.rekam-medis');
        });
        Route::get('pasien-export', [PasienController::class, 'export'])->name('pasien.export');
        Route::post('pasien-import', [PasienController::class, 'import'])->name('pasien.import');
        Route::get('pasien-template', [PasienController::class, 'template'])->name('pasien.template');
        Route::post('pasien/gabung', [PasienController::class, 'gabung'])->name('pasien.gabung');

        Route::middleware(EnsureRole::class.':admin,perawat,pendaftaran')->group(function (): void {
            Route::resource('kunjungan', KunjunganController::class);
            Route::get('antrian', [KunjunganController::class, 'antrian'])->name('antrian');
            Route::post('kunjungan/{kunjungan}/batal', [KunjunganController::class, 'batal'])->name('kunjungan.batal');
        });

        Route::middleware(EnsureRole::class.':admin,perawat,pendaftaran')->group(function (): void {
            Route::get('screening', [ScreeningController::class, 'index'])->name('screening.index');
            Route::get('screening/{kunjungan}', [ScreeningController::class, 'show'])->name('screening.show');
            Route::post('screening/{kunjungan}', [ScreeningController::class, 'store'])->name('screening.store');
            Route::put('screening/{kunjungan}', [ScreeningController::class, 'update'])->name('screening.update');
            Route::post('kunjungan/{kunjungan}/screening', [ScreeningController::class, 'store'])->name('kunjungan.screening.store');
        });

        Route::middleware(EnsureRole::class.':admin,dokter')->group(function (): void {
            Route::get('pemeriksaan', [PemeriksaanController::class, 'index'])->name('pemeriksaan.index');
            Route::get('pemeriksaan/{kunjungan}', [PemeriksaanController::class, 'show'])->name('pemeriksaan.show');
            Route::post('pemeriksaan/{kunjungan}', [PemeriksaanController::class, 'store'])->name('pemeriksaan.store');
            Route::put('pemeriksaan/{kunjungan}', [PemeriksaanController::class, 'update'])->name('pemeriksaan.update');
            Route::post('pemeriksaan/{kunjungan}/diagnosa', [PemeriksaanController::class, 'storeDiagnosa'])->name('pemeriksaan.diagnosa.store');
            Route::delete('pemeriksaan/diagnosa/{diagnosa}', [PemeriksaanController::class, 'destroyDiagnosa'])->name('pemeriksaan.diagnosa.destroy');
            Route::post('pemeriksaan/{kunjungan}/resep', [PemeriksaanController::class, 'storeResep'])->name('pemeriksaan.resep.store');
            Route::delete('pemeriksaan/resep/{resepObat}', [PemeriksaanController::class, 'destroyResep'])->name('pemeriksaan.resep.destroy');
            Route::post('pemeriksaan/{kunjungan}/tindakan', [PemeriksaanController::class, 'storeTindakan'])->name('pemeriksaan.tindakan.store');
            Route::delete('pemeriksaan/tindakan/{tindakanKunjungan}', [PemeriksaanController::class, 'destroyTindakan'])->name('pemeriksaan.tindakan.destroy');
            Route::post('pemeriksaan/{kunjungan}/surat', [PemeriksaanController::class, 'storeSurat'])->name('pemeriksaan.surat.store');
            Route::post('pemeriksaan/{kunjungan}/rujukan', [PemeriksaanController::class, 'storeRujukan'])->name('pemeriksaan.rujukan.store');
            Route::post('pemeriksaan/{kunjungan}/selesai', [PemeriksaanController::class, 'selesai'])->name('pemeriksaan.selesai');
            Route::get('icd10/search', [PemeriksaanController::class, 'searchIcd10'])->name('icd10.search');
        });

        Route::middleware(EnsureRole::class.':admin,farmasi')->group(function (): void {
            Route::get('farmasi', [FarmasiController::class, 'index'])->name('farmasi.index');
            Route::get('farmasi/{kunjungan}', [FarmasiController::class, 'show'])->name('farmasi.show');
            Route::post('farmasi/{kunjungan}', [FarmasiController::class, 'store'])->name('farmasi.store');
            Route::post('farmasi/{kunjungan}/selesai', [FarmasiController::class, 'selesai'])->name('farmasi.selesai');
        });

        Route::middleware(EnsureRole::class.':admin,kasir')->group(function (): void {
            Route::get('kasir', [KasirController::class, 'index'])->name('kasir.index');
            Route::get('kasir/{kunjungan}', [KasirController::class, 'show'])->name('kasir.show');
            Route::post('kasir/{kunjungan}', [KasirController::class, 'store'])->name('kasir.store');
            Route::get('kasir/{tagihan}/kuitansi', [KasirController::class, 'kuitansi'])->name('kasir.kuitansi');
        });
    });

    // Manajemen Stok
    Route::prefix('stok')->name('stok.')->middleware(EnsureAdminRole::class)->group(function () {
        Route::resource('purchase-order', PurchaseOrderController::class);
        Route::post('purchase-order/{purchaseOrder}/kirim', [PurchaseOrderController::class, 'kirim'])->name('purchase-order.kirim');
        Route::get('purchase-order/{purchaseOrder}/terima', [PurchaseOrderController::class, 'terimaBarang'])->name('purchase-order.terima.form');
        Route::post('purchase-order/{purchaseOrder}/terima', [PurchaseOrderController::class, 'prosesTerima'])->name('purchase-order.terima');

        Route::resource('penjualan-langsung', PenjualanLangsungController::class);
        Route::get('penjualan-langsung/{penjualanLangsung}/nota', [PenjualanLangsungController::class, 'nota'])->name('penjualan-langsung.nota');
    });

    // Laporan
    Route::prefix('laporan')->name('laporan.')->middleware(EnsureAdminRole::class)->group(function () {
        Route::get('kunjungan', [LaporanController::class, 'kunjungan'])->name('kunjungan');
        Route::get('pendapatan', [LaporanController::class, 'pendapatan'])->name('pendapatan');
        Route::get('stok', [LaporanController::class, 'stok'])->name('stok');
        Route::get('laboratorium', [LaporanController::class, 'laboratorium'])->name('laboratorium');
        Route::get('kunjungan/export', [LaporanController::class, 'exportKunjungan'])->name('kunjungan.export');
        Route::get('pendapatan/export', [LaporanController::class, 'exportPendapatan'])->name('pendapatan.export');
    });

    // Setting
    Route::middleware(EnsureAdminRole::class)->group(function (): void {
        Route::get('setting', [SettingController::class, 'index'])->name('setting.index');
        Route::put('setting', [SettingController::class, 'update'])->name('setting.update');
        Route::post('setting/logo', [SettingController::class, 'uploadLogo'])->name('setting.logo');
    });

    Route::middleware(EnsureAdminRole::class)->group(function () {
        Route::resource('users', UserController::class);
        Route::post('users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
    });

    // Pendaftaran (Registration Role)
    Route::prefix('pendaftaran')->name('pendaftaran.')->withoutMiddleware(RedirectRegistrationRole::class)->middleware(EnsureRegistrationRole::class)->group(function () {
        Route::get('/', [RegistrationController::class, 'dashboard'])->name('dashboard');

        Route::get('laporan-kunjungan', [RegistrationController::class, 'laporanKunjungan'])->name('laporan-kunjungan');
        Route::get('kunjungan/{kunjungan}/cetak-antrian', [RegistrationController::class, 'cetakAntrian'])->name('cetak-antrian');
        Route::get('kunjungan/{kunjungan}/edit', [RegistrationController::class, 'editKunjungan'])->name('edit-kunjungan');
        Route::put('kunjungan/{kunjungan}', [RegistrationController::class, 'updateKunjungan'])->name('update-kunjungan');
        Route::post('kunjungan/{kunjungan}/batal', [RegistrationController::class, 'batalKunjungan'])->name('batal-kunjungan');

        Route::get('pendaftaran-baru', [RegistrationController::class, 'pendaftaranBaru'])->name('pendaftaran-baru');
        Route::post('pendaftaran-baru', [RegistrationController::class, 'storePasienBaru'])->name('store-pasien-baru');

        Route::get('pendaftaran-lama', [RegistrationController::class, 'pendaftaranLama'])->name('pendaftaran-lama');
        Route::post('pendaftaran-lama', [RegistrationController::class, 'storePasienLama'])->name('store-pasien-lama');
        Route::get('pasien/search', [RegistrationController::class, 'searchPasien'])->name('pasien.search');
        Route::get('pasien/{pasien}/detail', [RegistrationController::class, 'getPasienDetail'])->name('pasien.detail');

        Route::get('database-pasien', [RegistrationController::class, 'databasePasien'])->name('database-pasien');

        Route::get('kunjungan-per-poli', [RegistrationController::class, 'kunjunganPerPoli'])->name('kunjungan-per-poli');

        Route::get('laporan-top-diagnosa', [RegistrationController::class, 'laporanTopDiagnosa'])->name('laporan-top-diagnosa');

        Route::get('jadwal-praktik', [RegistrationController::class, 'jadwalPraktik'])->name('jadwal-praktik');
        Route::post('jadwal-praktik', [RegistrationController::class, 'storeJadwalPraktik'])->name('store-jadwal-praktik');
        Route::delete('jadwal-praktik/{jadwal}', [RegistrationController::class, 'destroyJadwalPraktik'])->name('destroy-jadwal-praktik');
        Route::get('dokter/by-poli', [RegistrationController::class, 'getDokterByPoli'])->name('dokter.by-poli');
    });
});
