# Log implementasi penguatan RME

19 November 2026 — branch `rmeupdate`, perubahan working tree belum di-commit. [Tracker utama](rme-hardening-tracker.md) tetap menjadi daftar acceptance. Perubahan admin yang sudah ada sebelum batch ini dipertahankan. Tidak ada deploy atau pengiriman SATUSEHAT produksi.

## Batch akses, retensi dan integritas

| Task | Sudah diterapkan | Yang belum menutup acceptance |
| --- | --- | --- |
| T01/T30 | Draf SOP dan runbook dibuat; pengguna menyatakan SOP tertulis/off-site belum tersedia | Penetapan PJ, kewenangan, klasifikasi tindakan, RPO/RTO, pelatihan dan UAT klinis belum disahkan |
| T02/T03 | Policy kunjungan + middleware layanan membatasi role; endpoint klinis admin ditolak; tautan/props klinis admin dibatasi, template tetap tersedia | Lengkapi inventaris route×aksi×objek; review scope seluruh endpoint dan browser lintas role. T03 IN_REVIEW belum berarti semua proyeksi terverifikasi |
| T04 | Laporan klinis admin menjadi agregat, audit diekspor lewat whitelist, alasan stok klinis dimasking; top diagnosis nonklinis minimal lima pasien berbeda | Threshold ini mitigasi kelompok kecil, bukan bukti anonimisasi formal; audit kombinasi filter/ekspor dan fungsi manajemen lain belum selesai |
| T05 | Endpoint delete/batal kunjungan menjadi pembatalan beralasan, actor/time dan audit dalam transaksi; hanya status menunggu | Review reservasi/rujukan/double-submit dan penerimaan UI operasional akhir |
| T06 | Upload menyimpan SHA-256, download memverifikasi, void menyimpan alasan dan file asli tetap ada | Akses arsip/pemulihan beralasan dan UI riwayat lampiran belum lengkap; bukti legacy tanpa hash tidak dibuat-buat |
| T07 | Trigger DB melarang DELETE pada induk/transaksi inti; draf diagnosis/resep/tindakan/item dispensing memiliki voided state; daftar aktif memakai scope dan riwayat dokter menampilkan pembatalan | Inventaris semua FK/jalur console/import, validasi upgrade legacy dan engine target; tidak menganggap semua child table sudah terlindungi |
| T08 | Recorder actor nullable, role snapshot, UA, outcome dan request UUID; auth/penolakan/pembatalan/signature tercatat; sejumlah mutasi atomik | Standardisasi diff whitelist, cakupan mutasi dan fail-closed audit lengkap belum selesai |
| T09 | Trigger UPDATE/DELETE untuk audit/versi, model guard versi klinis; uji raw SQL SQLite menolak | Akun pemilik SQLite dapat menghapus trigger/file; perlu ACL dan anchor off-host. MySQL/PostgreSQL belum diuji |
| T10 | Ekspor audit Excel aman formula dan halaman cetak/simpan PDF; filter bersama, export/print diaudit | Belum ada bukti seluruh endpoint view/print/export tercakup; halaman cetak bukan generator PDF server dan bukan bukti printer berhasil |
| T11 | Idle server 15 menit; polling tidak memperpanjang; heartbeat aktivitas, warning/lintas-tab frontend; no-store respons autentik | Browser tab tidur/back/account switching serta pemulihan draf belum UAT; deadline frontend perlu disinkronkan persis dengan server |
| T12 | SQLite consistent DB snapshot + encrypted archive/private files, baca ulang checksum dan DB integrity/FK; jadwal harian conditional dan security-check | Hanya checkpoint lokal terbukti. Off-site, scheduler nyata, alarm, recovery layanan penuh dan konsistensi DB/file bersamaan upload belum terbukti; lihat runbook |
| T13 | Pemeriksaan konfigurasi produksi tersedia; cookie/cache/temporary credential diperketat | Handshake TLS, proxy/OS/private storage dan dependency scan final pada target belum selesai |
| T17 | Penulisan alergi oleh admin/pendaftaran dicegah; pengkajian klinis menjadi sumber verifikasi | Provenance/adopsi riwayat per field serta seluruh legacy/default masih harus diperiksa |
| T18 | Field kosong saat final tidak diisi narasi negatif; tindakan dokter memerlukan konfirmasi eksplisit sebelum completed; actor/time/ID tindakan tersimpan di versi final | Uji final/cancel/retry/BHP terbaru serta gate consent tindakan berisiko belum lengkap |
| T22 | Resolver versi efektif terverifikasi untuk narasi surat; facility snapshot; status completed diperbaiki | Koreksi diagnosis/resep/rencana dan seluruh consumer mengikuti T26; bukan berarti semua perubahan domain sudah terselesaikan |
| T31 | Reset/create password sementara, wajib ubah pribadi sebelum layanan; session_version mencabut sesi lama; signature lama tetap tersimpan dan perubahan diaudit | Reauth/MFA dan penugasan penjaga identitas/SOP akun perlu penilaian; browser sesi aktif belum UAT lengkap |

T14–T16, T19–T21 dan T23–T28 yang belum dirinci di atas tetap mengikuti status tracker. Batch awal belum mencakup general consent. Modul tersebut kemudian diterapkan pada batch berikut di bawah. Consent tindakan lengkap, QR/disclosure, pemetaan KFA, koreksi lintas domain dan adapter FHIR tetap belum diklaim selesai.

## Skema lokal dan checkpoint

Database lokal SQLite saja; bukan bukti migrasi produksi. Tiga migration additive diterapkan setelah checkpoint lokal terenkripsi:

1. `2026_11_18_214437_add_security_context_to_users_and_clinical_audit_events`: metadata sesi, pembatalan dan audit.
2. `2026_11_18_215833_protect_clinical_records_from_destructive_changes`: trigger retensi transaksi dan append-only versi/audit.
3. `2026_11_19_092709_add_void_state_to_clinical_draft_entries`: metadata pembatalan dan larangan delete empat tabel item klinis.

Checkpoint sebelum migration ketiga: `clinical-20261119-093448-984e19fe-d896-4972-96bb-5ad90e0f921f.zip`, hasil pembuatan/dekripsi/checksum/integritas DB lulus. Lokasi lokal di direktori recovery operator di luar repo; kunci disimpan terpisah, isinya tidak dicatat. Checkpoint ini tidak boleh dilabeli off-site.

Migration perlindungan tidak memiliki rollback destruktif; kegagalan diperbaiki dengan forward fix yang mempertahankan bukti. Pemulihan dari checkpoint dilakukan terisolasi lebih dahulu, bukan otomatis menimpa data setelah migration.

## Register verifikasi

Hasil di `storage/framework/testing/` merupakan artefak runtime lokal yang diabaikan git; angka final harus diambil dari run setelah perubahan terakhir.

| Pemeriksaan | Hasil checkpoint | Batas |
| --- | --- | --- |
| `npm run types:check` | Lulus, `hardening-types-4.txt` | Sebelum penyempurnaan UI berikutnya; ulangi bila frontend berubah |
| Backend terarah awal retensi | 108 tes: 105 lulus, 1 gagal, 2 error; `hardening-retention.txt` | Dua fixture menghapus pemeriksaan, satu belum menulis reaksi alergi; fixture diperbaiki, run ulang diperlukan |
| Backup/migration lokal ketiga | Backup verifikasi lulus; migration DONE | Tidak ada klaim restore layanan penuh/off-site |
| Backend sebelumnya | Subset inti 21/21 dan frontend 295/295 pernah lulus | Mendahului perubahan alergi/retensi/review terbaru; bukan hasil final |

Run `hardening-suite-3.json` dihentikan ketika source masih berubah dan **tidak sah sebagai hasil suite**. Jangan menjalankan suite backend bersamaan atau mengubah kode PHP saat suite yang akan dipakai sebagai bukti sedang berjalan.

## Langkah berikut yang tetap wajib

1. Selesaikan tes retensi/konfirmasi tindakan serta semua regresi; tutup jalur kehilangan file/item lainnya.
2. Lengkapi inventory akses, atomisitas audit/diff dan pembacaan arsip berwenang; uji sesi secara nyata.
3. Jalankan draf SOP dan spesifikasi infrastruktur ke review fungsi yang ditetapkan. Pekerjaan kode independen tetap berjalan.
4. Lengkapi consent, terminologi, dokumen, farmasi dan koreksi menurut dependensi tracker sebelum readiness SATUSEHAT.
5. Full checks + browser/print/engine target dan penerimaan klinis menjadi bukti terpisah; semua gate belum dinyatakan lulus.

## Batch setelah persetujuan SOP

Pengguna menyetujui penuh SOP dalam percakapan 19 November 2026. Persetujuan ini dipakai sebagai dasar implementasi, tanpa meminta persetujuan ulang. Bukti pelaksanaan dan kesiapan infrastruktur tetap diperiksa tersendiri.

- Regresi empat kelas dari suite sebelumnya: 60 tes / 1.115 assertions lulus (`hardening-regression-5.json`).
- General consent: tabel append-only beserta referensi kunjungan, bukti scan private/hash, versi penjelasan, penandatangan/wakil terverifikasi, accepted/refused/withdrawn, stale guard dan retry idempotent. Audit dan penyimpanan atomik; kegagalan menghapus hanya upload yang belum commit.
- Pengkajian kunjungan registrasi baru memerlukan keputusan terdokumentasi. Penolakan tidak otomatis menolak seluruh pelayanan. Penundaan administrasi pada triase darurat memiliki alasan/audit; tidak dibuat consent yang tidak ada.
- Kunjungan sebelum aktivasi tidak dibackfill sebagai setuju; flag migrasi membedakan rollout dan kunjungan baru. UI memperlihatkan status sebenarnya. Perlu program verifikasi administratif legacy tanpa memalsukan bukti.
- Migration kelima `2026_11_19_110000_create_general_consents` diterapkan lokal setelah checkpoint terenkripsi terverifikasi.
- Delapan tes awal general consent / 73 assertions lulus; suite regresi lintas registrasi/perawat/dokter/farmasi sedang diperiksa.
- T15 tetap IN_PROGRESS sampai regresi dan penerimaan UI tersedia. T16 dan T24 belum ditutup oleh general consent.

### Perbaikan lanjutan dan evidensi

- Setelah menambah FK consent, uji raw SQL mendeteksi trigger kunjungan hilang pada rebuild SQLite. Forward migration `2026_11_19_111000_restore_sqlite_clinical_guards_after_schema_upgrade` memulihkan seluruh set guard. Checkpoint lokal sebelum perbaikan: `clinical-20261119-101002-a652b1b3-d900-47e3-a0cd-0a59ad1a2c4d.zip`. Delapan hasil security-check lokal lulus setelahnya. Temuan F24 tetap menjadi acceptance upgrade setiap migration berikut.
- Seluruh export mendapat binder string eksplisit untuk NIK/nomor dan teks bebas. Uji workbook XLSX memeriksa NIK 16 digit dengan nol awal serta formula yang diawali whitespace. Tidak menyatakan data admin boleh berisi narasi klinis karena export sudah aman formula.
- Snapshot surat/resume kini mengambil diagnosis, resep dan nama rencana tindakan dari versi efektif terverifikasi, melengkapi narasi addendum yang sudah diperbaiki. Data master/proyeksi lama yang berubah tidak boleh diam-diam mengganti isi catatan final.
- Regresi general consent lintas registrasi/alur klinis: 75 tes / 981 assertions lulus (`hardening-consent-regression.json`). Frontend saat checkpoint general consent: 296/296 lulus. TypeScript kemudian menemukan htmlFor dan import CardDescription yang belum lengkap; diperbaiki dan diperiksa ulang, hasil build lama tidak dianggap bukti source baru.

### Perbandingan data sebelum/sesudah migration

Checkpoint terenkripsi pertama `clinical-20261118-215322-62f319c4-b18a-4487-b4bb-4bfa8e50a159.zip` dibuka untuk pemeriksaan read-only dan checksum DB diverifikasi. Perbandingan nilai kolom asli pada 15 tabel klinis terhadap DB lokal setelah migration: **tidak ada baris hilang dan tidak ada nilai asli berubah**. Cakupan termasuk 18 pasien, 42 kunjungan, 36 screening, 33 pemeriksaan, 23 resep, 31 item resep, 41 diagnosis, 41 tindakan, 26 temuan odontogram, 17 farmasi, 17 item farmasi, 1 farmasi mandiri, 11 surat, 28 versi dokter, 10 versi screening. PRAGMA integrity_check `ok`; foreign_key_check 0 pelanggaran. File plaintext pemeriksaan sementara dibersihkan. Ini bukti preservasi data lokal, bukan UAT produksi atau restore off-site. Artefak runtime agregat: `hardening-baseline-retention.json`.

### Kesiapan lingkungan yang diperiksa

`clinic:security-check --production --json` dijalankan read-only pada lingkungan pengembangan, bukan mengubahnya menjadi produksi. Seluruh delapan kontrol skema/trigger/sesi lokal lulus. Enam kontrol produksi belum terpenuhi: debug off, URL HTTPS, secure session cookie, backup enabled/scheduled, konfirmasi tujuan off-site dan backup off-site terverifikasi dalam 26 jam. Artefak: `hardening-production-readiness.json`. Tidak mengubah konfigurasi development secara membabi buta; konfigurasi dan bukti target rilis tetap mengikuti T12–T14/T30.

Checkpoint frontend setelah perbaikan field/import: TypeScript dan build lulus (`hardening-types-consent-4.txt`, `hardening-build-consent-4.txt`); 296/296 tes frontend lulus (`hardening-frontend-6.txt`); Pint check lulus; git diff --check tanpa masalah whitespace. Full backend `hardening-suite-6.json` masih menunggu hasil saat catatan ini ditulis.

### Checkpoint suite dan penguatan sesi/backup

- Full backend setelah general consent/guard/ekspor/dokumen: **549/549 tes lulus, 9.634 assertions** (`hardening-suite-6.json`). Hasil ini mendahului perubahan sesi-cache dan pemeriksaan referensi berkas backup di bawah.
- Penguatan sesi: endpoint status read-only mengembalikan identitas akun dan deadline server tanpa memperpanjang idle. Halaman yang dipulihkan dari browser cache disembunyikan sampai akun terverifikasi; pergantian akun lintas tab memuat ulang konteks yang benar. Tab lama tidak POST logout yang berpotensi memutus sesi operator baru. Saat verifikasi offline, halaman tetap tertutup dan menyediakan coba ulang; tidak menaruh PHI di localStorage.
- Lima tes efek komponen dengan browser surface terkendali lulus (`hardening-session-client.txt`), mencakup expiry, cache restore akun sama/berbeda, offline dan pergantian akun. Ini simulasi perilaku komponen, **bukan UAT browser nyata**.
- Verifikasi backup kini memeriksa referensi DB ke lampiran (termasuk yang dibatalkan), signature dan bukti consent di manifest. Backup tidak diberi status sukses bila salah satu referensi hilang. Enkripsi manifest/status monitoring dan pembersihan workspace juga diperiksa hasilnya.
- Regresi terarah sesi/backup: **20/20 tes lulus, 127 assertions** (`hardening-session-backup-2.json`). Pengujian awal menangkap kesalahan parameter fetch-column PDO; diperbaiki, kemudian keempat skenario kelengkapan file lulus.

### Status terakhir T16

Migration `2026_11_19_120000_bind_informed_consent_to_clinical_procedures` sudah diterapkan pada SQLite lokal setelah checkpoint terenkripsi `clinical-20261119-145251-a8c38f2e-ef15-44dc-902a-56c847779f12.zip`. Jumlah baris `informed_consent=0`, `tindakan_kunjungan=41`, `kunjungan=42`, dan `clinical_audit_events=13237` tetap sama; `foreign_key_check` tidak menemukan pelanggaran. Delapan pemeriksaan `clinic:security-check` lulus setelah migration, termasuk trigger append-only consent dan guard retensi klinis. Backup pascamigrasi `clinical-20261119-145312-3ebe6f6d-c39b-4836-8ae3-7ba8eeb6ccf9.zip` dibuat dan diverifikasi terenkripsi; nama artefak bukan pengungkapan kunci.

Route dan gate informed consent tindakan kini aktif. Tindakan baru wajib ditinjau dokter: `accepted`, `refused`, `withdrawn`, `not_required`, atau pengecualian darurat yang tetap mensyaratkan triase darurat. Keputusan terikat hash rencana tindakan, identitas kunjungan/tindakan, versi penjelasan, pasien/wakil/saksi, waktu server, actor, serta bukti private dengan checksum. Retry menggunakan request UUID; perubahan rencana membuat persetujuan lama stale. Bukti tidak dapat diganti/hapus dan akses unduh mengikuti scope kunjungan. Dokter tidak dapat memfinalisasi dan perawat tidak dapat memulai/menyelesaikan tindakan bila keputusan terkini tidak mengizinkan pelaksanaan. Tindakan legacy yang mendahului rollout tidak diberi consent fiktif.

Uji khusus T16 lulus **6/6, 77 assertions**; regresi tindakan lintas dokter/perawat/farmasi/gigi lulus **135/135, 2.273 assertions**. Setelah dua fixture sintetis lama memakai jalur klasifikasi dokter yang eksplisit, suite backend penuh lulus **560/560, 9.756 assertions** (`hardening-suite-8.xml`). Frontend lulus **301/301**, TypeScript lulus. T16 berstatus `IN_REVIEW`: acceptance teknis tersedia, tetapi UAT browser nyata, review klinis form, engine produksi dan deployment bukan diklaim oleh hasil SQLite/test renderer ini.

### Batch T19 — verifikasi diagnosis

UI diagnosis baru kini memakai ICD-10 WHO; kode ICD-10-CM/ICD-9 diagnosis tidak tersedia sebagai pilihan baru. Entri WHO mengambil uraian dan release dari katalog aktif, mencatat actor/waktu server dan status verifikasi. Katalog kosong atau entri manual tetap tersimpan sebagai `unresolved`. Final normal ditahan sampai pemetaan selesai; kondisi triase darurat atau konfigurasi downtime operator memerlukan konfirmasi serta alasan/rencana rekonsiliasi dokter. Snapshot final menyimpan status unresolved dan target review, tanpa memberi label siap interoperabilitas. Katalog yang dinonaktifkan/berubah sesudah draf diperiksa ulang saat finalisasi.

Migration lokal `2026_11_19_130000_add_diagnosis_coding_verification` diterapkan setelah backup terenkripsi terverifikasi `clinical-20261119-200311-b6e52c4d-cfd4-4e08-90d2-7d4d7814691a.zip`. Data tetap 41 diagnosis dan 33 pemeriksaan; 36 diagnosis arsip legacy dipertahankan, 5 diagnosis draf lama ditandai unresolved untuk review. FK 0 pelanggaran dan delapan security-check lokal lulus. Backup pascamigrasi `clinical-20261119-200426-bddd4013-88be-4425-b969-d48ce80c4c73.zip` terverifikasi. Tidak ada konversi kode atau verifikasi historis fiktif.

T19 tetap `IN_PROGRESS`: katalog/rilis yang disahkan klinik, rekonsiliasi diagnosis pascafinal melalui T26, dan UAT operasional belum ditutup. Enam tes coding awal menghasilkan 5 lulus dan satu assertion katalog gigi yang salah konteks; assertion diperbaiki. Regresi pertama 87/91 lulus, empat fixture lama kemudian disesuaikan ke katalog sintetis/downtime eksplisit. Suite penuh terbaru sedang dijalankan dan hasil akhirnya harus dicatat sebelum acceptance. TypeScript dan build lulus; frontend 301/301 lulus sebelum perbaikan assertion selector WHO, lalu tes selector khusus lulus sesudahnya.

### Hasil verifikasi akhir batch coding

- Suite backend penuh `hardening-suite-9.xml`: **566 tes, 552 lulus, 14 gagal**, 9.643 assertions. Seluruh enam tes khusus coding lulus (39 assertions). Keempat belas kegagalan berasal dari fixture pada `AdminClinicalAssistanceTest`, `ManualPatientRegistrationTest`, dan `OdontogramFlowTest` yang masih memakai diagnosis manual atau kode tanpa katalog saat final normal.
- Fixture tersebut diperbaiki dengan katalog sintetis yang dibuat eksplisit pada kasus terkait; diagnosis tanpa gigi tetap diuji sebagai diagnosis WHO tanpa konteks gigi. Regresi ulang ketiga kelas **66/66 lulus, 970 assertions** (`coding-regression-2.xml`). Kode produksi tidak dilonggarkan. Suite penuh tidak diklaim 566/566 karena tidak diulang setelah perubahan fixture terakhir.
- Frontend setelah perubahan UI terakhir **301/301 lulus** (`hardening-frontend-9.txt`), TypeScript lulus, build terbaru lulus (`hardening-build-9.txt`), Pint seluruh project lulus dan `git diff --check` bersih.
- Audit dependency saat pemeriksaan: Composer tanpa advisory/abandoned; npm production tanpa vulnerability. Ini hasil pada waktu pemeriksaan, bukan jaminan terhadap advisory yang terbit kemudian.
- T16 tetap `IN_REVIEW`, T19/T26 tetap `IN_PROGRESS`/`OPEN` sesuai tracker. Review browser/print nyata, infrastruktur off-site, engine produksi dan penerimaan operasional masih memerlukan bukti tersendiri.

### Rekonsiliasi diagnosis pascafinal dan review coding tindakan

Koreksi coding diagnosis tersedia dari pemeriksaan final untuk dokter penanggung jawab. Pemilihan kode WHO aktif/rilis, password, alasan dan review dampak klinis wajib. Versi sumber diperiksa dengan hash; retry request yang sama tidak membuat addendum ganda, sedangkan payload berbeda ditolak. Koreksi menambah versi immutable beserta diagnosis lama/baru dan provenance, mempertahankan tipe/gigi/permukaan serta seluruh rencana terapi. Baris diagnosis asli, resep, stok dan dokumen lama tidak ditimpa. Empat tes khusus lulus 59 assertions; regresi coding/terminologi/versi/dokumen **24/24 lulus, 282 assertions**. Koreksi domain lain, proyeksi laporan agregat diagnosis efektif dan komunikasi terapi tetap bagian T26/T22.

Review coding tindakan menambah klasifikasi eksplisit dokter: prosedur dipetakan ke katalog ICD-9-CM aktif beserta display/rilis/actor/waktu, atau perawatan yang tidak memerlukan kode prosedur dengan alasan dan konfirmasi. Status unresolved mengunci final normal; pengecualian darurat/downtime tetap tercatat unresolved. Kode yang retired/berubah diperiksa saat finalisasi. Perubahan kode rencana membuat consent sebelumnya stale. Draft dapat dipetakan dari tindakan klinik/manual tanpa mengganti nama lokal, lokasi gigi, kuantitas atau relasi BHP. Tindakan final tidak dapat dipetakan melalui endpoint draf. Migration `2026_11_19_203043` disiapkan; hasil pengujian dan penerapan lokal dicatat setelah verifikasi.

### Bukti batch rekonsiliasi dan ICD-9-CM

- Migration `2026_11_19_203043` diterapkan lokal setelah backup terenkripsi terverifikasi `clinical-20261119-203520-870cfce0-e6a8-4c3f-a57c-1c12e776b2e6.zip`. Jumlah tindakan tetap 41; semuanya legacy, tidak diberi klasifikasi historis fiktif. FK 0 pelanggaran; delapan security-check lulus. Backup pascamigrasi terverifikasi `clinical-20261119-204710-da65b424-f609-40d0-a4cd-52afc13ae5fe.zip`.
- Regresi tindakan/consent/diagnosis/dokter/perawat/farmasi/gigi: **119/119 tes, 1.960 assertions** (`procedure-coding-regression-2.xml`). Empat tes khusus review coding tindakan lulus 35 assertions. Setelah plan hash consent diperluas mencakup sistem/rilis/display kode serta normalisasi alasan, **20/20 tes, 210 assertions** lulus.
- Frontend batch coding/reconciliation **302/302** lulus (`hardening-frontend-10.txt`); TypeScript/build lulus. Proyeksi farmasi kemudian ditambah berat badan, waktu/sumber pengkajian kunjungan yang sama dan SIP dokter; hasil lanjutan dicatat terpisah.
- Referensi resmi [Terminologi SATUSEHAT](https://satusehat.kemkes.go.id/platform/docs/id/terminology/) diperiksa kembali: ICD-10 dan ICD-9-CM menggunakan versi 2010. Status verified aplikasi saat ini berarti cocok dengan katalog/rilis aktif lokal; **bukan readiness SATUSEHAT**. T19/T20/T27 tetap harus memvalidasi supported release serta asal katalog sebelum outbound. Fixture WHO 2016/FY 2015 hanya data pengujian, bukan katalog produksi yang direkomendasikan.
- Acuan review farmasi: [PMK 34/2021, sumber resmi Farmalkes](https://farmalkes.kemkes.go.id/unduh/permenkes-34-2021/). Klasifikasi tenaga apoteker/TTK dan review farmasi terstruktur tetap T25; tambahan BB/SIP belum menutup task tersebut.

Penguatan terakhir: hash rencana consent mencakup kode, sistem, rilis dan display, sehingga perubahan terminologi memerlukan review consent baru. Alasan koreksi/review/downtime dinormalisasi sebelum validasi panjang minimum. Regresi sesudah tambahan proyeksi farmasi **35/35 tes, 376 assertions** (`hardening-coding-pharmacy-11.xml`), frontend **302/302** (`hardening-frontend-11.txt`), TypeScript/build/Pint/diff lulus. Suite backend penuh `hardening-suite-12.xml` sedang diperiksa; hasil subset tidak dilabeli hasil suite penuh.

Penelusuran UI menangkap filter lama pada impor usulan diagnosis gigi yang masih menolak WHO. Filter diperbaiki; uji perilaku memastikan pemilihan WHO/rilis, konteks gigi/permukaan, dan draf untuk ditinjau tanpa penyimpanan diagnosis otomatis. Tes khusus lulus; frontend/TS/build diulang pada source terbaru (`hardening-frontend-12.txt`, `hardening-build-12.txt`).

Suite penuh sebelum batch QR **574/574 tes lulus, 9.922 assertions** (`hardening-suite-12.xml`). Frontend **303/303** lulus, TypeScript/build/Pint/diff lulus. Hasil ini menutup kegagalan fixture pada suite sebelumnya; belum mencakup kode QR berikut.

### T23 — bukti penerbitan dan pembatalan surat dengan QR

Surat baru melalui kedua jalur penerbitan dokter kini memperoleh bukti immutable berisi hash canonical arsip, actor/waktu server dan token acak 256 bit. Token disimpan terenkripsi dengan APP_KEY, lookup publik memakai SHA-256. Endpoint publik rate-limited hanya mengembalikan status arsip dan waktu pencatatan; tidak ada identitas pasien, nomor RM/surat, narasi, dokter, signature atau shared props akun. Uji menangkap shared props Inertia dari request sebelumnya; endpoint publik kini membersihkannya secara eksplisit dan tidak memakai layout aplikasi internal. Header no-store/no-referrer/noindex diterapkan.

Cetak surat menampilkan QR SVG lokal (BaconQrCode, tanpa layanan QR eksternal) dan watermark bila dibatalkan atau hash arsip tidak cocok. QR memeriksa arsip sistem; QR yang dipindahkan ke kertas lain tidak membuktikan isi kertas. UI menjelaskan batas ini dan tidak menyebut QR sebagai TTE tersertifikasi. Pembatalan memerlukan dokter penanggung jawab, password, alasan, transaksi dengan audit dan event append-only; retry tidak menggandakan pembatalan. Surat asli tetap tersimpan. Public lookup juga diaudit tanpa menyimpan token.

Migration `2026_11_19_210348` diterapkan setelah backup terenkripsi terverifikasi `clinical-20261119-210905-29499bd8-17a8-488b-9a44-eee7fa2ac23e.zip`. Sebelas surat lama tetap ada, tidak dibuat bukti penerbitan retroaktif (`proofs=0`); FK 0 pelanggaran. Security-check diperluas menjadi sembilan kontrol, termasuk empat trigger immutable bukti/event dokumen, dan semuanya lulus.

Regresi surat/versi/keamanan **34/34 tes, 706 assertions** (`document-verification-regression.xml`); frontend sebelum dua tes tampilan QR baru **303/303** (`hardening-frontend-13.txt`), dua tes tampilan tambahan lulus terarah; TypeScript/build lulus. Composer schema/lock valid (warning exact version Inertia sudah ada sebelumnya); Composer audit tanpa advisory/abandoned. QR SVG telah diperiksa sebagai XML valid; **pemindaian perangkat nyata dan print preview A4 belum dilakukan**.

T23 tetap IN_PROGRESS: relasi dokumen pengganti, QR rujukan internal dan consent, QA scan/print serta penerimaan klinis belum ditutup. Surat lama tetap memakai snapshot yang ada tanpa QR palsu. Pemulihan kunci APP_KEY untuk token arsip mengikuti runbook kunci, terpisah dari kunci AES backup.

Checkpoint akhir QR: **20/20 tes backend, 593 assertions** (`document-verification-final.xml`) setelah perubahan terakhir; **305/305 tes frontend** (`hardening-frontend-14.txt`); TypeScript/build/Pint/diff lulus. Enam tes khusus bukti/guard juga lulus 103 assertions. Kesembilan pemeriksaan keamanan lokal lulus. Backup pascamigrasi terverifikasi `clinical-20261119-211126-9d9e08f6-90b0-4ffe-a001-0bdcd6945e10.zip`. Suite penuh 574/574 mendahului QR dan tidak dipresentasikan sebagai full-suite source QR terbaru.

