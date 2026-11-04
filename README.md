# Klinik Pratama — Rekam Medis Elektronik

Aplikasi operasional Klinik Pratama dengan backend Laravel dan antarmuka React melalui Inertia. Implementasi berfokus pada pelayanan Poli Umum dan Poli Gigi: pendaftaran, pengkajian awal, pemeriksaan dokter, tindakan perawat, pelayanan farmasi, persuratan, dan pelaporan.

Dokumen ini merangkum implementasi dan cara pemeliharaan proyek. Kode, konfigurasi, migration, dan test merupakan sumber kebenaran teknis. Hasil test otomatis tidak menggantikan UAT browser atau persetujuan operasional klinis.

## Teknologi dan persyaratan

| Komponen | Implementasi |
| --- | --- |
| PHP | Lingkungan audit PHP 8.4; batas paket mengikuti `composer.json` |
| Backend | Laravel 13, Eloquent, PHPUnit 12 |
| Antarmuka | React 19, TypeScript 5, Inertia 3 |
| Tampilan | Tailwind CSS 4, komponen Radix UI, Lucide |
| Build | Vite 8.3.5 pada lockfile hasil audit |
| Database lokal dan test | SQLite; test backend memakai database `:memory:` |
| Impor/ekspor | Laravel Excel 4 |

Versi yang dapat direproduksi tersimpan dalam `composer.lock` dan `package-lock.json`. Gunakan versi PHP, Node.js, dan ekstensi yang memenuhi persyaratan paket terkunci. SQLite memerlukan ekstensi PDO SQLite.

## Cakupan aplikasi

- Admin: pengelolaan operasional, pasien, antrean, pengkajian, persuratan, data master, pengguna, audit bantuan, dan laporan sesuai hak akses.
- Pendaftaran: identitas pasien, kepesertaan internal, reservasi/kunjungan, jadwal praktik, serta monitoring pelayanan.
- Perawat: pengkajian awal, pelaksanaan tindakan, pencatatan BHP, dan laporan sesuai poli penugasan.
- Dokter: pemeriksaan, diagnosis berkode/manual, odontogram, tindakan, resep, edukasi, rencana kontrol, serta dokumen klinis sesuai otorisasi.
- Farmasi: penyiapan dan penyerahan obat, penyerahan parsial, resep luar, pemberian langsung sesuai eligibility, serta laporan dan jejak stok batch.
- Manajemen: ringkasan dan laporan sesuai pembatasan akses.

Alur utama: pendaftaran → pengkajian awal → pemeriksaan dokter → tindakan perawat jika diperlukan → farmasi jika diperlukan → selesai. Kunjungan dapat selesai tanpa resep; tindak lanjut mengikuti data pelayanan yang benar-benar dicatat. Reservasi mendatang mengikuti pembatasan tanggal pelayanan.

Modul laboratorium, depo obat, alat kesehatan, dan master tindakan dinonaktifkan secara bawaan melalui `config/clinic.php`. Kode, tabel, dan test modul tersebut tetap dipertahankan karena masih terhubung melalui konfigurasi, relasi, route, dan cakupan regresi.

## Struktur proyek

```text
app/
  Console/Commands/      Perintah pemeliharaan klinik
  Http/Controllers/     Endpoint per area dan peran
  Http/Middleware/      Autentikasi, otorisasi, audit, dan modul
  Http/Requests/        Validasi request yang telah dipisahkan
  Models/               Model dan relasi domain
  Exports/, Imports/    Impor dan ekspor data
  Rules/                Validasi domain khusus
  Providers/            Registrasi layanan aplikasi
  *.php                 Layanan, query, dan aturan domain yang digunakan bersama
bootstrap/              Bootstrap Laravel
config/                 Konfigurasi aplikasi dan modul
database/              Migration berurutan, factory, dan seeder
public/                 Entrypoint HTTP dan aset publik sumber
resources/
  css/                  Tema dan stylesheet aplikasi
  js/components/        Komponen UI dan komponen klinis
  js/layouts/           Layout dan navigasi berbasis peran
  js/lib/               Fungsi domain dan utilitas frontend
  js/pages/             Halaman Inertia per area
  js/types/             Kontrak TypeScript bersama
  views/app.blade.php   Shell Inertia
routes/                 Route web dan jadwal console
storage/                Berkas runtime, log, dan lampiran sesuai disk
tests/                 Feature test backend dan test render frontend
```

`vendor/`, `node_modules/`, hasil build, cache, log, dan hasil generasi bukan kode sumber aplikasi. `output/`, `tmp/`, dan compiled view khusus test diabaikan Git. PDF referensi lokal yang disediakan pengguna dipertahankan. Instruksi/skill alat pengembangan di direktori tersembunyi bukan dokumentasi aplikasi.

## Instalasi lokal

Jalankan dari direktori proyek menggunakan PowerShell. Langkah pembuatan `.env` dan application key hanya untuk instalasi baru; pertahankan konfigurasi dan key instalasi yang sudah berisi data.

```powershell
composer install
npm ci --ignore-scripts
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate --no-interaction
php artisan migrate --no-interaction
php artisan storage:link --no-interaction
npm run build
```

Atur koneksi database, URL aplikasi, zona waktu, dan kebutuhan layanan di `.env`. Contoh konfigurasi memakai SQLite dan `Asia/Jakarta`. Buat `database/database.sqlite` bila belum tersedia sebelum migration pertama. Jalankan `composer run dev` untuk server aplikasi, queue listener, dan Vite melalui perintah development proyek.

Setelah memperbarui dependensi frontend, jalankan ulang proses `composer run dev` yang sudah aktif agar memuat paket baru.

`php artisan db:seed --no-interaction` bersifat opsional untuk database pengembangan kosong. `DatabaseSeeder` berisi akun dan data contoh, serta dibatasi ke lingkungan `local`/`testing`. Jangan gunakan akun contoh untuk instalasi operasional. Jangan menjalankan `migrate:fresh` atau seeding contoh terhadap data klinis yang harus dipertahankan.

## Database dan integritas

Kelompok tabel utama mencakup identitas/kepesertaan, kunjungan/pengkajian/pemeriksaan, diagnosis/odontogram/tindakan, resep/farmasi, obat/batch/mutasi, persuratan, jadwal praktik, dan audit/versioning catatan klinis.

- Pertahankan urutan dan identitas migration yang sudah diterapkan. Perubahan schema berikutnya dibuat melalui migration baru; migration lama penghapusan modul pensiun tetap diperlukan untuk instalasi dari awal.
- Pencatatan stok mengikuti batch, kedaluwarsa, kondisi, dan jejak mutasi. Nilai stok agregat saja tidak cukup untuk memastikan obat layak diserahkan.
- Snapshot dokumen dan nomor surat dipertahankan sesuai waktu penerbitan. Jangan menghitung ulang dokumen historis dari data pasien yang sudah berubah.
- Pembatasan poli/peran dan pemisahan operator dengan penandatangan klinis harus dijaga pada endpoint maupun UI.
- Empat indeks lookup ditambahkan untuk `diagnosa.pemeriksaan_id`, `tindakan_kunjungan.kunjungan_id`, `resep_obat.resep_id`, dan `farmasi_item.farmasi_id`. Migration melewati indeks yang sudah ada dan rollback hanya menghapus indeks bernama milik migration tersebut.

Audit relasi database tanpa menulis atau menghapus data:

```powershell
php artisan clinic:audit-database --no-interaction
```

Perintah mengembalikan exit code `0` jika relasi yang dideklarasikan tidak bermasalah, atau `1` jika menemukan pelanggaran. Pemeriksaan ini tidak memvalidasi seluruh aturan klinis, konsistensi stok, atau relasi logis yang tidak dideklarasikan sebagai foreign key.

Pada audit lokal 3 November 2026, integritas fisik SQLite berstatus `ok`, tetapi ditemukan **8 pelanggaran foreign key** terhadap tabel `kunjungan`: screening (1), pemeriksaan (1), resep (1), farmasi (1), odontogram_findings (2), dan tindakan_kunjungan (2). Data tersebut dipertahankan dan memerlukan rekonsiliasi berdasarkan sumber klinis yang sah. Jangan menghapus atau mengaitkan ulang baris tersebut hanya untuk membuat audit berstatus hijau.

Backup sebelum perubahan indeks dan salinan berkas yang dibersihkan tersimpan di luar repositori pada `C:\Project\LARAVEL\klinik-pratama-cleanup-backup-20261103`. Backup database dapat berisi data klinis; perlakukan sesuai akses data sumber.

## Pemeriksaan kualitas

```powershell
php artisan test --compact
npm run test:frontend
npm run types:check
npm run build
php vendor/bin/pint --dirty --format agent
git diff --check
composer validate --no-check-publish
composer audit
npm audit
```

Test backend menggunakan SQLite in-memory dan compiled view di `storage/framework/testing/views`, terpisah dari server lokal. Test frontend memakai Node.js test runner, Vite, dan React server rendering; test tersebut tidak menjalankan interaksi browser. TypeScript menolak deklarasi lokal dan parameter yang tidak digunakan.

Saat melakukan perubahan, mulai dari test paling sempit yang mencakup perilaku terkait, lalu jalankan pemeriksaan menyeluruh sesuai cakupan. Pertahankan test yang dapat menangkap cacat berbeda; jangan menghapus regresi hanya karena modul sedang tidak aktif.

## Ringkasan pemeliharaan 3 November 2026

- Mengganti README bawaan framework dan mengonsolidasikan Markdown proyek lama menjadi dokumen ini.
- Menghapus entrypoint JavaScript lama, tiga komponen tanpa jalur penggunaan aplikasi, CSS anatomi lama, dua test contoh bawaan, perintah console inspirasi bawaan, dan PDF contoh hasil generasi.
- Membersihkan import/deklarasi yang tidak dipakai dan mengaktifkan pemeriksaan TypeScript agar masalah tersebut terdeteksi kembali.
- Memperbarui fixture dan ekspektasi test render agar mengikuti kontrak antrean, menu, label, dan aksi nonaktif saat ini, termasuk memeriksa bahwa aksi terlindungi tetap tidak dapat digunakan.
- Memperbarui Vite dari 8.3.2 ke 8.3.5 dengan persetujuan pengguna; audit npm setelah pembaruan melaporkan 0 kerentanan.
- Menambahkan audit foreign key yang tidak mengubah data dan empat indeks lookup dengan bukti perubahan query plan dari `SCAN` menjadi `SEARCH ... USING INDEX` pada SQLite lokal.
- Memperbaiki rollback konteks audit kepesertaan: hapus indeks sebelum menghapus kolom pada SQLite, dengan test rollback dan penerapan ulang.

| Pemeriksaan akhir | Hasil |
| --- | --- |
| Backend PHPUnit | 444 test lolos, 8.506 assertion |
| Frontend render | 236 test lolos, tanpa test dilewati |
| TypeScript | Lolos, termasuk deklarasi dan parameter tidak terpakai |
| Build produksi | Lolos pada Vite 8.3.5 |
| Pint dan git diff --check | Lolos |
| Validasi Composer | Valid; peringatan constraint Inertia yang dikunci tepat tetap dipertahankan |
| Audit npm | 0 kerentanan pada hasil registry saat audit |
| Audit keamanan Composer | Belum terverifikasi: Packagist gagal diakses karena reset koneksi/timeout |
| Database lokal | Integritas fisik ok; 8 pelanggaran foreign key yang telah ada tetap dipertahankan |

Build menampilkan peringatan fitur font fallback optimal yang membutuhkan paket opsional fontaine; build tetap berhasil dan tidak ada dependensi tambahan dipasang untuk peringatan tersebut. UAT browser langsung, penerimaan pengguna, rekonsiliasi delapan pelanggaran relasi lokal, keputusan kewenangan klinis bantuan Admin, dan kesiapan produksi tetap memerlukan bukti terpisah.
