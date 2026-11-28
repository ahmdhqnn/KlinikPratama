# Inventaris route untuk review batas akses RME

Snapshot 19 November 2026, dari `php artisan route:list --json`. Tabel ini inventaris kode, bukan bukti setiap kombinasi role/objek sudah diuji. Kebijakan efektif menggabungkan middleware role, ProtectClinicalInformation/KunjunganPolicy dan pemeriksaan controller. Tidak menyimpulkan izin dari sidebar.

| Metode | Route | Controller/action | Kontrol domain yang harus diuji |
| --- | --- | --- | --- |
| GET|HEAD | `admin.laporan.dokter` | `AggregateClinicalReportController@index` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `admin.laporan.dokter.export` | `AggregateClinicalReportController@export` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `admin.laporan.pemeriksaan` | `AggregateClinicalReportController@index` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `admin.laporan.pemeriksaan.export` | `AggregateClinicalReportController@export` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `admin.laporan.pengkajian` | `AggregateClinicalReportController@index` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `admin.laporan.pengkajian.export` | `AggregateClinicalReportController@export` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `admin.laporan.tindakan-perawat` | `AggregateClinicalReportController@index` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `admin.laporan.tindakan-perawat.cetak` | `AggregateClinicalReportController@export` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `admin.laporan.tindakan-perawat.export` | `AggregateClinicalReportController@export` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `admin.log-aktivitas` | `AdminActivityLogController@index` | Admin; metadata whitelist, isi klinis dimasking |
| GET|HEAD | `admin.log-aktivitas.print` | `AdminActivityLogController@print` | Admin; metadata whitelist, isi klinis dimasking |
| GET|HEAD | `admin.log-aktivitas.export` | `AdminActivityLogController@export` | Admin; metadata whitelist, isi klinis dimasking |
| GET|HEAD | `dokter.dashboard` | `DokterDashboardController@index` | Dokter; penugasan |
| GET|HEAD | `dokter.janji-kunjungan` | `DokterDashboardController@appointments` | Dokter; penugasan |
| GET|HEAD | `dokter.kunjungan` | `DokterDashboardController@appointments` | Dokter; penugasan |
| GET|HEAD | `dokter.laporan-pemeriksaan` | `DokterDashboardController@appointments` | Dokter; penugasan |
| GET|HEAD | `dokter.laporan-pemeriksaan.export` | `DokterDashboardController@exportExaminations` | Dokter; penugasan |
| GET|HEAD | `dokter.laporan-top-diagnosa` | `DokterDashboardController@topDiagnoses` | Dokter; penugasan |
| GET|HEAD | `dokter.laporan` | `DokterDashboardController@reports` | Dokter; penugasan |
| GET|HEAD | `dokter.laporan.export` | `DokterDashboardController@exportActivityReport` | Dokter; penugasan |
| GET|HEAD | `dokter.pasien` | `DokterDashboardController@patients` | Dokter; penugasan |
| GET|HEAD | `dokter.stok-obat` | `DokterDashboardController@stock` | Dokter; penugasan |
| GET|HEAD | `dokter.tindakan` | `DokterTindakanController@index` | Dokter; penugasan |
| GET|HEAD | `farmasi.dashboard` | `FarmasiDashboardController@index` | Farmasi |
| GET|HEAD | `farmasi.laporan.pemberian-langsung` | `FarmasiMandiriController@report` | Farmasi; obat langsung sesuai kewenangan |
| GET|HEAD | `farmasi.laporan.pemberian-langsung.export` | `FarmasiMandiriController@export` | Farmasi; obat langsung sesuai kewenangan |
| GET|HEAD | `farmasi.laporan` | `FarmasiController@report` | Farmasi; resep final + tahap |
| GET|HEAD | `farmasi.laporan.export` | `FarmasiController@exportReport` | Farmasi; resep final + tahap |
| GET|HEAD | `farmasi.laporan.resep-luar` | `FarmasiController@externalReport` | Farmasi; resep final + tahap |
| GET|HEAD | `farmasi.laporan.resep-luar.export` | `FarmasiController@exportExternalReport` | Farmasi; resep final + tahap |
| GET|HEAD | `farmasi.pasien` | `FarmasiMandiriController@patients` | Farmasi; obat langsung sesuai kewenangan |
| GET|HEAD | `farmasi.pasien.kunjungan` | `FarmasiMandiriController@patientVisit` | Farmasi; obat langsung sesuai kewenangan |
| GET|HEAD | `farmasi.pemberian-obat.index` | `FarmasiMandiriController@index` | Farmasi; obat langsung sesuai kewenangan |
| POST | `farmasi.pemberian-obat.store` | `FarmasiMandiriController@store` | Farmasi; obat langsung sesuai kewenangan |
| GET|HEAD | `farmasi.pemberian-obat.create` | `FarmasiMandiriController@create` | Farmasi; obat langsung sesuai kewenangan |
| GET|HEAD | `farmasi.pemberian-obat.cetak` | `FarmasiMandiriController@print` | Farmasi; obat langsung sesuai kewenangan |
| GET|HEAD | `farmasi.resep-luar.index` | `FarmasiController@externalPrescriptions` | Farmasi; resep final + tahap |
| GET|HEAD | `farmasi.resep-luar.cetak` | `PersuratanController@printPrescription` | Admin template saja; dokter dokumen klinis; cetak resep farmasi proyeksi minimum |
| GET|HEAD | `general-consent.show` | `GeneralConsentController@show` | Admin/pendaftaran catat; perawat/dokter baca sesuai scope |
| GET|HEAD | `procedure-consent.show` | `ProcedureConsentController@show` | Dokter/perawat sesuai scope kunjungan; perawat hanya baca |
| POST | `procedure-consent.store` | `ProcedureConsentController@store` | Dokter penanggung jawab; reautentikasi dan stale-plan guard |
| GET|HEAD | `pelayanan.pemeriksaan.diagnosa.correction.show` | `DiagnosisCodingCorrectionController@show` | Dokter penanggung jawab; versi final terverifikasi |
| POST | `pelayanan.pemeriksaan.diagnosa.correction.store` | `DiagnosisCodingCorrectionController@store` | Dokter penanggung jawab; password, stale hash, idempotensi, addendum append-only |
| POST | `pelayanan.pemeriksaan.tindakan.coding` | `PemeriksaanController@reviewProcedureCoding` | Dokter penanggung jawab; draf, klasifikasi eksplisit, revision hash dan audit atomik |
| GET|HEAD | `medical-document.verify` | `MedicalDocumentVerificationController@show` | Publik, token acak 256 bit, throttle 30/menit, status/waktu saja tanpa shared props klinis/akun |
| POST | `persuratan.surat.void` | `MedicalDocumentVerificationController@void` | Dokter penanggung jawab, reautentikasi, alasan, event immutable dan audit atomik |
| GET|HEAD | `procedure-consent.evidence` | `ProcedureConsentController@evidence` | Dokter/perawat sesuai scope; bukti private dan checksum |
| POST | `general-consent.store` | `GeneralConsentController@store` | Admin/pendaftaran catat; perawat/dokter baca sesuai scope |
| GET|HEAD | `general-consent.evidence` | `GeneralConsentController@evidence` | Admin/pendaftaran catat; perawat/dokter baca sesuai scope |
| GET|HEAD | `laporan.kunjungan` | `AggregateClinicalReportController@index` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `laporan.kunjungan.export` | `AggregateClinicalReportController@export` | Admin/manajemen sesuai route; agregat tanpa identitas |
| GET|HEAD | `pelayanan.farmasi.index` | `FarmasiController@index` | Farmasi; resep final + tahap |
| GET|HEAD | `pelayanan.farmasi.show` | `FarmasiController@show` | Farmasi; resep final + tahap |
| POST | `pelayanan.farmasi.store` | `FarmasiController@store` | Farmasi; resep final + tahap |
| POST | `pelayanan.farmasi.resep-luar` | `FarmasiController@markExternal` | Farmasi; resep final + tahap |
| POST | `pelayanan.farmasi.selesai` | `FarmasiController@selesai` | Farmasi; resep final + tahap |
| GET|HEAD | `pelayanan.icd10.search` | `PemeriksaanController@searchIcd10` | Dokter; penugasan + tahap |
| GET|HEAD | `pelayanan.icd9cm.search` | `PemeriksaanController@searchIcd9cm` | Dokter; penugasan + tahap |
| POST | `pelayanan.kunjungan.attachments.store` | `VisitAttachmentController@store` | Perawat sesuai poli / dokter bertugas baca |
| GET|HEAD | `pelayanan.kunjungan.attachments.show` | `VisitAttachmentController@show` | Perawat sesuai poli / dokter bertugas baca |
| DELETE | `pelayanan.kunjungan.attachments.destroy` | `VisitAttachmentController@destroy` | Perawat sesuai poli / dokter bertugas baca |
| POST | `pelayanan.kunjungan.attachments.restore` | `VisitAttachmentController@restore` | Perawat sesuai poli / dokter bertugas baca |
| POST | `pelayanan.kunjungan.screening.store` | `ScreeningController@store` | Perawat; scope poli |
| POST | `pelayanan.pasien.store` | `PasienController@store` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| GET|HEAD | `pelayanan.pasien.index` | `PasienController@index` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| GET|HEAD | `pelayanan.pasien.export` | `PasienController@export` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| GET|HEAD | `pelayanan.pasien.create` | `PasienController@create` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| POST | `pelayanan.pasien.gabung` | `PasienController@gabung` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| DELETE | `pelayanan.pasien.destroy` | `PasienController@destroy` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| PUT|PATCH | `pelayanan.pasien.update` | `PasienController@update` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| GET|HEAD | `pelayanan.pasien.show` | `PasienController@show` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| GET|HEAD | `pelayanan.pasien.edit` | `PasienController@edit` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| GET|HEAD | `pelayanan.pasien.rekam-medis` | `PasienController@rekamMedis` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| POST | `pelayanan.pasien.verify-special-access` | `PasienController@verifyMembership` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| POST | `pelayanan.pasien.verify-membership` | `PasienController@verifyMembership` | Identitas administratif minimum; RME hanya klinisi sesuai scope |
| GET|HEAD | `pelayanan.pemeriksaan.index` | `PemeriksaanController@index` | Dokter; penugasan + tahap |
| DELETE | `pelayanan.pemeriksaan.diagnosa.destroy` | `PemeriksaanController@destroyDiagnosa` | Dokter; penugasan + tahap |
| PATCH | `pelayanan.pemeriksaan.diagnosa.primary` | `PemeriksaanController@primaryDiagnosa` | Dokter; penugasan + tahap |
| DELETE | `pelayanan.pemeriksaan.resep.destroy` | `PemeriksaanController@destroyResep` | Dokter; penugasan + tahap |
| DELETE | `pelayanan.pemeriksaan.tindakan.destroy` | `PemeriksaanController@destroyTindakan` | Dokter; penugasan + tahap |
| GET|HEAD | `pelayanan.pemeriksaan.show` | `PemeriksaanController@show` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.store` | `PemeriksaanController@store` | Dokter; penugasan + tahap |
| PUT | `pelayanan.pemeriksaan.update` | `PemeriksaanController@update` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.addendum` | `PemeriksaanController@addendum` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.claim` | `PemeriksaanController@claim` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.assist` | `PemeriksaanController@assist` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.diagnosa.store` | `PemeriksaanController@storeDiagnosa` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.start` | `PemeriksaanController@start` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.odontogram.store` | `PemeriksaanController@storeOdontogram` | Dokter; penugasan + tahap |
| DELETE | `pelayanan.pemeriksaan.odontogram.destroy` | `PemeriksaanController@destroyOdontogram` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.odontogram.addendum` | `PemeriksaanController@addendumOdontogram` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.odontogram.sound` | `PemeriksaanController@storeSoundTeeth` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.resep.store` | `PemeriksaanController@storeResep` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.rujukan.store` | `PemeriksaanController@storeRujukan` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.selesai` | `PemeriksaanController@selesai` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.surat.store` | `PemeriksaanController@storeSurat` | Dokter; penugasan + tahap |
| POST | `pelayanan.pemeriksaan.tindakan.store` | `PemeriksaanController@storeTindakan` | Dokter; penugasan + tahap |
| GET|HEAD | `pelayanan.screening.index` | `ScreeningController@index` | Perawat; scope poli |
| GET|HEAD | `pelayanan.screening.history` | `ScreeningController@history` | Perawat; scope poli |
| GET|HEAD | `pelayanan.screening.show` | `ScreeningController@show` | Perawat; scope poli |
| POST | `pelayanan.screening.store` | `ScreeningController@store` | Perawat; scope poli |
| PUT | `pelayanan.screening.update` | `ScreeningController@update` | Perawat; scope poli |
| GET|HEAD | `pelayanan.screening.print` | `ScreeningController@print` | Perawat; scope poli |
| POST | `pelayanan.screening.start` | `ScreeningController@start` | Perawat; scope poli |
| GET|HEAD | `pelayanan.tindakan.index` | `TindakanPerawatController@index` | Perawat; scope poli + instruksi final |
| GET|HEAD | `pelayanan.tindakan.show` | `TindakanPerawatController@show` | Perawat; scope poli + instruksi final |
| POST | `pelayanan.tindakan.start` | `TindakanPerawatController@start` | Perawat; scope poli + instruksi final |
| POST | `pelayanan.tindakan.complete` | `TindakanPerawatController@complete` | Perawat; scope poli + instruksi final |
| GET|HEAD | `perawat.laporan-pengkajian` | `ScreeningController@history` | Perawat; scope poli |
| GET|HEAD | `perawat.laporan-pengkajian.export` | `ScreeningController@exportReport` | Perawat; scope poli |
| GET|HEAD | `perawat.laporan-tindakan` | `TindakanPerawatController@report` | Perawat; scope poli + instruksi final |
| GET|HEAD | `perawat.laporan-tindakan.cetak` | `TindakanPerawatController@printReport` | Perawat; scope poli + instruksi final |
| GET|HEAD | `perawat.laporan-tindakan.export` | `TindakanPerawatController@exportReport` | Perawat; scope poli + instruksi final |
| GET|HEAD | `persuratan.index` | `PersuratanController@index` | Admin template saja; dokter dokumen klinis; cetak resep farmasi proyeksi minimum |
| GET|HEAD | `persuratan.resep.cetak` | `PersuratanController@printPrescription` | Admin template saja; dokter dokumen klinis; cetak resep farmasi proyeksi minimum |
| GET|HEAD | `persuratan.rujukan.cetak` | `PersuratanController@printReferral` | Admin template saja; dokter dokumen klinis; cetak resep farmasi proyeksi minimum |
| GET|HEAD | `persuratan.surat.cetak` | `PersuratanController@printLetter` | Admin template saja; dokter dokumen klinis; cetak resep farmasi proyeksi minimum |
| POST | `persuratan.template.store` | `PersuratanController@storeTemplate` | Admin template saja; dokter dokumen klinis; cetak resep farmasi proyeksi minimum |
| PUT | `persuratan.template.update` | `PersuratanController@updateTemplate` | Admin template saja; dokter dokumen klinis; cetak resep farmasi proyeksi minimum |
| DELETE | `persuratan.template.destroy` | `PersuratanController@destroyTemplate` | Admin template saja; dokter dokumen klinis; cetak resep farmasi proyeksi minimum |
| GET|HEAD | `persuratan.surat.create` | `PersuratanController@createLetter` | Admin template saja; dokter dokumen klinis; cetak resep farmasi proyeksi minimum |
| POST | `persuratan.surat.store` | `PersuratanController@storeLetter` | Admin template saja; dokter dokumen klinis; cetak resep farmasi proyeksi minimum |

## Kombinasi uji yang masih harus diperiksa

Untuk setiap objek kunjungan, ulangi dengan admin/pendaftaran/perawat/dokter/farmasi/manajemen, akun nonaktif, poli lain, penugasan dokter lain, kunjungan batal/final dan JSON/Inertia partial props. Uji unduh/ekspor dengan ID asing dan pastikan tidak ada konten sensitif sebelum penolakan. Inventaris tidak menggantikan review serializer atau bukti engine/deployment.

Pengujian endpoint baru general consent terdapat di `GeneralConsentFlowTest`; audit, retensi dan penolakan klinis admin di `ClinicalSecurityHardeningTest`. Daftar acceptance lengkap tetap pada [tracker](rme-hardening-tracker.md).
