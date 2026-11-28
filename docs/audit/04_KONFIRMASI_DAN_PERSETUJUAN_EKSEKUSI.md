# 04 — DOKUMEN KONFIRMASI & PERSETUJUAN RENCANA EKSEKUSI
**Kepada:** Pemilik Proyek / Penanggung Jawab Aplikasi RME Klinik Pratama Kementerian Pariwisata  
**Dari:** Tim Audit Multi-Disiplin (Clinical Informatics, Principal Backend/Security, Frontend/UI Lead, QA Lead)  
**Status Saat Ini:** **6 Keputusan Arsitektural & Klinis Utama Telah Dikonfirmasi Pengguna (25 November 2026)**

---

## 1. Rekapitulasi Keputusan yang Telah Disetujui (*Locked-In Decisions*)

| No | Area Keputusan | Pilihan yang Dikonfirmasi | Tindak Lanjut Teknis |
| :-: | :--- | :--- | :--- |
| **1** | **Target Engine Database** | **PostgreSQL** (`postgres:17-alpine` terdeteksi aktif di `127.0.0.1:5433`, DB `klinik_pratama` & `klinik_pratama_test`) | Konfigurasi aplikasi utama & test ke PostgreSQL 17, validasi penuh seluruh trigger imutabilitas `plpgsql`, indeks komposit, `CheckClinicalSecurity` (`pg_trigger`), serta dukungan `pg_dump` terenkripsi pada `ClinicalBackup`. |
| **2** | **Kredensial Tanda Tangan Klinis (`SEC-01`)** | **Setuju Opsi A** — Pisahkan PIN/Passphrase Tanda Tangan Klinis dari password login + versi gambar tanda tangan imutabel | Tambah `signing_pin_hash` mandiri bagi Dokter/Perawat/Apoteker (Admin hanya bisa *reset* status, tidak pernah tahu PIN), wajib re-autentikasi saat unggah tanda tangan, dan simpan snapshot tanda tangan per versi dokumen RME. |
| **3** | **Keselamatan Peresepan, Alergi & Kehamilan/Menyusui** | **Setuju Opsi A** — Peringatan mencolok + wajib *override reason* alergi + status Hamil/Menyusui terstruktur | Tabel `patient_allergies` + *cross-check* zat aktif/nama obat & stok layak pakai di `storeResep`; eskalasi otomatis TTV kritis ke triase; serta propagasi status Hamil/Menyusui dari Pengkajian ke layar Resep Dokter & Review Apoteker. |
| **4** | **Kepesertaan, Persuratan & Etiket Obat Kemenpar** | **Setuju Opsi A** — Standar resmi Kementerian Pariwisata & Permenkes | Kop dinamis `Klinik Pratama Kementerian Pariwisata`, Surat Sakit ber-`NIP` & `Unit Kerja`, Resume Medis lengkap PMK 24 Pasal 22, Surat Sehat ber-`buta_warna`/`visus`/`IMT`, Etiket Obat Putih/Biru Permenkes 73/2016, serta kategori `PNS`, `PPPK`, `Keluarga Pegawai`, `Tenaga Penunjang`, dan `Tamu Dinas`. |
| **5** | **Ergonomi Kecepatan Input Nakes (*Minim Klik*)** | **Setuju Opsi A** — Hapus *popup* konfirmasi pada simpan draf rutin + alur super cepat | Simpan draf 1 klik (*spinner* + toast), gabung sub-tab Odontogram jadi 1 panel cepat, tambah `<PatientContextBar>` *sticky* + `useUnsavedChangesGuard` + `<ClinicalCombobox>` ramah keyboard (`ArrowUp/Down/Enter`) untuk ICD-10, ICD-9-CM, Obat, & Pasien. |
| **6** | **Cakupan Rilis Perdana vs Fase Berikutnya** | **Setuju Opsi A** — Fokus kesempurnaan & stabilitas Pra-Rilis | Nonaktifkan sementara opsi `'racikan'` yang belum multi-komponen, pasang pengaman tombol *"Tandai gigi lain sehat"* pada anak fase gigi bercampur, dan jadwalkan Racikan Multi-Obat & Odontogram Poligon 5-Permukaan ke Fase Pasca-Rilis. |

---

## 2. Konfirmasi Teknis Terakhir Sebelum Eksekusi Penuh

Hasil deteksi lingkungan lokal menunjukkan:
- Ekstensi PHP `pdo_pgsql` **sudah aktif** (`PHP 8.4.21`).
- Container Docker **`klinikpratama-postgres-1` (`postgres:17-alpine`) sudah menyala dan *healthy*** di `127.0.0.1:5433` dengan database kosong `klinik_pratama` dan `klinik_pratama_test`.
- File `database/database.sqlite` saat ini menyimpan **41.710 baris katalog resmi `clinical_terminologies` (ICD-10 WHO & ICD-9-CM)** serta 20 pasien & 49 kunjungan riwayat UAT lokal.

Tiga poin praktis terakhir (strategi pengisian data PostgreSQL untuk presentasi/uji coba klinis, profil alamat default kop klinik di `/setting`, dan mode eksekusi) dikonfirmasi langsung melalui dialog interaktif sebelum tim memulai pengerjaan kode.

---

## 3. Rencana Eksekusi Terpadu Menuju Kesiapan Presentasi Pra-Rilis & Uji Coba Klinis

1. **Gelombang 1 — Migrasi Penuh PostgreSQL 17, Indexing, Performa & Keamanan Inti:**
   - Pengalihan `.env` ke PostgreSQL 17 (`klinik_pratama`), pembuatan 18+ indeks komposit panas, penghapusan `whereDate()` non-sargable, optimasi `EffectiveDiagnosisReport`, `SESSION_ENCRYPT=true`, pemeriksaan trigger `pg_trigger` di `CheckClinicalSecurity`, adaptor `pg_dump` pada `ClinicalBackup`, sistem **PIN Tanda Tangan Klinis** terpisah + snapshot gambar tanda tangan imutabel, nomor antrean persisten (`no_antrian`), serta penutupan kebocoran mutasi obat Admin.
2. **Gelombang 2 — Keselamatan Klinis Maksimal & Skema *SATUSEHAT-Ready*:**
   - Mesin triase otomatis berbasis nilai numerik TTV kritis (termasuk *red flag* tekanan darah tindakan gigi), kolom terstruktur Kehamilan & Menyusui dari Pengkajian -> Resep -> Review Farmasi, tabel `patient_allergies` + *cross-check* alergi (*override reason*), duplikasi terapi, & stok/kedaluwarsa pada peresepan, *gate* addendum > 2x24 jam, serta seluruh kolom *additive* SATUSEHAT (`nakes.nik`/`ihs_number`, kode wilayah `pasien`, ID `Organization`/`Location`/`Encounter`, dan `dosageInstruction` `resep_obat`).
3. **Gelombang 3 — Kecepatan Input Nakes (*Zero Bottleneck*) & Konsistensi UI/UX Enterprise:**
   - `<PatientContextBar>` *sticky* (Identitas, Usia, BB, TTV, Alergi, Hamil/Menyusui selalu terlihat), `useUnsavedChangesGuard`, `<ClinicalCombobox>` super cepat berbasis keyboard untuk pencarian Pasien, Kepesertaan, ICD-10, ICD-9-CM, dan Obat Resep, penghapusan *popup* konfirmasi draf rutin + penyatuan panel Odontogram 1-klik, perbaikan kontras *dark mode*, perataan tabel & *checkbox*, responsivitas laptop 1280–1366px, serta modularisasi `pemeriksaan/show.tsx`.
4. **Gelombang 4 — Dokumen Resmi Kemenpar, Etiket Obat, Seeding Siap Presentasi & QA Final:**
   - Kop surat dinamis penuh dari `/setting`, penyempurnaan Surat Sakit (`NIP` + `Unit Kerja`), Surat Sehat (`buta_warna`/`visus`/`IMT`), Rujukan, Resume Medis PMK 24 Pasal 22, Etiket Obat Putih/Biru Permenkes 73/2016, kepesertaan `PNS`/`PPPK`/`Keluarga`/`Tamu Dinas`, migrasi 41.710 katalog ICD-10/ICD-9-CM ke PostgreSQL, serta verifikasi lulus 100% seluruh test suite, TypeScript, build produksi, dan `clinic:security-check`.
