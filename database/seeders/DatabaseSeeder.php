<?php

namespace Database\Seeders;

use App\Models\Asuransi;
use App\Models\BiayaAdmin;
use App\Models\DepoObat;
use App\Models\Diagnosa;
use App\Models\Farmasi;
use App\Models\FarmasiItem;
use App\Models\Icd10;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\LabIndikator;
use App\Models\Laboratorium;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\ResepObat;
use App\Models\Screening;
use App\Models\StokMutasi;
use App\Models\Tagihan;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('DatabaseSeeder berisi akun dan data contoh; gunakan hanya pada lingkungan local/testing.');
        }

        // Admin user
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@klinik.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Registration staff user for local/manual testing
        User::create([
            'name' => 'Petugas Pendaftaran',
            'email' => 'pendaftaran@klinik.com',
            'password' => Hash::make('password'),
            'role' => 'pendaftaran',
            'is_active' => true,
        ]);

        // Klinik Setting
        KlinikSetting::create([
            'nama_klinik' => 'Klinik Pratama',
            'alamat' => 'Jl. Contoh No. 1, Kota',
            'telepon' => '021-12345678',
            'email' => 'info@klinikpratama.com',
            'kepala_klinik' => 'dr. Kepala Klinik',
        ]);

        // Depo Obat
        $apotek = DepoObat::create(['kode' => 'APT', 'nama' => 'Apotek', 'is_active' => true]);
        $gudang = DepoObat::create(['kode' => 'GDG', 'nama' => 'Gudang Farmasi', 'is_active' => true]);
        DepoObat::create(['kode' => 'UGD', 'nama' => 'UGD', 'is_active' => false]);

        // Poliklinik
        $poliUmum = Poliklinik::create(['kode' => 'PU', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'depo_obat_id' => $apotek->id]);
        $poliGigi = Poliklinik::create(['kode' => 'PG', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'depo_obat_id' => $apotek->id]);
        $poliKia = Poliklinik::create(['kode' => 'KIA', 'nama' => 'Poli KIA', 'jenis' => 'kia', 'depo_obat_id' => $apotek->id, 'is_active' => false]);
        $poliLab = Poliklinik::create(['kode' => 'LAB', 'nama' => 'Laboratorium', 'jenis' => 'lab', 'depo_obat_id' => null, 'is_active' => false]);

        // Nakes
        $dokter = Nakes::create([
            'kode' => 'DKT001', 'nama' => 'dr. Ahmad Sehat',
            'kategori' => 'medis', 'jabatan' => 'dokter',
            'no_sip' => 'SIP.001/2024', 'is_active' => true,
        ]);
        Nakes::create([
            'kode' => 'PRT001', 'nama' => 'Siti Perawat, Amd.Kep',
            'kategori' => 'medis', 'jabatan' => 'perawat', 'is_active' => true,
        ]);
        Nakes::create([
            'kode' => 'FRM001', 'nama' => 'Budi Farmasi, S.Farm',
            'kategori' => 'non_medis', 'jabatan' => 'farmasi', 'is_active' => true,
        ]);
        Nakes::create([
            'kode' => 'KSR001', 'nama' => 'Dewi Kasir',
            'kategori' => 'non_medis', 'jabatan' => 'kasir', 'is_active' => true,
        ]);

        // Asuransi
        Asuransi::create(['kode' => 'BPJS', 'nama' => 'BPJS Kesehatan', 'jenis' => 'bpjs']);
        Asuransi::create(['kode' => 'UMUM', 'nama' => 'Umum (Bayar Sendiri)', 'jenis' => 'umum']);

        // Obat
        Obat::create([
            'kode' => 'OBT001', 'nama' => 'Paracetamol 500mg',
            'satuan_besar' => 'Strip', 'satuan_kecil' => 'Tablet',
            'konversi_satuan' => 10, 'harga_beli' => 2000, 'harga_jual' => 3000,
            'stok' => 100, 'stok_minimum' => 20, 'jenis' => 'obat',
        ]);
        Obat::create([
            'kode' => 'OBT002', 'nama' => 'Amoxicillin 500mg',
            'satuan_besar' => 'Strip', 'satuan_kecil' => 'Kapsul',
            'konversi_satuan' => 10, 'harga_beli' => 3000, 'harga_jual' => 5000,
            'stok' => 80, 'stok_minimum' => 20, 'jenis' => 'obat',
        ]);
        Obat::create([
            'kode' => 'OBT003', 'nama' => 'Antasida Doen',
            'satuan_besar' => 'Box', 'satuan_kecil' => 'Tablet',
            'konversi_satuan' => 100, 'harga_beli' => 15000, 'harga_jual' => 25000,
            'stok' => 50, 'stok_minimum' => 10, 'jenis' => 'obat',
        ]);
        Obat::create([
            'kode' => 'OBT004', 'nama' => 'Ibuprofen 400mg',
            'satuan_besar' => 'Strip', 'satuan_kecil' => 'Tablet',
            'konversi_satuan' => 10, 'harga_beli' => 4000, 'harga_jual' => 6000,
            'stok' => 60, 'stok_minimum' => 15, 'jenis' => 'obat',
        ]);
        Obat::create([
            'kode' => 'BHP001', 'nama' => 'Sarung Tangan (Pasang)',
            'satuan_besar' => 'Box', 'satuan_kecil' => 'Pasang',
            'konversi_satuan' => 50, 'harga_beli' => 3000, 'harga_jual' => 5000,
            'stok' => 200, 'stok_minimum' => 50, 'jenis' => 'bhp',
        ]);
        Obat::create([
            'kode' => 'BHP002', 'nama' => 'Kapas Gulung',
            'satuan_besar' => 'Roll', 'satuan_kecil' => 'Gram',
            'konversi_satuan' => 100, 'harga_beli' => 5000, 'harga_jual' => 8000,
            'stok' => 50, 'stok_minimum' => 10, 'jenis' => 'bhp',
        ]);

        // Tindakan
        $hecting = Tindakan::create([
            'kode' => 'TDK001', 'kode_icd9' => '86.59', 'nama' => 'Hecting / Jahit Luka',
            'kategori' => 'medis', 'poliklinik_id' => $poliUmum->id,
            'tarif' => 150000, 'tarif_dokter' => 100000, 'tarif_asisten' => 25000, 'tarif_klinik' => 25000,
        ]);
        Tindakan::create([
            'kode' => 'TDK002', 'nama' => 'Injeksi IV',
            'kategori' => 'medis', 'poliklinik_id' => $poliUmum->id,
            'tarif' => 50000, 'tarif_dokter' => 30000, 'tarif_asisten' => 10000, 'tarif_klinik' => 10000,
        ]);
        Tindakan::create([
            'kode' => 'TDK003', 'nama' => 'EKG / Rekam Jantung',
            'kategori' => 'medis', 'poliklinik_id' => $poliUmum->id,
            'tarif' => 100000, 'tarif_dokter' => 70000, 'tarif_asisten' => 15000, 'tarif_klinik' => 15000,
        ]);

        // Laboratorium
        $labDarah = Laboratorium::create([
            'kode' => 'LAB001', 'nama' => 'Darah Lengkap',
            'poliklinik_id' => $poliLab->id, 'tarif' => 80000,
        ]);
        LabIndikator::create(['laboratorium_id' => $labDarah->id, 'nama' => 'Hemoglobin', 'satuan' => 'g/dL', 'nilai_rujukan_min' => '12', 'nilai_rujukan_max' => '17', 'format_input' => 'number', 'urutan' => 1]);
        LabIndikator::create(['laboratorium_id' => $labDarah->id, 'nama' => 'Leukosit', 'satuan' => 'ribu/µL', 'nilai_rujukan_min' => '4.5', 'nilai_rujukan_max' => '11', 'format_input' => 'number', 'urutan' => 2]);
        LabIndikator::create(['laboratorium_id' => $labDarah->id, 'nama' => 'Trombosit', 'satuan' => 'ribu/µL', 'nilai_rujukan_min' => '150', 'nilai_rujukan_max' => '400', 'format_input' => 'number', 'urutan' => 3]);

        $labGDA = Laboratorium::create([
            'kode' => 'LAB002', 'nama' => 'Gula Darah Acak',
            'poliklinik_id' => $poliLab->id, 'tarif' => 25000,
        ]);
        LabIndikator::create(['laboratorium_id' => $labGDA->id, 'nama' => 'Gula Darah', 'satuan' => 'mg/dL', 'nilai_rujukan_min' => '70', 'nilai_rujukan_max' => '200', 'format_input' => 'number', 'urutan' => 1]);

        $labGolDar = Laboratorium::create([
            'kode' => 'LAB003', 'nama' => 'Golongan Darah',
            'poliklinik_id' => $poliLab->id, 'tarif' => 20000,
        ]);
        LabIndikator::create(['laboratorium_id' => $labGolDar->id, 'nama' => 'Golongan Darah', 'satuan' => '', 'format_input' => 'select', 'pilihan' => json_encode(['A', 'B', 'O', 'AB']), 'urutan' => 1]);

        // Biaya Admin
        BiayaAdmin::create(['nama' => 'Biaya Administrasi', 'tarif' => 5000]);
        BiayaAdmin::create(['nama' => 'Biaya Ambulans', 'tarif' => 200000]);

        // ICD-10
        $icd10Data = [
            ['kode' => 'A09', 'nama' => 'Diare dan gastroenteritis noninfeksi'],
            ['kode' => 'B34.9', 'nama' => 'Infeksi virus, tidak spesifik'],
            ['kode' => 'E11', 'nama' => 'Diabetes melitus tipe 2'],
            ['kode' => 'E78.5', 'nama' => 'Hiperlipidemia, tidak spesifik'],
            ['kode' => 'F32.9', 'nama' => 'Episode depresi, tidak spesifik'],
            ['kode' => 'G43.9', 'nama' => 'Migrain, tidak spesifik'],
            ['kode' => 'H10', 'nama' => 'Konjungtivitis'],
            ['kode' => 'I10', 'nama' => 'Hipertensi esensial (primer)'],
            ['kode' => 'J00', 'nama' => 'Nasofaringitis akut (common cold)'],
            ['kode' => 'J06.9', 'nama' => 'Infeksi saluran napas atas akut, tidak spesifik'],
            ['kode' => 'J11', 'nama' => 'Influenza, virus tidak teridentifikasi'],
            ['kode' => 'J18.9', 'nama' => 'Pneumonia, tidak spesifik'],
            ['kode' => 'K21', 'nama' => 'Penyakit refluks gastroesofagus'],
            ['kode' => 'K30', 'nama' => 'Dispepsia fungsional'],
            ['kode' => 'K59.0', 'nama' => 'Konstipasi'],
            ['kode' => 'L20', 'nama' => 'Dermatitis atopik'],
            ['kode' => 'L50', 'nama' => 'Urtikaria'],
            ['kode' => 'M54.5', 'nama' => 'Nyeri punggung bawah'],
            ['kode' => 'N39.0', 'nama' => 'Infeksi saluran kemih, tidak spesifik'],
            ['kode' => 'R05', 'nama' => 'Batuk'],
            ['kode' => 'R50.9', 'nama' => 'Demam, tidak spesifik'],
            ['kode' => 'R51', 'nama' => 'Sakit kepala'],
            ['kode' => 'Z00.0', 'nama' => 'Pemeriksaan umum/check-up'],
            ['kode' => 'A00', 'nama' => 'Kolera'],
            ['kode' => 'B02', 'nama' => 'Herpes zoster'],
            ['kode' => 'K92.1', 'nama' => 'Melena'],
            ['kode' => 'S00.0', 'nama' => 'Cedera superfisial kepala'],
            ['kode' => 'T14.0', 'nama' => 'Luka, tidak spesifik lokasi'],
        ];

        foreach ($icd10Data as $data) {
            Icd10::create($data);
        }

        // Sample Pasien
        $bpjs = Asuransi::where('kode', 'BPJS')->first();
        $umum = Asuransi::where('kode', 'UMUM')->first();

        $pasien1 = Pasien::create([
            'no_rm' => 'RM-2026-0001',
            'nama' => 'Budi Santoso',
            'nik' => '3201234567890001',
            'tanggal_lahir' => '1990-05-15',
            'jenis_kelamin' => 'L',
            'golongan_darah' => 'O',
            'alamat' => 'Jl. Merdeka No. 10, Jakarta Pusat',
            'telepon' => '081234567890',
            'pekerjaan' => 'Karyawan Swasta',
            'agama' => 'Islam',
            'status_perkawinan' => 'Menikah',
            'asuransi_id' => $bpjs?->id,
            'no_asuransi' => '0001234567891',
            'riwayat_alergi' => 'Alergi Penisilin',
        ]);

        $pasien2 = Pasien::create([
            'no_rm' => 'RM-2026-0002',
            'nama' => 'Siti Rahmawati',
            'nik' => '3201234567890002',
            'tanggal_lahir' => '1985-11-20',
            'jenis_kelamin' => 'P',
            'golongan_darah' => 'B',
            'alamat' => 'Jl. Melati No. 5, Jakarta Selatan',
            'telepon' => '081298765432',
            'pekerjaan' => 'Guru',
            'agama' => 'Islam',
            'status_perkawinan' => 'Menikah',
            'asuransi_id' => $umum?->id,
            'riwayat_alergi' => 'Tidak ada',
        ]);

        $pasien3 = Pasien::create([
            'no_rm' => 'RM-2026-0003',
            'nama' => 'Andi Wijaya',
            'nik' => '3201234567890003',
            'tanggal_lahir' => '2000-01-10',
            'jenis_kelamin' => 'L',
            'golongan_darah' => 'A',
            'alamat' => 'Jl. Kenanga No. 12, Bandung',
            'telepon' => '085712345678',
            'pekerjaan' => 'Mahasiswa',
            'agama' => 'Kristen',
            'status_perkawinan' => 'Belum Menikah',
            'asuransi_id' => $bpjs?->id,
            'no_asuransi' => '0009876543210',
        ]);

        // Sample Kunjungan Aktif & Selesai
        $kunjunganSelesai = Kunjungan::create([
            'no_kunjungan' => 'KNJ-'.now()->format('Ymd').'-0001',
            'pasien_id' => $pasien1->id,
            'poliklinik_id' => $poliUmum->id,
            'dokter_id' => $dokter->id,
            'asuransi_id' => $bpjs?->id,
            'tanggal' => today(),
            'status' => 'selesai',
            'jenis_pasien' => 'baru',
            'jenis_bayar' => 'bpjs',
            'catatan' => 'Kunjungan kontrol flu & demam',
        ]);

        Screening::create([
            'kunjungan_id' => $kunjunganSelesai->id,
            'keluhan' => 'Demam dan batuk pilek sejak 2 hari yang lalu',
            'td_sistole' => 120,
            'td_diastole' => 80,
            'nadi' => 82,
            'suhu' => 38.2,
            'berat_badan' => 68,
            'tinggi_badan' => 172,
            'spo2' => 98,
            'respirasi' => 20,
            'riwayat_penyakit' => 'Gastritis',
            'riwayat_alergi' => 'Penisilin',
        ]);

        $pemeriksaan = Pemeriksaan::create([
            'kunjungan_id' => $kunjunganSelesai->id,
            'dokter_id' => $dokter->id,
            'anamnesis' => 'Pasien mengeluhkan demam tinggi, nyeri tenggorokan, dan bersin-bersin sejak 2 hari.',
            'pemeriksaan_fisik' => 'Faring hiperemis (+), tonsil T1/T1 tenang, rhonki (-/-), wheezing (-/-).',
            'status' => 'selesai',
        ]);

        Diagnosa::create([
            'pemeriksaan_id' => $pemeriksaan->id,
            'kode_icd10' => 'J00',
            'nama_diagnosa' => 'Nasofaringitis akut (common cold)',
            'jenis' => 'utama',
        ]);

        $resep = Resep::create([
            'no_resep' => 'RSP-'.now()->format('Ymd').'-0001',
            'kunjungan_id' => $kunjunganSelesai->id,
            'dokter_id' => $dokter->id,
            'status' => 'selesai',
        ]);

        $obatParacetamol = Obat::where('kode', 'OBT001')->first();
        if ($obatParacetamol) {
            $resepObat = ResepObat::create([
                'resep_id' => $resep->id,
                'obat_id' => $obatParacetamol->id,
                'nama_obat' => $obatParacetamol->nama,
                'jumlah' => 10,
                'satuan' => 'Tablet',
                'aturan_pakai' => '3x1 tablet sesudah makan',
                'jenis' => 'jadi',
            ]);

            $farmasi = Farmasi::create([
                'kunjungan_id' => $kunjunganSelesai->id,
                'resep_id' => $resep->id,
                'status' => 'selesai',
            ]);
            FarmasiItem::create([
                'farmasi_id' => $farmasi->id,
                'resep_obat_id' => $resepObat->id,
                'obat_id' => $obatParacetamol->id,
                'jumlah_diberikan' => 10,
                'aturan_pakai' => $resepObat->aturan_pakai,
            ]);
            $obatParacetamol->update(['stok' => 90]);
            StokMutasi::create([
                'obat_id' => $obatParacetamol->id,
                'jenis' => 'keluar',
                'referensi_type' => 'Farmasi',
                'referensi_id' => $farmasi->id,
                'jumlah' => 10,
                'harga' => $obatParacetamol->harga_jual,
                'stok_sebelum' => 100,
                'stok_sesudah' => 90,
                'keterangan' => "Dispensing kunjungan {$kunjunganSelesai->no_kunjungan}",
            ]);
        }

        $tagihan = Tagihan::create([
            'no_tagihan' => 'TGH-'.now()->format('Ymd').'-0001',
            'kunjungan_id' => $kunjunganSelesai->id,
            'subtotal' => 85000,
            'diskon' => 0,
            'total' => 85000,
            'bayar' => 85000,
            'kembalian' => 0,
            'metode_bayar' => 'bpjs',
            'status' => 'lunas',
        ]);

        // Kunjungan 2: Antrian Menunggu
        Kunjungan::create([
            'no_kunjungan' => 'KNJ-'.now()->format('Ymd').'-0002',
            'pasien_id' => $pasien2->id,
            'poliklinik_id' => $poliUmum->id,
            'dokter_id' => $dokter->id,
            'asuransi_id' => $umum?->id,
            'tanggal' => today(),
            'status' => 'menunggu',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
            'catatan' => 'Pemeriksaan tensi rutin',
        ]);

        // Kunjungan 3: Sedang Screening
        $kunjungan3 = Kunjungan::create([
            'no_kunjungan' => 'KNJ-'.now()->format('Ymd').'-0003',
            'pasien_id' => $pasien3->id,
            'poliklinik_id' => $poliGigi->id,
            'asuransi_id' => $bpjs?->id,
            'tanggal' => today(),
            'status' => 'screening',
            'jenis_pasien' => 'baru',
            'jenis_bayar' => 'bpjs',
            'catatan' => 'Sakit gigi geraham bawah',
        ]);
    }
}
