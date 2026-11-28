# Rancangan SOP penyelenggaraan RME dan consent

Versi 0.1 — 19 November 2026. **DISETUJUI PENUH oleh pengguna dalam percakapan pada 19 November 2026.** Persetujuan mencakup rancangan SOP ini sebagai acuan implementasi. Persetujuan tidak dianggap bukti pelatihan, uji operasional atau infrastruktur yang belum dilakukan. Dokumen ini mengisi T01/T30; implementasi dan verifikasinya tetap mengikuti [tracker](rme-hardening-tracker.md).

## Pelaksanaan setelah persetujuan

| Bagian | Pengusul / pemeriksa | Penetap | Bukti yang belum tersedia |
| --- | --- | --- | --- |
| Kewenangan layanan dan koreksi | Dokter, perawat/terapis gigi, farmasi, petugas RMIK sesuai penugasan | PJ klinik | Nama, kompetensi, surat penugasan, pengganti dan jalur eskalasi |
| Consent dan pelepasan informasi | Dokter, pendaftaran, fungsi RMIK/PDP | PJ klinik | Teks informasi, ruang lingkup, masa berlaku, verifikasi wakil |
| Akses, retensi dan insiden | Fungsi PDP, PJ, pengelola TI | Pimpinan institusi yang berwenang | Matriks akses disahkan, kontak insiden dan jadwal review |
| Backup dan downtime | Pengelola TI dan PJ | Pimpinan institusi yang berwenang | Lokasi off-site, pengelola kunci, target pemulihan dan bukti simulasi |

Acuan implementasi berlaku sejak persetujuan percakapan 19 November 2026. Nomor dokumen institusi, identitas penetap formal dan tanda tangan dicatat sesuai bukti yang diberikan; aplikasi tidak membuat identitas atau tanda tangan fiktif. Daftar klasifikasi tindakan berisiko di Poli Umum/Gigi harus ditetapkan klinisi; pengembang tidak menetapkan sendiri kewenangan profesi atau pengecualian klinis.

## 1. Identifikasi dan pengisian

1. Petugas registrasi memverifikasi identitas dengan sumber yang tersedia; nomor RM internal tetap identitas lokal. NIK yang belum diketahui tidak dibuat-buat. Koreksi identitas dibedakan dari perubahan catatan klinis.
2. Gunakan akun pribadi sesuai penugasan. Admin sistem tidak mengisi atau menandatangani atas nama dokter/perawat/farmasi. Akun sementara wajib diganti password sebelum pelayanan; perubahan hak akses mencabut sesi lama.
3. Setiap kunjungan memiliki asesmen sendiri. Riwayat ditampilkan bersama sumber dan tanggal. Adopsi informasi menetap memerlukan penilaian ulang; nilai kosong berarti belum dicatat, bukan otomatis normal/tidak ada penyakit.
4. Pengisi menyelesaikan catatan sesuai pelayanan yang benar-benar dilakukan. Waktu pencatatan dan tanda tangan berasal dari server. Entri terlambat menyebut waktu kejadian yang dilaporkan dan alasan; timestamp asli tidak dimundurkan.
5. Dokter meninjau handoff, temuan, diagnosis, rencana, resep dan realisasi tindakan sebelum tanda tangan. Tindakan yang belum dilakukan tetap rencana. Perawat dan farmasi mencatat pelaksanaan/review dalam kewenangan masing-masing.

## 2. General consent

Petugas menjelaskan tata tertib, penggunaan RME dan kerahasiaan dengan bahasa yang dipahami pasien. Rekam versi naskah, identitas penjelas, keputusan per tujuan, pasien/wakil, hubungan/kewenangan wakil, waktu dan bukti. Berikan kesempatan bertanya dan salinan bila diminta.

Persetujuan pelayanan umum tidak otomatis berarti persetujuan semua tindakan atau semua pelepasan informasi. Dasar pemrosesan yang merupakan kewajiban hukum/pelayanan harus dijelaskan terpisah dari pilihan opsional. Penolakan pilihan opsional tidak boleh otomatis menolak seluruh pelayanan. Pasien lama tanpa bukti ditandai **belum terdokumentasi**, tidak otomatis setuju.

PJ menentukan kapan perlu pembaruan (misalnya perubahan materi atau wakil), petugas pencatat, format scan/canvas, dan mekanisme pencabutan yang tidak menghapus bukti sebelumnya. Keadaan darurat ditangani menurut SOP klinis; kekurangan administrasi dicatat dan diselesaikan setelah keadaan memungkinkan.

## 3. Informed consent / penolakan tindakan

1. Dokter penjelas memverifikasi pasien, tindakan spesifik, lokasi/gigi dan kompetensi pengambil keputusan.
2. Dokumentasikan diagnosis/indikasi, tujuan, cara, alternatif, risiko/komplikasi, prognosis serta konsekuensi bila tidak dilakukan; rinciannya disusun klinisi untuk tindakan yang tersedia di klinik.
3. Rekam persetujuan atau penolakan setelah penjelasan, identitas penjelas/pasien/wakil/saksi sesuai kebutuhan, waktu, versi naskah dan bukti. Bukti scan/canvas diikat ke pasien, kunjungan dan tindakan; canvas tidak dilabeli sebagai TTE tersertifikasi.
4. Sebelum pelaksanaan, petugas memastikan keputusan masih berlaku dan tindakan/lokasi sesuai. Pencabutan atau penggantian menambah catatan baru; bukti lama dipertahankan.
5. Untuk kegawatdaruratan, dokter mendokumentasikan alasan, kondisi, tindakan, waktu dan penjelasan setelahnya. Pengecualian memerlukan dasar dan audit; tidak tersedia sebagai centang bebas untuk melewati persetujuan.

## 4. Koreksi, pembatalan dan addendum

Bedakan koreksi administratif, revisi draf klinis, addendum final, serta koreksi terapi yang sudah dilaksanakan. Data asli, alasan, pengusul, penanggung jawab, waktu dan versi harus tetap tersedia.

- **Draf:** pengisi yang berwenang memperbaiki melalui jalur tercatat. Diagnosis, item resep dan rencana tindakan yang dibatalkan keluar dari daftar aktif tetapi baris asli disimpan. Alasan tidak boleh hanya spasi.
- **Final:** jangan membuka kunci atau menimpa catatan asli. Tambahkan versi berkaitan dengan alasan dan sumber versi; pembaca melihat versi efektif terverifikasi serta riwayat.
- **Tenggat:** terapkan ketentuan koreksi sesuai Pasal 30 PMK 24/2022 dan SOP fasilitas. Batas 2×24 jam dihitung sejak entri dimasukkan, bukan sejak pasien pertama terdaftar. Ketentuan persetujuan untuk koreksi administratif lewat tenggat tidak boleh digeneralisasi menjadi izin admin mengubah SOAP atau menyatakan semua addendum klinis dilarang setelah 48 jam.
- **Dampak layanan:** koreksi resep/diagnosis/tindakan sesudah final memerlukan penilaian dokter, pemberitahuan petugas yang masih melayani, dan jejak pengganti/pembatalan. Bila sudah dispensing, farmasi memeriksa penyerahan/retur; jangan otomatis mengembalikan stok atau menghapus resep lama.
- **Lampiran:** salah lampiran dibatalkan dengan alasan; file asli dan hash dipertahankan. Pemulihan harus tercatat dan tidak menyembunyikan bahwa bukti pernah dibatalkan.
- **Perselisihan:** catat permintaan pasien dan keputusan petugas berwenang. Jangan menghapus catatan sah hanya karena permintaan koreksi tidak disepakati.

PJ menetapkan pengusul, pemeriksa dan pemberi persetujuan untuk tiap jenis koreksi sebelum fungsi persetujuan diterapkan. Daftar koreksi terlambat ditinjau berkala untuk mutu dokumentasi.

## 5. Salinan, pelepasan dan cetak

Verifikasi pemohon dan kuasa/wakil, dasar permintaan, penerima, tujuan serta cakupan minimum. Petugas berwenang menilai dasar consent atau dasar hukum lain; general consent tidak dianggap kuasa menyeluruh. Gunakan resume/dokumen dari versi efektif, nomor dan snapshot yang dapat ditelusuri. Catat otorisasi, versi yang dilepas dan bukti penyerahan. Permintaan cetak pada sistem hanya membuktikan intent; penerimaan salinan dicatat terpisah.

Admin sistem mengelola template dan audit operasional, bukan bebas mengunduh narasi klinis. Penugasan petugas pelepasan harus ditetapkan institusi; fungsi tersebut belum otomatis diberikan ke role admin.

## 6. Retensi, akses dan gangguan

RME disimpan paling singkat 25 tahun sejak kunjungan terakhir sesuai Pasal 39 PMK 24/2022. Tidak ada purge transaksi otomatis. Kebijakan backup berotasi tidak menggantikan retensi arsip. Permintaan pemusnahan/penahanan hukum dinilai petugas berwenang dan dicatat.

Petugas mengunci/logout workstation saat meninggalkan meja; idle 15 menit merupakan kontrol tambahan. Laporkan salah akses, kehilangan perangkat, dugaan kebocoran dan gagal backup ke kontak insiden yang akan ditetapkan. Pengelola TI mengisolasi kejadian, menjaga bukti, dan berkoordinasi dengan fungsi PDP untuk kewajiban tindak lanjut. Downtime menggunakan formulir bernomor, identitas pengisi, waktu kejadian dan rekonsiliasi tanpa backdate saat sistem pulih.

## Register persetujuan

| Tanggal | Bukti persetujuan | Lingkup |
| --- | --- | --- |
| 19 November 2026 | Pesan pengguna: "untuk sop sudah disetujui secara penuh" | Isi SOP sebagai acuan implementasi; pelatihan, UAT dan kesiapan infrastruktur tetap dibuktikan melalui pelaksanaan |

## Bukti pelaksanaan minimum

- Persetujuan SOP sudah dicatat; daftar penugasan aktual dan klasifikasi tindakan dimasukkan berdasarkan data klinik, bukan identitas/keputusan klinis fiktif.
- Simulasi pasien/wakil, penolakan, pencabutan, darurat, revisi draf/final/lewat tenggat dan terapi yang sudah diserahkan.
- Petugas mencoba akses role lain dan memastikan penolakan, audit serta kerahasiaan ekspor.
- Bukti pelatihan, daftar akun pribadi, uji pemulihan dan kontak eskalasi tersedia.

## Dasar kajian

Status halaman resmi diperiksa 19 November 2026; draf harus ditinjau kembali saat disahkan.

- [PMK 24/2022, dokumen resmi JDIH](https://jdih.kemkes.go.id/storage/documents/pdfs/2022permenkes024.pdf): SOP fasilitas, hak akses/koreksi, kerahasiaan, pembukaan dan retensi.
- [PMK 290/2008, katalog resmi JDIH](https://jdih.kemkes.go.id/documents/peraturan-menteri-kesehatan-nomor-290menkeperiii2008): penjelasan, persetujuan, penolakan dan pengecualian darurat.
- Rujukan UU Kesehatan/PDP dan aturan pelaksana yang relevan tetap mengikuti register kajian pada [audit](rme-hardening-audit-2026-11-18.md); draf ini tidak menggantikan telaah hukum institusi.
