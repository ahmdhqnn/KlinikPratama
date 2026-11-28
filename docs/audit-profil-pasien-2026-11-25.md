# Audit profil pasien dan alur pelayanan

25 November 2026

Profil pasien menghubungkan data identitas dengan kunjungan, layanan klinis, persetujuan, surat, rujukan, dan farmasi. Setiap menu memakai kewenangan peran dan membuka catatan pada modul asal agar proses pencatatan, versi, dan audit tetap berjalan di satu tempat.

## Menu dan hak akses

| Menu | Pemegang akses | Isi dan tindakan |
| --- | --- | --- |
| Informasi pasien | Admin, pendaftaran, dokter, perawat, farmasi | Identitas, kepesertaan, kontak, alamat dan kunjungan terbaru sesuai peran. Admin dan pendaftaran dapat mengedit biodata dan mendaftarkan kunjungan. Alergi ditampilkan kepada pemberi layanan. Farmasi melihat identitas minimum setelah pasien memiliki konteks layanan farmasi. |
| Riwayat kunjungan | Admin, pendaftaran, dokter, perawat | Daftar terurut berdasarkan tanggal layanan dan nomor internal, dengan paginasi dan tautan detail sesuai peran. |
| Kontrol lanjutan | Admin, pendaftaran, dokter, perawat | Reservasi kontrol dan kunjungan lanjutan yang memiliki hubungan ke kunjungan sebelumnya. |
| Catatan medis | Dokter, perawat | Pemeriksaan, diagnosis, rencana perawatan, versi koreksi, dan tautan ke RME lengkap. |
| Pengkajian awal | Dokter, perawat | Tanda vital dan catatan pengkajian per kunjungan. Perawat dapat membuka tampilan pengkajian. |
| Odontogram | Dokter, perawat | Riwayat kunjungan dengan temuan gigi, melalui catatan klinis sumber. |
| Penunjang dan lampiran | Dokter, perawat | Kunjungan dengan hasil penunjang terdokumentasi atau lampiran, termasuk arsip lampiran pada RME. |
| Tindakan | Dokter, perawat | Rencana tindakan dan status pelaksanaan pada kunjungan yang berwenang dilihat. |
| Resep dan penyerahan obat | Dokter, perawat, farmasi | Dokter dan perawat membuka riwayat resep dalam RME. Farmasi membuka proses penyerahan aktif atau detail penyerahan selesai. |
| Pemberian obat langsung | Farmasi | Riwayat terpisah dan bukti pemberian obat langsung per pasien. |
| Persetujuan dan penolakan | Admin, pendaftaran, dokter, perawat | Keputusan persetujuan umum terbaru dan riwayatnya. Dokter dan perawat dapat melihat persetujuan atau penolakan tindakan yang berkaitan dengan kunjungan dalam lingkup akses mereka. |
| Surat medis | Dokter | Dokumen yang sudah diterbitkan untuk kunjungan dalam penugasan dokter. Penerbitan tetap melalui pemeriksaan final dan verifikasi dokumen. |
| Rujukan | Admin, pendaftaran, dokter | Admin dan pendaftaran menjadwalkan atau memantau tujuan rujukan internal. Dokter membuka dokumen rujukan internal dan eksternal yang berasal dari kunjungannya. |
| Pelepasan informasi | Pendaftaran, dokter | Register permintaan, keputusan, dan penyerahan menurut peran. Otorisasi dan penolakan dilakukan dokter berwenang melalui alur sumber. |

Dokter melihat kunjungan yang ditugaskan kepadanya dan perawat melihat kunjungan dalam poli aktif tempatnya bertugas. Pembatasan dijalankan pada query dan diperiksa kembali di endpoint tujuan. Menu klinis muncul sejalan dengan penugasan. Penunjang memakai dokumentasi pemeriksaan yang sudah ada. Modul laboratorium mandiri, rawat inap, kasir, deposit, dan alur bedah mandiri belum termasuk dalam alur yang diimplementasikan.

## Cakupan peninjauan

| Area | Hubungan dengan profil dan hasil peninjauan |
| --- | --- |
| Akun, sesi, peran dan penugasan | Inventaris 254 rute aplikasi sebagai peta awal. Profil dan halaman sumber memakai autentikasi, role middleware, pembatasan penugasan, log akses, dan kebijakan `private, no-store`. |
| Kepesertaan dan pendaftaran | Profil membuka pendaftaran dengan identitas pasien yang sudah terpilih. Detail kunjungan Pendaftaran kembali langsung ke profil. Proyeksi data farmasi dibatasi pada identitas dan konteks layanan yang diperlukan. |
| Antrean, kontrol, pengkajian dan pemeriksaan | Status kunjungan memandu tindakan. RME dari profil dapat langsung menampilkan kunjungan tertentu dan memeriksa ulang apakah kunjungan tersebut termasuk dalam lingkup akses. |
| Diagnosa, tindakan, gigi dan penunjang | RME memuat catatan sumber, versi, dan lampiran privat. Tampilan cetak RME hanya memuat catatan final dengan integritas terverifikasi. |
| Persetujuan, Persuratan dan pelepasan | Persetujuan umum, keputusan atas tindakan, dan pelepasan tetap menggunakan register masing-masing. Riwayat pelepasan tetap terlihat dan tindak lanjut hanya tersedia pada versi terbaru setiap permintaan. |
| Farmasi dan persediaan | Profil menautkan penyerahan dan obat langsung dari catatan yang sudah ada. Akses detail penyerahan dan tampilan bukti obat langsung dicatat pada log audit. Stok dan retur tetap berada pada modul farmasi dan persediaan. |
| Master, laporan dan pengaturan | Master menyediakan data rujukan dan definisi layanan. Laporan dan administrasi lintas pasien tetap berada pada menu peran. |

## Temuan yang diperbaiki

1. Profil sebelumnya hanya mengambil sebagian kunjungan berdasarkan waktu dibuat. Daftar riwayat kini dipaginasi, diurutkan menurut tanggal layanan serta ID untuk tanggal yang sama, dan memisahkan kontrol lanjutan.
2. Halaman profil farmasi sebelumnya dapat menyajikan biodata dan kunjungan di luar kebutuhan penyerahan. Profil farmasi kini mensyaratkan layanan farmasi yang sudah mencapai tahap penyerahan atau catatan obat langsung. Direktori umum dialihkan ke direktori farmasi.
3. Beberapa jalur edit biodata admin belum mencatat audit perubahan. Seluruh jalur edit kini diaudit, dan pengecualian perubahan alergi pada jalur lama dinyatakan langsung di controller serta diuji.
4. Penggabungan lama memindahkan kunjungan tanpa merekonsiliasi persetujuan yang terikat identitas, penyerahan obat langsung, dan rekam medis. Aksi kini mengarsipkan identitas duplikat yang cocok dan kosong. Rekam berisi riwayat pelayanan, alergi, atau bukti persetujuan tetap terlindungi. Pengarsipan biasa ikut mencatat audit. Biodata dan verifikasi kepesertaan pasien utama dapat diproses menggunakan identitas yang sudah tercatat.
5. Halaman pelepasan menawarkan tindakan pada versi lama yang akhirnya ditolak backend. Tampilan kini mengikuti versi yang dapat ditindaklanjuti, menampilkan galat keputusan, dan kembali ke profil pasien.
6. Navigasi persetujuan tindakan perawat dapat kembali ke editor pengkajian yang sudah terkunci. Kini perawat kembali ke profil bila berasal dari profil, atau ke catatan kunjungan bila pengkajian terkunci.

## Verifikasi lokal

Uji Chrome pada 1.440 dan 390 piksel menggunakan SQLite terpisah dan data sintetis. Skenario dokter mencakup membuka profil, membuka halaman kedua riwayat kunjungan, melihat persetujuan umum, dan kembali ke profil. Pada 390 piksel, tindakan tiap kunjungan tersedia pada daftar tanpa perlu menggeser layar secara horizontal.

Build Vite, pemeriksaan TypeScript, Pint pada berkas yang berubah, serta 312 pengujian render frontend dan sesi lulus.

Seluruh 624 pengujian Laravel lulus dengan 10.877 asersi. Cakupannya meliputi pembatasan dokter berdasarkan penugasan, perawat berdasarkan poli, dan farmasi berdasarkan konteks penyerahan. Pengujian profil juga meliputi akses melalui URL, pemisahan data pasien, riwayat yang dipaginasi, arsip duplikat, verifikasi kepesertaan, dan tahapan register pelepasan informasi.

## Ketentuan dan pemeriksaan operasional

- Permenkes 24/2022 Pasal 29–30 mendasari pengendalian kerahasiaan, integritas, ketersediaan dan kewenangan melihat, menginput, serta memperbaiki data. Pasal 32–34 mendasari perlindungan rahasia dan pembatasan pembukaan informasi sesuai kebutuhan. [Permenkes 24/2022 tentang Rekam Medis](https://jdih.kemkes.go.id/documents/peraturan-menteri-kesehatan-nomor-24-tahun-2022)
- UU 27/2022 Pasal 4 menggolongkan data kesehatan sebagai data pribadi spesifik. Pasal 35–39 mengatur pengamanan, kerahasiaan, pengawasan dan pencegahan akses yang tidak sah. [UU 27/2022 tentang Pelindungan Data Pribadi](https://peraturan.bpk.go.id/Details/229798/uu-no-27-tahun-2022)
- Sebelum produksi, tetapkan matriks akses dalam SOP klinik, persetujuan koreksi administratif setelah batas 2×24 jam sejak entri menurut Pasal 30, serta kewenangan persetujuan pelepasan atas nama pimpinan. Validasi jalur rekonsiliasi rekam duplikat yang sudah berisi pelayanan.
- Verifikasi operasional mencakup pemulihan cadangan di lingkungan target, TLS, pembatasan distribusi cetakan, serta UAT petugas lintas peran dan poli. Inventaris rute dan hasil pengujian lokal menjadi bukti pemeriksaan kode dan aplikasi lokal. Persetujuan penerapan SOP dan verifikasi produksi berada pada pengelola klinik.
- Pekerjaan lintas modul masih mencakup katalog dan snapshot KFA resmi, konfirmasi tindak lanjut koreksi lintas layanan, serta penggantian atau retur setelah penyerahan obat. Status dan dependensi dicatat pada pelacak RME. [Pelacak pengerasan RME](rme-hardening-tracker.md)
