# Audit RME Klinik Pratama — 18 November 2026

Status: kajian kode dan sumber resmi; **belum merupakan pernyataan kepatuhan, sertifikasi, atau penutupan temuan**.

Dokumen terkait: [rancangan](rme-hardening-rancangan.md), [tracker utama](rme-hardening-tracker.md), [pemetaan data](rme-hardening-metadata.md). Audit admin sebelumnya tetap menjadi catatan historis, tetapi keputusan target akses dalam paket ini mengikuti arahan terbaru pengguna.

## 1. Baseline dan metode

- Proyek Laravel 13 + React/Inertia, branch `rmeupdate`, HEAD `8f4a9cc`; penilaian mencakup **working tree**, termasuk perubahan yang belum committed. Tanggal menggunakan Asia/Jakarta.
- Klinik internal, kepesertaan internal, Poli Umum dan Poli Gigi. Alur yang diterima: pendaftaran → pengkajian → pemeriksaan → tindakan perawat bila diperlukan → farmasi bila diperlukan → selesai. Reservasi, rujukan internal, resep luar dan obat langsung tetap mengacu implementasi yang ada.
- Diperiksa: route web, middleware, layanan otorisasi, controller, model, migration, serializer Inertia, template dokumen, pengisian riwayat, konfigurasi sesi, jadwal console, dan feature test terkait. Ini aplikasi web Inertia; pembatasan harus melindungi endpoint web/JSON/download, bukan mengandaikan ada API klinis terpisah.
- Tidak membaca isi rekam medis pasien atau rahasia `.env`; tidak mengubah database layanan, menjalankan migration/seeder, melakukan deployment, atau mengubah kode aplikasi.
- Temuan statis disebut terbukti apabila jalurnya tampak dalam kode. Risiko turunan disebut risiko, bukan eksploitasi yang sudah dilakukan. Status produksi, hak database efektif, volume/keutuhan data lama, TLS, backup nyata, dan SOP institusi belum diverifikasi.
- Tidak mengaktifkan kembali modul kasir, billing, laboratorium internal, pengadaan, depo, atau master tindakan yang sudah dipensiunkan.

## 2. Dasar resmi dan koreksi konsep

Tautan di bawah diperiksa pada tanggal audit. Status PMK 24/2022 tercantum berlaku pada katalog BPK. Ini kajian regulasi yang relevan terhadap scope aplikasi, bukan inventaris seluruh peraturan kesehatan nasional. Pemeriksaan perubahan regulasi saat implementasi/rilis tetap wajib menjadi gate T01.

| Ref | Sumber resmi dan bagian yang digunakan | Arti bagi kajian |
| --- | --- | --- |
| R01 | [PMK 24/2022, naskah resmi](https://jdih.kemkes.go.id/storage/documents/pdfs/2022permenkes024.pdf); [status BPK](https://peraturan.bpk.go.id/Details/245544/permenkes-no-24-tahun-2022) | Pasal 10–12: interoperabilitas, variabel/metadata, registrasi sistem. Pasal 16: pencatatan lengkap, jelas, identitas pemberi layanan, waktu dan urutan. Pasal 20: cadangan periodik, lokasi berbeda, SOP. Pasal 21: interoperabilitas Kemenkes. Pasal 26: isi dan penyampaian kepada pasien. Pasal 29–31: keamanan, hak akses, koreksi dan autentikasi. Pasal 33–37: pembukaan terbatas menurut dasar yang sah. Pasal 39: retensi minimal 25 tahun sejak kunjungan terakhir. |
| R02 | [KMK HK.01.07/MENKES/1423/2022, PDF melalui dokumentasi SATUSEHAT](https://storage.googleapis.com/satusehat-platform/supporting-docs/11.%20KMK%20No.HK.01.07-MENKES-1423-2022%20ttg%20Standar%20dan%20Metadata%20Rekam%20Medis-signed.pdf) | Bagian B Rawat Jalan, khususnya identitas, general consent, asesmen, pemeriksaan dan terapi; bagian farmasi ditelaah sesuai layanan yang benar-benar ada. Tidak semua variabel IGD/rawat inap berlaku pada klinik ini. |
| R03 | [UU 27/2022, JDIH Kemkomdigi](https://jdih.komdigi.go.id/produk_hukum/view/id/832/t/undangundang%2Bnomor%2B27%2Btahun%2B2022) | Data kesehatan merupakan data spesifik. Pasal 20 membedakan dasar pemrosesan; persetujuan bukan satu-satunya dasar. Pasal 31, 34–39, 46 dan 53 relevan untuk pencatatan pemrosesan, penilaian dampak, perlindungan, insiden dan fungsi PDP. |
| R04 | [UU 17/2023 Pasal 293, publikasi MK](https://s.mkri.id/public/content/infoumum/undang/pdf/Anotasi_189_2.%20UU%20No.%2017%20-%202023%20Kesehatan.pdf); [PP 28/2024, katalog pemerintah](https://jdih.kemenkoinfra.go.id/pp-no-28-tahun-2024) | Persetujuan tindakan sesudah penjelasan; tertulis sebelum tindakan invasif dan/atau berisiko tinggi. Wakil, saksi, penolakan dan keadaan darurat perlu aturan SOP. Pencarian menampilkan bagian Pasal 293; PDF anotasi MK tidak berhasil dibuka penuh karena ukuran. Kajian hukum rilis harus mengonfirmasi naskah konsolidasi dan aturan turunannya. |
| R05 | [PMK 34/2021, Farmalkes](https://farmalkes.kemkes.go.id/unduh/permenkes-34-2021/), PDF melalui tombol Unduh | Lampiran pengkajian resep memuat aspek administratif, farmasetik, klinis, komunikasi ketidaksesuaian kepada dokter, serta penyerahan dan informasi obat. Pembatasan akses farmasi perlu tetap memungkinkan pengkajian yang aman. |
| R06 | [Terminologi SATUSEHAT](https://satusehat.kemkes.go.id/platform/docs/id/terminology/) | Diagnosis ICD-10 versi 2010, prosedur ICD-9-CM versi 2010; KFA dan LOINC mempunyai fungsi berbeda. ICD-10-CM dan ICD-9-CM diagnosis tidak otomatis ekuivalen dengan ICD-10 WHO. |
| R07 | [MPI pasien tanpa NIK](https://satusehat.kemkes.go.id/platform/docs/id/master-data/master-patient-index/pasien-no-nik/) | Ada mekanisme pencarian pasien tanpa NIK. NIK 16 digit harus didukung dan divalidasi saat tersedia; jangan membuat NIK palsu atau menganggap NIP sebagai NIK/IHS. |
| R08 | [Playbook Rawat Jalan SATUSEHAT](https://satusehat.kemkes.go.id/platform/docs/id/interoperability/rme-rawat-jalan/); [LOINC laboratorium](https://satusehat.kemkes.go.id/platform/docs/id/terminology/loinc/laboratory/) | Pemetaan FHIR mengikuti resource dan use case; scan PDF tidak otomatis menjadi hasil laboratorium terstruktur. Kebutuhan nilai, satuan, metode dan sumber harus dipetakan bila hasil terstruktur dicatat. |

### Keputusan interpretasi untuk rancangan

1. Rujukan eksplisit variabel/metadata berada pada **Pasal 11**, melengkapi Pasal 10 dan 21. General consent ditelaah dari KMK bagian rawat jalan; jangan menyamakan dengan persetujuan tindakan atau persetujuan pelepasan kepada semua pihak.
2. Tenggat **2×24 jam** pada Pasal 30 dihitung sejak data diinput; persetujuan koreksi administratif lewat tenggat bukan izin menimpa catatan klinis final. Addendum klinis lewat tenggat perlu prosedur yang disahkan pimpinan, menjaga catatan asli; jangan menyatakan addendum tanpa batas waktu otomatis sah.
3. Idle 10–15 menit, backup harian, TLS 1.2/1.3, encrypted cast, nama tabel addendum dan QR adalah pilihan kontrol teknis untuk memenuhi tujuan keamanan. Angka, library dan nama tabel tersebut bukan kutipan kewajiban literal PMK.
4. Kunci pemeriksaan dokter berbeda dari penutupan seluruh kunjungan: tindakan perawat dan farmasi masih perlu mencatat pelaksanaan setelah dokter menandatangani.
5. Pemisahan peran adalah target proyek sesuai arahan pengguna. Regulasi mengharuskan kewenangan dan tujuan akses yang ditetapkan fasilitas; bukan menetapkan nama role Laravel atau larangan universal membaca untuk semua petugas administrasi.
6. Farmasi tidak mendapat akses bebas SOAP/odontogram. Data minimum pengkajian obat dapat mencakup indikasi/diagnosis relevan, umur, jenis kelamin dan kondisi relevan selain resep, alergi dan berat badan. Penetapan proyeksi data dilakukan bersama apoteker; jangan mempersempit hingga mengganggu keselamatan. [R05]
7. Gambar tanda tangan, kata sandi finalisasi, hash berantai dan QR bukan bukti tersendiri bahwa TTE tersertifikasi telah tersedia. QR verifikasi tidak boleh membocorkan rekam medis ke publik.
8. Persiapan SATUSEHAT **belum memenuhi kewajiban interoperabilitas aktual**. Integrasi produksi tetap item terbuka, sesuai permintaan pengguna dikerjakan setelah celah internal ditutup.
9. Penghapusan setelah masa retensi bukan pekerjaan otomatis pada ulang tahun ke-25. Target aplikasi menolak penghapusan transaksi, dengan pemusnahan formal di luar scope tahap ini.

## 3. Kontrol yang sudah ada dan dipertahankan

| Area | Bukti pemilik | Penilaian |
| --- | --- | --- |
| Tahap layanan dan kunjungan baru | `Kunjungan`, `VisitCareWorkflow`, `VisitSchedulingService`; unique `kunjungan_id` pada screening/pemeriksaan/resep | Pemisahan record per kunjungan dan tahapan sudah ada. Bukan alasan membuat RME baru dari nol. |
| Tanggal layanan | `VisitSchedulingService::resolveDoctor`, `ensureServiceDate` | Pembatasan tanggal sudah ada; perlu uji timestamp/provenance menyeluruh, bukan klaim seluruh entri bisa backdate. |
| Final dokter | `PemeriksaanController::selesai`, `authorizeMutableVisit` | Kata sandi, review, lock transaksi dan `signed_at` server sudah digunakan. |
| Addendum dan integritas | `ClinicalNoteRecorder`, `ClinicalNoteVersion`, `addendum`, `addendumOdontogram` | Final dan addendum memiliki payload, versi dan hash. Mutasi final melalui endpoint biasa sudah dibatasi. |
| Pengkajian perawat | `ScreeningVersion`, `ScreeningController`, `Kunjungan::canUpdateScreening` | Riwayat versi dan larangan update/delete model versi ada; revisi dibatasi sebelum dokter mulai. |
| Penugasan klinis | `DoctorAccessService`, `PerawatClinicResolver`, route middleware | Dokter terikat penugasan/jadwal dan perawat terikat poli; bukan aplikasi tanpa RBAC. |
| Dokumen | `ClinicDocumentNumber`, `CorrespondenceService`, `MedicalLetterData`, `PersuratanController` | Nomor otomatis, snapshot surat, jenis `ringkasan`, rujukan luar/internal, SIP/STR dan gambar tanda tangan sudah ada. |
| Farmasi dan stok | `FarmasiController::selesai`, `PersediaanRecorder` | Final penyerahan bertimestamp, pencocokan item resep, alasan parsial, batch, retur dan perlindungan proses berulang sudah ada. |
| Data pasien | `Pasien` SoftDeletes, `PasienController::destroy` | Pasien dengan kunjungan/obat langsung ditolak penghapusannya. Jangan mengklaim pasien bertransaksi bebas dihapus dari UI. |
| Lampiran | `VisitAttachmentController`, `VisitAttachmentTest` | Private disk, pemeriksaan berkas, otorisasi dan lock setelah handoff tersedia. |
| Audit | `ClinicalAuditRecorder`, `AdminActivityLogController` | Berbagai view, print, ekspor dan perubahan telah dicatat; tidak ada endpoint edit/hapus log yang ditemukan. Cakupannya belum lengkap. |

## 4. Register temuan

Prioritas: **P0** risiko akses/kehilangan/integritas utama; **P1** kelengkapan kontrol dan keselamatan; **P2** kesiapan integrasi/penyempurnaan. P0 tidak berarti insiden telah terjadi. Status seluruh F01–F23 masih OPEN; detail tugas ada di tracker.

| ID | Prioritas; status bukti | Temuan dan bukti kode | Akibat / arah penutupan |
| --- | --- | --- | --- |
| F01 | P0; terbukti | `routes/web.php` mengizinkan admin pada screening/pemeriksaan/farmasi/lampiran/persuratan; `DoctorAccessService::visibleVisits` memberi cakupan semua poli aktif. `AdminMedicalRecordTest` sengaja menguji admin membaca lintas poli. | Akses admin bertentangan target terbaru. Tutup sisi server, serializer, navigasi dan semua URL turunan; T02–T04. |
| F02 | P0; terbukti | `AdminClinicalAssistance::authorizeDraft`, `ScreeningController::start/store`, `AdminPharmacyAssistance` menyediakan bantuan menulis bagi admin dengan alasan/konteks. | Audit/alasan bukan pengganti kewenangan profesi. Hentikan bantuan tulis klinis admin pada target default; pertahankan jejak historis dan jalur koreksi petugas sah; T03. |
| F03 | P0; terbukti parsial | Route laporan admin melayani laporan individu screening/pemeriksaan/tindakan/obat. `LaporanController` dan berbagai Exports perlu inventaris kolom lengkap. | Penolakan halaman RME saja menyisakan jalur ekspor. Agregasikan laporan nonklinis, minimalkan direktori/NIK, uji ketiadaan data dalam props dan file; T04. |
| F04 | P0; terbukti | `KunjunganController::destroy` memakai `delete()` nyata untuk kunjungan menunggu tanpa pemeriksaan/resep/screening. `Kunjungan` tidak memakai SoftDeletes. | Riwayat registrasi hilang; lampiran belum diperiksa di guard tersebut. Ganti pembatalan beralasan dan audit, uji legacy URL; T05. |
| F05 | P0; terbukti | `VisitAttachmentController::destroy` membuang metadata dan menghapus file sesudah commit saat tahap awal. | Bukti medis yang sudah diterima bisa hilang walau kunjungan masih ada. Batalkan secara logis dengan alasan, simpan versi/arsip dan batasi akses; T06. |
| F06 | P0; risiko skema terbukti | Migration awal memakai cascade pasien→kunjungan→screening/pemeriksaan/resep/farmasi/surat/consent; tabel versi final menggunakan restrict sehingga sebagian rantai tertahan. | Jalur hard delete layanan/draf tidak semuanya tertahan pada DB. Inventaris FK akhir dan ubah dengan migration baru ke restrict/no action; uji engine target; T07. Bukan bukti seluruh data final dapat dihapus dari UI. |
| F07 | P0; terbukti | `ClinicalAuditRecorder` menyimpan actor, target, aksi, route, IP, waktu, metadata; tidak menyimpan role snapshot/user agent/diff standar. `ClinicalNoteVersion` belum mempunyai larangan update/delete seperti `ScreeningVersion`; query builder dapat melewati model hook. | Tidak ada bukti append-only pada hak DB/trigger. Hash mendeteksi perubahan tertentu, bukan melarang penulisan ulang oleh pihak berhak DB. Perlu ledger, proteksi dan verifikasi; T08–T09. |
| F08 | P1; terbukti | `LoginController` belum merekam gagal login; recorder mengasumsikan `$request->user()->id`. Beberapa ekspor farmasi dan print browser tidak melalui recorder. | Kejadian anonim/ditolak/ekspor/cetak tidak tercakup penuh. Rancang actor nullable, hasil kejadian, korelasi dan audit tanpa rahasia; T08, T10. |
| F09 | P1; terbukti | Default `SESSION_LIFETIME=120`; login menerima remember. Tidak ditemukan kontrol idle frontend/backend khusus atau invalidasi remember untuk idle. | Belum ada penguncian 10–15 menit yang terjamin. Polling tidak boleh memperpanjang idle; T11. Nilai efektif produksi belum diperiksa. |
| F10 | P0; bukti repo / produksi belum diuji | `routes/console.php` hanya menjadwalkan prune jadwal; tidak ditemukan sistem backup/restore terkelola atau spatie backup dalam composer. | Backup eksternal mungkin ada tetapi belum dibuktikan. Lindungi data sebelum migration: backup database+file, off-site, kunci, pemantauan dan latihan restore; T12. |
| F11 | P1; belum diverifikasi di produksi | Cast NIK dan catatan adalah plain data; `SESSION_SECURE_COOKIE` bergantung environment. Tidak ada bukti konfigurasi TLS/disk encryption/key custody pada repo. | Nilai keamanan produksi belum diketahui; jangan menyatakan pasti HTTP atau disk tidak terenkripsi. Verifikasi TLS, trusted proxy, cookie, debug, secret dan penyimpanan; rancang enkripsi tanpa merusak pencarian/hash; T13–T14. |
| F12 | P1; terbukti | General consent tidak ditemukan pada route/form/model. `InformedConsent` merupakan tabel/model dasar, tidak ditemukan alur simpan/cetak/tanda tangan/penolakan atau syarat sebelum tindakan. | Consent belum operasional walau nama tabel ada. Form, bukti, identitas penjelas/wakil/saksi, versi dan gate sebelum pelaksanaan; T15–T16. |
| F13 | P1; terbukti | `PatientClinicalHistory::forVisit`, `PatientAllergyHistory` memakai kunjungan lalu; `ScreeningController::show` mengisi field kosong dari hasil itu. `PemeriksaanController::show` mengisi defaults dari perawat kunjungan kini dan riwayat sebelumnya. | Handoff kunjungan sama berguna, tetapi asal data lama dan konfirmasi saat ini belum menjadi provenance seragam per field. Tampilkan sumber read-only dan konfirmasi eksplisit; jangan menghapus peringatan alergi; T17. |
| F14 | P1; terbukti | `PemeriksaanController::selesai` mengisi narasi tidak diperlukan/tidak ada pada beberapa field kosong; tindakan dokter pending otomatis completed saat finalisasi. | Review umum tidak selalu membuktikan keputusan tiap field atau pelaksanaan tiap tindakan. Konfirmasi spesifik tanpa menambah tahap baru, pertahankan tidak diperiksa/tidak diketahui; T18. |
| F15 | P1; terbukti | `storeDiagnosa` mengizinkan manual, ICD-10-CM, ICD-9-CM diagnosis; bila katalog tertentu kosong validasi tidak selalu mengikat keberadaan kode. `storeTindakan` mengizinkan manual/master lokal tanpa ICD-9-CM. Finalisasi umum hanya menuntut tepat satu diagnosis utama, bukan seluruh coding standar. | Tidak cukup siap standar. Draft naratif tetap aman; kelengkapan final berkode diperiksa pada diagnosis dan tindakan relevan, dengan prosedur gangguan katalog dan rekonsiliasi terkontrol; T19–T20. Jangan mengarang kode legacy. |
| F16 | P2; terbukti | `Obat.kode_kfa` nullable, validasi string max 30 dan impor bebas; belum ada pembeda tipe produk, versi/sumber, status validasi. LOINC/IHS/mapping FHIR tidak ditemukan pada model/layanan aktif yang diperiksa. | Siapkan mapping terverifikasi dan kontrak data, bukan sekadar field kode; T21, T27–T28. |
| F17 | P1; terbukti | `MedicalLetterData::snapshot` mengambil `$exam` dasar. Addendum narasi menyimpan payload versi, tidak menimpa catatan final dasar. | Surat baru sesudah addendum berisiko mengambil isi lama. Gunakan versi efektif terverifikasi dan simpan ID versi; surat lama tidak diubah diam-diam; T22. |
| F18 | P1; terbukti | `MedicalLetterData::snapshot` mengecek tindakan perawat `execution_status !== 'selesai'`, sementara controller memakai `completed`. | Salah label tindakan selesai sebagai direncanakan. Perbaiki kontrak status dan pisahkan rencana dokter dari realisasi; T22. |
| F19 | P1; terbukti parsial | Jenis `ringkasan` dan cetak RME tersedia; penerbitan surat oleh dokter dengan `signed_at` dan integritas sudah diperiksa. Tidak ditemukan QR registry/verifikasi atau register permintaan/pelepasan. Template snapshot belum mengikat seluruh identitas faskes/versi dokumen sebagai arsip tetap (`baseDocument` mengambil profil klinik saat ini). | Perkuat resume per kunjungan, snapshot lengkap, pembatalan/pengganti, penerima dan tujuan salinan. QR tanpa identitas sensitif publik; T23–T24. |
| F20 | P1; terbukti parsial | `FarmasiController::show` mengirim resep dan alergi tetapi belum berat badan/umur/jenis kelamin/SIP sebagai konteks lengkap pengkajian. Belum ditemukan form terstruktur review administrasi/farmasetik/klinis dan klarifikasi sebelum dispensing. | Kelengkapan review farmasi belum dibuktikan oleh stok dan catatan penyerahan. Tambahkan data minimum serta rekaman review/komunikasi dengan dokter; T25. Tidak menambah mesin keputusan obat otomatis. |
| F21 | P1; terbukti parsial | Addendum narasi dan odontogram tersedia; endpoint narasi tidak mencakup koreksi diagnosis dan resep final. Tidak ditemukan batas waktu koreksi seragam atau alur persetujuan administratif lewat 48 jam. | Catatan koreksi, dokumen, allergy alert, rencana kontrol, obat yang sudah diserahkan dan outbound kelak harus konsisten. Rancang koreksi per domain; T26. |
| F22 | P1; bukti belum lengkap | No-store ada pada sebagian respons, belum seragam pada seluruh respons sukses klinis. Belum ditemukan register hak pasien, DPIA, SOP insiden/pemulihan, kebijakan retensi menyeluruh atau bukti UAT khusus kontrol ini. | Kebijakan, hak pasien, cache/history setelah logout, incident response dan bukti operasional menjadi pekerjaan nyata; T01, T11–T14, T24, T29–T30. Audit dependency sebelumnya bukan bukti keadaan hari ini; periksa ulang sebelum rilis. |
| F23 | P0; risiko kredensial terbukti | `UserController::resetPassword` menerima password yang dipilih admin dan mengubah hash, tanpa status wajib ganti dalam metode itu. `ProfileController::updatePassword` dan reset tidak memperlihatkan pencabutan seluruh sesi/remember; perlu uji perilaku sesi aktif. | Mengurangi role admin belum menyelesaikan risiko penggunaan kredensial dokter yang diketahui pengelola. Rancang reset sementara dan aktivasi/ubah mandiri sebelum akses klinis/final, revocation sesi dan audit signature. Tidak mengklaim sysadmin dapat dibatasi sepenuhnya oleh RBAC aplikasi; T31. |

## 5. Hasil uji pada checkpoint kajian

Pengujian memakai `phpunit.xml` dengan SQLite `:memory:`, session array dan storage fake pada tes lampiran. Tidak ada pengujian browser/produksi, restore off-site, atau SATUSEHAT API nyata pada sesi ini.

```text
php vendor/phpunit/phpunit/phpunit --filter 'AdminMedicalRecordTest|RoleBoundaryTest|ClinicalNoteVersionFlowTest|ClinicalProcedureFlowTest|VisitAttachmentTest|RmeAuthFlowTest|PatientAllergyContinuityTest' --no-progress
PASS: 64 tests, 846 assertions.
```

Batch kedua:

```text
php vendor/phpunit/phpunit/phpunit --filter 'AdminClinicalAssistanceTest|AdminPharmacyAssistanceTest|ClinicalAccessAndTwoPoliTest|ScreeningWorkflowTest|GeneralAssessmentAlignmentTest|PersuratanFlowTest|InventoryTraceabilityTest|RegistrationFlowEnhancementTest|NonPharmacologicalWorkflowTest|AdminActivityLogTest|LaporanInertiaTest' --no-progress
PASS: 131 tests, 2.838 assertions.
Total dua batch tanpa overlap kelas: 195 tests, 3.684 assertions.
```

Makna hasil: mengonfirmasi perilaku lama, termasuk akses admin, manual coding dan penghapusan lampiran yang justru perlu disesuaikan. Tidak ada temuan yang ditutup hanya karena baseline ini hijau. Ini bukan full suite; frontend/build tidak dijalankan ulang karena sesi ini hanya menambah dokumen kajian.

## 6. Batas keputusan dan pekerjaan yang memerlukan bukti lain

- Pimpinan/penanggung jawab klinik: matriks akses formal, penugasan pengganti dokter, koreksi lewat tenggat, persetujuan darurat/wakil/saksi, masa berlaku general consent, kebijakan obat langsung dan aturan pelepasan informasi.
- Apoteker: data minimum review resep, otoritas apoteker vs TTK, konseling dan batas perubahan aturan pakai. Jangan menyamakan semua akun `farmasi` dengan apoteker tanpa verifikasi profil.
- Operator infrastruktur: topologi dan engine DB aktual, akun DB runtime, TLS/proxy, off-site dalam yurisdiksi yang sesuai, enkripsi dan recovery key, jadwal nyata dan latihan pemulihan.
- Pengelola data/klinisi: profil kualitas data lama yang diizinkan secara agregat; kode belum dipetakan, kunjungan final tanpa tanda tangan/versi, NIK tidak lengkap, file hilang dan audit sebelum instrumentasi. Tidak boleh membuat consent, timestamp, tanda tangan atau kode historis seolah sudah diverifikasi.

SOP terbuka tidak menghentikan pekerjaan teknis independen. Tugas yang bergantung kepadanya tetap OPEN/NEEDS_SOP dan tidak diganti asumsi tersembunyi. Sasaran kelulusan adalah kontrol yang teruji dan bukti yang dapat ditinjau; tidak menjanjikan sistem tanpa kemungkinan cacat.

## 7. Temuan implementasi tambahan � 19 November 2026

F24: SQLite rebuild tabel akibat perubahan foreign key dapat membuang trigger retensi. Kasus nyata pada migration general consent terdeteksi sebelum rilis oleh SQL-delete regression/security-check. Forward migration memulihkan set guard; buktinya dilacak pada T07/T09/T29.

F25: export perlu mempertahankan identitas sebagai string dan melindungi teks berawalan whitespace dari interpretasi formula. Binder eksplisit diterapkan pada semua kelas export; uji file XLSX aktual pada T04/T10/T29. Ini melengkapi sanitasi prefiks yang sudah ada, tanpa menganggap semua hasil cetak/ekspor telah UAT visual.
