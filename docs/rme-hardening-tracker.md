# Tracker utama penguatan RME

Tanggal mulai: 18 November 2026. Baseline: `rmeupdate`, HEAD `8f4a9cc` + working tree. Status diperbarui 19 November 2026; rincian perubahan dan batas bukti terdapat pada [log implementasi](rme-hardening-implementasi.md). Tidak ada task yang dinyatakan DONE hanya karena sebagian kode sudah tersedia.

- [Audit, bukti kode, sumber regulasi dan register F01–F23](rme-hardening-audit-2026-11-18.md)
- [Rancangan perilaku, akses, data, migrasi dan integrasi](rme-hardening-rancangan.md)
- [Pemetaan data dan daftar pemeriksaan variabel](rme-hardening-metadata.md)

## Aturan pelaksanaan

Status sah: `OPEN`, `IN_PROGRESS`, `NEEDS_SOP`, `NEEDS_INFRA`, `IN_REVIEW`, `DONE`. Seluruh task implementasi dimulai OPEN; label SOP/infra di bawah adalah dependensi yang belum terpenuhi, bukan alasan menunda pekerjaan independen. `DONE` mensyaratkan kode/konfigurasi, uji, bukti dan persetujuan domain yang relevan. Checklist `[x]` hanya untuk hasil yang benar-benar tersedia.

Setiap batch mencatat commit/diff, perubahan skema, sumber bukti, hasil test dengan tanggal, risiko legacy, keputusan SOP dan rollback. Jangan menganggap hasil suite lama sebagai hasil kode baru. Temuan tidak dihapus; pecah atau kaitkan ID bila berubah scope. Penurunan prioritas harus diberi alasan dan penanggung jawab. Temuan baru mendapat F24 dst., task baru T32 dst.

**Urutan:** G0 baseline → G1 akses dan pencegahan kehilangan → G2 integritas, sesi dan infrastruktur → G3 kelengkapan pelayanan/dokumen → G4 readiness SATUSEHAT → G5 UAT/rilis. Kajian dan perbaikan independen boleh berjalan di tahap awal, tetapi fase tidak dinyatakan lulus dengan celah sebelumnya masih terbuka. Jangan membuat adapter SATUSEHAT produksi untuk melewati pekerjaan internal.

## G0 — baseline dan keputusan

- [x] Inspeksi implementasi saat ini, identifikasi kontrol yang sudah ada, pisahkan risiko kode dari bukti produksi.
- [x] Telusuri sumber resmi PMK/KMK, UU PDP, persetujuan tindakan dan terminologi SATUSEHAT.
- [x] Buat audit, rancangan, pemetaan dan tracker yang saling tertaut.
- [x] Jalankan baseline terarah: 195 tes, 3.684 assertions; rinciannya pada audit. Hasil tidak menutup gap kebijakan baru.
- [ ] T01 tetapkan daftar keputusan operasional dan pemilik bukti, tanpa menghalangi perubahan teknis yang sudah jelas.

## Daftar task dan acceptance

PIC pada tabel adalah **fungsi yang dibutuhkan**, belum penugasan kepada orang tertentu. Dev = pengembang; PJ = penanggung jawab klinik; Infra = operator server; PDP = penanggung jawab perlindungan data institusi.

| ID | Tahap / prioritas | Temuan | Hasil kerja dan acceptance minimum | Dependensi | PIC fungsi | Status |
| --- | --- | --- | --- | --- | --- | --- |
| T01 | G0 / P1 | F12,F21,F22 | Register keputusan: matriks role, PMIK/pelepasan, koreksi 48 jam/terlambat, consent/darurat, obat langsung, apoteker/TTK, retensi, RPO/RTO; tautkan SOP dan pembaruan regulasi resmi saat implementasi. Tidak menerima asumsi sebagai persetujuan. | PJ/PDP/Infra: SOP dan bukti aktual | Dev+PJ+PDP | IN_PROGRESS |
| T02 | G1 / P0 | F01,F03 | Inventaris seluruh route/action/serializer/export/download, petakan izin target; implement Policy dengan scope dan projection eksplisit. Daftar role×objek×aksi×tahap diuji, termasuk ID milik petugas lain, akun nonaktif dan JSON/Inertia partial requests. | Matriks rancangan; rincian SOP dilacak T01 | Dev | IN_PROGRESS |
| T03 | G1 / P0 | F01,F02 | Admin ditolak server untuk isi klinis, bantuan tulis pengkajian/SOAP/odontogram/resep/tindakan/dispensing. Hapus pintasan dan props sensitif, pertahankan monitoring status/bantuan administratif serta audit bantuan lama. Tidak ada bypass `isAdmin()` tersisa pada jalur klinis. | T02; menyesuaikan tes bantuan lama | Dev | IN_REVIEW |
| T04 | G1 / P0 | F03,F22 | Laporan admin/manajemen agregat; direktori/monitoring hanya data minimum. Audit kolom seluruh Excel/PDF, dashboard, pencarian dan shared props. Uji bahwa diagnosis/narasi/nama pasien+detail klinis tidak bocor pada agregat; mitigasi kelompok kecil yang mudah mengidentifikasi. | T02,T03 | Dev+PDP | IN_PROGRESS |
| T05 | G1 / P0 | F04 | Hilangkan hard delete kunjungan; pembatalan beralasan, actor/time dan audit, termasuk endpoint lama. Uji menunggu dengan lampiran, reservasi, double submit dan FK anak tetap utuh. | T02; audit kompatibel sementara sebelum T08 | Dev | IN_REVIEW |
| T06 | G1 / P0 | F05 | Lampiran immutable/versioned, void beralasan tanpa hilang file; akses arsip sesuai tugas, unduh tidak lintas kunjungan/poli. Uji failed upload orphan terpisah, file hash, batas ukuran/type, void dan restore. | T02,T05; T12 sebelum migrasi data | Dev | IN_PROGRESS |
| T07 | G1 / P0 | F06 | Inventaris FK akhir dan semua jalur model/query builder/console/import delete; migration baru restrict/no action serta guard transaksi. Draft tersimpan dibatalkan logis, bukan hilang tanpa jejak. Uji legacy upgrade dan akun runtime pada engine target. | T05,T06,T12; Infra engine | Dev+Infra | IN_PROGRESS |
| T08 | G2 / P0 | F07,F08 | Perluas recorder yang ada: nullable actor, role snapshot, UA, outcome, correlation dan diff whitelist; mutasi kritis atomik dengan audit. Uji gagal audit menyebabkan rollback, failed login tidak butuh user autentik, penolakan terekam walau transaksi batal; tidak ada rahasia di log. | T02; skema additive dan backup sebelum migrasi | Dev | IN_PROGRESS |
| T09 | G2 / P0 | F07 | Append-only audit dan versi klinis pada aplikasi **dan** storage sesuai engine; akun migration/runtime dipisah bila engine mendukung, anchor/salinan integritas off-host. Uji update/delete/query builder/raw SQL oleh runtime ditolak atau batas SQLite didokumentasikan dengan mitigasi yang teruji. | T07,T08,T12; hak Infra | Dev+Infra | IN_PROGRESS |
| T10 | G2 / P1 | F08 | Instrumentasi setiap view history, print intent, export/download, final/addendum, consent, auth dan penolakan. Ekspor audit PDF/Excel admin dimasking/diotorisasi, filter konsisten dan aman formula injection; ekspor dicatat. Tidak menyatakan printer berhasil. | T04,T08,T09 | Dev | IN_PROGRESS |
| T11 | G2 / P1 | F09,F22 | Idle 15 menit server+frontend, warning, lintas tab/sleep/remember/polling; no-store dan penanganan history/cache respons klinis. Uji request sesudah expiry ditolak, polling tidak menunda timeout, pergantian akun tidak melihat data sebelumnya, draf tersimpan dapat diteruskan sesuai izin. | T02,T08; kebijakan sesi T01 | Dev | IN_PROGRESS |
| T12 | G1–G2 / P0 | F10 | Backup DB+file harian off-site terenkripsi, monitor gagal/terlambat dan runbook restore beserta kunci terpisah. Restore terisolasi pada engine target lulus integritas DB/file/versi dan alur sintetis. Bukti scheduler nyata, manifest, lokasi, waktu hasil dan RPO/RTO; bukan sekadar install paket. | Infra: storage/kunci/scheduler; PJ target RPO/RTO | Dev+Infra | NEEDS_INFRA |
| T13 | G2 / P1 | F11,F22 | Periksa deployment TLS 1.2/1.3, cookies, trusted proxies, debug, private storage, akses OS/DB dan secret. Dokumentasikan hasil aktual, rate limit dan akses file; scan dependency PHP/npm terbaru + penilaian paparan advisory. Tidak mengubah versi mayor tanpa uji kompatibilitas. | Infra: endpoint/topologi target | Dev+Infra | IN_PROGRESS |
| T14 | G2 / P1 | F11,F22 | Threat model dan enkripsi at-rest seluruh salinan sensitif; jika field encryption dipilih, blind index NIK, mask, dual-read/backfill, canonical hash tidak berubah. Uji pencarian/duplikasi/import, key rotation/recovery dan restore data terenkripsi. | T12,T13; keputusan Infra/PDP | Dev+Infra+PDP | IN_PROGRESS |
| T15 | G3 / P1 | F12 | General consent berversi dalam registrasi, tujuan terpisah, penjelas/wakil/bukti dan status berlaku. Pasien lama belum terdokumentasi tetap ditandai demikian; tidak auto setuju. Uji penolakan opsional, penggantian dan kebutuhan darurat sesuai SOP. | T02,T06,T08,T01 consent | Dev+Pendaftaran+PJ | IN_PROGRESS |
| T16 | G3 / P1 | F12,F14 | Consent tindakan memakai tabel existing yang dikembangkan, kaitkan tindakan/gigi, risiko dan penjelasan, wakil/saksi, keputusan/penolakan, scan/canvas private. Gate backend sebelum pelaksanaan invasif/risiko tinggi; uji revokasi, salah pasien/tindakan, bukti palsu, darurat dan tindakan manual belum diklasifikasi. | T15,T18; klasifikasi/SOP PJ | Dev+Dokter+PJ | IN_REVIEW |
| T17 | G3 / P1 | F13 | Referensi riwayat read-only dengan sumber/tanggal; adopsi memerlukan konfirmasi current encounter dan provenance. Handoff kunjungan sama tetap berjalan. Uji alergi tidak hilang, unknown bukan tidak ada, keluhan/TTV/temuan tidak terbawa otomatis. | T02,T08; form existing | Dev+Perawat+Dokter | IN_PROGRESS |
| T18 | G3 / P1 | F14 | Hapus narasi negatif otomatis dari field kosong; review akhir eksplisit dan catat konfirmasi tindakan dokter sebelum completed. Uji blank/unknown/tidak diperiksa, rencana tanpa pelaksanaan, hasil nyata, cancel dan retry final tidak menggandakan BHP. | T08,T17; T16 wajib sebelum tindakan berisiko diaktifkan | Dev+Dokter | IN_PROGRESS |
| T19 | G3 / P1 | F15 | Diagnosis normal final ICD-10 WHO terverifikasi dari katalog/release yang didukung. Uji kode palsu/katalog kosong/ICD-10-CM/ICD-9 diagnosis, primary/secondary, legacy read-only. Manual tetap draft atau unresolved sesuai downtime terkontrol; tidak mendapat label siap integrasi. | T17,T18; katalog resmi+SOP downtime | Dev+Dokter | IN_PROGRESS |
| T20 | G3 / P1 | F15 | Mapping tindakan lokal/manual ke ICD-9-CM, snapshot display/release; validasi pada tindakan relevan sebelum final normal, tidak menganggap semua tindakan keperawatan sama dengan prosedur medis berkode. Uji multi-gigi, BHP, katalog retired dan legacy tanpa kode. | T18,T19; verifikasi klinisi/katalog | Dev+Dokter+Perawat | IN_PROGRESS |
| T21 | G3 / P2 | F16 | KFA: tipe produk, kode/sumber/rilis/status verifikasi, mapping lokal dan snapshot resep/dispensing; inventaris racikan/resep luar. Uji mapping tidak sah, perubahan master, unmapped, retired, bentuk/kekuatan/satuan; tanpa menebak kode. | T19,T20,T25; sumber KFA+apoteker | Dev+Farmasi | OPEN |
| T22 | G3 / P1 | F17,F18 | Resolver versi efektif terverifikasi untuk surat/resume/proyeksi relevan; perbaiki `completed` vs `selesai`. Uji surat baru setelah addendum narasi/alergi/plan, integrity failure, tindakan selesai, planned dan dokumen lama tidak berubah. | T08,T18,T26 desain koreksi | Dev | IN_PROGRESS |
| T23 | G3 / P1 | F19 | Lengkapi `ringkasan`, rujukan/sakit/sehat existing dengan snapshot identitas faskes/pasien/dokter/template/versi, nomor unik, dokumen pengganti/void dan QR minimal tanpa PHI. Uji scan QR, token enumeration/rate limit, master berubah, pagination cetak A4/multi-page. | T22; identitas faskes dan penerbit PJ | Dev+Dokter | IN_PROGRESS |
| T24 | G3 / P1 | F19,F22 | Register permintaan salinan/pelepasan: dasar, pemohon/wakil, penerima/tujuan/cakupan, otorisasi dan bukti penyerahan. Uji pasien/wakil tidak sah, scope sebagian, pembukaan tanpa consent dengan dasar sah, audit/retensi dan akses petugas admisi minimum. | T15,T23; SOP PJ/PDP | Dev+Pendaftaran+PDP | IN_PROGRESS |
| T25 | G3 / P1 | F20 | Proyeksi minimal review farmasi (identitas, BB bersumber/waktu, resep/SIP, alergi, indikasi/kondisi relevan) dan review admin/farmasetik/klinis, klarifikasi dokter, konseling/penerima. Uji belum direview, parsial/resep luar, TTK vs apoteker, perubahan aturan pakai dan final/retry/retur. Tidak membangun rekomendasi obat otomatis. | T02,T08,T17; verifikasi apoteker+T01 | Dev+Farmasi+Dokter | IN_PROGRESS |
| T26 | G3 / P1 | F21 | Matriks koreksi draf/final/late untuk administrasi, screening, narasi, diagnosis, odontogram, rencana dan resep; implement versi/relasi pengganti dan dampak downstream. Uji tepat/lewat 48 jam, approval administrasi, stale version, koreksi sesudah dispensing, control/referral/allergy alert. Original dan stok tidak ditimpa. | T08,T09,T18,T19,T20; SOP T01 | Dev+PJ+Dokter | IN_PROGRESS |
| T27 | G4 / P2 | F16,F22 | Lengkapi kamus variabel per field dan klasifikasi applicable/conditional/NA; profil agregat legacy dan validator readiness offline, MPI/IHS/profesi/faskes serta timezone/unit/status map. Hasil unresolved jelas, tidak mengarang nilai. | G1–G3 lulus; pemetaan awal tersedia | Dev+PJ | OPEN |
| T28 | G4 / P2 | F16 | Kontrak FHIR/outbox/mapping ID/versi/retry/koreksi/reconciliation dan sandbox sintetis; minimal Patient/Encounter/Condition/Procedure/Observation/Allergy/MedicationRequest/Dispense/Composition sesuai use case terverifikasi. Tidak mengirim produksi dalam task persiapan. | T21,T22,T25,T26,T27 | Dev | OPEN |
| T29 | G5 / P1 | F22 | Paket bukti: semua test relevan, full suite backend/frontend, TS/build, format/diff, engine target migration+restore, browser per role, QA print/QR, ancaman IDOR/export dan dependency. Hasil bertanggal setelah perubahan terakhir; test lama yang konfliknya sah diperbarui dengan kasus penolakan baru. | Seluruh task teknis sebelumnya | Dev+QA | IN_PROGRESS |
| T30 | G5 / P1 | F10,F12,F19,F21,F22 | UAT kontrol bersama PJ/klinis/farmasi/admisi, SOP insiden/downtime/hak pasien/retensi/akses, DPIA dan penanggung jawab PDP dinilai institusi; daftar gap nihil untuk scope rilis atau rilis ditahan. Bukti restore/deployment wajib; integrasi produksi tetap gate eksternal terpisah. | T01,T12,T24,T29; bukti dan penanggung jawab nyata | PJ+Infra+PDP+QA | IN_PROGRESS |
| T31 | G1–G2 / P0 | F23 | Reset password admin tidak langsung menjadi kredensial siap menandatangani sebagai dokter: temporary credential/aktivasi mandiri, paksa ubah sebelum akses klinis/final, cabut sesi/remember lama, audit lifecycle dan perubahan signature. Uji reset/ubah role/nonaktif pada sesi aktif, akun bersama ditolak SOP; evaluasi reauth/MFA sesuai risiko. | T02,T08,T11; kebijakan akun T01 | Dev+PJ+PDP | IN_PROGRESS |

## Gate yang tidak boleh dilewati

22 November 2026: pengguna menyetujui seluruh kebutuhan acceptance sebagai arah implementasi untuk aplikasi internal kementerian. Persetujuan ini mencakup rancangan/SOP dan pelaksanaan pengembangan; bukti UAT perangkat, identitas profesi, pengujian restore dan konfigurasi produksi tetap dicatat berdasarkan hasil aktual. Tidak mengisi bukti yang belum tersedia atau menandai task selesai berdasarkan persetujuan semata.

Pembaruan T25 (20 November 2026): kualifikasi profesi/penugasan immutable, review apoteker terstruktur dan klarifikasi, hash sumber, gate dispensing, konseling/penerima serta review pemberian langsung sudah diterapkan. Data petugas klinik existing tetap belum terverifikasi sampai bukti individual diperiksa pada form master. Rincian bukti dan batas pengujian ada di `rme-pharmacy-review-implementation.md`; status T25 tetap IN_PROGRESS.

Checkpoint 20 November 2026: lihat `rme-critical-checkpoint-2026-11-20.md`. Scope integritas laporan efektif diperbaiki (26/26 tes); relasi surat pengganti dan UI/cetak/audit diterapkan (regresi awal 20/20). Frontend 305/305, TS/build/Pint lulus. T22/T23 belum menutup seluruh acceptance, T24/T25/T26 belum dinyatakan selesai.

| Gate | Bukti lulus | Penghalang eksplisit |
| --- | --- | --- |
| G1 | T02–T07 + kontrol awal T31; baseline akses dan anti-delete teruji | Admin masih membaca isi klinis lewat endpoint/file; penghapusan transaksi/file medis tetap mungkin melalui alur aplikasi |
| G2 | T08–T14 dan T31 lengkap; T12 restore nyata | Audit dapat diubah runtime tanpa mitigasi; idle tidak berlaku server; backup hanya konfigurasi/belum pernah dipulihkan |
| G3 | T15–T26 + SOP dependensi yang diperlukan | Consent hanya gambar/form tanpa gate; final coding tidak jelas; surat mengambil catatan usang; review farmasi belum terbukti |
| G4 | T27–T28; readiness offline dan sandbox sintetis sesuai kontrak terkini | Kode/IHS/metadata palsu, temuan internal tersisa, data unresolved dikirim seolah valid |
| G5 | T29–T30, evidensi deployment dan UAT kontrol disahkan | Menyatakan produksi patuh karena test SQLite memory atau menu sudah rapi |

## Paket UAT minimum

1. Pasien lama/baru dengan NIK, tanpa NIK dan kepesertaan NIP; general consent valid/ditolak/belum terdokumentasi.
2. Poli Umum/Gigi: screening baru, riwayat lama sebagai referensi, alergi unknown/ada, tanda bahaya dan data tidak dapat diukur; revisi sebelum dokter mulai.
3. Dokter sesuai penugasan/jadwal, claim valid, stale review, final berkode, tindakan multi-gigi; tidak ada terapi baru dari admin.
4. Consent diterima/ditolak/dicabut, wakil/saksi dan tindakan berisiko; darurat sesuai SOP tanpa memalsukan bukti.
5. Tanpa resep, tindakan perawat lalu farmasi, dispensing parsial, resep luar, obat langsung yang sah, retur dan final double-click.
6. Addendum dini/terlambat, koreksi administratif lewat tenggat, koreksi diagnosis/resep setelah final; surat baru versus arsip lama dan versi yang ditampilkan konsisten.
7. Rujukan internal ke poli lain, rujukan luar, kontrol/reservasi mendatang, pembatalan kunjungan dan lampiran yang di-void tanpa hilang arsip.
8. Setiap role mencoba URL pihak lain, export/download/print, JSON/partial props dan cache browser; admin/manajemen hanya proyeksi yang diizinkan.
9. Idle/polling/tab tidur/remember/lintas tab/logout/back, pergantian petugas, reset password dan pemakaian sesi lama.
10. Backup gagal/terlambat, disk penuh/audit gagal, restore DB+file+kunci, gangguan katalog/jaringan dan rekonsiliasi.

## Template bukti per task

```text
Task / temuan:
Status sebelum → sesudah:
Pelaksana / reviewer:
Tanggal / branch / commit atau berkas diff:
Perilaku sebelum → sesudah:
Migration / data legacy / rollback aman:
Kasus uji + perintah + hasil + waktu:
QA browser/cetak/infra/SOP dan bukti:
Risiko tersisa / task turunan:
Keputusan penerimaan oleh fungsi yang berwenang:
```

## Dokumen operasional

- [SOP consent, koreksi, akses dan pelepasan](rme-sop-rancangan.md) — disetujui pengguna 19 November 2026; bukti operasional mengikuti register.
- [Kebutuhan infrastruktur dan runbook backup/restore](rme-backup-runbook.md) — off-site belum tersedia.
- [Threat model enkripsi at-rest dan pemulihan kunci](rme-encryption-threat-model.md) — pemeriksaan volume Windows lokal belum mendapat akses; bukti host produksi tetap diperlukan.
- [Bukti implementasi dan pekerjaan tersisa per task](rme-hardening-implementasi.md).

## Log kemajuan

| Tanggal | Peristiwa | Bukti | Status implementasi |
| --- | --- | --- | --- |
| 2026-11-18 | Kajian awal dan dokumen rencana selesai; 23 temuan, 31 task; 2 batch baseline terarah hijau | Audit dan tiga dokumen pendamping; 195 tes/3.684 assertions | Belum ada task implementasi DONE; tidak ada migration/deploy |
| 2026-11-19 | Implementasi akses, retensi, sesi, audit, dokumen efektif dan backup lokal; tiga migration additive diterapkan lokal setelah checkpoint terenkripsi | Log implementasi; hasil suite terbaru masih diperiksa | Task tetap IN_PROGRESS/IN_REVIEW; SOP/off-site/produksi belum lulus |
| 2026-11-19 | Gate informed consent tindakan diaktifkan pada dokter/perawat, lengkap dengan bukti private ber-hash, reautentikasi, penolakan/pencabutan, stale-plan guard dan jalur darurat | 6 tes khusus/77 assertions; regresi tindakan 135/2.273; suite penuh 560/9.756 | T16 teknis selesai dan masuk review klinis/UI; UAT browser/produksi tetap G5 |
| 2026-11-19 | Diagnosis baru memakai WHO, verifikasi katalog/rilis/actor/waktu; final normal menolak unresolved, darurat/downtime memerlukan alasan dan snapshot unresolved | 6 tes coding/39 assertions; suite penuh 552/566, 14 fixture diperbaiki dan regresi tiga kelas 66/66; frontend 301/301, TS/build/Pint/diff lulus | T19 IN_PROGRESS; rekonsiliasi pascafinal T26 dan penerimaan operasional belum selesai |
| 2026-11-19 | Addendum rekonsiliasi diagnosis, review coding tindakan, dan BB/SIP farmasi selesai dalam scope batch; gate tetap menjaga consent | Backend penuh 574/574, 9.922 assertions; frontend 303/303; TS/build/Pint/diff lulus | Bukti penuh mendahului batch QR; T19/T20/T25/T26 tetap mengikuti acceptance tersisa |

**Urutan yang tetap berlaku:** T02 inventaris izin dan T03 pembatasan admin, disertai T05/T06 pencegahan kehilangan. T12 bukti backup dimulai sejak awal, sebelum migration. T31 ditangani bersama batas role agar penutupan akses admin tidak menyisakan jalur kredensial yang tidak terlindungi. SATUSEHAT produksi tidak menjadi pekerjaan pertama.

### Persetujuan 19 November 2026

Pengguna menyatakan SOP disetujui penuh dan memerintahkan implementasi sampai selesai. Tidak meminta persetujuan SOP yang sama lagi. T01/T30 berlanjut ke implementasi dan pengumpulan bukti. Ketersediaan off-site, scheduler, nama PIC, klasifikasi tindakan dan uji operasional tidak dibuat-buat dari persetujuan ini.

### Batch general consent

Persetujuan SOP dicatat; modul general consent berversi dengan bukti private, wakil, penolakan, pencabutan, idempotensi, kontrol akses dan audit atomik diterapkan. Registrasi/rujukan internal/kontrol baru menandai kebutuhan dokumentasi; pengiriman pengkajian normal memeriksanya. Draf tetap dapat disimpan; penundaan darurat mensyaratkan penilaian triase dan alasan, bukan persetujuan fiktif. Kunjungan legacy tetap berstatus belum terdokumentasi bila memang tidak ada bukti. Migration lokal `2026_11_19_110000` diterapkan setelah checkpoint lokal terenkripsi `clinical-20261119-100436-72f9b4b7-779d-409f-9fc3-4502ccc45f00.zip`. Delapan tes awal lulus (73 assertions); regresi luas setelah perubahan lulus seperti dirinci pada log implementasi. Pelepasan informasi T24 tetap terpisah.

### Temuan tambahan selama implementasi

- F26 / T19,T20 (22 November 2026): ringkasan coding mengabaikan tindakan legacy dan dapat tampil verified penuh. ClinicalCodingService diperbaiki untuk mempertahankan status legacy; regression 5/5, 36 assertions. Audit lanjutan 6–10 dan batas cakupan ditulis di rme-high-priority-6-10-audit-2026-11-22.md.
- F27 / T15 (24 November 2026): gate general consent berbukti unggahan sebelumnya hanya memeriksa kolom hash, bukan keberadaan dan hash aktual file. GeneralConsentService kini memakai verifikasi integritas yang sama untuk status, tanggal aktif, dan handoff pengkajian; regression tambahan menutup bukti yang dimodifikasi setelah pencatatan. Batch T15–T20 lulus 153/153 (2.234 assertions), frontend 305/305; UAT perangkat nyata tetap terbuka.

- F24 / T07,T09,T29: perubahan foreign key membangun ulang tabel SQLite dan dapat menghilangkan trigger. Terdeteksi oleh uji raw SQL dan security-check; diperbaiki dengan migration forward `2026_11_19_111000`. Setelah migration lokal, seluruh delapan pemeriksaan konfigurasi/trigger kembali lulus. Perubahan skema berikutnya wajib menguji ulang guard sesudah upgrade, bukan hanya saat instalasi awal.
- F25 / T04,T10,T29: binder Excel bawaan dapat menafsirkan string sebagai formula/angka. Seluruh export menggunakan binder string eksplisit untuk menjaga NIK/nomor berawalan nol serta menolak interpretasi formula, termasuk whitespace di depan. Pengamanan prefiks legacy dipertahankan agar kontrak lama tidak berubah; uji workbook aktual ditambahkan.
- F17,T22: surat baru sebelumnya masih mengambil diagnosis/resep/nama tindakan dari proyeksi mutable meskipun narasi sudah memakai addendum. Resolver dokumen kini memakai payload versi efektif untuk seluruh rencana klinis tersebut; status realisasi perawat berasal dari transaksi pelaksanaan yang sesuai. Dokumen lama tetap memakai snapshot saat diterbitkan.
- 2026-11-20,T22: Top Diagnosis dokter dan query bersama laporan/ekspor pendaftaran kini memakai EffectiveDiagnosisReport: versi final terakhir terverifikasi, draft aktif, tanpa menimpa diagnosis awal. Regresi 21/21 (560 assertions) dan tambahan 7/7 (216 assertions, sebagian beririsan), Pint/diff lulus. SQL mesin selain SQLite dan optimasi scope pemeriksaan integritas masih terbuka; detail di rme-effective-diagnosis-report-plan.md.

- 2026-11-24,T02–T14: inventaris runtime menambahkan guard regresi untuk 73 route klinis/sensitif agar selalu memiliki middleware role eksplisit; jalur tanda tangan pasien berbasis token tetap dikecualikan secara sengaja. Audit terarah RBAC, masking laporan, retensi/lampiran, audit trail, dan sesi lulus 104/104 (1.546 assertions); route inventory lulus 1/1 (73 assertions). Bukti TLS/deployment, off-site backup/restore, rotasi kunci, dan UAT perangkat tetap terbuka.

[Inventaris route untuk review akses](rme-route-access-inventory.md) mencakup endpoint, bukan hanya menu. Matriks policy per enam role diuji terpisah dari endpoint.
