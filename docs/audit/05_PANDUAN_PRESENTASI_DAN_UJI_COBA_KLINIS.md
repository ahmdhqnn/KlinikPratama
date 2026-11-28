# Panduan Presentasi Pra-Rilis & Uji Coba Langsung Tim Klinis
**Proyek:** Rekam Medis Elektronik (RME) Klinik Pratama Kementerian Pariwisata  
**Lokasi Klinik:** Gedung Sapta Pesona, Jl. Medan Merdeka Barat No. 17, Gambir, Jakarta Pusat 10110  
**Engine Database Aktif:** PostgreSQL 17 (`klinik_pratama` · `127.0.0.1:5433`)  
**Katalog Terminologi Aktif:** 41.710 entri resmi ICD-10 WHO & ICD-9-CM Volume 3

---

## 0. Cara Menjalankan Server Demo Aplikasi

1. **Pastikan Docker Desktop aktif** (container PostgreSQL 17 `klinikpratama-postgres-1` berjalan otomatis atau dihidupkan otomatis oleh skrip `composer run dev`).
2. **Buka terminal baru** pada folder proyek `c:\Project\LARAVEL\klinik-pratama` (jika sebelumnya ada `composer run dev` lama yang masih berjalan, tekan `Ctrl+C` terlebih dahulu), lalu jalankan:
   ```powershell
   composer run dev
   ```
   *(Skrip `scripts/run-dev.php` otomatis menyinkronkan variabel `.env` PostgreSQL, memastikan tautan penyimpanan `public/storage`, memastikan container PostgreSQL aktif, lalu menjalankan Web Server + Queue Worker + Vite HMR).*
3. **Buka peramban (Chrome / Edge):** Akses **`http://127.0.0.1:8000`** atau **`http://localhost:8000`**.
4. *(Opsional)* Jika ingin me-reset ulang skenario demo hari ini kapan saja tanpa menghapus 41.710 terminologi:
   ```powershell
   php artisan db:seed --class="Database\Seeders\KemenparPreReleaseSeeder"
   ```

---

## 1. Akun Demo Siap Pakai per Role

Seluruh akun demo di bawah ini sudah diaktifkan beserta profil tenaga kesehatan (`Nakes`), penugasan poliklinik, jadwal praktik aktif setiap hari (Senin–Minggu, 07:30–17:00 WIB), citra tanda tangan klinis, serta kualifikasi Apoteker terverifikasi SIPA.

- **Kata Sandi Login (Semua Akun Demo):** `Kemenpar2026!`
- **PIN Tanda Tangan Klinis (Terpisah dari Password Login):** `123456`

| Peran Operasional | Email Login | Pejabat / Petugas Demo | Cakupan Akses Utama |
| --- | --- | --- | --- |
| **Administrator Sistem** | `admin@kemenpar.go.id` | Admin Sistem Klinik Kemenpar | Pengaturan Klinik, Master Data, Direktori Kepesertaan, Verifikasi Kualifikasi Farmasi, Audit & Keamanan |
| **Petugas Pendaftaran** | `pendaftaran@kemenpar.go.id` | Nadia Putri, A.Md.RMIK | Pendaftaran Pasien (Direktori Kepesertaan / Terdaftar / Baru), General Consent & SATUSEHAT Consent, Antrean & Cetak Tiket |
| **Perawat Poli Umum** | `perawat.umum@kemenpar.go.id` | Ns. Siti Nurhaliza, S.Kep. | Pengkajian Awal Poli Umum, Triase TTV Otomatis, Skrining Kehamilan/Menyusui, Pelaksanaan Tindakan Perawat |
| **Perawat / Terapis Gigi** | `perawat.gigi@kemenpar.go.id` | Dewi Lestari, A.Md.Kes. | Pengkajian Awal Poli Gigi & Mulut, Skrining Tanda Bahaya Ekstraoral & Mukosa |
| **Dokter Poli Umum** | `dokter.umum@kemenpar.go.id` | dr. Ratna Kusumawati, M.K.M. | Pemeriksaan Poli Umum (SOAP 1-Klik, Body Map Anatomi, Diagnosis ICD-10, Tindakan ICD-9-CM, CDS Resep, Surat Medis) |
| **Dokter Gigi (Poli Gigi)** | `dokter.gigi@kemenpar.go.id` | drg. Hendra Wijaya, Sp.KG | Pemeriksaan Poli Gigi (Odontogram FDI 1-Panel Ringkas, Diagnosis Gigi, Tindakan, CDS Resep, Rontgen Dental) |
| **Apoteker Farmasi** | `apoteker@kemenpar.go.id` | apt. Rina Kartika, S.Farm. | Penyiapan Obat FEFO, Telaah Resep Klinis (Administratif/Farmasetik/Klinis), Etiket Putih & Biru Permenkes 73/2016, Penyerahan Obat |

> **Reset Data Demo Kapan Saja:**  
> Jalankan perintah `php artisan db:seed --class="Database\Seeders\KemenparPreReleaseSeeder"` untuk memastikan seluruh akun dan skenario antrean hari ini tersedia tanpa menghapus katalog 41.710 terminologi klinis.

---

## 2. Skenario Kunjungan Hari Ini (Langsung Siap Didemokan)

Agar sesi presentasi berjalan mulus tanpa harus menginput dari nol untuk setiap peran, database sudah dilengkapi **6 skenario kunjungan aktif & selesai hari ini** pada masing-masing tahap:

### Skenario A — Meja Pendaftaran (`pendaftaran@kemenpar.go.id`)
1. Buka menu **Pendaftaran Pasien** (`/pendaftaran/pendaftaran-baru`).
2. **Uji Direktori Kepesertaan Internal:** Pilih tab **Dari Kepesertaan**, cari nama pegawai (`Budi`, `Ayu`, atau `Dimas`) — data identitas, NIP/NI PPPK, dan Unit Kerja Eselon I Kemenpar terisi otomatis.
3. **Uji Pasien Tamu Dinas / Keluarga Pegawai:** Pilih tab **Pendaftaran Baru** atau **Pasien Terdaftar** (`I Made Wirawan · RM-KP-0007`), pilih unit kerja dari saran otomatis, konfirmasi **General Consent + Persetujuan Berbagi Data SATUSEHAT**, lalu daftarkan kunjungan dalam hitungan detik.

### Skenario B — Pengkajian Awal Perawat Poli Umum (`perawat.umum@kemenpar.go.id`)
1. Buka menu **Pengkajian Awal** (`/pelayanan/screening`) dan pilih pasien **Budi Santoso (`RM-KP-0001` · Sekretariat Kementerian)** yang berstatus *Menunggu pengkajian awal*.
2. Klik **Mulai pengkajian**. Perhatikan **Sticky Patient Context Bar** di bagian atas layar yang selalu menampilkan identitas, TTV, alergi, dan status draf saat layar digulir.
3. Coba masukkan nilai tanda vital kritis (mis. TD `185/115` mmHg atau SpO₂ `91%`) untuk mendemonstrasikan **eskalasi triase otomatis berbasis Early Warning Score (EWS)** serta kalkulasi **IMT standar Kemenkes Asia-Pasifik**.
4. Gunakan centang cepat *"Semua jawaban kondisi awal normal"* untuk menyelesaikan pengkajian rutin secara kilat, lalu klik **Simpan & kirim ke dokter**.

### Skenario C — Pemeriksaan Dokter Umum + Pengaman CDS Alergi & Kehamilan (`dokter.umum@kemenpar.go.id`)
1. Buka menu **Pemeriksaan Umum** (`/pelayanan/pemeriksaan`) dan pilih pasien **Ayu Kartika Sari, S.Par. (`RM-KP-0002` · PPPK Deputi Bidang Pemasaran)**.
2. Perhatikan peringatan otomatis dari pengkajian perawat: **Alergi Penisilin / Amoxicillin** dan **Sedang Hamil (16 minggu)**.
3. Klik **Mulai pemeriksaan**:
   - **Ergonomi Minim Klik:** Klik *Simpan anamnesis* atau *Tambah diagnosis* — tersimpan langsung dalam **1 klik** tanpa popup konfirmasi yang menghambat pelayanan!
   - **Navigasi Keyboard Cepat:** Pada pencarian ICD-10 (`#icd-search`) dan ICD-9-CM (`#procedure-search`), ketik kata kunci (mis. `faringitis` atau `J02`), tekan panah `↓` / `↑` lalu `Enter`.
   - **Demo Clinical Decision Support (CDS) Resep:** Buka tab **Resep klinik**, pilih obat **Amoxicillin 500 mg**. Sistem langsung menampilkan **Peringatan Merah Alergi Obat (lintas golongan Penisilin)** dan mewajibkan pengisian *Alasan Pertimbangan Klinis (Override Reason)* sebelum obat dapat ditambahkan, atau dokter dapat menggantinya ke obat non-beta-laktam yang aman bagi ibu hamil (mis. **Paracetamol 500 mg**).
   - Klik **Selesai periksa** dan tandatangani secara elektronik menggunakan **PIN Tanda Tangan Klinis (`123456`)**.

### Skenario D — Pemeriksaan Dokter Gigi & Odontogram 1-Panel Ringkas (`dokter.gigi@kemenpar.go.id`)
1. Buka menu **Pemeriksaan Gigi & Mulut** (`/pelayanan/pemeriksaan`) dan pilih pasien **Dimas Prasetyo (`RM-KP-0003` · Deputi Pengembangan Destinasi)**.
2. Klik **Mulai pemeriksaan**, lalu buka tab **Pemeriksaan & odontogram**:
   - Klik gigi **46** langsung pada peta FDI interaktif.
   - Seluruh pemilihan permukaan (`M`, `O`, `D`, `B`, `L`), tes vitalitas/perkusi, rincian karies, dan tombol **Simpan temuan** kini menyatu dalam **1 panel tunggal tanpa sub-tab tambahan**.
   - Klik tombol **Susun diagnosis** dari tabel temuan odontogram untuk mengimpor konteks gigi ke diagnosis ICD-10 WHO secara otomatis.
   - Uji juga pengaman tombol **Tandai gigi lain sehat** (otomatis mengecualikan molar belum erupsi pada pasien anak `< 14` tahun).

### Skenario E — Pelaksanaan Tindakan Perawat (`perawat.umum@kemenpar.go.id`)
1. Buka menu **Tindakan Medis** (`/pelayanan/tindakan-perawat`) dan pilih pasien **Rizky Ramadhan (`RM-KP-0004` · Tenaga Penunjang Inspektorat Utama)**.
2. Klik **Mulai tindakan** pada instruksi *Terapi Inhalasi / Nebulisasi Bronkodilator*, catat respons klinis pasien pasca nebulisasi, lalu klik **Simpan pelaksanaan tindakan**.

### Skenario F — Telaah Klinis Apoteker & Etiket Putih/Biru Permenkes 73/2016 (`apoteker@kemenpar.go.id`)
1. Buka menu **Penyerahan Obat** (`/pelayanan/farmasi`) dan pilih pasien **Clara Nathania (`RM-KP-0005` · Deputi Industri dan Investasi)**.
2. Perhatikan resep memiliki 2 item berbeda rute:
   - **Cetirizine 10 mg** (Oral -> otomatis ditandai **Etiket Putih · Obat Dalam**)
   - **Hidrokortison Krim 2.5%** (Topikal -> otomatis ditandai **Etiket Biru · Obat Luar**)
3. Klik **Simpan draf**, lalu klik **Review resep oleh apoteker** — centang telaah administratif, farmasetik, dan klinis, masukkan catatan telaah serta PIN `123456`, lalu simpan.
4. Kembali ke layar penyerahan, klik ikon **Cetak etiket** pada masing-masing obat untuk menampilkan pratinjau **Etiket Putih** dan **Etiket Biru ("OBAT LUAR — TIDAK UNTUK DITELAN")** lengkap dengan Kop Klinik Kemenpar, Nama & SIPA Apoteker, Nomor Resep, 2 Identitas Pasien (`Nama` + `No. RM` & `Tanggal Lahir`), serta Tanggal Kedaluwarsa Batch (`ED`).
5. Isi penerima obat & catatan konseling, lalu klik **Serahkan obat & selesaikan** (stok otomatis terpotong FEFO dari batch yang kedaluwarsa paling awal).

### Skenario G — Dokumen Resmi Kemenpar, Cetak RME & Verifikasi QR (`dokter.umum@kemenpar.go.id`)
1. Buka menu **Persuratan** (`/persuratan`) — lihat kunjungan selesai atas nama **Hendra Gunawan, S.H., M.H. (`RM-KP-0006` · Deputi SDM dan Kelembagaan)**.
2. Klik ikon **Cetak** pada **Surat Istirahat Sakit (`SKS/0001/KP/...`)** dan **Resume Medis (`RSM/0001/KP/...`)**:
   - Kop surat menampilkan identitas resmi **Klinik Pratama Kementerian Pariwisata (Gedung Sapta Pesona)** beserta Nomor Izin Operasional dinamis.
   - Surat Sakit memuat **NIP & Unit Kerja**; Surat Sehat mendukung **IMT, Golongan Darah, Tes Buta Warna, dan Visus**; Resume Medis memenuhi standar **PMK No. 24 Tahun 2022 Pasal 22** (Anamnesis, TTV & Pemeriksaan Fisik, Diagnosis ICD-10, Tindakan ICD-9-CM, Terapi, Kondisi Akhir, dan Jadwal Kontrol).
   - Setiap dokumen dilengkapi **QR Verifikasi Arsip** berlogo Kemenpar tanpa membocorkan data medis sensitif ke publik (patuh UU PDP).

---

## 3. Daftar Periksa Kesiapan Pra-Rilis (Release Readiness Checklist)

- [x] **Database Engine Enterprise:** PostgreSQL 17 aktif dengan enkripsi sesi (`SESSION_ENCRYPT=true`), 20+ composite indexes pada jalur antrean & pelaporan, nomor antrian persisten (`no_antrian`), serta backup native `pg_dump` terenkripsi AES-256.
- [x] **Kepatuhan PMK No. 24 Tahun 2022 & UU PDP:**
  - [x] Pemisahan **PIN Tanda Tangan Klinis** dari password login akun (`ClinicalSigningCredential`).
  - [x] Snapshot tanda tangan imutabel per dokumen RME & pengkajian (`signature_path_snapshot`).
  - [x] Immutability rekam medis melalui versi catatan ber-hash SHA-256 (`clinical_note_versions`), database trigger pelindung penghapusan fisik (13/13 pemeriksaan `clinic:security-check` bernilai `true`), dan gerbang konfirmasi tata kelola untuk addendum `> 2×24 jam`.
- [x] **Keselamatan Pasien (Clinical Decision Support):**
  - [x] Deteksi alergi obat lintas golongan + kewajiban pengisian *clinical override reason*.
  - [x] Kontinum status **Kehamilan & Menyusui** dari Pengkajian Perawat -> Resep Dokter -> Telaah Apoteker.
  - [x] Penanda obat **HIGH ALERT**, **LASA**, dan batch mendekati kedaluwarsa (`≤ 30 hari`).
  - [x] Eskalasi triase otomatis berbasis nilai numerik tanda vital kritis (termasuk peringatan hipertensi kritis `≥ 180/110 mmHg` pada Poli Gigi).
- [x] **SATUSEHAT-Ready by Design (Tanpa Perlu Rombak Skema Saat Integrasi):**
  - [x] Kolom `satusehat_org_id`, `satusehat_location_id`, `satusehat_ihs_number` (Pasien & Nakes), `satusehat_encounter_id`, `arrived_at`, `finished_at`, `satusehat_condition_id`, `satusehat_procedure_id`, `kfa_poa_code`, `kfa_pov_code`, dosis terstruktur FHIR, serta `satusehat_sharing_decision` pada General Consent telah siap di database.
