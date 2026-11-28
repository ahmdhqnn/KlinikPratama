# T24 — Register pelepasan informasi

Checkpoint 20 November 2026. Status IN_PROGRESS; tidak menutup seluruh prioritas kritis.

## Kontrol yang diterapkan

- Route `information-release.show/store`, diakses dokter pemeriksa dan pendaftaran. Admin, perawat, farmasi dan kasir ditolak. Policy service dan middleware sesuai role; dokter dibatasi kunjungan sendiri.
- Pendaftaran hanya menerima identitas administratif pasien, permintaan dan riwayat keputusan. Tidak mendapat isi surat, SOAP, diagnosis atau tautan cetak klinis melalui modul ini.
- Permintaan mencatat pemohon, kedudukan, verifikasi identitas, dasar kewenangan/referensi bukti, penerima, tujuan dan cakupan satu jenis dokumen. General consent tidak digunakan sebagai izin otomatis.
- Dokter memilih dokumen satu kunjungan dan jenis sesuai cakupan. Catatan harus final terverifikasi; dokumen harus mempunyai bukti penerbitan yang valid. ID/hash dokumen disimpan dalam otorisasi.
- State machine: requested → authorized/denied; authorized → delivered/revoked. State terminal tidak dibuka kembali. Setiap perubahan memerlukan versi dasar yang masih berlaku; otorisasi/penyerahan/pencabutan/penolakan memakai password petugas.
- Penyerahan memerlukan metode, verifikasi penerima dan referensi tanda terima yang diarsipkan. Status/hash dokumen diperiksa kembali; dokumen yang dibatalkan atau diganti tidak dapat diserahkan berdasarkan otorisasi lama.
- Event berurutan disimpan encrypted:array, actor/time server, unik request/version. Model serta trigger database menolak update/delete. Mutasi dan audit satu transaksi; password tidak masuk payload/log.
- Link modul tersedia dari persetujuan umum (dokter/pendaftaran) dan penerbitan dokumen dokter. Riwayat dipaginasi; input/output di-escape oleh React.

## Bukti pengujian

- Regresi MedicalDocumentVerificationTest + GeneralConsentFlowTest + ClinicalSecurityHardeningTest: 32/32, 346 assertions.
- Tambahan test_release_: 3/3, 38 assertions, mencakup alur admisi–dokter–penyerahan, enkripsi, raw update ditolak, stale/role, rollback audit, dokumen void dan pencabutan.
- Frontend 306/306, termasuk render administratif dan escaping pemohon. TypeScript, build, Pint dan diff check lulus. Security check lokal 10/10 true.
- Backup sebelum migrasi: clinical-20261120-221909-94f64d99-4bb4-4910-9e37-3ae4cf0640d6.zip.
- Backup sesudah migrasi: clinical-20261120-221953-88de1eba-de8a-4a73-b90a-d9b3c93897af.zip.

## Acceptance tersisa

- Browser UAT petugas dan pasien/wakil, termasuk pemeriksaan bukti kewenangan yang nyata.
- Penyimpanan scan bukti otorisasi/tanda terima terintegrasi: implementasi saat ini menyimpan referensi arsip, bukan upload file bukti baru.
- Daftar tugas lintas kunjungan bagi petugas pelepasan yang ditunjuk; saat ini register per kunjungan.
- Ekspor register bermasking dan retensi/restore pada engine produksi.
- Matriks penugasan PMIK/PJ fasilitas dan eskalasi permintaan dengan dasar non-consent harus diverifikasi institusi; sistem tidak mengesahkan dasar hukum dari teks bebas.

Rilis T24 memerlukan acceptance tersebut; kemampuan cetak existing tetap dicatat sebagai intent, bukan bukti penerimaan.
