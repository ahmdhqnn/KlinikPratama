<?php

namespace Database\Seeders;

use App\ClinicalNoteRecorder;
use App\GeneralConsentService;
use App\HakLayananVerifier;
use App\MedicalDocumentVerificationService;
use App\MedicalLetterData;
use App\Models\CorrespondenceTemplate;
use App\Models\DepoObat;
use App\Models\Diagnosa;
use App\Models\Farmasi;
use App\Models\FarmasiItem;
use App\Models\GeneralConsent;
use App\Models\Icd10;
use App\Models\JadwalDokter;
use App\Models\Kepesertaan;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\Pasien;
use App\Models\PatientAllergy;
use App\Models\Pemeriksaan;
use App\Models\PharmacyStaffCredential;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\ResepObat;
use App\Models\RuangPoli;
use App\Models\Screening;
use App\Models\SuratMedis;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\PersediaanRecorder;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KemenparPreReleaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Kemenpar2026!';

    public const DEMO_SIGNING_PIN = '123456';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing', 'staging'])) {
            throw new \RuntimeException('KemenparPreReleaseSeeder hanya diizinkan pada lingkungan local, testing, atau staging.');
        }

        // 1. Profil Resmi Klinik Pratama Kementerian Pariwisata
        $settingPayload = [
            'nama_klinik' => 'Klinik Pratama Kementerian Pariwisata',
            'kode_fasyankes' => 'P31710102',
            'izin_operasional' => '6/B.6.1/31.09.86/e/2023',
            'alamat' => 'Gedung Sapta Pesona, Jl. Medan Merdeka Barat No. 17, Gambir, Jakarta Pusat',
            'kode_provinsi' => '31',
            'kode_kabupaten' => '3171',
            'kode_kecamatan' => '317101',
            'kode_kelurahan' => '3171011001',
            'kode_pos' => '10110',
            'telepon' => '(021) 3838142',
            'email' => 'klinik@kemenpar.go.id',
            'website' => 'https://kemenpar.go.id',
            'tagline' => 'Layanan Kesehatan Prima Aparatur Kementerian Pariwisata',
            'kepala_klinik' => 'dr. Ratna Kusumawati, M.K.M.',
            'nip_kepala' => '198204152009122001',
            'pelaksana_ttv' => 'perawat',
        ];
        $existingSetting = KlinikSetting::query()->first();
        if ($existingSetting) {
            $existingSetting->update($settingPayload);
        } else {
            KlinikSetting::query()->create($settingPayload);
        }

        // 2. Depo Farmasi Terpisah per Poli, Poliklinik & Ruang Poli (Poli Umum & Poli Gigi Aktif)
        $depoUmum = DepoObat::query()->updateOrCreate(
            ['kode' => 'DEPO-PU'],
            ['nama' => 'Depo Farmasi Poli Umum Kemenpar', 'deskripsi' => 'Depo obat khusus pelayanan Poli Umum Gedung Sapta Pesona', 'is_active' => true]
        );
        $depoGigi = DepoObat::query()->updateOrCreate(
            ['kode' => 'DEPO-PG'],
            ['nama' => 'Depo Farmasi Poli Gigi Kemenpar', 'deskripsi' => 'Depo obat khusus pelayanan Poli Gigi Gedung Sapta Pesona', 'is_active' => true]
        );

        $poliUmum = Poliklinik::query()->updateOrCreate(
            ['kode' => 'PU'],
            ['nama' => 'Poli Umum', 'jenis' => 'umum', 'depo_obat_id' => $depoUmum->id, 'is_active' => true]
        );
        $poliGigi = Poliklinik::query()->updateOrCreate(
            ['kode' => 'PG'],
            ['nama' => 'Poli Gigi', 'jenis' => 'gigi', 'depo_obat_id' => $depoGigi->id, 'is_active' => true]
        );
        Poliklinik::query()->updateOrCreate(
            ['kode' => 'KIA'],
            ['nama' => 'Poli KIA', 'jenis' => 'kia', 'is_active' => false]
        );

        $ruangUmum = RuangPoli::query()->updateOrCreate(
            ['kode' => 'R-PU-01'],
            ['poliklinik_id' => $poliUmum->id, 'nama' => 'Ruang Pemeriksaan Umum 1 (Lt. Dasar Gedung Sapta Pesona)', 'is_active' => true]
        );
        $ruangGigi = RuangPoli::query()->updateOrCreate(
            ['kode' => 'R-PG-01'],
            ['poliklinik_id' => $poliGigi->id, 'nama' => 'Ruang Pemeriksaan Gigi & Mulut (Lt. Dasar Gedung Sapta Pesona)', 'is_active' => true]
        );

        // 3. Akun Demo per Role + PIN Tanda Tangan Klinis Terpisah + Citra Tanda Tangan
        $passwordHash = Hash::make(self::DEMO_PASSWORD);
        $pinHash = Hash::make(self::DEMO_SIGNING_PIN);
        $fallbackPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAALQAAAA8CAYAAAAa9X6cAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAABYSURBVHhe7dAxAQAAAMKg9U9tCU+gAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAgLsBTwAB8a5N8QAAAABJRU5ErkJggg==',
            true
        ) ?: '';

        $renderSignaturePng = static function (string $label) use ($fallbackPng): string {
            if (! function_exists('imagecreatetruecolor')) {
                return $fallbackPng;
            }
            $img = imagecreatetruecolor(240, 80);
            imagesavealpha($img, true);
            imagealphablending($img, false);
            $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
            imagefill($img, 0, 0, $transparent);
            imagealphablending($img, true);
            $ink = imagecolorallocate($img, 18, 46, 112);
            imagesetthickness($img, 2);
            imageline($img, 24, 58, 76, 22, $ink);
            imageline($img, 76, 22, 92, 54, $ink);
            imageline($img, 92, 54, 148, 28, $ink);
            imageline($img, 148, 28, 212, 48, $ink);
            imageline($img, 36, 64, 198, 56, $ink);
            imagestring($img, 2, 28, 64, substr($label, 0, 26), $ink);
            ob_start();
            imagepng($img);
            $bytes = ob_get_clean() ?: $fallbackPng;
            imagedestroy($img);

            return $bytes;
        };

        $accounts = [
            'admin' => ['name' => 'Admin Sistem Klinik Kemenpar', 'email' => 'admin@kemenpar.go.id', 'role' => 'admin'],
            'pendaftaran' => ['name' => 'Nadia Putri, A.Md.RMIK', 'email' => 'pendaftaran@kemenpar.go.id', 'role' => 'pendaftaran'],
            'perawat_umum' => ['name' => 'Ns. Siti Nurhaliza, S.Kep.', 'email' => 'perawat.umum@kemenpar.go.id', 'role' => 'perawat'],
            'perawat_gigi' => ['name' => 'Dewi Lestari, A.Md.Kes.', 'email' => 'perawat.gigi@kemenpar.go.id', 'role' => 'perawat'],
            'dokter_umum' => ['name' => 'dr. Ratna Kusumawati, M.K.M.', 'email' => 'dokter.umum@kemenpar.go.id', 'role' => 'dokter'],
            'dokter_gigi' => ['name' => 'drg. Hendra Wijaya, Sp.KG', 'email' => 'dokter.gigi@kemenpar.go.id', 'role' => 'dokter'],
            'apoteker_umum' => ['name' => 'apt. Rina Kartika, S.Farm.', 'email' => 'apoteker.umum@kemenpar.go.id', 'role' => 'farmasi'],
            'apoteker_gigi' => ['name' => 'apt. Bagas Pratama, S.Farm.', 'email' => 'apoteker.gigi@kemenpar.go.id', 'role' => 'farmasi'],
        ];

        $users = [];
        foreach ($accounts as $key => $spec) {
            $sigPath = "signatures/demo-{$key}.png";
            $signaturePng = $renderSignaturePng($spec['name']);
            if ($signaturePng !== '') {
                Storage::disk('local')->put($sigPath, $signaturePng);
            }
            $attributes = [
                'name' => $spec['name'],
                'email' => $spec['email'],
                'password' => $passwordHash,
                'signing_pin_hash' => $pinHash,
                'signing_pin_set_at' => now(),
                'must_setup_signing_pin' => false,
                'role' => $spec['role'],
                'is_active' => true,
                'email_verified_at' => now(),
                'signature_path' => $sigPath,
            ];
            $existingUser = User::query()->where('email', $spec['email'])->first()
                ?? (in_array($spec['role'], ['admin', 'pendaftaran'], true)
                    ? User::query()->where('role', $spec['role'])->first()
                    : null);

            if ($existingUser) {
                $existingUser->forceFill($attributes)->save();
                $users[$key] = $existingUser;
            } else {
                $users[$key] = User::query()->forceCreate($attributes);
            }
        }

        // 4. Profil Tenaga Kesehatan & Penugasan Poliklinik
        $licenseExpiry = today()->addYears(3)->toDateString();
        $dokterUmum = Nakes::query()->updateOrCreate(
            ['kode' => 'DKT-KP-01'],
            [
                'user_id' => $users['dokter_umum']->id,
                'poliklinik_id' => $poliUmum->id,
                'nik' => '3171015504820001',
                'nama' => 'dr. Ratna Kusumawati, M.K.M.',
                'jenis_kelamin' => 'P',
                'kategori' => 'medis',
                'jabatan' => 'dokter',
                'no_sip' => '6/B.1/31.71/-1.779.3/2025',
                'sip_berlaku_sampai' => $licenseExpiry,
                'no_str' => '3121100221123456',
                'str_berlaku_sampai' => $licenseExpiry,
                'telepon' => '08118001001',
                'is_active' => true,
            ]
        );
        $dokterGigi = Nakes::query()->updateOrCreate(
            ['kode' => 'DKT-KP-02'],
            [
                'user_id' => $users['dokter_gigi']->id,
                'poliklinik_id' => $poliGigi->id,
                'nik' => '3174011208840002',
                'nama' => 'drg. Hendra Wijaya, Sp.KG',
                'jenis_kelamin' => 'L',
                'kategori' => 'medis',
                'jabatan' => 'dokter',
                'no_sip' => '7/B.1/31.71/-1.779.3/2025',
                'sip_berlaku_sampai' => $licenseExpiry,
                'no_str' => '3112200221654321',
                'str_berlaku_sampai' => $licenseExpiry,
                'telepon' => '08118001002',
                'is_active' => true,
            ]
        );
        $perawatUmum = Nakes::query()->updateOrCreate(
            ['kode' => 'PRT-KP-01'],
            [
                'user_id' => $users['perawat_umum']->id,
                'poliklinik_id' => $poliUmum->id,
                'nik' => '3172024506920003',
                'nama' => 'Ns. Siti Nurhaliza, S.Kep.',
                'jenis_kelamin' => 'P',
                'kategori' => 'medis',
                'jabatan' => 'perawat',
                'no_sip' => '12/SIPP/31.71/2025',
                'sip_berlaku_sampai' => $licenseExpiry,
                'no_str' => '1201721223344556',
                'str_berlaku_sampai' => $licenseExpiry,
                'telepon' => '08118001003',
                'is_active' => true,
            ]
        );
        $perawatGigi = Nakes::query()->updateOrCreate(
            ['kode' => 'PRT-KP-02'],
            [
                'user_id' => $users['perawat_gigi']->id,
                'poliklinik_id' => $poliGigi->id,
                'nik' => '3173045809940004',
                'nama' => 'Dewi Lestari, A.Md.Kes.',
                'jenis_kelamin' => 'P',
                'kategori' => 'medis',
                'jabatan' => 'terapis_gigi',
                'no_sip' => '19/SIPTGM/31.71/2025',
                'sip_berlaku_sampai' => $licenseExpiry,
                'no_str' => '1204521229988776',
                'str_berlaku_sampai' => $licenseExpiry,
                'telepon' => '08118001004',
                'is_active' => true,
            ]
        );
        $apotekerUmum = Nakes::query()->updateOrCreate(
            ['kode' => 'APT-KP-01'],
            [
                'user_id' => $users['apoteker_umum']->id,
                'poliklinik_id' => $poliUmum->id,
                'nik' => '3175065208910005',
                'nama' => 'apt. Rina Kartika, S.Farm.',
                'jenis_kelamin' => 'P',
                'kategori' => 'medis',
                'jabatan' => 'farmasi',
                'no_sip' => '19910812/SIPA_31.71/2025/2048',
                'sip_berlaku_sampai' => $licenseExpiry,
                'no_str' => '19910812/STRA-UI/2021/245890',
                'str_berlaku_sampai' => $licenseExpiry,
                'telepon' => '08118001005',
                'is_active' => true,
            ]
        );
        $apotekerGigi = Nakes::query()->updateOrCreate(
            ['kode' => 'APT-KP-02'],
            [
                'user_id' => $users['apoteker_gigi']->id,
                'poliklinik_id' => $poliGigi->id,
                'nik' => '3175061504900007',
                'nama' => 'apt. Bagas Pratama, S.Farm.',
                'jenis_kelamin' => 'L',
                'kategori' => 'medis',
                'jabatan' => 'farmasi',
                'no_sip' => '19900415/SIPA_31.71/2025/2091',
                'sip_berlaku_sampai' => $licenseExpiry,
                'no_str' => '19900415/STRA-UGM/2020/219432',
                'str_berlaku_sampai' => $licenseExpiry,
                'telepon' => '08118001007',
                'is_active' => true,
            ]
        );
        Nakes::query()->updateOrCreate(
            ['kode' => 'PDF-KP-01'],
            [
                'user_id' => $users['pendaftaran']->id,
                'nik' => '3171046103960006',
                'nama' => 'Nadia Putri, A.Md.RMIK',
                'jenis_kelamin' => 'P',
                'kategori' => 'non_medis',
                'jabatan' => 'pendaftaran',
                'telepon' => '08118001006',
                'is_active' => true,
            ]
        );

        // Verifikasi Kualifikasi Apoteker Poli Umum & Poli Gigi (agar Review Resep & Penyerahan Farmasi langsung aktif)
        $validUntil = today()->addYears(2)->toDateString();
        foreach ([
            [$apotekerUmum, $users['apoteker_umum'], 'SK Penugasan Apoteker Poli Umum Klinik Pratama Kemenpar No. KP.04/112/2025'],
            [$apotekerGigi, $users['apoteker_gigi'], 'SK Penugasan Apoteker Poli Gigi Klinik Pratama Kemenpar No. KP.04/113/2025'],
        ] as [$staffModel, $staffUser, $skReference]) {
            $latestCred = PharmacyStaffCredential::query()->where('nakes_id', $staffModel->id)->latest('id')->first();
            if (! $latestCred || $latestCred->qualification !== 'apoteker' || ($latestCred->data['valid_until'] ?? '') < today()->toDateString()) {
                PharmacyStaffCredential::query()->create([
                    'nakes_id' => $staffModel->id,
                    'actor_id' => $users['admin']->id,
                    'qualification' => 'apoteker',
                    'recorded_at' => now(),
                    'data' => [
                        'user_id' => $staffUser->id,
                        'sip' => $staffModel->no_sip,
                        'str' => $staffModel->no_str,
                        'reference' => $skReference,
                        'valid_until' => $validUntil,
                    ],
                ]);
            }
        }

        // Jadwal Praktik Aktif Setiap Hari (Senin - Minggu) agar presentasi siap dijalankan kapan pun
        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'] as $day) {
            JadwalDokter::query()->updateOrCreate(
                ['dokter_id' => $dokterUmum->id, 'poliklinik_id' => $poliUmum->id, 'hari' => $day],
                ['berlaku_mulai' => '2025-01-01', 'jam_mulai' => '07:30', 'jam_selesai' => '17:00', 'is_active' => true]
            );
            JadwalDokter::query()->updateOrCreate(
                ['dokter_id' => $dokterGigi->id, 'poliklinik_id' => $poliGigi->id, 'hari' => $day],
                ['berlaku_mulai' => '2025-01-01', 'jam_mulai' => '07:30', 'jam_selesai' => '17:00', 'is_active' => true]
            );
        }

        // 5. Master Obat, KFA/High-Alert/LASA/Rute & Batch FEFO
        $formulary = [
            ['kode' => 'KP-OBT-01', 'nama' => 'Paracetamol 500 mg', 'kandungan' => 'Paracetamol', 'rute_default' => 'oral', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Tablet', 'konversi_satuan' => 100, 'stok_minimum' => 30, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => false, 'boleh_diberikan_langsung' => true, 'batch' => 'PCM-26A', 'qty' => 250],
            ['kode' => 'KP-OBT-02', 'nama' => 'Amoxicillin 500 mg', 'kandungan' => 'Amoxicillin trihydrate', 'rute_default' => 'oral', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Kaplet', 'konversi_satuan' => 100, 'stok_minimum' => 30, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => false, 'boleh_diberikan_langsung' => false, 'batch' => 'AMX-26B', 'qty' => 180],
            ['kode' => 'KP-OBT-03', 'nama' => 'Cefadroxil 500 mg', 'kandungan' => 'Cefadroxil monohydrate', 'rute_default' => 'oral', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Kapsul', 'konversi_satuan' => 100, 'stok_minimum' => 20, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => false, 'boleh_diberikan_langsung' => false, 'batch' => 'CFD-26A', 'qty' => 120],
            ['kode' => 'KP-OBT-04', 'nama' => 'Amlodipine 5 mg', 'kandungan' => 'Amlodipine besilate', 'rute_default' => 'oral', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Tablet', 'konversi_satuan' => 30, 'stok_minimum' => 30, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => true, 'boleh_diberikan_langsung' => false, 'batch' => 'AML5-26A', 'qty' => 150],
            ['kode' => 'KP-OBT-05', 'nama' => 'Amlodipine 10 mg', 'kandungan' => 'Amlodipine besilate', 'rute_default' => 'oral', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Tablet', 'konversi_satuan' => 30, 'stok_minimum' => 30, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => true, 'boleh_diberikan_langsung' => false, 'batch' => 'AML10-26A', 'qty' => 150],
            ['kode' => 'KP-OBT-06', 'nama' => 'Glimepiride 2 mg', 'kandungan' => 'Glimepiride', 'rute_default' => 'oral', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Tablet', 'konversi_satuan' => 50, 'stok_minimum' => 20, 'jenis' => 'obat', 'is_high_alert' => true, 'is_lasa' => true, 'boleh_diberikan_langsung' => false, 'batch' => 'GLM-26H', 'qty' => 100],
            ['kode' => 'KP-OBT-07', 'nama' => 'Cetirizine 10 mg', 'kandungan' => 'Cetirizine HCl', 'rute_default' => 'oral', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Tablet', 'konversi_satuan' => 50, 'stok_minimum' => 20, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => false, 'boleh_diberikan_langsung' => true, 'batch' => 'CTZ-26A', 'qty' => 160],
            ['kode' => 'KP-OBT-08', 'nama' => 'Omeprazole 20 mg', 'kandungan' => 'Omeprazole', 'rute_default' => 'oral', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Kapsul', 'konversi_satuan' => 30, 'stok_minimum' => 20, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => false, 'boleh_diberikan_langsung' => false, 'batch' => 'OMP-26A', 'qty' => 140],
            ['kode' => 'KP-OBT-09', 'nama' => 'Asam Mefenamat 500 mg', 'kandungan' => 'Mefenamic acid', 'rute_default' => 'oral', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Kaplet', 'konversi_satuan' => 100, 'stok_minimum' => 25, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => false, 'boleh_diberikan_langsung' => false, 'batch' => 'MEF-26A', 'qty' => 200],
            ['kode' => 'KP-OBT-10', 'nama' => 'Hidrokortison Krim 2.5% 5 g', 'kandungan' => 'Hydrocortisone acetate', 'rute_default' => 'topikal', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Tube', 'konversi_satuan' => 24, 'stok_minimum' => 10, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => false, 'boleh_diberikan_langsung' => false, 'batch' => 'HDC-26T', 'qty' => 45],
            ['kode' => 'KP-OBT-11', 'nama' => 'Povidone Iodine Gargle 1% 100 mL', 'kandungan' => 'Povidone iodine', 'rute_default' => 'kumur', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Botol', 'konversi_satuan' => 12, 'stok_minimum' => 10, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => false, 'boleh_diberikan_langsung' => true, 'batch' => 'PVI-26K', 'qty' => 36],
            ['kode' => 'KP-OBT-12', 'nama' => 'Azithromycin 500 mg (Contoh Stok Kosong -> Resep Luar)', 'kandungan' => 'Azithromycin dihydrate', 'rute_default' => 'oral', 'satuan_besar' => 'Box', 'satuan_kecil' => 'Kaplet', 'konversi_satuan' => 20, 'stok_minimum' => 10, 'jenis' => 'obat', 'is_high_alert' => false, 'is_lasa' => false, 'boleh_diberikan_langsung' => false, 'batch' => null, 'qty' => 0],
        ];

        $dentalDepoCodes = ['KP-OBT-01', 'KP-OBT-02', 'KP-OBT-03', 'KP-OBT-09', 'KP-OBT-11'];
        $medicines = [];
        foreach ($formulary as $spec) {
            $batchNumber = $spec['batch'];
            $initialQty = $spec['qty'];
            unset($spec['batch'], $spec['qty']);

            $med = Obat::query()->firstOrCreate(
                ['kode' => $spec['kode']],
                [...$spec, 'stok' => 0, 'is_active' => true]
            );
            $med->update($spec + ['is_active' => true]);

            if ($batchNumber !== null && ! ObatBatch::query()->where('obat_id', $med->id)->where('nomor_batch', $batchNumber)->exists()) {
                app(PersediaanRecorder::class)->receive($med->id, [
                    'depo_id' => $depoUmum->id,
                    'nomor_batch' => $batchNumber,
                    'expired_at' => today()->addMonths(18)->toDateString(),
                    'jumlah' => $initialQty,
                    'sumber' => 'distribusi',
                    'referensi' => 'BAST Gudang Farmasi Poli Umum Kemenpar 2026',
                ], $users['admin']);
            }
            if ($batchNumber !== null && in_array($spec['kode'], $dentalDepoCodes, true)) {
                $dentalBatch = $batchNumber.'-PG';
                if (! ObatBatch::query()->where('obat_id', $med->id)->where('nomor_batch', $dentalBatch)->exists()) {
                    app(PersediaanRecorder::class)->receive($med->id, [
                        'depo_id' => $depoGigi->id,
                        'nomor_batch' => $dentalBatch,
                        'expired_at' => today()->addMonths(18)->toDateString(),
                        'jumlah' => max(20, (int) round($initialQty * 0.4)),
                        'sumber' => 'distribusi',
                        'referensi' => 'BAST Gudang Farmasi Poli Gigi Kemenpar 2026',
                    ], $users['admin']);
                }
            }
            $medicines[$spec['kode']] = $med->fresh();
        }

        // 6. Master Tindakan & ICD-10 Dasar + Template Persuratan Resmi Kemenpar
        $tindakanNebul = Tindakan::query()->updateOrCreate(
            ['kode' => 'TDK-KP-01'],
            ['kode_icd9' => '93.94', 'nama' => 'Terapi Inhalasi / Nebulisasi Bronkodilator', 'kategori' => 'medis', 'can_delegate_to_nurse' => true, 'poliklinik_id' => $poliUmum->id, 'is_active' => true]
        );
        Tindakan::query()->updateOrCreate(
            ['kode' => 'TDK-KP-02'],
            ['kode_icd9' => '93.57', 'nama' => 'Perawatan Luka Steril (Wound Toilet)', 'kategori' => 'medis', 'can_delegate_to_nurse' => true, 'poliklinik_id' => $poliUmum->id, 'is_active' => true]
        );
        Tindakan::query()->updateOrCreate(
            ['kode' => 'TDK-KP-03'],
            ['kode_icd9' => '96.54', 'nama' => 'Scaling Supra & Subgingiva', 'kategori' => 'medis', 'can_delegate_to_nurse' => true, 'poliklinik_id' => $poliGigi->id, 'is_active' => true]
        );
        Tindakan::query()->updateOrCreate(
            ['kode' => 'TDK-KP-04'],
            ['kode_icd9' => '23.2', 'nama' => 'Restorasi Komposit Sinar (Light-Cured)', 'kategori' => 'medis', 'can_delegate_to_nurse' => false, 'poliklinik_id' => $poliGigi->id, 'is_active' => true]
        );

        foreach ([
            ['kode' => 'J06.9', 'nama' => 'Infeksi saluran napas atas akut, tidak spesifik'],
            ['kode' => 'I10', 'nama' => 'Hipertensi esensial (primer)'],
            ['kode' => 'K30', 'nama' => 'Dispepsia fungsional'],
            ['kode' => 'L23.9', 'nama' => 'Dermatitis kontak alergi, penyebab tidak spesifik'],
            ['kode' => 'J45.9', 'nama' => 'Asma bronkial, tidak spesifik'],
            ['kode' => 'K04.0', 'nama' => 'Pulpitis'],
            ['kode' => 'K02.1', 'nama' => 'Karies dentin'],
            ['kode' => 'K05.1', 'nama' => 'Gingivitis kronis'],
        ] as $icd) {
            Icd10::query()->updateOrCreate(['kode' => $icd['kode']], ['nama' => $icd['nama']]);
        }

        foreach ([
            ['kode' => 'KP-SKS', 'nama' => 'Surat Istirahat Sakit Kemenpar', 'jenis' => 'sakit', 'prefix' => 'SKS', 'format_nomor' => '[prefix]/[urut]/KP/[bulan]/[tahun]', 'isi' => 'Mohon diberikan istirahat sakit sesuai tanggal di atas dan evaluasi ulang bila keluhan belum membaik.'],
            ['kode' => 'KP-SKD', 'nama' => 'Surat Keterangan Kesehatan Kemenpar', 'jenis' => 'sehat', 'prefix' => 'SKD', 'format_nomor' => '[prefix]/[urut]/KP/[bulan]/[tahun]', 'isi' => 'Telah dilakukan pemeriksaan kesehatan fisik di Klinik Pratama Kementerian Pariwisata.'],
            ['kode' => 'KP-SRE', 'nama' => 'Surat Rujukan Fasilitas Kesehatan Lanjutan', 'jenis' => 'rujukan', 'prefix' => 'SRJ', 'format_nomor' => '[prefix]/[urut]/KP/[bulan]/[tahun]', 'isi' => 'Mohon evaluasi dan tata laksana spesialistik lebih lanjut atas sejawat/pasien tersebut.'],
            ['kode' => 'KP-RSM', 'nama' => 'Resume Medis Rawat Jalan (PMK 24/2022)', 'jenis' => 'ringkasan', 'prefix' => 'RSM', 'format_nomor' => '[prefix]/[urut]/KP/[bulan]/[tahun]', 'isi' => 'Ringkasan pelayanan rawat jalan diterbitkan sesuai rekam medis elektronik yang telah ditandatangani dokter.'],
        ] as $tpl) {
            CorrespondenceTemplate::query()->firstOrCreate(
                ['kode' => $tpl['kode']],
                [...$tpl, 'nomor_berikutnya' => 1, 'is_active' => true]
            );
        }

        // 7. Direktori Kepesertaan Kemenpar & Pasien Demo Realistis
        $directorySpecs = [
            [
                'key' => 'pns_sekretariat',
                'rm' => 'RM-KP-0001',
                'nama' => 'Budi Santoso, S.Sos., M.Si.',
                'nik' => '3171011403840011',
                'nip' => '198403142009121002',
                'kategori' => 'asn',
                'jenis_lainnya' => null,
                'unit_kerja' => 'Sekretariat Kementerian',
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '1984-03-14',
                'jenis_kelamin' => 'L',
                'golongan_darah' => 'O',
                'agama' => 'Islam',
                'telepon' => '081210002001',
                'alamat' => 'Jl. Cempaka Putih Tengah No. 14, Jakarta Pusat',
                'riwayat_alergi' => 'Tidak ada alergi obat',
            ],
            [
                'key' => 'pppk_pemasaran',
                'rm' => 'RM-KP-0002',
                'nama' => 'Ayu Kartika Sari, S.Par.',
                'nik' => '3174055807930012',
                'nip' => '199307182023212014',
                'kategori' => 'pppk',
                'jenis_lainnya' => null,
                'unit_kerja' => 'Deputi Bidang Pemasaran',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '1993-07-18',
                'jenis_kelamin' => 'P',
                'golongan_darah' => 'A',
                'agama' => 'Islam',
                'telepon' => '081210002002',
                'alamat' => 'Jl. Tebet Timur Dalam III No. 8, Jakarta Selatan',
                'riwayat_alergi' => 'Alergi Penisilin / Amoxicillin (urtikaria & sesak ringan)',
            ],
            [
                'key' => 'pns_destinasi',
                'rm' => 'RM-KP-0003',
                'nama' => 'Dimas Prasetyo, S.T., M.Sc.',
                'nik' => '3173022211880013',
                'nip' => '198811222014021001',
                'kategori' => 'asn',
                'jenis_lainnya' => null,
                'unit_kerja' => 'Deputi Bidang Pengembangan Destinasi dan Infrastruktur',
                'tempat_lahir' => 'Yogyakarta',
                'tanggal_lahir' => '1988-11-22',
                'jenis_kelamin' => 'L',
                'golongan_darah' => 'B',
                'agama' => 'Islam',
                'telepon' => '081210002003',
                'alamat' => 'Jl. Kemanggisan Utama No. 21, Jakarta Barat',
                'riwayat_alergi' => 'Tidak ada alergi obat',
            ],
            [
                'key' => 'penunjang_inspektorat',
                'rm' => 'RM-KP-0004',
                'nama' => 'Rizky Ramadhan',
                'nik' => '3172041905950014',
                'nip' => null,
                'kategori' => 'lainnya',
                'jenis_lainnya' => 'tenaga_penunjang',
                'unit_kerja' => 'Inspektorat Utama',
                'tempat_lahir' => 'Bogor',
                'tanggal_lahir' => '1995-05-19',
                'jenis_kelamin' => 'L',
                'golongan_darah' => 'AB',
                'agama' => 'Islam',
                'telepon' => '081210002004',
                'alamat' => 'Jl. Rawamangun Muka No. 9, Jakarta Timur',
                'riwayat_alergi' => 'Tidak ada alergi obat',
            ],
            [
                'key' => 'pns_investasi',
                'rm' => 'RM-KP-0005',
                'nama' => 'Clara Nathania, S.E., M.M.',
                'nik' => '3175084902890015',
                'nip' => '198902092015032004',
                'kategori' => 'asn',
                'jenis_lainnya' => null,
                'unit_kerja' => 'Deputi Bidang Industri dan Investasi',
                'tempat_lahir' => 'Surabaya',
                'tanggal_lahir' => '1989-02-09',
                'jenis_kelamin' => 'P',
                'golongan_darah' => 'O',
                'agama' => 'Kristen',
                'telepon' => '081210002005',
                'alamat' => 'Jl. Kelapa Gading Boulevard Blok QA-3, Jakarta Utara',
                'riwayat_alergi' => 'Tidak ada alergi obat',
            ],
            [
                'key' => 'pns_sdm',
                'rm' => 'RM-KP-0006',
                'nama' => 'Hendra Gunawan, S.H., M.H.',
                'nik' => '3171071009810016',
                'nip' => '198109102006041003',
                'kategori' => 'asn',
                'jenis_lainnya' => null,
                'unit_kerja' => 'Deputi Bidang Sumber Daya dan Kelembagaan',
                'tempat_lahir' => 'Semarang',
                'tanggal_lahir' => '1981-09-10',
                'jenis_kelamin' => 'L',
                'golongan_darah' => 'A',
                'agama' => 'Islam',
                'telepon' => '081210002006',
                'alamat' => 'Jl. Bendungan Hilir Raya No. 45, Jakarta Pusat',
                'riwayat_alergi' => 'Tidak ada alergi obat',
            ],
        ];

        $patients = [];
        foreach ($directorySpecs as $item) {
            $member = Kepesertaan::query()->updateOrCreate(
                ['nik' => $item['nik']],
                [
                    'nama' => $item['nama'],
                    'nip' => $item['nip'],
                    'kategori' => $item['kategori'],
                    'jenis_lainnya' => $item['jenis_lainnya'],
                    'status_kepegawaian' => 'aktif',
                    'unit_kerja' => $item['unit_kerja'],
                    'tempat_lahir' => $item['tempat_lahir'],
                    'tanggal_lahir' => $item['tanggal_lahir'],
                    'jenis_kelamin' => $item['jenis_kelamin'],
                    'golongan_darah' => $item['golongan_darah'],
                    'agama' => $item['agama'],
                    'alamat' => $item['alamat'],
                    'hak_layanan' => true,
                    'referensi_bukti' => 'Daftar Nominatif Pegawai Kementerian Pariwisata TA 2026',
                    'verified_by' => $users['admin']->id,
                    'verified_at' => now(),
                ]
            );

            $patient = Pasien::withTrashed()->updateOrCreate(
                ['no_rm' => $item['rm']],
                [
                    'kepesertaan_id' => $member->id,
                    'nik' => $item['nik'],
                    'nip' => $item['nip'],
                    'nama' => $item['nama'],
                    'tempat_lahir' => $item['tempat_lahir'],
                    'tanggal_lahir' => $item['tanggal_lahir'],
                    'jenis_kelamin' => $item['jenis_kelamin'],
                    'golongan_darah' => $item['golongan_darah'],
                    'agama' => $item['agama'],
                    'unit_kerja' => $item['unit_kerja'],
                    'telepon' => $item['telepon'],
                    'alamat' => $item['alamat'],
                    'riwayat_alergi' => $item['riwayat_alergi'],
                ]
            );
            $patients[$item['key']] = $patient;
        }

        // Pasien Non-Direktori (Tamu Dinas / Keluarga Pegawai via Pendaftaran Baru)
        $patients['tamu_dinas'] = Pasien::withTrashed()->updateOrCreate(
            ['no_rm' => 'RM-KP-0007'],
            [
                'kepesertaan_id' => null,
                'nik' => '5171022404900017',
                'nip' => null,
                'nama' => 'I Made Wirawan, S.STP.',
                'tempat_lahir' => 'Denpasar',
                'tanggal_lahir' => '1990-04-24',
                'jenis_kelamin' => 'L',
                'golongan_darah' => 'O',
                'agama' => 'Hindu',
                'unit_kerja' => 'Tamu Dinas Kementerian Pariwisata (Dispar Prov. Bali)',
                'telepon' => '081210002007',
                'alamat' => 'Jl. Raya Puputan Renon, Denpasar, Bali',
                'riwayat_alergi' => 'Tidak ada alergi obat',
            ]
        );

        // Sinkronisasi Alergi Terstruktur (FHIR AllergyIntolerance-ready) untuk Ayu Kartika Sari
        foreach (['Penisilin', 'Amoxicillin'] as $allergen) {
            PatientAllergy::query()->updateOrCreate(
                [
                    'pasien_id' => $patients['pppk_pemasaran']->id,
                    'allergen_display' => $allergen,
                ],
                [
                    'recorded_by_user_id' => $users['perawat_umum']->id,
                    'category' => 'medication',
                    'criticality' => 'high',
                    'reaction_text' => 'Urtikaria menyeluruh dan sesak napas ringan',
                    'clinical_status' => 'active',
                    'verification_status' => 'confirmed',
                ]
            );
        }

        // 8. Skenario Kunjungan Aktif & Selesai Hari Ini untuk Demo Langsung Seluruh Role
        $dateTag = today()->format('Ymd');
        $request = Request::create('/');
        $request->setUserResolver(fn () => $users['pendaftaran']);

        $ensureVisit = function (string $seq, Pasien $patient, Poliklinik $clinic, RuangPoli $room, Nakes $doctor, string $status, int $queueNo, string $note) use ($dateTag, $request, $users): Kunjungan {
            $visitNo = "KNJ-KP-{$dateTag}-{$seq}";
            $visit = Kunjungan::query()->firstOrNew(['no_kunjungan' => $visitNo]);
            $isNew = ! $visit->exists;
            $entitlement = app(HakLayananVerifier::class)->patient($patient, $request);
            $visit->fill([
                ...$entitlement,
                'no_antrian' => $queueNo,
                'pasien_id' => $patient->id,
                'poliklinik_id' => $clinic->id,
                'ruang_poli_id' => $room->id,
                'dokter_id' => $doctor->id,
                'registered_by' => $users['pendaftaran']->id,
                'tanggal' => today(),
                'status' => $isNew ? $status : $visit->status,
                'jenis_pasien' => 'lama',
                'catatan' => $note,
                'arrived_at' => $visit->arrived_at ?? now()->subMinutes(45),
            ]);
            $visit->save();

            // Pastikan General Consent aktif & valid secara kriptografis
            if (! $visit->general_consent_id) {
                $existingConsent = GeneralConsent::query()->where('pasien_id', $patient->id)->latest('id')->first();
                if (! $existingConsent || ! app(GeneralConsentService::class)->verifies($existingConsent)) {
                    $recordedAt = now()->subMinutes(40)->startOfSecond();
                    $reqId = (string) Str::uuid();
                    $existingConsent = new GeneralConsent([
                        'pasien_id' => $patient->id,
                        'kunjungan_id' => $visit->id,
                        'request_id' => $reqId,
                        'request_hash' => hash('sha256', $reqId),
                        'decision' => 'accepted',
                        'satusehat_sharing_decision' => 'opt_in',
                        'text_version' => GeneralConsentService::VERSION,
                        'text_snapshot' => GeneralConsentService::TEXT,
                        'signer_name' => $patient->nama,
                        'signer_relationship' => 'patient',
                        'recorded_by' => $users['pendaftaran']->id,
                        'recorded_at' => $recordedAt,
                        'signature_token' => (string) Str::uuid(),
                    ]);
                    $existingConsent->signature_hash = app(GeneralConsentService::class)->signature($existingConsent, (string) config('app.key'));
                    $existingConsent->save();
                }
                $visit->forceFill(['general_consent_id' => $existingConsent->id])->save();
            }

            return $visit->fresh();
        };

        // Skenario 1: Antrean Pengkajian Perawat Poli Umum (status: menunggu)
        $ensureVisit('0101', $patients['pns_sekretariat'], $poliUmum, $ruangUmum, $dokterUmum, 'menunggu', 1, 'Keluhan sakit kepala tegang & kontrol tekanan darah');

        // Skenario 2: Siap Diperiksa Dokter Umum — Pasien Hamil 16 Minggu + Alergi Penisilin! (status: pemeriksaan)
        $visitUmumExam = $ensureVisit('0102', $patients['pppk_pemasaran'], $poliUmum, $ruangUmum, $dokterUmum, 'pemeriksaan', 2, 'Batuk nyeri menelan sejak 3 hari (Hamil 16 minggu · Alergi Penisilin)');
        if (! $visitUmumExam->screening) {
            Screening::query()->create([
                'kunjungan_id' => $visitUmumExam->id,
                'petugas_id' => $perawatUmum->id,
                'recorded_by_user_id' => $users['perawat_umum']->id,
                'started_at' => now()->subMinutes(30),
                'submitted_at' => now()->subMinutes(22),
                'last_submitted_at' => now()->subMinutes(22),
                'submitted_by_user_id' => $users['perawat_umum']->id,
                'submitted_petugas_id' => $perawatUmum->id,
                'vitals_measured_at' => now()->subMinutes(25),
                'triaged_at' => now()->subMinutes(24),
                'keluhan' => 'Nyeri menelan, batuk berdahak ringan, dan meriang sejak 3 hari lalu',
                'lama_keluhan' => '3 hari',
                'sumber_informasi' => 'pasien',
                'td_sistole' => 116,
                'td_diastole' => 74,
                'nadi' => 86,
                'suhu' => 37.8,
                'respirasi' => 18,
                'spo2' => 99,
                'berat_badan' => 58.5,
                'tinggi_badan' => 160,
                'status_kehamilan' => 'hamil',
                'usia_kehamilan_minggu' => 16,
                'status_menyusui' => 'tidak',
                'status_alergi' => 'ada',
                'riwayat_alergi' => 'Alergi Penisilin / Amoxicillin (urtikaria & sesak ringan)',
                'alergi_jenis' => 'Obat antibiotik Penisilin',
                'alergi_reaksi' => 'Urtikaria dan sesak ringan',
                'alergi_keparahan' => 'sedang',
                'status_riwayat_penyakit' => 'tidak_ada',
                'skala_nyeri' => 3,
                'nyeri_dada' => 'tidak',
                'kejang' => 'tidak',
                'nadi_teraba' => 'teraba',
                'pola_pernapasan' => 'normal',
                'kesadaran' => 'sadar',
                'kondisi_psikiatri' => 'normal',
                'risiko_jatuh_visual' => 'rendah',
                'kesimpulan_triase' => 'kuning',
                'triase_perawat' => 'kuning',
                'prioritas_layanan' => 'segera',
                'signature_path_snapshot' => $users['perawat_umum']->signature_path,
            ]);
        }

        // Skenario 3: Siap Diperiksa Dokter Gigi — Pengkajian Gigi sudah dikirim! (status: pemeriksaan)
        $visitGigiExam = $ensureVisit('0201', $patients['pns_destinasi'], $poliGigi, $ruangGigi, $dokterGigi, 'pemeriksaan', 1, 'Ngilu tajam pada gigi geraham kanan bawah saat minum dingin');
        if (! $visitGigiExam->screening) {
            Screening::query()->create([
                'kunjungan_id' => $visitGigiExam->id,
                'petugas_id' => $perawatGigi->id,
                'recorded_by_user_id' => $users['perawat_gigi']->id,
                'started_at' => now()->subMinutes(28),
                'submitted_at' => now()->subMinutes(18),
                'last_submitted_at' => now()->subMinutes(18),
                'submitted_by_user_id' => $users['perawat_gigi']->id,
                'submitted_petugas_id' => $perawatGigi->id,
                'vitals_measured_at' => now()->subMinutes(22),
                'triaged_at' => now()->subMinutes(20),
                'keluhan' => 'Gigi geraham kanan bawah berlubang dan ngilu berdenyut saat minum dingin sejak 4 hari',
                'lama_keluhan' => '4 hari',
                'sumber_informasi' => 'pasien',
                'td_sistole' => 122,
                'td_diastole' => 78,
                'nadi' => 78,
                'suhu' => 36.6,
                'respirasi' => 18,
                'spo2' => 99,
                'berat_badan' => 72,
                'tinggi_badan' => 174,
                'status_alergi' => 'tidak_ada',
                'riwayat_alergi' => 'Tidak ada alergi',
                'status_riwayat_penyakit' => 'tidak_ada',
                'skala_nyeri' => 5,
                'lokasi_nyeri_gigi' => 'Regio 46 kanan bawah',
                'pemicu_nyeri_gigi' => ['dingin', 'manis', 'mengunyah'],
                'durasi_keluhan_gigi' => 'Nyeri tajam 1-2 menit setelah rangsang dingin',
                'extraoral_symmetry' => 'symmetric',
                'extraoral_swelling' => 'none',
                'dental_red_flags' => ['none'],
                'mukosa_mulut' => ['lidah' => 'normal', 'pipi' => 'normal', 'bibir' => 'normal', 'palatum' => 'normal', 'gusi' => 'normal'],
                'nyeri_dada' => 'tidak',
                'kejang' => 'tidak',
                'nadi_teraba' => 'teraba',
                'pola_pernapasan' => 'normal',
                'kesadaran' => 'sadar',
                'kondisi_psikiatri' => 'normal',
                'risiko_jatuh_visual' => 'rendah',
                'kesimpulan_triase' => 'hijau',
                'triase_perawat' => 'hijau',
                'prioritas_layanan' => 'normal',
                'signature_path_snapshot' => $users['perawat_gigi']->signature_path,
            ]);
        }

        // Skenario 4: Antrean Pelaksanaan Tindakan Perawat (status: tindakan)
        $visitNurseProc = $ensureVisit('0103', $patients['penunjang_inspektorat'], $poliUmum, $ruangUmum, $dokterUmum, 'tindakan', 3, 'Sesak napas ringan riwayat asma bronkial');
        if (! $visitNurseProc->pemeriksaan) {
            Screening::query()->firstOrCreate(
                ['kunjungan_id' => $visitNurseProc->id],
                [
                    'petugas_id' => $perawatUmum->id,
                    'recorded_by_user_id' => $users['perawat_umum']->id,
                    'started_at' => now()->subMinutes(50),
                    'submitted_at' => now()->subMinutes(42),
                    'last_submitted_at' => now()->subMinutes(42),
                    'submitted_by_user_id' => $users['perawat_umum']->id,
                    'submitted_petugas_id' => $perawatUmum->id,
                    'vitals_measured_at' => now()->subMinutes(46),
                    'triaged_at' => now()->subMinutes(44),
                    'keluhan' => 'Napas terasa berat dan mengi sejak pagi hari setelah terpapar debu arsip',
                    'lama_keluhan' => '5 jam',
                    'sumber_informasi' => 'pasien',
                    'td_sistole' => 124, 'td_diastole' => 80, 'nadi' => 96, 'suhu' => 36.7, 'respirasi' => 22, 'spo2' => 96,
                    'berat_badan' => 66, 'tinggi_badan' => 170,
                    'status_alergi' => 'ada', 'riwayat_alergi' => 'Alergi debu rumah (bukan obat)',
                    'kesimpulan_triase' => 'kuning', 'triase_perawat' => 'kuning', 'prioritas_layanan' => 'segera',
                    'signature_path_snapshot' => $users['perawat_umum']->signature_path,
                ]
            );
            $examProc = Pemeriksaan::query()->create([
                'kunjungan_id' => $visitNurseProc->id,
                'dokter_id' => $dokterUmum->id,
                'started_at' => now()->subMinutes(38),
                'anamnesis' => 'Mengi ringan sejak pagi pasca paparan debu ruang arsip. Riwayat asma intermiten (+).',
                'pemeriksaan_fisik' => 'Thoraks: vesikuler (+/+), wheezing ekspirasi minimal (+/+), retraksi (-).',
                'edukasi' => 'Gunakan masker saat di ruang arsip, evaluasi ulang saturasi oksigen 15 menit pasca nebulisasi.',
                'non_farmakologis' => 'Posisi semi-Fowler dan observasi jalan napas di ruang tindakan.',
                'status' => 'selesai',
                'signed_at' => now()->subMinutes(30),
                'signed_by_user_id' => $users['dokter_umum']->id,
                'signature_path_snapshot' => $users['dokter_umum']->signature_path,
            ]);
            Diagnosa::query()->create([
                'pemeriksaan_id' => $examProc->id,
                'kode_icd10' => 'J45.9',
                'code_system' => 'icd10_who',
                'coding_status' => 'verified',
                'nama_diagnosa' => 'Asma bronkial, tidak spesifik',
                'jenis' => 'utama',
            ]);
            TindakanKunjungan::query()->create([
                'kunjungan_id' => $visitNurseProc->id,
                'tindakan_id' => $tindakanNebul->id,
                'dokter_id' => $dokterUmum->id,
                'kode_icd9cm' => '93.94',
                'code_system' => 'icd9cm_procedure',
                'coding_status' => 'verified',
                'nama_tindakan' => $tindakanNebul->nama,
                'jumlah' => 1,
                'execution_role' => 'perawat',
                'execution_status' => 'pending',
                'catatan' => 'Nebulisasi bronkodilator selama 10-15 menit, evaluasi SpO2 dan wheezing pasca tindakan.',
            ]);
            app(ClinicalNoteRecorder::class)->recordFinal($examProc, app(ClinicalNoteRecorder::class)->snapshot($visitNurseProc->fresh(), $examProc), $users['dokter_umum']->id);
        }

        // Skenario 5: Antrean Farmasi & Review Apoteker — Resep Obat Dalam (Putih) + Obat Luar (Biru)! (status: farmasi)
        $visitPharmacy = $ensureVisit('0104', $patients['pns_investasi'], $poliUmum, $ruangUmum, $dokterUmum, 'farmasi', 4, 'Ruam kemerahan gatal di punggung tangan sejak 2 hari');
        if (! $visitPharmacy->pemeriksaan) {
            Screening::query()->firstOrCreate(
                ['kunjungan_id' => $visitPharmacy->id],
                [
                    'petugas_id' => $perawatUmum->id,
                    'recorded_by_user_id' => $users['perawat_umum']->id,
                    'started_at' => now()->subMinutes(55),
                    'submitted_at' => now()->subMinutes(48),
                    'last_submitted_at' => now()->subMinutes(48),
                    'submitted_by_user_id' => $users['perawat_umum']->id,
                    'submitted_petugas_id' => $perawatUmum->id,
                    'vitals_measured_at' => now()->subMinutes(52),
                    'triaged_at' => now()->subMinutes(50),
                    'keluhan' => 'Bercak kemerahan terasa gatal pada punggung kedua tangan setelah kontak cairan pembersih',
                    'lama_keluhan' => '2 hari',
                    'sumber_informasi' => 'pasien',
                    'td_sistole' => 118, 'td_diastole' => 76, 'nadi' => 76, 'suhu' => 36.5, 'respirasi' => 18, 'spo2' => 99,
                    'berat_badan' => 56, 'tinggi_badan' => 162,
                    'status_kehamilan' => 'tidak_hamil', 'status_menyusui' => 'tidak',
                    'status_alergi' => 'tidak_ada', 'riwayat_alergi' => 'Tidak ada alergi obat',
                    'kesimpulan_triase' => 'hijau', 'triase_perawat' => 'hijau', 'prioritas_layanan' => 'normal',
                    'signature_path_snapshot' => $users['perawat_umum']->signature_path,
                ]
            );
            $examPharm = Pemeriksaan::query()->create([
                'kunjungan_id' => $visitPharmacy->id,
                'dokter_id' => $dokterUmum->id,
                'started_at' => now()->subMinutes(44),
                'anamnesis' => 'Makula eritematosa berbatas tegas disertai pruritus di dorsum manus bilateral pasca kontak deterjen baru.',
                'pemeriksaan_fisik' => 'Status dermatologis dorsum manus: plak eritematosa ringan, skuama tipis (+), tanda infeksi sekunder (-).',
                'edukasi' => 'Hindari kontak langsung dengan deterjen iritan, gunakan pelembap setelah mencuci tangan.',
                'non_farmakologis' => 'Kompres sejuk bila gatal memberat.',
                'kondisi_akhir' => 'Keadaan umum baik, keluhan teratasi sebagian',
                'disposisi' => 'pulang',
                'status' => 'selesai',
                'signed_at' => now()->subMinutes(35),
                'signed_by_user_id' => $users['dokter_umum']->id,
                'signature_path_snapshot' => $users['dokter_umum']->signature_path,
            ]);
            Diagnosa::query()->create([
                'pemeriksaan_id' => $examPharm->id,
                'kode_icd10' => 'L23.9',
                'code_system' => 'icd10_who',
                'coding_status' => 'verified',
                'nama_diagnosa' => 'Dermatitis kontak alergi, penyebab tidak spesifik',
                'jenis' => 'utama',
            ]);
            $rxPharm = Resep::query()->create([
                'no_resep' => "RSP-KP-{$dateTag}-0104",
                'kunjungan_id' => $visitPharmacy->id,
                'dokter_id' => $dokterUmum->id,
                'status' => 'menunggu',
            ]);
            ResepObat::query()->create([
                'resep_id' => $rxPharm->id,
                'obat_id' => $medicines['KP-OBT-07']->id,
                'nama_obat' => $medicines['KP-OBT-07']->nama,
                'jumlah' => 10,
                'satuan' => 'Tablet',
                'rute_pemberian' => 'oral',
                'aturan_pakai' => '1 x 1 tablet malam hari sesudah makan',
                'jenis' => 'jadi',
                'is_resep_luar' => false,
            ]);
            ResepObat::query()->create([
                'resep_id' => $rxPharm->id,
                'obat_id' => $medicines['KP-OBT-10']->id,
                'nama_obat' => $medicines['KP-OBT-10']->nama,
                'jumlah' => 1,
                'satuan' => 'Tube',
                'rute_pemberian' => 'topikal',
                'aturan_pakai' => '2 x sehari oles tipis pada area ruam kulit (Obat Luar)',
                'jenis' => 'jadi',
                'is_resep_luar' => false,
            ]);
            app(ClinicalNoteRecorder::class)->recordFinal($examPharm, app(ClinicalNoteRecorder::class)->snapshot($visitPharmacy->fresh(), $examPharm), $users['dokter_umum']->id);
        }

        // Skenario 6: Kunjungan Selesai Lengkap + RME Final + Farmasi Selesai + Surat Sakit & Resume Medis ber-QR! (status: selesai)
        $visitDone = $ensureVisit('0105', $patients['pns_sdm'], $poliUmum, $ruangUmum, $dokterUmum, 'selesai', 5, 'Demam akut, nyeri ulu hati, dan lemas sejak 2 hari');
        if (! $visitDone->pemeriksaan) {
            Screening::query()->firstOrCreate(
                ['kunjungan_id' => $visitDone->id],
                [
                    'petugas_id' => $perawatUmum->id,
                    'recorded_by_user_id' => $users['perawat_umum']->id,
                    'started_at' => now()->subMinutes(90),
                    'submitted_at' => now()->subMinutes(82),
                    'last_submitted_at' => now()->subMinutes(82),
                    'submitted_by_user_id' => $users['perawat_umum']->id,
                    'submitted_petugas_id' => $perawatUmum->id,
                    'vitals_measured_at' => now()->subMinutes(86),
                    'triaged_at' => now()->subMinutes(84),
                    'keluhan' => 'Demam naik turun, mual, nyeri ulu hati, dan pegal seluruh badan sejak 2 hari',
                    'lama_keluhan' => '2 hari',
                    'sumber_informasi' => 'pasien',
                    'td_sistole' => 120, 'td_diastole' => 78, 'nadi' => 88, 'suhu' => 38.1, 'respirasi' => 18, 'spo2' => 99,
                    'berat_badan' => 70, 'tinggi_badan' => 172,
                    'status_alergi' => 'tidak_ada', 'riwayat_alergi' => 'Tidak ada alergi obat',
                    'kesimpulan_triase' => 'hijau', 'triase_perawat' => 'hijau', 'prioritas_layanan' => 'normal',
                    'signature_path_snapshot' => $users['perawat_umum']->signature_path,
                ]
            );
            $examDone = Pemeriksaan::query()->create([
                'kunjungan_id' => $visitDone->id,
                'dokter_id' => $dokterUmum->id,
                'started_at' => now()->subMinutes(78),
                'anamnesis' => 'Febris hari ke-2 disertai odinofagia ringan dan nyeri tekan epigastrium minimal.',
                'pemeriksaan_fisik' => 'KU tampak sakit ringan, kompos mentis. Faring hiperemis (+), tonsil T1-T1. Abdomen supel, nyeri tekan epigastrium ringan (+).',
                'edukasi' => 'Istirahat cukup 2 hari, minum air putih minimal 2 liter/hari, makan porsi kecil tapi sering, hindari kopi dan makanan pedas.',
                'non_farmakologis' => 'Istirahat rumah 2 hari dan kompres hangat bila demam.',
                'kondisi_akhir' => 'Stabil, sadar penuh, rawat jalan dengan obat oral',
                'disposisi' => 'pulang',
                'kontrol_berikutnya' => today()->addDays(3)->toDateString(),
                'status' => 'selesai',
                'signed_at' => now()->subMinutes(65),
                'signed_by_user_id' => $users['dokter_umum']->id,
                'signature_path_snapshot' => $users['dokter_umum']->signature_path,
            ]);
            Diagnosa::query()->create([
                'pemeriksaan_id' => $examDone->id,
                'kode_icd10' => 'J06.9',
                'code_system' => 'icd10_who',
                'coding_status' => 'verified',
                'nama_diagnosa' => 'Infeksi saluran napas atas akut, tidak spesifik',
                'jenis' => 'utama',
            ]);
            Diagnosa::query()->create([
                'pemeriksaan_id' => $examDone->id,
                'kode_icd10' => 'K30',
                'code_system' => 'icd10_who',
                'coding_status' => 'verified',
                'nama_diagnosa' => 'Dispepsia fungsional',
                'jenis' => 'tambahan',
            ]);
            $rxDone = Resep::query()->create([
                'no_resep' => "RSP-KP-{$dateTag}-0105",
                'kunjungan_id' => $visitDone->id,
                'dokter_id' => $dokterUmum->id,
                'status' => 'selesai',
            ]);
            $itemPcm = ResepObat::query()->create([
                'resep_id' => $rxDone->id,
                'obat_id' => $medicines['KP-OBT-01']->id,
                'nama_obat' => $medicines['KP-OBT-01']->nama,
                'jumlah' => 10,
                'satuan' => 'Tablet',
                'rute_pemberian' => 'oral',
                'aturan_pakai' => '3 x 1 tablet sesudah makan bila suhu ≥ 37,5°C',
                'jenis' => 'jadi',
                'stok_dikurangi' => true,
            ]);
            $itemOmp = ResepObat::query()->create([
                'resep_id' => $rxDone->id,
                'obat_id' => $medicines['KP-OBT-08']->id,
                'nama_obat' => $medicines['KP-OBT-08']->nama,
                'jumlah' => 10,
                'satuan' => 'Kapsul',
                'rute_pemberian' => 'oral',
                'aturan_pakai' => '2 x 1 kapsul 30 menit sebelum makan',
                'jenis' => 'jadi',
                'stok_dikurangi' => true,
            ]);
            app(ClinicalNoteRecorder::class)->recordFinal($examDone, app(ClinicalNoteRecorder::class)->snapshot($visitDone->fresh(), $examDone), $users['dokter_umum']->id);

            $farmasiDone = Farmasi::query()->create([
                'kunjungan_id' => $visitDone->id,
                'resep_id' => $rxDone->id,
                'petugas_id' => $apotekerUmum->id,
                'status' => 'selesai',
                'prepared_at' => now()->subMinutes(58),
                'dispensed_by' => $users['apoteker_umum']->id,
                'dispensed_at' => now()->subMinutes(50),
                'catatan' => 'Obat telah diserahkan lengkap disertai edukasi cara minum sebelum dan sesudah makan.',
            ]);
            foreach ([[$itemPcm, $medicines['KP-OBT-01'], 10], [$itemOmp, $medicines['KP-OBT-08'], 10]] as [$rxRow, $medModel, $qty]) {
                $fItem = FarmasiItem::query()->create([
                    'farmasi_id' => $farmasiDone->id,
                    'resep_obat_id' => $rxRow->id,
                    'obat_id' => $medModel->id,
                    'jumlah_diberikan' => $qty,
                    'aturan_pakai' => $rxRow->aturan_pakai,
                ]);
                app(PersediaanRecorder::class)->issue($medModel->id, $qty, $users['apoteker_umum'], $visitDone, 'Farmasi', $farmasiDone->id, $fItem->id);
            }
            $visitDone->forceFill(['finished_at' => now()->subMinutes(50)])->save();

            // Terbitkan Surat Istirahat Sakit & Resume Medis Resmi Kemenpar dengan QR Verification
            $snapshot = app(MedicalLetterData::class)->snapshot($visitDone->fresh(), $dokterUmum, today());
            $suratSakit = SuratMedis::query()->create([
                'kunjungan_id' => $visitDone->id,
                'dokter_id' => $dokterUmum->id,
                'jenis' => 'sakit',
                'nomor_surat' => 'SKS/0001/KP/'.today()->format('m/Y'),
                'tanggal' => today(),
                'konten' => 'Dianjurkan beristirahat cukup dan kontrol kembali bila demam masih berlanjut.',
                'document_data' => $snapshot + [
                    'details' => [
                        'rest_start' => today()->toDateString(),
                        'rest_end' => today()->addDay()->toDateString(),
                    ],
                    'replacement' => null,
                ],
            ]);
            app(MedicalDocumentVerificationService::class)->issue($suratSakit, $users['dokter_umum']);

            $resumeMedis = SuratMedis::query()->create([
                'kunjungan_id' => $visitDone->id,
                'dokter_id' => $dokterUmum->id,
                'jenis' => 'ringkasan',
                'nomor_surat' => 'RSM/0001/KP/'.today()->format('m/Y'),
                'tanggal' => today(),
                'konten' => null,
                'document_data' => $snapshot + ['details' => [], 'replacement' => null],
            ]);
            app(MedicalDocumentVerificationService::class)->issue($resumeMedis, $users['dokter_umum']);
        }
    }
}
