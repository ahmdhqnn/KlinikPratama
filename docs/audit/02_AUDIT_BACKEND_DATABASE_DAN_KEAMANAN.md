# 02 — AUDIT BACKEND, DATABASE & KEAMANAN (STAGING & PRODUCTION READINESS)
**Perspektif Tim:** *Principal Backend, Database & Security Architect* + *QA Lead* + *Clinical Compliance Specialist*  
**Cakupan:** 48 Tabel Aktual, 130 Migrasi, 39 Database Triggers, 250+ Rute, Middleware, Policies, Eloquent Models, Query Services, Queue/Backup/Console, dan Konfigurasi Infrastruktur.

---

## 1. Evaluasi Arsitektur Skema Database, Indexing & Portabilitas Engine

### 1.1 Matriks Indeks Database yang Hilang pada Jalur Query Panas (*Hot Paths*)
Berdasarkan pemeriksaan langsung daftar indeks fisik (`Schema::getIndexes()`), **SQLite tidak membuat indeks otomatis pada kolom *Foreign Key***. Saat ini tabel inti `kunjungan` hanya memiliki 3 indeks (`no_kunjungan` UNIQUE, `kontrol_asal_id` UNIQUE, dan `registered_by`) — **sama sekali belum memiliki indeks pada `tanggal`, `status`, `pasien_id`, `poliklinik_id`, `dokter_id`, maupun `rujukan_internal_id`!**

Berikut daftar indeks yang wajib ditambahkan melalui satu migrasi *additive* baru sebelum masuk Staging:

| Tabel | Indeks Eksisting Saat Ini | Indeks Baru yang Wajib Ditambahkan | Endpoint / Service yang Terdampak Langsung (*Full Table Scan* -> *Index Seek*) |
| :--- | :--- | :--- | :--- |
| **`kunjungan`** | `no_kunjungan` (UQ), `kontrol_asal_id` (UQ), `registered_by` | 1. `(tanggal, status, poliklinik_id)`<br/>2. `(status, poliklinik_id)`<br/>3. `(dokter_id, tanggal, status)`<br/>4. `(pasien_id, tanggal, id)`<br/>5. `(rujukan_internal_id, status)` | Dipanggil di **setiap request halaman Inertia** melalui `HandleInertiaRequests.php:88-90, 140-148`, `NurseQueueCounts`, `VisitQueryService`, `DoctorAccessService.php:40-43`, `PasienController.php:264, 437-476`, `PatientAllergyHistory.php:14, 63`. |
| **`pasien`** | `no_rm` (UQ), `nip` (UQ), `kepesertaan_id` (UQ) | 1. `nik` (UNIQUE / `nik_hash` UNIQUE jika dienkripsi)<br/>2. `(deleted_at, nama)` | `PatientRegistrationService.php:60, 88`, `RegistrationController.php:261, 320-325`, `PasienController.php:55-59, 150, 198`, `KunjunganController.php:239`. |
| **`clinical_audit_events`** | `(actor_id, occurred_at)`, `(patient_id, occurred_at)`, `membership_id`, `request_id` | 1. `(occurred_at, id)`<br/>2. `(action, occurred_at, id)`<br/>3. `(kunjungan_id, action, id)` | `AuditLogQuery.php:16-22` (log aktivitas global admin), `AdminAssistanceAuditController.php:42-65`, `AdminClinicalAssistance.php:20-25`. |
| **`stok_mutasi`** | `mutasi_asal_id` | 1. `(obat_id, id)`<br/>2. `(batch_id, id)`<br/>3. `(kunjungan_id)`<br/>4. `(referensi_type, referensi_id)` | `PersediaanController.php:50`, `ObatController.php:133`, `FarmasiController.php:305` (riwayat mutasi obat & kartu stok). |
| **`farmasi`** | `kunjungan_id` (UQ) | 1. `(status, dispensed_at)`<br/>2. `(dispensed_by, dispensed_at)` | `HandleInertiaRequests.php:148`, `FarmasiDashboardController.php:26`, `FarmasiController.php:75-76, 264-269`. |
| **`surat_medis`** | `nomor_surat` | 1. `(kunjungan_id, id)`<br/>2. `(jenis, tanggal)` | `PersuratanController.php:63-70, 162`, `DashboardController.php:52`, `AggregateClinicalReportController.php:66-68`. |
| **`rujukan_internal`** | `nomor_surat` | 1. `(status, ke_poli_id)`<br/>2. `(kunjungan_id)` | `HandleInertiaRequests.php:137-142` (notifikasi badge rujukan masuk pendaftaran). |
| **`jadwal_dokter`** & **`jadwal_khusus_dokter`** | `(dokter_id, poliklinik_id, ...)` (UQ) | `jadwal_dokter`: `(poliklinik_id, hari, is_active)`<br/>`jadwal_khusus_dokter`: `(poliklinik_id, tanggal)` | `JadwalPraktikResolver.php:39-47` (mencari jadwal berdasarkan `poliklinik_id` tanpa `dokter_id`), `PruneExpiredDoctorSchedules.php:18-19`. |
| **Tabel Tata Kelola Klinis (0 Indeks Non-PK)** | Hanya `id` (PK) | - `pharmacy_clinical_reviews`: `(kunjungan_id, id)`<br/>- `pharmacy_staff_credentials`: `(nakes_id, id)`<br/>- `medical_document_verification_events`: `(verification_id, action)`<br/>- `medical_information_release_events`: `(kunjungan_id, id)`<br/>- `procedure_signature_sessions`: `(procedure_id, cancelled_at)` | `PharmacyClinicalReviewService.php:40`, `PharmacyCredentialController.php:25`, `SuratMedis.php:25`, `MedicalInformationReleaseController.php:44`, `ProcedureConsentService.php:107`. |

### 1.2 Portabilitas Engine Database (SQLite vs MySQL/MariaDB/PostgreSQL)
Saat menuju Staging/Produksi, tim infrastruktur harus menetapkan target engine database secara eksplisit:
* **Jika Produksi Menggunakan Single-Node SQLite (Cocok untuk Beban Klinik Internal < 500 Kunjungan/Hari):** Wajib mengaktifkan `PRAGMA journal_mode = WAL` dan `PRAGMA synchronous = NORMAL` di `config/database.php`. Saat ini SQLite berjalan dalam mode `DELETE` yang mengunci seluruh file database setiap kali ada penulisan log `clinical_audit_events` pada request `GET`.
* **Jika Produksi Menggunakan MySQL / MariaDB / PostgreSQL:** Terdapat **3 pemutus kompatibilitas kritis** yang wajib diperbaiki terlebih dahulu (lihat Temuan `[CRITICAL] DB-01` di bawah): driver `'mariadb'` belum ditangani pada 9 migrasi trigger & `EffectiveDiagnosisReport`, `ClinicalBackup` menolak berjalan selain di SQLite, dan `CheckClinicalSecurity` melewatkan pemeriksaan trigger di luar SQLite.
* **Foreign Key `ON DELETE CASCADE` Warisan Awal:** 11 tabel klinis (`kunjungan.pasien_id`, `screening.kunjungan_id`, `pemeriksaan.kunjungan_id`, `diagnosa.pemeriksaan_id`, `resep.kunjungan_id`, `farmasi.kunjungan_id`, `surat_medis.kunjungan_id`, dll.) masih memiliki definisi FK `ON DELETE CASCADE` dari migrasi awal Oktober 2026. Di SQLite saat ini aman karena ditahan trigger `BEFORE DELETE`, namun di MySQL/InnoDB aksi *cascade delete* dari tabel induk **tidak mengeksekusi trigger `BEFORE DELETE` pada tabel anak**. Seluruh FK klinis perlu diseragamkan ke `RESTRICT` saat migrasi ke RDBMS server.

---

## 2. Daftar Lengkap Masalah Teknis & Keamanan (Berlabel Tingkat Keparahan)

### A. Temuan Tingkat `[CRITICAL]` (Wajib Diselesaikan Sebelum Masuk Staging/Produksi)

#### `[CRITICAL] SEC-01` — Celah Non-Repudiasi Tanda Tangan Dokter via Reset Password Admin & Penggantian Gambar Tanda Tangan Tanpa Re-Autentikasi
* **Lokasi Kode:**
  - `app/Http/Controllers/UserController.php:240-253` (`resetPassword`)
  - `app/Http/Controllers/ProfileController.php:68-87` (`updatePassword`) & `138-170` (`uploadSignature`, `deleteSignature`)
  - `app/Http/Controllers/Pelayanan/PemeriksaanController.php:1176` (`signature_password` memakai `current_password`)
  - `app/Http/Controllers/Pelayanan/PasienController.php:628` & `ScreeningController.php:306` (`signatureDataUrl`)
* **Dampak Klinis & Hukum:**
  1. Saat Admin mereset password akun dokter di `UserController@resetPassword`, Admin mengetik sendiri password sementara secara *plaintext* (tanpa verifikasi password Admin sendiri!). Meskipun `must_change_password = true` disetel, Admin yang nakal atau akun Admin yang terkompromi dapat login sebagai dokter tersebut, mengganti password di `/profile/password` menggunakan password sementara yang ia ketahui (otomatis menghapus flag `must_change_password`), lalu **menandatangani finalisasi RME, persetujuan tindakan, atau surat medis atas nama dokter tersebut**!
  2. Pada `ProfileController@uploadSignature` dan `deleteSignature`, tidak ada validasi `current_password`. Lebih fatal lagi, mengganti file `users.signature_path` akan **mengubah tampilan tanda tangan secara surut (*retroactive*) pada seluruh cetakan RME dan pengkajian historis** karena view memanggil path aktif `users.signature_path`, bukan *snapshot* versi tanda tangan saat dokumen disahkan!
  3. Mengubah `session_version` belum menghapus baris sesi aktif di tabel `sessions` secara langsung (`DB::table('sessions')->where('user_id', $user->id)->delete()`).
* **Solusi Refactoring Konkret:**
  1. Pisahkan **PIN/Passphrase Tanda Tangan Klinis** (`signing_pin_hash` pada `users`) dari password login akun. Admin hanya dapat me-*revoke* PIN tanda tangan (tidak pernah bisa mengisinya); dokter wajib mengaktifkan sendiri PIN tanda tangannya setelah verifikasi identitas. Wajibkan juga `current_password` Admin saat mereset password user dan hapus baris `sessions` user terkait.
  2. Wajibkan `current_password` pada `uploadSignature` & `deleteSignature`, simpan file tanda tangan dengan nama berversi imutabel (`signatures/{user_id}/{sha256}.png`), dan simpan referensi path/hash tanda tangan ke dalam payload `ClinicalNoteVersion` serta `ScreeningVersion`.
  3. Perketat aturan password dari `min:8` menjadi `Password::min(12)->mixedCase()->numbers()->max(72)`.

#### `[CRITICAL] SEC-02` — Penyimpanan PII/PHI Sensitif Tanpa Enkripsi *At-Rest* & `SESSION_ENCRYPT=false` (Kepatuhan UU PDP)
* **Lokasi Kode:**
  - `config/session.php:50` & `.env.example:33` (`'encrypt' => env('SESSION_ENCRYPT', false)`)
  - `app/Models/Pasien.php:16-25` (`nik`, `nip`, `telepon`, `alamat`, `nama_ibu`, `nama_wali`, `telepon_wali`, `riwayat_alergi`)
  - `app/Models/Kepesertaan.php:16-26` (`nik`, `nip`, `alamat`, `referensi_bukti`)
  - `app/Http/Controllers/KepesertaanController.php:132-144` (mencatat `nik` & `nip` mentah ke kolom JSON `clinical_audit_events.metadata` saat hapus kepesertaan)
* **Dampak:** Payload sesi di tabel `sessions` tersimpan dalam base64 terbuka. Kebocoran file database atau salinan disk akan mengekspos NIK, nomor telepon, alamat, dan riwayat medis pegawai Kementerian Pariwisata secara langsung (melanggar UU No. 27 Tahun 2022 Pasal 35).
* **Solusi Refactoring Konkret:**
  1. Ubah default `'encrypt' => env('SESSION_ENCRYPT', true)` di `config/session.php:50` dan tambahkan pengecekan `session_encrypted` di `CheckClinicalSecurity --production`.
  2. Terapkan cast `'encrypted'` Laravel pada kolom identitas sensitif (`pasien.nik`, `telepon`, `alamat`, `nama_wali`, `telepon_wali`; `kepesertaan.nik`, `alamat`) yang dipasangkan dengan kolom *blind index* `HMAC-SHA256` (`nik_hash`, `nip_hash` memakai key aplikasi terpisah) agar validasi keunikan dan pencarian eksak tetap secepat indeks B-Tree.
  3. Hapus penulisan `nik`/`nip` mentah pada `KepesertaanController@destroy` ke `clinical_audit_events.metadata` (cukup simpan `membership_id` dan `nik_masked`), serta wajibkan enkripsi disk penuh (BitLocker/LUKS/Cloud Volume Encryption) pada server produksi untuk melindungi tabel narasi klinis.

#### `[CRITICAL] PERF-01` — Loop Verifikasi Hash-Chain Seluruh Histori Secara Sinkron di `EffectiveDiagnosisReport`
* **Lokasi Kode:** `app/EffectiveDiagnosisReport.php:17-23` (dipanggil oleh `RegistrationController.php:648, 693` dan `DokterDashboardController.php:364`).
* **Dampak:** Setiap kali petugas pendaftaran atau dokter membuka halaman/ekspor **Laporan Top Diagnosa** (yang secara default memuat seluruh histori tanpa batas tanggal!), PHP melakukan `chunkById(100)` atas **seluruh baris `Pemeriksaan` yang pernah ditandatangani beserta seluruh JSON `ClinicalNoteVersion`**, melakukan rekursi `ksort`, `json_encode`, dan `hash('sha256')` di memori sebelum menjalankan query agregat SQL! Pada skala > 5.000–10.000 kunjungan, endpoint ini akan memicu lonjakan CPU/RAM dan HTTP 504 Timeout — serta 1 baris historis yang korup akan mematikan seluruh halaman laporan dengan HTTP 409.
* **Solusi Refactoring Konkret:**
  - Karena tabel `clinical_note_versions` sudah dijaga trigger *append-only* (tidak bisa di-UPDATE/DELETE) dan sudah diverifikasi saat penulisan (`ClinicalNoteRecorder::record`), pindahkan verifikasi rantai hash massal ke *scheduled artisan command* malam hari (`clinic:verify-integrity`) dan tetap pertahankan verifikasi sinkron hanya saat membuka/mencetak 1 kunjungan spesifik (`EffectiveClinicalNote`).

#### `[CRITICAL] DB-01` — Ketidakcocokan Engine MariaDB/MySQL/PostgreSQL pada Trigger, Backup & Security Check
* **Lokasi Kode:**
  - `config/database.php:67-85` vs 9 file migrasi trigger (`2026_11_18_215833` s.d. `2026_11_20_231958`) dan `app/EffectiveDiagnosisReport.php:26-31`
  - `app/ClinicalBackup.php:34-36, 151-179`
  - `app/Console/Commands/CheckClinicalSecurity.php:26-45`
* **Dampak:**
  1. Jika server Staging/Produksi memakai `DB_CONNECTION=mariadb`, `php artisan migrate` dan laporan Top Diagnosa langsung gagal total (`RuntimeException` / `LogicException`) karena blok `match ($driver)` hanya mengenali `'sqlite'`, `'mysql'`, `'pgsql'`.
  2. `php artisan clinic:backup` melempar exception jika `DB_CONNECTION !== 'sqlite'`.
  3. `php artisan clinic:security-check` melewatkan seluruh pemeriksaan keberadaan trigger imutabilitas apabila driver bukan `'sqlite'`, memberikan hasil positif palsu (`SUCCESS`).
* **Solusi Refactoring Konkret:**
  - Tambahkan `'mariadb'` bersama `'mysql'` pada seluruh `match ($driver)` di migrasi dan `EffectiveDiagnosisReport.php:28`.
  - Perluas `CheckClinicalSecurity` agar memeriksa `information_schema.triggers` pada MySQL/MariaDB dan `pg_trigger` pada PostgreSQL.
  - Tambahkan dukungan `mysqldump --single-transaction` / `pg_dump` pada `ClinicalBackup` jika target server menggunakan RDBMS eksternal, atau tetapkan standar deployment SQLite + `WAL` terkelola.

---

### B. Temuan Tingkat `[HIGH]` (Wajib Diselesaikan Sebelum Rilis Produksi)

#### `[HIGH] DB-02` — SQLite Berjalan pada Journal Mode `DELETE` (Risiko `SQLITE_BUSY` Saat Jam Sibuk Klinik)
* **Lokasi Kode:** `config/database.php:35-45` (`'journal_mode' => null`, `'synchronous' => null`).
* **Dampak:** Karena tabel `sessions`, `cache`, `jobs`, dan `clinical_audit_events` (yang ditulis pada hampir setiap request `GET` halaman klinis!) berbagi satu file SQLite dengan mode `DELETE`, pembaca dan penulis saling memblokir secara eksklusif. Saat Pendaftaran, Perawat, Dokter Umum, Dokter Gigi, dan Farmasi bekerja bersamaan, request berpotensi tertahan hingga 5 detik dan gagal dengan `database is locked`.
* **Solusi:** Set `'journal_mode' => env('DB_JOURNAL_MODE', 'wal')` dan `'synchronous' => env('DB_SYNCHRONOUS', 'normal')` di `config/database.php:42-43`.

#### `[HIGH] SEC-03` — Enam Salinan Database `.sqlite.bak` (~39,8 MB) Tidak Terenkripsi di Folder `database/`
* **Lokasi File:** `database/database.before-pg09-*.sqlite.bak` s.d. `database/database.pre-dental-physical-summary.sqlite.bak` (6 berkas).
* **Dampak:** Meski diabaikan Git (`database/.gitignore`), keenam salinan penuh database RME ini tersimpan *plaintext* di luar brankas AES-256 `ClinicalBackup` dan rawan ikut tersalin saat deployment `rsync`/ZIP/Docker (belum ada `.dockerignore`).
* **Solusi:** Arsipkan ke brankas terenkripsi di luar workspace lalu hapus keenam file `.sqlite.bak` tersebut, serta buat `.dockerignore` yang mengecualikan `database/*.sqlite*` dan `.env*`.

#### `[HIGH] SEC-04` — Kebocoran Isolasi Admin di `ObatController@stok` & Pencarian Alergi Lintas Pasien di `FarmasiMandiriController`
* **Lokasi Kode:**
  1. `app/Http/Controllers/Master/ObatController.php:155` (`GET /master/obat/{obat}/stok`)
  2. `app/Http/Controllers/FarmasiMandiriController.php:72-87` (`GET /farmasi/pemberian-obat/baru?search=...`)
* **Dampak:**
  1. Berbeda dengan `PersediaanController@show:55` yang menyensor deskripsi mutasi untuk `admin`, `ObatController@stok` menampilkan `$movement->keterangan` mentah (`"Pemakaian KNJ-..."` / `"Pemberian obat langsung kepada pasien RM-..."`). Akibatnya, `admin` dapat mengetahui pasien mana (`no_rm` / `no_kunjungan`) yang menerima obat sensitif tertentu!
  2. Endpoint form pemberian obat langsung mengizinkan akun `farmasi` mana pun (termasuk non-Apoteker/TTK yang sebenarnya ditolak saat `store()`) mencari pasien siapa saja di seluruh klinik dan melihat ekstraksi `riwayat_alergi` dari RME tanpa pencatatan log audit pembacaan.
* **Solusi:** Terapkan *masking* identik `PersediaanController@show:55` pada `ObatController@stok:155` (atau arahkan langsung ke `stok.persediaan.show`); batasi `FarmasiMandiriController@create` hanya untuk Apoteker terverifikasi (`PharmacyStaffAccess`) dan muat alergi hanya saat 1 `pasien_id` dipilih secara eksplisit disertai `ClinicalAuditRecorder::record('direct_pharmacy.patient_lookup', ...)`.

#### `[HIGH] PERF-02` — Ketiadaan Indeks Tabel Inti & Penggunaan `whereDate()` yang Mematikan Indeks B-Tree
* **Lokasi Kode:** `VisitQueryService.php:38`, `AuditLogQuery.php:16-17`, `HandleInertiaRequests.php:88`, `DokterDashboardController.php:38-42`.
* **Dampak:** Selain 18+ indeks yang hilang pada tabel di Bagian 1.1, pemanggilan `whereDate('tanggal', ...)` dan `whereDate('occurred_at', ...)` menghasilkan fungsi SQL `strftime('%Y-%m-%d', col) = ?` / `DATE(col) = ?` yang membuat database tidak bisa memakai indeks kolom tersebut!
* **Solusi:** Buat migrasi penambahan seluruh indeks Bagian 1.1; ganti `whereDate('tanggal', $d)` pada kolom bertipe `DATE` menjadi `where('tanggal', $d)`, dan ganti `whereDate('occurred_at', ...)` pada kolom `DATETIME` menjadi `whereBetween('occurred_at', [$startOfDay, $endOfDay])`.

#### `[HIGH] PERF-03` — Risiko N+1 Query, Hidrasi Memori Tanpa Batas (`FromCollection`) & Disk Hashing Saat Autocomplete
* **Lokasi Kode:**
  1. `app/Providers/AppServiceProvider.php:24-32`: `Model::preventLazyLoading(! $this->app->isProduction())` belum diaktifkan.
  2. `app/Exports/PasienExport.php:15-18` & `ObatExport.php:14-17`: Memakai `FromCollection` (`->get()`) yang memuat seluruh tabel pasien/obat ke RAM.
  3. `app/Http/Controllers/Pelayanan/TindakanPerawatController.php:61`: `printReport()` memanggil `$query->get()` tanpa batas tanggal maupun `limit()`.
  4. `app/Http/Controllers/Stok/PersediaanController.php:34-43` & `PemeriksaanController.php:155`: Memuat seluruh model `Obat` + subquery batch ke memori pada setiap pembukaan halaman.
  5. `RegistrationController.php:59-61, 1006-1012` & `PerawatDashboardController.php:24-27`: Memanggil `JadwalPraktikResolver` di dalam loop dokter/poli (**3N+1 query**).
  6. `PemeriksaanController.php:372` & `TindakanPerawatController.php:176`: `ProcedureConsentService::latest()` (`L54`) & `hasPendingSignature()` (`L107`) menjalankan query ulang per baris tindakan meskipun `informedConsents` sudah di-*eager load* (**2N query**).
  7. `PasienController.php:467`, `PemeriksaanController.php:141`, `ScreeningReportQuery.php:12`: Memanggil `$screening->handoverSummary()` yang mengakses `$this->submittedBy?->name` tanpa *eager load* `screening.submittedBy` (**N+1 query** ke `users`).
  8. `RegistrationController.php:328-329 -> 388` (`searchPasien`): Menjalankan `Storage::exists()` + `hash_file('sha256', ...)` untuk memverifikasi file PDF General Consent pada setiap 10 baris hasil ketikan *autocomplete* pasien!
* **Solusi:** Aktifkan `Model::preventLazyLoading(! $this->app->isProduction())`; ubah export ke `FromQuery`; tambahkan batas `limit(1000)` pada cetak laporan tindakan perawat; *eager load* `screening.submittedBy` dan gunakan relasi koleksi yang sudah dimuat pada `ProcedureConsentService`; *batch-load* jadwal dokter dalam 3 query tetap; dan tunda verifikasi `hash_file` hanya saat pasien dipilih (`getPasienDetail`), bukan saat *autocomplete* ketikan.

#### `[HIGH] TX-01` — Race Condition Duplikasi NIK, Nomor Antrean Dinamis & Divergensi `KunjunganController` vs `RegistrationController`
* **Lokasi Kode:**
  - `app/PatientRegistrationService.php:85-106` (cek `Pasien::withTrashed()->where('nik', ...)->exists()` tanpa constraint UNIQUE di tabel `pasien`)
  - `app/Http/Controllers/Pendaftaran/RegistrationController.php:1025-1034` (`cetakAntrian` menghitung `COUNT(*)` dinamis)
  - `app/Http/Controllers/Pelayanan/KunjunganController.php:268-276` (`Kunjungan::generateNomor()` di luar transaksi & lupa mengisi `'registered_by' => $request->user()->id`)
  - `app/ClinicalNoteRecorder.php:190-216` (inkremen nomor versi bergantung pada caller memegang `lockForUpdate()`)
  - `app/Http/Controllers/Master/ObatController.php:179-192` (`ObatImport` tanpa `DB::transaction()` dan mem-flash `$e->getMessage()` SQL mentah ke browser)
* **Solusi:** Tambahkan indeks unik pada `pasien(nik)`/`nik_hash`; tambahkan kolom `no_antrian` (`unsignedInteger`, indeks unik `(poliklinik_id, tanggal, no_antrian)`) yang di-assign atomik saat registrasi/konfirmasi kontrol; satukan pembuatan kunjungan `KunjunganController@store` dan `RegistrationController` ke satu service bertransaksi yang mengisi `registered_by`; bungkus `ClinicalNoteRecorder::record()` dengan `lockForUpdate()` internal; dan bungkus `ObatImport` dalam `DB::transaction()` dengan pesan error ramah pengguna.

#### `[HIGH] SEC-05` — Celah Jejak Audit (*Audit Trail*), Rollback Log Penolakan & Brute-Force PIN QR Sesi Tanda Tangan
* **Lokasi Kode:**
  - `app/Http/Middleware/AuditAdminAssistanceFailures.php:36-46`: Insert manual ke `clinical_audit_events` tanpa `actor_role`, `ip_address`, `user_agent`, `request_id`, dan menulis `outcome = 'success'` untuk percobaan yang ditolak!
  - `app/Http/Controllers/Pelayanan/PemeriksaanController.php:550, 571, 602, 816, 896, 1051, 1110`: Memanggil `ClinicalAuditRecorder::record()` **sebelum** `$request->validate()` di dalam transaksi.
  - `EnsureRole.php`, `PemeriksaanController.php:1746`, `ScreeningController.php:396`, `PersuratanController.php:426`: Percobaan akses lintas dokter/poli (IDOR 403/404) tidak tercatat sebagai `access.denied` di `clinical_audit_events`.
  - `app/Http/Controllers/ProcedureSignatureController.php:69-79`: `pair_code` 6 digit hanya dibatasi `throttle:8,1` berbasis IP tanpa penghitung gagal per sesi (`failed_attempts`).
* **Solusi:** Gunakan `ClinicalAuditRecorder` dengan `outcome = 'denied'` di `AuditAdminAssistanceFailures`; pindahkan pencatatan audit mutasi ke **setelah** validasi sukses; catat seluruh penolakan otorisasi (403 / lintas penugasan) di luar transaksi yang ter-*rollback*; dan batasi maksimal 5 kali salah input `pair_code` per `ProcedureSignatureSession` (otomatis batalkan sesi + catat audit).

---

### C. Temuan Tingkat `[MEDIUM]` & `[LOW]` (Penyempurnaan Kualitas & Kesiapan Infrastruktur)

| ID & Keparahan | Lokasi File / Kode | Deskripsi Masalah & Dampak | Solusi Refactoring Konkret |
| :--- | :--- | :--- | :--- |
| **`[MEDIUM] SEC-06`** | `app/Http/Middleware/EnforceClinicalSession.php:17, 29` | Baris 29 menulis ulang `$lastActivity` lama alih-alih `now()->timestamp`, sehingga perpanjangan sesi bergantung 100% pada ping JS `POST /session/activity`. Jika browser men-*throttle* tab di latar belakang saat dokter mengetik SOAP panjang, klik Simpan di menit ke-15:01 akan ditolak 401 dan draf hilang! | Perbarui `clinical_last_activity = now()->timestamp` pada setiap request mutasi nyata (`! $request->isMethodSafe()`) dan navigasi halaman penuh Inertia, sambil tetap menjaga endpoint polling `/session/status` tidak memperpanjang sesi. |
| **`[MEDIUM] SEC-07`** | `app/Http/Middleware/ProtectClinicalInformation.php:53-58` | Belum menyertakan *security headers* produksi: `X-Frame-Options: DENY`, `Content-Security-Policy` (`frame-ancestors 'none'`), `Permissions-Policy`, dan `Strict-Transport-Security` (saat HTTPS aktif). | Tambahkan header pengaman HTTP standar pada response di `ProtectClinicalInformation` dan uji kompatibilitas dengan aset Vite & QR data-URL. |
| **`[MEDIUM] INFRA-01`** | `routes/console.php:5-8` & `app/ClinicalBackup.php:68-81` | `clinic:backup` belum dijadwalkan di `routes/console.php`, tidak memiliki *retention pruning* (arsip ZIP harian akan memenuhi disk server), dan belum ada jadwal pembersihan `procedure_signature_sessions` kedaluwarsa. | Jadwalkan `Schedule::command('clinic:backup')->dailyAt('01:30')->withoutOverlapping()` di `routes/console.php`, tambahkan retensi arsip lokal terkonfigurasi (misal simpan 30 harian + 12 bulanan lokal sementara off-site menyimpan jangka panjang), dan bersihkan sesi tanda tangan kedaluwarsa. |
| **`[MEDIUM] ARCH-01`** | `PemeriksaanController.php` (1.968 baris), `RegistrationController.php` (1.159 baris), `app/Http/Requests/` | Hanya ada 1 `FormRequest` (`StoreScreeningRequest`); logika pembuatan surat diduplikasi antara `PemeriksaanController@storeSurat` (tanpa `signature_password`!) dan `PersuratanController@storeLetter`; kode bantuan klinis Admin (`AdminClinicalAssistance`, `PemeriksaanController@assist`) sudah mati karena diblokir `KunjunganPolicy`. | Ekstrak `FormRequest` untuk endpoint kompleks secara bertahap; arahkan pembuatan surat ke satu service tunggal yang seragam mewajibkan `signature_password`; bersihkan parameter `:admin` dari route klinis yang memang 100% diblokir agar tidak menyisakan kode mati. |
| **`[LOW] SEC-08`** | `app/Http/Controllers/Pelayanan/PasienController.php:480-492` | `rekamMedis` memantulkan query string `Referer` host yang sama ke `backUrl` tanpa *whitelist* path/parameter seperti pada `KunjunganController::contextualVisitBackUrl` (`L373-400`). | Gunakan helper *whitelist* `contextualVisitBackUrl` yang sama pada `PasienController@rekamMedis`. |
| **`[LOW] ARCH-02`** | `app/Models/StokObat.php` & `app/Models/Obat.php:41-44` | Model dan tabel `stok_obat` merupakan peninggalan skema lama yang sudah digantikan oleh `obat_batch` + `PersediaanRecorder`. | Tandai *deprecated* dan hapus relasi `Obat::stokDepo()` yang tidak lagi dipakai agar developer tidak salah menggunakannya untuk cek stok obat. |

---

## 3. Checklist QA & Kesiapan Cutover Staging / Pra-Rilis

Sebelum aplikasi dinyatakan lulus **Gate Pra-Rilis Produksi**, tim QA & Infrastruktur wajib memverifikasi daftar periksa berikut:

- [ ] `php artisan clinic:audit-database --no-interaction` menghasilkan **0 pelanggaran foreign key** pada database target.
- [ ] `php artisan clinic:security-check --production` menghasilkan **100% LULUS** (`APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `CLINIC_BACKUP_ENABLED=true`, `CLINIC_BACKUP_OFFSITE_CONFIRMED=true`, seluruh trigger imutabilitas aktif di engine target).
- [ ] Uji *Restore Drill* terisolasi: pulihkan 1 bundel ZIP terenkripsi hasil `clinic:backup` ke instance Staging kosong, verifikasi integritas tabel + berkas lampiran PDF/gambar privat + kecocokan hash `ClinicalNoteVersion`.
- [ ] Uji beban konkurensi klinik (simulasi 5 role aktif bersamaan di SQLite `WAL` atau MySQL/PostgreSQL): registrasi 2 pasien simultan (tidak ada nomor antrean kembar), penyerahan resep *double-submit* (hanya 1 pemotongan batch FEFO), dan pembukaan Laporan Top Diagnosa (< 300 ms).
- [ ] Seluruh *suite* otomatis lulus 100% setelah perubahan terakhir: `php artisan test --compact`, `npm run test:frontend`, `npm run types:check`, `npm run build`, `php vendor/bin/pint --test`, `composer audit`, dan `npm audit`.
