# 03 — AUDIT FRONTEND, KONSISTENSI UI/UX & MINI DESIGN SYSTEM GUIDELINE
**Perspektif Tim:** *Senior Frontend & UI/UX Design System Lead* + *Clinical Informatics Specialist* + *QA Lead*  
**Stack:** React 19.3, TypeScript 5.9, Inertia.js 3.8, Tailwind CSS v4.0, Radix UI, Lucide Icons, Plus Jakarta Sans Variable (`resources/css/app.css`, `resources/js/**`, `resources/views/**`).

---

## 1. Evaluasi Fondasi Design System, Token & Cacat Kontras Dark Mode

### 1.1 Kekuatan Eksisting yang Sudah Sangat Baik
* **Migrasi Murni Tailwind CSS v4:** Seluruh direktori `resources/` **100% bersih dari utilitas usang Tailwind v3** (0 kemunculan `flex-shrink-*`, `bg-opacity-*`, `overflow-ellipsis`, dll.), telah menggunakan `@import "tailwindcss"` dan deklarasi CSS-first `@theme` / `@theme inline`.
* **Tipografi & Hierarki Radius Konsisten:** Mengusung *self-hosted* `Plus Jakarta Sans Variable` dengan disiplin angka `tabular-nums` pada metrik klinis serta skala sudut 4 tingkat yang rapi (`rounded-2xl` kartu/dialog, `rounded-xl` sub-panel/alert, `rounded-lg` kontrol input/tombol, `rounded-md` tombol aksi tabel).
* **Bebas `window.confirm()`:** Seluruh dialog konfirmasi telah menggunakan komponen Radix `ConfirmDialog` (`resources/js/components/ui/confirm-dialog.tsx`).

### 1.2 Daftar Masalah Token CSS & Dark Mode (Aktif Terekspos ke Pengguna!)
Tombol `ThemeToggle` (`components/ui/theme-toggle.tsx`) ditampilkan secara aktif di Topbar seluruh role (`layouts/app-layout.tsx:621`) maupun halaman Login (`pages/auth/login.tsx:66`), serta `resources/views/app.blade.php:9-15` otomatis mengaktifkan class `.dark` mengikuti preferensi OS Windows/macOS petugas. Namun, mekanisme inversi warna otomatis di `resources/css/app.css:108-265` menimbulkan **5 cacat visual nyata**:

| Keparahan | Lokasi File & Baris | Bukti Ketidakkonsistenan / Cacat Visual | Solusi *Surgical* Tanpa Merusak Tes SSR |
| :--- | :--- | :--- | :--- |
| **`[HIGH]`** | `resources/css/app.css:165, 183` + `components/ui/alert.tsx:10-11` + `screening/show.tsx:642` + `pemeriksaan/show.tsx:1203-1204` | **Gagal Kontras WCAG AA pada Peringatan Alergi & Error di Dark Mode (~2,1:1):** Di `.dark`, `--color-amber-200` dan `--color-red-200` digelapkan ke warna cokelat/merah tua (`42% mix surface`), sementara komponen memakai `dark:bg-amber-950/30 dark:text-amber-200` dan `dark:bg-red-950/30 dark:text-red-200` -> teks gelap di atas latar hitam pekat! | Ubah `dark:text-red-200` / `dark:text-amber-200` menjadi `dark:text-red-300` / `dark:text-amber-300` (atau hapus override `dark:` manual karena `bg-red-50 text-red-900` sudah otomatis di-remap dengan kontras tinggi oleh `app.css`). |
| **`[HIGH]`** | `resources/css/app.css:162-196` vs `253-264` + `pages/pelayanan/screening/print.tsx:81` | **Banner Putih-di-Atas-Pink Tak Terbaca (~1,1:1):** `app.css:253-264` menerangkan `.text-rose-900` (serta `cyan`, `teal`, `sky`) menjadi hampir putih di `.dark`, tetapi lupa menggelapkan `--color-rose-50..200`! Akibatnya banner *"KUNJUNGAN DIBATALKAN"* (`bg-rose-50 text-rose-900`) lenyap tak terbaca di layar mode gelap. | Tambahkan definisi `.dark` untuk `--color-rose-50..200`, `--color-cyan-50..200`, `--color-teal-50..200`, dan `--color-sky-50..200` di `resources/css/app.css:196`. |
| **`[MEDIUM]`** | `resources/css/app.css:5, 9` | **Impor CSS Livewire Flux Tidak Terpakai (1.162 Baris):** Mengimpor `../../vendor/livewire/flux/dist/flux.css` pada aplikasi murni React + Inertia, menambah beban CSS dan menimpa variabel `--color-accent`. | Hapus baris `@import '../../vendor/livewire/flux/dist/flux.css'` beserta `@source` Flux di `app.css:5,9` dan bersihkan class mati `.badge-green..indigo` di `app.css:340-384`. |
| **`[MEDIUM]`** | `components/admin/record-details.tsx:11`, `laporan/stok.tsx:57`, `master/nakes/index.tsx:53,69`, `farmasi/show.tsx:190,229`, `rekam-medis.tsx:149`, `tindakan/show.tsx:79`, `users/index.tsx:139` | **10 Pemakaian `text-muted-foreground` Tanpa Definisi Token:** `--color-muted-foreground` tidak pernah didefinisikan di `app.css`, sehingga teks sekunder/timestamp dirender hitam pekat setara teks utama. | Tambahkan `--color-muted-foreground: var(--clinic-neutral-500);` di dalam blok `@theme inline` pada `resources/css/app.css:108`. |
| **`[MEDIUM]`** | `pages/pelayanan/farmasi/review.tsx:35`, `pemberian-obat/form.tsx:55`, `pemeriksaan/show.tsx:1434`, `odontogram-fdi.tsx:532` | **Inversi Ganda `dark:neutral-*`:** Karena `--color-neutral-50..950` sudah otomatis dibalik pada `.dark`, penulisan `dark:border-neutral-800` justru menghasilkan garis pembatas putih menyilaukan, dan badge gigi aktif `dark:bg-neutral-100` berbalik menjadi gelap. | Hapus prefix `dark:` pada utilitas `neutral-*` di keempat lokasi tersebut agar mengikuti sistem inversi tunggal `app.css`. |

---

## 2. Daftar Ketidakkonsistenan Komponen, Tabel & Proporsionalitas Layout

### 2.1 Tabel Global Rata Tengah (`text-center`) & Pemintas Komponen Form
1. **`[HIGH]` Seluruh Kolom Tabel Default `text-center` (`resources/js/components/ui/table.tsx:25, 29`):**
   - `TableHead` dan `TableCell` memaksakan class `text-center` secara global. Akibatnya, nama pasien, alamat, email, nama obat, keluhan, dan diagnosis di `visit-table.tsx`, `pendaftaran/dashboard.tsx`, `screening/index.tsx`, `pemeriksaan/index.tsx`, `master/obat/index.tsx`, `master/nakes/index.tsx`, dan `users/index.tsx` tampil rata tengah dan sulit dipindai cepat oleh mata petugas!
2. **`[HIGH]` Layout Checkbox Rusak pada 5 Halaman Kepatuhan/Farmasi:**
   - File terdampak: `pelayanan/farmasi/show.tsx:185-186`, `pelayanan/farmasi/review.tsx:25`, `farmasi/pemberian-obat/form.tsx:57`, `master/nakes/kualifikasi-farmasi.tsx:26`, dan `persuratan/pelepasan.tsx:51` (serta raw checkbox di `pendaftaran-baru.tsx:197` dan `medical-letter-fields.tsx:44-45`).
   - Kelima halaman membungkus `<input type="checkbox">` mentah di dalam `<Field label="...">`. Karena `<Field>` (`components/ui/field.tsx:37`) adalah `grid gap-2` vertikal, teks pernyataan tampil penuh di baris atas lalu kotak *checkbox* kecil browser muncul sendirian di baris bawahnya! Wajib diganti dengan komponen `<Checkbox>` (`components/ui/checkbox.tsx`) dalam baris fleksibel horizontal (`flex items-start gap-2.5`).
3. **`[MEDIUM]` Badge Status Antrean Monokrom (`resources/js/components/ui/badge.tsx:9-15`):**
   - Varian `waiting`, `screening`, `examination`, `pharmacy`, dan `complete` semuanya dipetakan ke string class yang persis sama (`'text-neutral-700'`). Petugas tidak dapat membedakan tahap layanan pasien secara sekilas warna di tabel antrean padat.
4. **`[MEDIUM]` Tombol Utama `ConfirmDialog` Setara dengan Tombol Batal (`components/ui/confirm-dialog.tsx:81-86`):**
   - Untuk aksi non-destruktif (*Mulai pemeriksaan*, *Lanjutkan*), tombol konfirmasi dan tombol *"Batal"* sama-sama dirender dengan `variant="secondary"`, menghilangkan hierarki aksi primer vs sekunder.
5. **`[MEDIUM]` Duplikasi Landmark `<h1>` & Inkonsistensi Header pada 10 Halaman:**
   - `AppLayout` (`layouts/app-layout.tsx:564`) sudah me-render `<h1 className="sr-only">`. Sebanyak ~45 halaman sudah tertib menggunakan `<h2>` (`text-2xl font-semibold tracking-tight`), namun 10 halaman melanggar standar ini dengan membuat `<h1>` kedua atau ukuran berbeda: `persuratan/index.tsx:118`, `persuratan/terbitkan.tsx:32`, `persuratan/pelepasan.tsx:29`, `pemeriksaan/diagnosis-correction.tsx:55` (`text-xl`), `pelayanan/farmasi/review.tsx:20`, `master/nakes/kualifikasi-farmasi.tsx:20`, `farmasi/pemberian-obat/form.tsx:39`, `farmasi/pasien-kunjungan.tsx:15`, `stok/persediaan/show.tsx:42`, dan `pelayanan/pasien/show.tsx:99`.

### 2.2 Proporsionalitas Viewport pada Laptop Klinik (1280px – 1366px) & Fragmentasi Lebar Form
* **Matematika Ruang Layar Aktual:** Dengan *sidebar* terbuka (`lg:pl-[19rem]` = `304px` di `layouts/app-layout.tsx:539`) ditambah *padding* utama (`lg:p-8` = `64px`), layar laptop klinik standar **1280px** hanya menyisakan **`912px`** area konten, dan layar **1366px** menyisakan **`998px`**.
* **`[HIGH]` *Split-Grid Squeeze* Parah di Dashboard & Workspace:**
  - `pages/pendaftaran/dashboard.tsx:68, 82` dan `pages/dokter/dashboard.tsx:52, 74` menerapkan `xl:grid-cols-[minmax(16rem,0.75fr)_minmax(0,1.5fr)]` mulai `1280px`, yang menyempitkan kartu kanan menjadi hanya **`592px`** padahal di dalamnya terdapat tabel dengan `min-w-[980px]` (**terpotong horizontal 388px!**).
  - `pages/pelayanan/pemeriksaan/show.tsx:1413` dan `components/anatomy/physical-examination-workspace.tsx:198` membelah `xl:grid-cols-2` pada `1280px` menjadi ~`420px` per kolom dengan tinggi paksa `xl:min-h-[58rem]`, membuat diagram `OdontogramFdi` (`w-[440px]`) terhimpit.
* **Fragmentasi Lebar Kontainer Form:** Form `pendaftaran-baru.tsx:94` dan `pelayanan/farmasi/show.tsx:133` melar penuh hingga `1600px` (~`750px` per kolom input!), sementara halaman sejenis memakai `max-w-5xl` (`screening/show.tsx`), `max-w-4xl` (`setting/index.tsx`), dan `max-w-3xl` (`farmasi/review.tsx`).

### 2.3 File Komponen Monolitik & Dampaknya pada Kecepatan Ketik Dokter
* `resources/js/pages/pelayanan/pemeriksaan/show.tsx` mencapai **2.075 baris** dengan **21 instance `useForm()`** dan **26 `useState`** dalam satu komponen tunggal! Setiap ketikan huruf pada kotak Anamnesis atau SOAP memicu *re-render* seluruh 2.075 baris pohon komponen beserta kalkulasi ulang narasi odontogram dan ringkasan pemeriksaan.
* File besar lain yang perlu dipecah secara modular: `procedure-consent.tsx` (**934 baris**), `odontogram-fdi.tsx` (**739 baris**), `screening/show.tsx` (**727 baris**), dan `layouts/app-layout.tsx` (**631 baris**).

---

## 3. Audit Ergonomi UX Klinis untuk Tenaga Kesehatan (*Minim Klik & Anti-Human-Error*)

1. **`[CRITICAL UX]` Identitas Pasien, Alergi & TTV Hilang Saat *Scrolling* (Tidak Ada *Sticky Patient Banner*):**
   - Di `screening/show.tsx:600-642`, `pemeriksaan/show.tsx:1104-1141`, dan `farmasi/show.tsx:135-151`, kartu identitas pasien, peringatan alergi obat, berat badan (`BB`), serta hasil TTV perawat berada di bagian paling atas halaman statis.
   - Begitu dokter menggulir ke bawah menuju tab **Diagnosis**, **Odontogram**, atau **Resep** (`#tab-resep`, baris 1812), **seluruh informasi Nama Pasien, Usia, Berat Badan, Tekanan Darah, dan Banner Alergi hilang dari layar** — meningkatkan risiko *wrong-patient error* atau salah dosis saat dokter berganti pasien di antrean ramai!
2. **`[CRITICAL UX]` Nol Proteksi *Unsaved Changes* Saat Pindah Halaman:**
   - Tidak ada satu pun listener `beforeunload` maupun `router.on('before')` di seluruh codebase frontend. Di dalam kartu pasien `pemeriksaan/show.tsx:1107`, tombol `"Riwayat RME"` adalah `<Link>` Inertia biasa: jika dokter yang sedang mengetik SOAP panjang mengklik `"Riwayat RME"` atau menu sidebar, **seluruh ketikan yang belum disimpan langsung hilang tanpa peringatan**!
3. **`[HIGH UX]` Pajak Klik Modal Konfirmasi Berlebihan pada Penyimpanan Draf Rutin:**
   - Fungsi `confirmSave()` di `pemeriksaan/show.tsx:414-416` memunculkan modal `AlertDialog` (*"Simpan data ini?"*) untuk **setiap aksi simpan draf biasa** (simpan anamnesis, pemeriksaan fisik, tambah diagnosis, tambah resep, tambah temuan gigi).
   - Mencatat **1 temuan Body Map** membutuhkan **2 modal konfirmasi berturut-turut**; mencatat **1 gigi pada Odontogram** membutuhkan **5–6 klik** karena dipisah ke 2 sub-tab (`Gigi dan permukaan` -> klik *Lanjut* -> `Catatan dan simpan` -> klik *Simpan* -> klik modal konfirmasi)!
   - Selain itu, klik tombol bawah `"Lanjut ke ..."` otomatis menyimpan draf (`advanceSection`, baris 919), sedangkan klik tab atas `<TabsTrigger>` (baris 1280) pindah tab **tanpa menyimpan**, memicu kebingungan dokter karena muncul peringatan draf belum tersimpan.
4. **`[HIGH UX]` Pencarian ICD-10, ICD-9-CM & Pemilihan Obat Belum Ramah Keyboard:**
   - Pencarian ICD-10 (`#icd-search` di `pemeriksaan/show.tsx:1610, 1701`) dan ICD-9-CM (`procedure-coding-review.tsx:49`) merender `<div>` daftar tombol biasa tanpa `role="combobox"`, tanpa penutupan `Escape`/klik luar, dan tanpa navigasi `ArrowDown` / `ArrowUp` / `Enter` (menekan `Enter` justru men-submit form!).
   - Pemilihan obat resep klinik (`pemeriksaan/show.tsx:1815` & `pemberian-obat/form.tsx:52`) memakai Radix `<Select>` tanpa kolom ketik cari (*search filter*), menyulitkan dokter mencari 1 obat di antara ratusan item formularium.
5. **Indikator Loading, Toast Ganda & Cetakan A4:**
   - Komponen `components/ui/spinner.tsx` memiliki **0 import** di seluruh proyek; tombol kritis seperti *"Tandatangani pemeriksaan"* (`pemeriksaan/show.tsx:1949`) dan *"Serahkan obat & selesaikan"* (`farmasi/show.tsx:190`) tidak menampilkan indikator proses.
   - `AppLayout` (`L411`) dan `RequestFeedback` (`L26`) sama-sama mendaftarkan `router.on('error')`, memunculkan **dua toast error kembar** saat validasi gagal tanpa field terfokus.
   - Pada `persuratan/cetak.tsx:154`, elemen `<span style={{float:'right'}}>` di daftar resep tidak memiliki *clearfix* / `flex justify-between`, dan `.rme-print-map-card` (`app.css:488`) mematok `min-height: 158mm` yang memotong halaman cetak A4 terlalu dini.

---

## 4. Pedoman Standar UI (*Mini Design System Guideline*)

Pedoman ini wajib dipatuhi saat eksekusi refactoring Fase 3 agar seluruh halaman konsisten **tanpa merusak satu pun dari 312 pengujian SSR di `tests/frontend-render.test.mjs`** (pertahankan seluruh `id`, `aria-label`, `href`, nama export, dan teks label tombol utama):

### 4.1 Aturan Warna, Token & Dark Mode
1. **Gunakan Skala Netral Otomatis Tanpa Override `dark:` Ganda:** Gunakan `bg-canvas`, `bg-surface`, `text-neutral-950` (utama), `text-neutral-700` (label/subjudul), `text-neutral-500` / `text-muted-foreground` (keterangan sekunder), dan `border-neutral-200` **tanpa** menambahkan `dark:border-neutral-800` atau `dark:text-neutral-*`.
2. **Warna Semantik Klinis yang Terkendali:**
   - **Status Antrean (`ui/badge.tsx`):**
     - `waiting` (Menunggu): `border-neutral-300 bg-neutral-100 text-neutral-700`
     - `screening` (Pengkajian): `border-sky-300 bg-sky-50 text-sky-900`
     - `examination` (Diperiksa / Tindakan): `border-blue-300 bg-blue-50 text-blue-900`
     - `pharmacy` (Farmasi): `border-violet-300 bg-violet-50 text-violet-900`
     - `complete` (Selesai): `border-emerald-300 bg-emerald-50 text-emerald-900`
   - **Alert Keselamatan Klinis (`ui/alert.tsx`):** `destructive` (Alergi Obat / Triase Merah / Error Kritis) dan `warning` (Perhatian / Hamil-Menyusui / Near-Expiry) menggunakan teks `dark:text-red-300` / `dark:text-amber-300` dengan rasio kontras minimum **>= 4,5:1** pada mode terang maupun gelap, serta **dilarang memakai `autoDismissMs`** untuk error klinis/stok.

### 4.2 Standar Struktur Halaman, Kontainer & Tabel
1. **Header Halaman Seragam (`<PageHeader>`):** Di dalam `AppLayout`, judul halaman visual **wajib memakai `<h2>`** (`text-2xl font-semibold tracking-tight text-neutral-950`), diawali *eyebrow* kategori (`text-sm font-medium text-neutral-700`), deskripsi singkat (`mt-1 text-sm text-neutral-500`), dan tombol aksi rata kanan (`sm:flex-row sm:items-end justify-between`).
2. **Standar 3 Tingkat Lebar Kontainer:**
   - **Antrean, Dashboard & Workspace Split:** `max-w-[1600px]` (`max-w-none` khusus workspace pemeriksaan multi-panel).
   - **Form Klinis & Detail Pelayanan** (`pendaftaran-baru`, `screening/show`, `tindakan/show`, `farmasi/show`, `procedure-consent`, `pemberian-obat/form`): `mx-auto max-w-5xl space-y-6`.
   - **Form Pengaturan / Review Fokus Tunggal** (`setting/index`, `farmasi/review`, `diagnosis-correction`): `mx-auto max-w-4xl space-y-6`.
3. **Aturan Responsivitas Laptop Klinik (1280px–1366px):** Dilarang menempatkan tabel berlebar `min-w-[>=760px]` di dalam grid 2 kolom pada breakpoint `xl:` (`1280px`). Gunakan breakpoint **`2xl:` (`1536px`)** untuk pembelahan 2 kolom besar di dashboard dan workspace Odontogram/Body Map, serta beri opsi *auto-collapse* sidebar pada halaman pemeriksaan dokter.
4. **Perataan Kolom Tabel (`ui/table.tsx`):**
   - Default `TableHead` & `TableCell`: **`text-left`** (Nama Pasien, No. RM, Poli, Dokter, Obat, Diagnosis, Catatan).
   - Angka kuantitas/metrik (`Stok`, `Jumlah`, `Usia`, `TTV`): **`text-right tabular-nums`** (atau `text-left tabular-nums` bila digabung satuan).
   - Token pendek tetap (`No.`, `Status`, `Prioritas`, `Gigi FDI`, `Aksi`): **`text-center`**.

### 4.3 Standar Komponen Interaksi Nakes
1. **`<PatientContextBar>` Sticky:** Wajib muncul menempel di bawah Topbar (`sticky top-16 lg:top-[4.5rem] z-20`) saat kartu atas pasien tergulir melewati viewport pada halaman Pengkajian, Pemeriksaan, Tindakan, dan Farmasi — menampilkan **Nama Pasien + No. RM + Usia/JK + BB + TTV Ringkas + Lencana Merah Alergi + Lencana Hamil/Menyusui + Status Draf**.
2. **Kebijakan Modal vs Simpan Langsung:**
   - **Tanpa Modal (1 Klik + Spinner + Toast):** Simpan draf SOAP/Anamnesis/Fisik, tambah baris diagnosis/tindakan/resep draf, dan simpan temuan gigi/body map (gabungkan form permukaan & catatan gigi dalam 1 panel tanpa harus pindah sub-tab).
   - **Wajib `ConfirmDialog` (`variant="default"` atau `"destructive"`):** Finalisasi/Tandatangani RME, Kirim Pengkajian ke Dokter, Serahkan Obat Selesai, Batalkan Kunjungan/Surat, Void Entri, dan Pindah Halaman saat ada *unsaved changes*.
3. **`<ClinicalCombobox>` Ramah Keyboard:** Seluruh input pencarian referensi klinis (ICD-10, ICD-9-CM, Pemilihan Obat Resep, Pencarian Pasien/Kepesertaan) wajib mendukung navigasi penuh `ArrowDown`, `ArrowUp`, `Enter` (pilih item tanpa men-submit form induk), `Escape`, dan klik di luar area untuk menutup daftar.
