# 03 — Standarisasi UI/UX, Design System, Layout & Seluruh Screen
**Peran:** Principal UI/UX & Design System Engineer (Standarisasi Antarmuka, Layout Profil, Tabel, Filter, Alert, Tombol & KPI)  
**Mode Audit:** 100% Read-Only Codebase Inspection atas seluruh 83 halaman Inertia (`resources/js/pages/**`), komponen UI (`resources/js/components/**`), dan `resources/css/app.css`.

---

## 1. Redesain Screen Profil Pasien & Standarisasi Seluruh Screen Profil

### 1.1 Temuan Audit Screen Profil Pasien Saat Ini (`resources/js/pages/pelayanan/pasien/show.tsx`)
1. **Proporsi Layout Sempit & Terpecah (`line 119`):** Memakai kartu identitas penuh di atas grid `lg:grid-cols-[14rem_minmax(0,1fr)]` (`224px` kiri), menu teks polos tanpa ikon & tanpa panah kanan (`lines 122–130`), serta mencampur biodata umum + kontak ke satu `<dl>` 3 kolom di kanan (`lines 185–215`).
2. **Bug Backend Penghapusan Biodata Antar-Bagian (`PasienController.php:266`):** `$showBiodata = $role !== 'farmasi' && $data['section'] === 'informasi';` — begitu pengguna membuka bagian selain `informasi`, seluruh field `nik`, `bloodType`, `phone`, `address`, `membership` dihapus dari prop `patient`, sehingga panel kiri tidak dapat menampilkan biodata persisten!
3. **Field Nyata Tabel `pasien` yang Belum Dihubungkan ke UI Profil:** Kolom kontak darurat/wali (`nama_wali`, `telepon_wali`) dan `kode_pos` **sudah ada di model `app/Models/Pasien.php:20–22`**, namun belum dikirim oleh `PasienController::show()` (`lines 290–316`).
4. **Tabel Kunjungan Tanpa Tab & Tanpa Filter (`lines 163–176, 234–245`):** Hanya menampilkan 5 baris statis pada ringkasan atau 10 baris per halaman tanpa tab `Mendatang vs Riwayat` dan tanpa filter `Periode`, `Poli`, maupun `Status`.
5. **Tidak Ada Card Dokumen Pasien & Card Catatan Medis / Ringkasan Klinis:** Bagian bawah area kerja utama belum menampilkan kartu berkas dokumen pasien maupun kartu ringkasan catatan medis.

### 1.2 Penegasan Konteks Proyek vs Gambar Referensi (Zero Asumsi / Anti Mengada-ngada)

Agar hasil redesain mengadopsi **proporsi dan kebersihan tata letak gambar referensi** secara presisi namun **100% setia pada domain nyata Klinik Pratama Kementerian Pariwisata RI**, berikut pemetaan tegas elemen yang diadopsi vs elemen fiktif mockup yang **DILARANG** dibuat-buat:

| Elemen pada Gambar Referensi | Keputusan Konteks Proyek `klinik-pratama` (Faktual & Berdasarkan Kode Nyata) |
| :--- | :--- |
| **Tema Warna Hijau Toska (`#1c7c74`), Menu `Messages` & Lonceng Notifikasi** | **TIDAK DIADOPSI (Jangan Mengada-ngada).** Tetap menggunakan shell `AppLayout` & Design System monokrom netral aplikasi (`bg-canvas`, `bg-surface`, `border-neutral-200`, `Plus Jakarta Sans Variable`, `tabular-nums`, *Light & Dark Mode*) tanpa menu pesan/notifikasi fiktif. |
| **Foto Wajah Pasien pada Avatar Kiri Atas** | **Gunakan Avatar Bulat Inisial Nama (`AvatarFallback`).** Tabel `pasien` tidak menyimpan foto wajah pasien; dilarang mengada-ngada fitur upload foto pasien. |
| **Menu Cepat Kiri: `Chart`, `Billing`, `Insurance`, `Patient Notes`, `Payments`** | **GANTI 100% dengan Menu Nyata `app/PatientProfileSections.php` sesuai RBAC!** Modul *kasir/billing/tagihan* dan *asuransi komersial* sudah resmi dipensiunkan di migrasi `2026_10_16_075052` & `2026_11_14_120000`. Menu cepat kiri wajib memakai seksi asli aplikasi (`Informasi Pasien`, `Riwayat Kunjungan`, `Kontrol Lanjutan`, `Catatan Medis`, `Pengkajian Awal`, `Odontogram`, `Penunjang & Dokumen`, `Tindakan`, `Resep & Obat`, `Persetujuan & Penolakan`, `Surat Medis`, `Rujukan`, `Pelepasan Informasi`) yang otomatis menyesuaikan role login. |
| **Sub-blok `General Info` (`Gender`, `Age`, `DOB`, `Blood Group B+`, `Preferred Language`)** | **Hanya Gunakan Kolom Nyata `Pasien` & `Kepesertaan`!** Tampilkan pasangan *Label Kiri — Nilai Rata Kanan* untuk: `Jenis Kelamin`, `Usia`, `Tempat, Tgl Lahir`, `Golongan Darah` (`A/B/AB/O` murni sesuai validasi DB tanpa rhesus fiktif), `NIK`, `Kepesertaan` (`ASN / PPPK / Lainnya`), `NIP`, dan `Unit Kerja` Kemenpar. **Hapus `Preferred Language`!** |
| **Sub-blok `Contact Info` (`Phone`, `Emergency Contact`, `Email Address`, `Mailing Address`)** | **Hanya Gunakan Kolom Kontak Nyata Tabel `pasien`!** Tampilkan `Nomor Telepon` (`telepon`), `Kontak Darurat / Wali` (`nama_wali` & `telepon_wali` — kolom nyata yang sudah ada di `Pasien.php:22`), serta `Alamat Domisili` (`alamat`, `RT/RW`, `kelurahan`, `kecamatan`, `kode_pos`). **Hapus `Email Address`** karena tabel `pasien` & alur pendaftaran klinik tidak menggunakan email pasien. |
| **Kolom Tabel Kunjungan (`Symptoms`, `Category: Follow-Up`, `Doctor (Oncology/Cardiology)`)** | **Sesuaikan dengan 2 Poli Klinik Pratama & Kerahasiaan RBAC!**<br>• **Poli & Dokter:** Hanya `Poli Umum` dan `Poli Gigi` (bukan poli spesialis RS!).<br>• **Kolom `Keluhan`:** Hanya ditampilkan untuk `dokter` & `perawat`; bagi `admin` & `pendaftaran` otomatis diganti menjadi info `Poliklinik / Ruang` karena non-nakes dilarang melihat keluhan medis (`RoleBoundaryTest` & `PatientProfileTest`).<br>• **Kolom `Kategori`:** Diturunkan murni dari status nyata `Kunjungan`: `Kunjungan Reguler`, `Kontrol Lanjutan` (`kontrol_asal_id`), `Rujukan Internal`, atau `Reservasi`. |
| **Kartu `Documents` (`+ Add Documents`, ikon Unduh `⬇`, Lihat `👁`, Hapus `🗑`, `See All`)** | **Adaptasi Patuh PMK 24/2022:** Judul `Dokumen Pasien` + tombol `+ Tambah Dokumen` (sesuai RBAC). Ikon aksi kanan: `Unduh`, `Pratinjau`, dan **`Arsipkan`** (*soft-archive* wajib alasan $\ge 10$ karakter & audit log — **bukan hapus fisik permanen**!). Tombol bawah `Lihat Semua`. |
| **Kartu `Medical Notes` (`+ Add Record`, daftar catatan, ikon Lihat `👁` & Hapus `🗑`, `See All`)** | **Adaptasi Patuh Alur RME & Trigger Imutabilitas!**<br>1. **DILARANG membuat tombol `+ Add Record` catatan lepas di luar kunjungan!** Pencatatan medis wajib melalui alur `Kunjungan -> Pemeriksaan`. Tombol kanan atas diganti secara kontekstual menjadi **`Buka Pemeriksaan`** (bila ada kunjungan aktif hari ini) atau **`Rekam Medis Lengkap`** (`recordUrl`).<br>2. **DILARANG menampilkan ikon Hapus (`🗑`) pada Catatan Medis!** Tabel `pemeriksaan` dilindungi trigger anti-hapus & PMK 24/2022 Pasal 37; hanya tampilkan ikon **`Lihat Detail` (`👁`)** ke episode RME terkait.<br>3. **Adaptasi Role Non-Medis:** Bagi `admin` & `pendaftaran` (yang diblokir dari RME medis), kartu kanan bawah menampilkan **`Persetujuan & Administrasi Layanan`** (*General Consent*, Rujukan Internal, Pelepasan Informasi) sehingga struktur 2 kartu bawah tetap utuh tanpa melanggar kerahasiaan medis! |

### 1.3 Spesifikasi Arsitektur Layout Baru Profil Pasien (Mengikuti Struktur Visual Referensi)

```
+---------------------------------------------------------------------------------------------------+
| Breadcrumb: Data Pasien > [Nama Pasien]             [<- Kembali] [Edit Data] [+ Daftarkan Kunjungan]|
+-------------------------------+-------------------------------------------------------------------+
| PANEL KIRI SATU KARTU UTUH    | KOLOM KANAN — AREA KERJA UTAMA (~70% / minmax(0, 1fr))            |
| (~30% / bg-surface rounded-2xl|                                                                   |
|                               | +---------------------------------------------------------------+ |
|  (Inisial) Nama Lengkap       | | [Kunjungan/Reservasi Mendatang] [Semua Riwayat]  [Poli][Tahun]| |
|            RM-000123          | |---------------------------------------------------------------| |
|  [Badge Kepesertaan / Alergi] | | Tanggal & No. | Keluhan/Poli | Kategori | Dokter | Status |Aksi| |
|                               | | 26 Nov 2026   | Nyeri gigi   | Kontrol  | drg. A | Selesai|Lihat| |
|  [o] Informasi Pasien       > | | KNJ-202611-01 | (khusus nakes| Lanjutan | (Gigi) |        |Detail|
|  [o] Riwayat Kunjungan      > | | [Pagination]                                                  | |
|  [o] Catatan Medis          > | +---------------------------------------------------------------+ |
|  [o] Persetujuan Umum       > |                                                                   |
|  [o] Resep & Obat           > | +------------------------------+ +------------------------------+ |
|  (sesuai RBAC role login)     | | Dokumen Pasien    [+ Tambah] | | Catatan Medis     [Buka RME] | |
|                               | |------------------------------| |------------------------------| |
|  +-------------------------+  | | [DOC] Hasil Lab Luar.pdf     | | [RME] J06.9 ISPA Akut    [👁]| |
|  | Informasi Umum          |  | |       20 Nov 2026  [⬇][👁][🗄]| |       26 Nov · dr. Budi      | |
|  | Jenis Kelamin  Perempuan|  | | [IMG] Rontgen Gigi 36.png    | | [RME] K04.0 Pulpitis     [👁]| |
|  | Usia            32 tahun|  | |       12 Okt 2026  [⬇][👁][🗄]| |       12 Okt · drg. Siti     | |
|  | Tgl Lahir    12 Jun 1994|  | |                              | |  (Tanpa ikon hapus! Untuk    | |
|  | Gol. Darah             B|  | |                              | |   Admin/Pendaftaran tampil   | |
|  | NIK     3171xxxxxxxxxxxx|  | |                              | |   Persetujuan & Administrasi)| |
|  | Kepesertaan   ASN · Aktif| | +------------------------------+ +------------------------------+ |
|  | Unit Kerja    Sekretariat| | |         [Lihat Semua]        | |         [Lihat Semua]        | |
|  +-------------------------+  | +------------------------------+ +------------------------------+ |
|                               |                                                                   |
|  +-------------------------+  |                                                                   |
|  | Informasi Kontak        |  |                                                                   |
|  | No. Telepon  0812xxxxxxx|  |                                                                   |
|  | Kontak Wali  Budi (081x)|  |                                                                   |
|  | Alamat       Jl. Medan  |  |                                                                   |
|  |              Merdeka... |  |                                                                   |
|  +-------------------------+  |                                                                   |
+-------------------------------+-------------------------------------------------------------------+
```

#### Detail Implementasi Teknis & RBAC pada Profil Pasien (`pelayanan/pasien/show.tsx` + `PasienController.php`):
- **Grid Proporsional:** `grid min-w-0 items-start gap-6 lg:grid-cols-[minmax(19rem,0.30fr)_minmax(0,0.70fr)]`.
- **Panel Kiri Terpadu (Satu `<Card>` Utama seperti Referensi):**
  1. **Header Identitas:** Avatar bulat inisial (`size-12 rounded-full bg-neutral-100 font-semibold`), `patient.name`, `patient.medicalRecordNumber` (`tabular-nums`), diikuti *badge* kepesertaan & *badge* status alergi (khusus `dokter`/`perawat`/`farmasi`).
  2. **Daftar Menu Cepat RBAC (`<nav aria-label="Menu profil pasien">`):** Mempertahankan urutan & kontrak `PatientProfileSections.php` namun diberi ikon kontekstual di kiri + `<ChevronRight className="size-4" />` di kanan.
  3. **Sub-blok `Informasi Umum` (`rounded-xl bg-neutral-50 p-4`):** Baris `flex items-baseline justify-between gap-3` (label kiri `text-xs text-neutral-500`, nilai kanan `text-right text-xs font-medium text-neutral-900`) untuk `Jenis Kelamin`, `Usia`, `Tempat, Tgl Lahir`, `Golongan Darah`, `NIK`, `Kepesertaan`, `NIP`, dan `Unit Kerja` (tetap disembunyikan bagi role `farmasi` sesuai `PatientProfileTest:115`).
  4. **Sub-blok `Informasi Kontak` (`rounded-xl bg-neutral-50 p-4`):** Baris rata kiri–kanan untuk `Nomor Telepon`, `Kontak Wali / Darurat` (`nama_wali` & `telepon_wali`), dan `Alamat Domisili` (`alamat`, `rt`/`rw`, `kelurahan`, `kecamatan`, `kode_pos`).
- **Kolom Kanan:**
  1. **Kartu Utama Kunjungan Bertab:** Saat `section === 'informasi'` atau `'kunjungan'`/`'kontrol'`, menampilkan *underline tabs* (`Kunjungan / Reservasi Mendatang` | `Semua Riwayat Kunjungan`) di kiri header dan filter kompak (`Poliklinik`, `Status`, `Periode/Tahun`) di kanan header. Saat pengguna mengklik menu cepat lain di panel kiri (misal `Persetujuan`, `Surat Medis`, `Rujukan`, `Pelepasan Informasi`, `Obat Langsung`), kartu utama menampilkan tabel/daftar kerja bagian tersebut secara konsisten tanpa menghilangkan biodata di panel kiri!
  2. **Grid 2 Kartu Bawah (`Dokumen Pasien` & `Catatan Medis` / `Ringkasan Administrasi`):** Tampil sejajar di bawah kartu utama pada tampilan ringkasan profil, lengkap dengan tombol footer penuh `Lihat Semua` (`variant="secondary" className="w-full"`).

### 1.4 Audit & Penyeragaman Seluruh Screen Profil Lainnya
1. **Profil Pengguna / Akun (`resources/js/pages/profile/show.tsx`):**
   - *Temuan:* Saat ini kolom kiri (`~59%`) hanya berisi 1 kartu pendek (`Informasi akun` 3 input), sedangkan kolom kanan (`~41%`) menumpuk 4 kartu panjang (`Foto profil`, `Tanda tangan`, `PIN pengesahan`, `Ganti kata sandi`), menyisakan ruang kosong besar di kiri; tombol kanan memakai `w-full` sementara kiri `justify-end`.
   - *Rencana Standarisasi:* Ubah ke struktur 2 kolom standar `~32% Kiri / ~68% Kanan`: Kolom Kiri memuat **Card Identitas Akun & Avatar** + **Card Foto Profil** + **Card Spesimen Tanda Tangan**; Kolom Kanan memuat **Card Informasi Akun** + **Card Keamanan & Kredensial (Ganti Kata Sandi & PIN Pengesahan Klinis)** dengan seluruh tombol simpan rata kanan (`FormActionFooter`).
2. **Profil & Pengaturan Klinik (`resources/js/pages/setting/index.tsx`):**
   - *Temuan:* Satu kolom memanjang (`max-w-4xl`) menumpuk 13 field di atas kartu `Logo klinik` kecil, tombol `Simpan logo` rata kiri, belum ada input kode wilayah SATUSEHAT.
   - *Rencana Standarisasi:* Ubah ke layout 2 kolom `~32% Kiri / ~68% Kanan`: Kolom Kiri memuat **Card Identitas Visual & Logo Klinik** (pratinjau logo, unggah, footer tombol kanan); Kolom Kanan memuat **Card Identitas Resmi, Perizinan & Kode Wilayah Fasyankes**.
3. **Profil Tenaga Kesehatan & Kualifikasi Farmasi (`master/nakes/index.tsx` & `master/nakes/kualifikasi-farmasi.tsx`):**
   - *Temuan:* Tautan `Verifikasi kualifikasi farmasi` tersembunyi di dalam modal Edit (`nakes/index.tsx:58`); halaman `kualifikasi-farmasi.tsx` merender tombol kembali tanpa ikon di bawah subjudul, tombol simpan rata kiri (`line 35`), serta riwayat memakai `<div>` dan link paginasi mentah (`line 37`).
   - *Rencana Standarisasi:* Tambahkan `ActionButton` langsung menuju Kualifikasi Farmasi pada baris tabel Nakes bertipe `farmasi`/`apotek`; rapikan `kualifikasi-farmasi.tsx` menggunakan `PageHeaderWithActions`, `FormActionFooter`, `<Table>`, dan `<Pagination>`.
4. **Detail Poliklinik (`master/poliklinik/show.tsx`) & Detail Kepesertaan (`kepesertaan/show.tsx`):**
   - Seragamkan header ke `PageHeaderWithActions` (hapus tombol ghost melayang di atas judul pada `poliklinik/show.tsx:60`), tambahkan `Pagination` & `hover:bg-transparent` pada tabel ruang poli, dan tambahkan pintasan `Buka Profil Pasien` pada `kepesertaan/show.tsx` bila peserta sudah memiliki No. RM.

---

## 2. Standarisasi Global Tabel Data, Filter & Efek Hover

### 2.1 Audit Tabel Data yang Belum Memiliki Filter / Filter Belum Lengkap
Dari total **74 tabel** yang diaudit di seluruh aplikasi:
- **29 tabel** termasuk kategori **"Hari Ini" / Antrean Aktif / Tabel Mini dalam Card / Tabel Kerja Klinis Tunggal / Tabel Cetak** (dikecualikan dari bar filter penuh sesuai aturan).
- **45 tabel** termasuk kategori **Tabel Data Utama / Riwayat / Master / Laporan** yang **WAJIB** memiliki bar filter lengkap dan seragam (`TableFilterBar` berbasis `FilterPanel`).

Berikut daftar tabel yang wajib ditambahkan atau dilengkapi filternya:

| Prioritas | Halaman & File Path | Nama Tabel | Kondisi Saat Ini | Spesifikasi Filter Standar yang Wajib Ditambahkan |
| :--- | :--- | :--- | :--- | :--- |
| **P0 (Target Eksplisit)** | `pendaftaran/kunjungan-per-poli.tsx:112` | **Reservasi 7 Hari ke Depan** | **0 Filter pada Card!** | Tambahkan bar filter: `Cari Pasien / No. RM / No. Kunjungan`, `Poliklinik` (bila multi-poli), `Dokter`, `Rentang Tanggal Reservasi`, dan `Status Konfirmasi` (`Menunggu Konfirmasi` / `Terkonfirmasi`). |
| **P0 (Target Eksplisit)** | `pendaftaran/jadwal-praktik.tsx:279` | **Kalender Libur Praktik** | **0 Filter!** | Tambahkan bar filter: `Cari Nama Hari Libur / Dasar Surat`, `Jenis Libur` (`Libur Nasional`, `Cuti Bersama`, `Libur Instansi`), dan `Tahun / Rentang Tanggal`. |
| **P0** | `pendaftaran/jadwal-praktik.tsx:331` | **Pengecualian Jadwal per Tanggal** | **0 Filter!** | Tambahkan filter: `Cari Alasan`, `Dokter`, `Poliklinik`, `Keputusan` (`Buka Praktik` / `Tutup Praktik`), `Periode Tanggal`. |
| **P0** | `dokter/stock.tsx:46` | **Stok Obat Aktif (Dokter)** | **0 Filter!** | Tambahkan `FilterPanel`: `Cari Nama / Kode Obat`, `Jenis` (`Obat` / `BHP`), `Status Ketersediaan` (`Tersedia`, `Stok Menipis`, `Habis`). |
| **P0** | `pelayanan/pasien/show.tsx:234` | **Tabel Kunjungan Profil Pasien** | **0 Filter!** | Tambahkan filter inline proporsional di kanan atas card: `Periode / Tahun`, `Poliklinik`, `Status Kunjungan`. |
| **P0** | `stok/persediaan/show.tsx:46–47` & `master/obat/stok.tsx:19` | **Batch & Kartu Stok Obat** | **0 Filter!** | Tambahkan filter `Status Batch` (`Tersedia`, `Karantina`, `Mendekati Kedaluwarsa`) dan pada Kartu Stok: `Rentang Tanggal`, `Jenis Mutasi` (`Masuk`, `Keluar`, `Opname`, `Retur`), `Cari Referensi / Petugas`. |
| **P1** | `pendaftaran/kunjungan-per-poli.tsx:155` | **Daftar Kunjungan Semua Tanggal** | Hanya `poliklinik_id`, `tanggal`, `status` | Lengkapi dengan input **`Cari Pasien / No. RM / No. Kunjungan`** (`search`), `Dokter`, dan dukung `DateRangePicker`. |
| **P1** | `dokter/appointments.tsx:92` | **Kunjungan Dokter & Laporan Pemeriksaan** | Mode `!isReport` tidak punya `search`! | Aktifkan `Cari Pasien / No. RM`, `Poliklinik`, dan `Status Kunjungan` pada mode kunjungan maupun laporan (`Final` vs `Draf`). |
| **P1** | `dokter/top-diagnoses.tsx:55` & `pendaftaran/laporan-top-diagnosa.tsx:74` | **Diagnosis Terbanyak** | Dokter hanya punya `DateRangePicker` | Samakan kelengkapan filter: `Cari Kode / Nama Diagnosis`, `Rentang Tanggal` (`DateRangePicker`), dan `Poliklinik` (bagi Admin). |
| **P1** | `pelayanan/screening/history.tsx:87` & `pendaftaran/laporan-kunjungan.tsx:115` | **Laporan Pengkajian Awal & Laporan Kunjungan Pendaftaran** | Memakai `DatePicker` tunggal pada halaman Laporan | Tingkatkan menjadi `DateRangePicker` (`dari`–`sampai`) + tambahkan filter `Status Pengkajian` (`Terkirim` / `Draf`) pada Laporan Pengkajian Awal. |
| **P1** | `persuratan/index.tsx:143, 149, 173` & `pendaftaran/rujukan-internal.tsx:51` | **Surat Medis & Rujukan Internal** | Kurang filter periode, jenis & status | Lengkapi dengan `DateRangePicker`, `Jenis Surat`, `Status Dokumen` (`Aktif` / `Dibatalkan`), serta pada Rujukan Internal: `Poli Asal`, `Poli Tujuan`, `Status Rujukan`. |
| **P1** | `farmasi/pemberian-obat/index.tsx:45`, `farmasi/pasien.tsx:31`, `farmasi/resep-luar.tsx:45` | **Riwayat Obat Langsung, Pasien Farmasi & Resep Luar** | Sebagian hanya `search` atau tanggal tunggal | Lengkapi dengan `DateRangePicker` (`dari`–`sampai`) serta `Asal Resep Luar` (`Diresepkan Dokter` vs `Dialihkan Farmasi`). |
| **P2** | `kepesertaan/index.tsx:90`, `pendaftaran/database-pasien.tsx:71`, `pelayanan/pasien/index.tsx:150`, `admin/audit-bantuan.tsx:64`, `pelayanan/screening/index.tsx:119`, `pelayanan/tindakan/index.tsx:93` | **Tabel Master & Operasional Lainnya** | Filter parsial | Lengkapi `Status Kepegawaian` & `Hak Layanan` (`kepesertaan`), `Jenis Kelamin` & `Kategori` (`pasien`), `Peran` & `Hasil` (`audit-bantuan`), `Status Pengkajian` & `Prioritas` (`screening`), `Poliklinik` (`tindakan` Admin), serta konversi register `pelepasan.tsx` & `kualifikasi-farmasi.tsx` ke `<Table>` + `<FilterPanel>`. |

### 2.2 Audit Cacat Hover & Warna Tabel (Light Mode & Dark Mode) — Standar Tunggal Baru
Inspeksi terhadap `resources/js/components/ui/table.tsx` dan `resources/css/app.css` menemukan **4 masalah kontras & hover nyata**:
1. **[P0 — Teks Putih di Atas Putih pada Dark Mode] (`pelayanan/farmasi/show.tsx:343–356` + `app.css:142–154`):**
   - Pada `.dark`, variabel `--clinic-neutral-950` dibalik menjadi putih (`hsl(0 0% 98%)`). Etiket obat mematok `bg-white text-neutral-950` tanpa `dark:`, menghasilkan **teks putih di atas latar putih murni** yang hilang tak terbaca! *Solusi:* Gunakan token eksplisit kertas cetak `bg-white text-slate-950` (atau `style={{ colorScheme: 'light' }}` dengan warna teks heksadesimal tetap) pada pratinjau etiket fisik.
2. **[P0 — Tabrakan Warna Hover Baris Tabel terhadap Badge & Bar Progres] (`table.tsx:21` + `app.css:145–146`):**
   - Di `app.css:145–146`, `.dark` menyamakan `--clinic-neutral-50` dan `--clinic-neutral-100` ke nilai identik `hsl(0 0% 14.9%)`.
   - Saat baris tabel memiliki `hover:bg-neutral-100/60` (`table.tsx:21`), seluruh `<Badge variant="default">` (`bg-neutral-100`), *chip* hari praktik (`bg-neutral-50`), serta *track* bar persentase (`bg-neutral-100`) di dalam sel tabel **kehilangan batas visual/menyatu dengan latar baris**, dan pada baris berwarna peringatan/pilihan menyebabkan perubahan warna yang kotor.
   - *Solusi Standar Tabel Tunggal:*
     - Pisahkan token `.dark` di `app.css`: `--clinic-neutral-50: hsl(0 0% 12.2%)` dan `--clinic-neutral-100: hsl(0 0% 16.5%)`.
     - Pada `resources/js/components/ui/table.tsx`, tetapkan gaya baris bersih tanpa efek hover yang menabrak konten sel: gunakan pemisah baris tajam `divide-y divide-neutral-200/80`, header `bg-neutral-50 text-neutral-600`, dan hilangkan `hover:bg-neutral-100/60` yang mengubah kontras badge/teks (atau gunakan transisi sangat halus `hover:bg-neutral-50/50` hanya pada baris data murni tanpa menimpa warna teks maupun baris berstatus khusus, serta pastikan `Empty` row selalu statis tanpa hover di `pendaftaran/kunjungan-per-poli.tsx:122`, `master/poliklinik/show.tsx:97`, `consent-templates.tsx:149`, dan `laporan/agregat.tsx:137`).
3. **[P1 — Tombol `secondary` Menghilang Saat Hover di Dalam Panel `bg-neutral-50`] (`components/ui/button.tsx:12`):**
   - `variant="secondary"` menggunakan `bg-surface hover:bg-neutral-50`. Saat diletakkan di dalam header akordeon/panel `bg-neutral-50` (`rekam-medis.tsx:115`, `pemeriksaan/show.tsx:1619`), latar tombol lenyap menyatu dengan panel saat di-hover! *Solusi:* Ubah hover `secondary` ke `hover:bg-neutral-100 border-neutral-200 hover:border-neutral-300`.
4. **[P2 — Hover Mati pada `FollowUp` Card & Grafik Dasbor]:**
   - Teruskan prop `href` langsung ke `<StatCard>` di `components/dashboard/follow-up.tsx:20` agar state hover kartu aktif secara konsisten.

---

## 3. Standarisasi Global Card Message / Alert / Toast Notification

### 3.1 Temuan Audit Sistem Umpan Balik Saat Ini
1. **Cacat Logika Komponen `<Alert>` (`resources/js/components/ui/alert.tsx:20–43`):**
   - Baris 24–26: `isCritical = variant === 'destructive' || variant === 'warning'`, dan tombol **Close (X)** hanya dirender apabila `closeLabel` terisi (`dismissLabel ?? (duration ? 'Tutup pesan' : undefined)`).
   - Akibatnya, **seluruh `<Alert variant="default">`, `<Alert variant="warning">`, dan `<Alert variant="destructive">` di aplikasi TIDAK MEMILIKI tombol Close (X) dan TIDAK auto-dismiss** kecuali developer menulis prop manual!
   - Varian `success` (`alert.tsx:12`) memiliki kelas CSS yang 100% identik dengan `default` (`border-neutral-200 bg-neutral-50`), tanpa aksen hijau/emerald.
   - Tombol Close `hover:bg-neutral-200` bertabrakan secara visual di dalam alert merah/kuning pada *dark mode*.
2. **Konflik Konfigurasi Toast (`sonner.tsx:20` vs `app.tsx:21`) & Hilangnya Flash `warning`/`info`:**
   - `sonner.tsx` menyetel `richColors={false}` sebelum `{...props}`, namun ditimpa `richColors` (`true`) oleh `app.tsx:21`.
   - `HandleInertiaRequests.php:72–75` dan `app-layout.tsx:510–518` hanya meneruskan session flash `success` dan `error`; flash `warning` dan `info` terbuang!
3. **20+ Kotak Pesan Kustom Terfragmentasi di Halaman:**
   - Banyak halaman membuat `<div className="rounded-xl border border-amber-200 bg-amber-50 ...">` atau `<p className="... bg-red-50">` sendiri tanpa tombol Close (X), tanpa *auto-dismiss*, dan tanpa dukungan *dark mode* (antara lain di `master/nakes/index.tsx:47`, `rekam-medis.tsx:116`, `stok/persediaan/show.tsx:43`, `pelayanan/farmasi/show.tsx:224–229, 282`, `pelayanan/farmasi/review.tsx:21–24`, `pelayanan/screening/show.tsx:370, 381, 583, 681–682`, `pelayanan/pemeriksaan/show.tsx:1198–1199, 1253, 1499, 1672, 1871–1900`).

### 3.2 Spesifikasi Standar Tunggal `Alert` & `GlobalToast`
1. **Satu Standar `<Alert>` (`resources/js/components/ui/alert.tsx`):**
   - Mendukung 4 varian semantik jelas (lengkap light & dark mode): `success` (emerald), `info`/`default` (blue/neutral), `warning` (amber), dan `destructive`/`error` (red).
   - **Tombol Close (X) Manual WAJIB Aktif Secara Default (`dismissible = true`)** pada seluruh varian, dengan warna hover mengikuti varian (`hover:bg-black/5 dark:hover:bg-white/10`).
   - **Auto-Dismiss Terstandar (dengan jeda otomatis saat *hover* / *focus*):**
     - `success` & `info`: **4.500 ms (4,5 detik)**.
     - `warning`: **7.000 ms (7 detik)**.
     - `destructive` / `error`: **8.000 ms (8 detik)** untuk pesan umpan balik aksi/flash; khusus *callout* keselamatan klinis permanen (seperti alergi obat pasien atau penguncian status hukum persetujuan umum) dapat menyetel `persistent={true}` tetapi tetap rapi atau dapat diciutkan sesuai konteks.
2. **Satu Standar `<Toaster>` (`sonner.tsx` & `HandleInertiaRequests.php`):**
   - Aktifkan ke-4 kanal session flash (`success`, `error`, `warning`, `info`), pastikan `closeButton={true}` selalu tampil di kanan atas tiap toast, durasi `4500` ms untuk `success`/`info` dan `7000` ms untuk `warning`/`error` (kecuali `419` sesi berakhir dengan tombol aksi `Muat ulang`), serta migrasi seluruh 20+ kotak pesan kustom di halaman ke komponen `<Alert>` standar.

---

## 4. Audit & Pemetaan Standarisasi Seluruh Tombol (Button System)

### 4.1 Aturan Baku Sistem Tombol Klinik Pratama
1. **Header Halaman (`PageHeaderWithActions`):**
   - Judul halaman (`<h2>`) & subjudul di sebelah kiri; grup aksi kontekstual & tombol `Kembali` (`variant="secondary"`, ikon `<ArrowLeft className="size-4" />`) maupun tombol utama `+ Tambah ...` (`variant="default"`, tinggi `h-10` seragam) **selalu berada sejajar di sebelah kanan header**.
   - **DILARANG** meletakkan tombol melayang di atas judul halaman atau berupa teks bergaris bawah polos di bawah subjudul.
2. **Footer Eksekusi Formulir (`FormActionFooter`):**
   - Seluruh tombol eksekusi utama (`Simpan`, `Simpan Register`, `Simpan Perubahan`, `Konfirmasi`, `Terbitkan`, `Kirim`) **WAJIB berada di sebelah kanan (`flex flex-wrap items-center justify-end gap-3`)**, dengan urutan `[Batal (secondary)] [Simpan/Kirim (primary)]`.
3. **Aksi Baris Tabel:**
   - Menggunakan komponen ikon ringkas ber-tooltip `<ActionButton>` di dalam `<div className="flex items-center justify-center gap-1.5">` (tidak mencampur tombol teks lebar dengan tombol ikon dalam satu sel).

### 4.2 Daftar Lengkap Halaman yang Wajib Diperbaiki Penempatan Tombolnya

| Prioritas | Halaman & File Path | Tombol Bermasalah Saat Ini | Perbaikan Standarisasi |
| :--- | :--- | :--- | :--- |
| **P0 (Wajib Eksplisit)** | `persuratan/pelepasan.tsx:62, 30, 32` | • `Simpan register` (`line 62`) rata kiri di bawah form!<br>• `Profil pasien` (`line 30`) ghost melayang di atas judul!<br>• `Permintaan baru` (`line 32`) lepas di atas isi card. | Pindahkan `Simpan register` ke `FormActionFooter` (`justify-end` kanan); pindahkan `Kembali` ke kanan `PageHeaderWithActions`; pindahkan `Permintaan baru` ke slot kanan `CardHeader`. |
| **P0 (Wajib Eksplisit)** | `pelayanan/kunjungan/general-consent.tsx:36, 62–63` | • `Register pelepasan informasi` (`line 36`) muncul sembarangan di paling atas sebelum judul halaman!<br>• `Batal` & `Konfirmasi keputusan pasien` (`lines 62–63`) rata kiri! | Tata ulang `Register pelepasan informasi` bersama tombol `Kembali` di grup kanan `PageHeaderWithActions`; pindahkan tombol submit & batal ke `FormActionFooter` (`justify-end`). |
| **P0** | `pelayanan/farmasi/show.tsx:201, 260` | • `Riwayat review dan penyerahan` (`line 201`) melayang di atas judul halaman (identik bug `general-consent.tsx`)!<br>• `Review resep oleh apoteker` (`line 260`) lepas di dalam `CardContent`. | Pindahkan `Riwayat pengkajian & penyerahan` ke grup aksi kanan `PageHeaderWithActions`; pindahkan tombol pengkajian resep ke kanan `CardHeader`. |
| **P1** | `pelayanan/farmasi/review.tsx:22, 44` | `Kembali` di bawah subjudul tanpa ikon (`line 22`) & `Simpan review` rata kiri (`line 44`). | Gunakan `PageHeaderWithActions` (dengan `<ArrowLeft />`) + `FormActionFooter` (`justify-end`). |
| **P1** | `master/nakes/kualifikasi-farmasi.tsx:21, 35` | `Kembali ke tenaga kesehatan` tanpa ikon di bawah subjudul (`line 21`) & `Simpan hasil verifikasi` rata kiri (`line 35`). | Gunakan `PageHeaderWithActions` + `FormActionFooter` (`justify-end`). |
| **P1** | `pelayanan/pemeriksaan/procedure-consent.tsx:812–819` & `procedure-signature.tsx:68–79` | `Simpan keputusan tindakan`, `Buat QR tanda tangan`, dan `Konfirmasi dan arsipkan PDF` rata kiri; tombol `Kembali` berada di bawah kartu tanpa ikon. | Pindahkan `Kembali` ke kanan header; rata-kanankan seluruh tombol aksi & submit dengan `FormActionFooter` (`justify-end`). |
| **P1** | `setting/index.tsx:83` & `profile/show.tsx:187–229` | `Simpan logo` rata kiri di samping file input; 4 kartu kanan `profile/show.tsx` memakai tombol `w-full` sementara kartu kiri `justify-end`. | Seragamkan seluruh kartu form pada Profil Akun & Pengaturan Klinik menggunakan `FormActionFooter` (`justify-end`). |
| **P1** | `pelayanan/pemeriksaan/show.tsx:1117` & `persuratan/cetak.tsx:194` | `Mulai bantuan draf` rata kiri; `Batalkan dokumen` melayang di bawah pratinjau kertas A4 (`cetak.tsx:194`). | Rata-kanankan submit form bantuan; pindahkan `Batalkan dokumen` ke toolbar aksi atas `persuratan/cetak.tsx`. |
| **P1** | **Penyeragaman Tombol `Back` di 10 Halaman Lain:**<br>`pelayanan/pasien/show.tsx:99`, `rekam-medis.tsx:86`, `master/poliklinik/show.tsx:60`, `master/obat/stok.tsx:17`, `master/terminologi/show.tsx:47`, `pendaftaran/pendaftaran-baru.tsx:98`, `pelayanan/pemeriksaan/show.tsx` *(tambah tombol Back)* | Terpecah menjadi 4 pola berbeda (ghost di atas judul, secondary di atas judul, link garis bawah polos tanpa komponen `Button`, atau tidak ada tombol Back). | Seragamkan 100% halaman ke **Pola Standar Tunggal**: `<Button variant="secondary" asChild><Link href={backUrl}><ArrowLeft className="size-4" />Kembali</Link></Button>` di sisi kanan `PageHeaderWithActions`. |
| **P2** | `pelayanan/pasien/index.tsx:140`, `consent-templates.tsx:133–146`, `stok/persediaan/show.tsx:46` | Tombol header memakai `size="sm"` sendiri (`pasien/index.tsx`); sel aksi tabel memakai tombol teks lebar. | Gunakan ukuran standar `default` (`h-10`) untuk CTA utama header & seragamkan aksi baris tabel ke `<ActionButton>`. |

---

## 5. Audit & Standarisasi Card Total (KPI Summary) pada Seluruh Laporan & Dashboard

### 5.1 Penambahan Wajib Card Total pada Laporan Pengkajian Awal (`pelayanan/screening/history.tsx`)
Saat ini `resources/js/pages/pelayanan/screening/history.tsx` (`ScreeningController::history` & `AggregateClinicalReportController`) memiliki **0 Card Total**, berbeda mencolok dari Laporan Tindakan Perawat (`pelayanan/tindakan/report.tsx` — 4 card) dan Laporan Diagnosis Terbanyak (`pendaftaran/laporan-top-diagnosa.tsx`).

**Spesifikasi 4 Card Total Baru pada Laporan Pengkajian Awal (Reaktif terhadap Filter Aktif):**
1. **Total Pengkajian Awal:** Jumlah seluruh pengkajian pada periode & filter aktif (`icon: ClipboardCheck`).
2. **Pengkajian Terkirim (Final):** Jumlah pengkajian yang telah diserahkan ke ruang periksa dokter (`submitted_at IS NOT NULL`, `icon: CheckCircle2`).
3. **Prioritas Segera & Darurat:** Jumlah pasien dengan triase `segera` atau `darurat` (`icon: AlertTriangle`).
4. **Draf Pengkajian:** Jumlah pengkajian yang masih berstatus draf (`submitted_at IS NULL`, `icon: Clock3`).

### 5.2 Audit Menyeluruh Seluruh Card Total di Laporan & Dashboard Lainnya

| Halaman & File Path | Masalah KPI / Card Total Saat Ini | Standarisasi & Perbaikan Proporsi |
| :--- | :--- | :--- |
| **Diagnosis Terbanyak (Dokter)**<br>`dokter/top-diagnoses.tsx` | **0 Card Total!** Tidak konsisten dengan `pendaftaran/laporan-top-diagnosa.tsx` yang memiliki KPI card, pencarian, ekspor Excel, dan kolom persentase `%`. | Tambahkan **3 Card Total** yang konsisten (`Jenis Diagnosis Unik`, `Total Kasus Diagnosis`, `Diagnosis Terbanyak #1`) + lengkapi filter pencarian, kolom `%`, dan tombol `Ekspor Excel` di kedua halaman Diagnosis Terbanyak. |
| **Laporan Kunjungan (Pendaftaran)**<br>`pendaftaran/laporan-kunjungan.tsx` | **0 Card Total!** Tidak konsisten dengan `laporan/kunjungan.tsx` milik Admin yang memiliki 3 KPI card. | Tambahkan **4 Card Total** reaktif filter (`Total Kunjungan`, `Kunjungan Selesai`, `Menunggu / Sedang Dilayani`, `Dibatalkan`) pada grid `sm:grid-cols-2 xl:grid-cols-4`. |
| **Log Aktivitas & Audit Bantuan**<br>`admin/audit-bantuan.tsx` | **0 Card Total!** | Tambahkan **3 Card Total** reaktif filter (`Total Kejadian Audit`, `Akses Tercatat Berhasil`, `Akses Ditolak / Gagal`). |
| **Laporan Stok Obat**<br>`laporan/stok.tsx` | Hanya 2 card (`Total item`, `Stok menipis`). | Lengkapi menjadi **4 Card Total** proporsional (`Total Jenis Item`, `Layak Pakai Aman`, `Stok Menipis / Habis`, `Mendekati Kedaluwarsa ≤ 90 Hari`) + reaktif terhadap filter Poli/Depo. |
| **Kunjungan per Poliklinik**<br>`pendaftaran/kunjungan-per-poli.tsx:70` | **Angka KPI Tidak Mengikuti Filter!** Saat petugas memilih `Poli Gigi`, tabel berubah ke Poli Gigi namun ke-4 Card Total di atas tetap menghitung total gabungan semua poli (`RegistrationController.php:610–617`)! | Perbaiki query statistik di `RegistrationController::kunjunganPerPoli()` agar menghitung sesuai `poliklinik_id` dan `tanggal` aktif. |
| **Mutasi & Stok Farmasi**<br>`stok/persediaan/index.tsx:30` | **Angka KPI Statis!** Memilih filter `jenis=obat` atau kondisi stok tidak memperbarui `stats` (`PersediaanController.php:37–41`), serta belum tersaring per depo poli farmasi. | Hitung `stats` berdasarkan cakupan depo poli aktif dan filter yang relevan. |
| **Kunjungan Dokter (`!isReport`)**<br>`dokter/appointments.tsx:63` | **Angka KPI Mengabaikan Filter Periode!** Mengubah `DateRangePicker` memperbarui tabel, tetapi Card Total tetap membeku pada angka sepanjang waktu (`DokterDashboardController.php:133–143`). | Ikatkan query `stats` pada `$base` query yang sudah memfilter periode `dari`–`sampai` dan status. |
| **Dasbor Perawat & Dasbor Farmasi**<br>`perawat/dashboard.tsx:51` & `farmasi/dashboard.tsx:43` | **Cacat Tata Letak 5 Kartu (`.balanced-stats`):** Karena `.balanced-stats` memakai `flex: 1 1 16rem`, penempatan **5 kartu** menghasilkan 4 kartu di baris atas dan **1 kartu raksasa melebar penuh sendirian di baris kedua** pada layar desktop (`1280px–1536px`)! | Gunakan grid eksplisit terukur (`grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5` dengan teks ringkas proporsional, atau kelompokkan 4 KPI utama di baris atas `xl:grid-cols-4` dan metrik pendamping di baris kedua bersama kartu tindak lanjut) agar tidak pernah ada kartu tunggal yang melar jomplang. |
