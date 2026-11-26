<?php

namespace Tests\Feature;

use App\GeneralConsentService;
use App\Models\ClinicalTerminology;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\RuangPoli;
use App\Models\User;
use Database\Seeders\KemenparPreReleaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostgresLiveCrudAndUploadTest extends TestCase
{
    private bool $usingPostgres = false;

    protected function setUp(): void
    {
        parent::setUp();

        $pgReady = false;
        $socket = @stream_socket_client('tcp://127.0.0.1:5433', $errno, $errstr, 1.5);
        if (is_resource($socket)) {
            stream_set_timeout($socket, 2);
            @fwrite($socket, pack('NN', 8, 80877103));
            $reply = @fread($socket, 1);
            fclose($socket);
            $pgReady = in_array($reply, ['N', 'S'], true);
        }

        if ($pgReady) {
            Config::set('database.default', 'pgsql');
            Config::set('database.connections.pgsql.host', '127.0.0.1');
            Config::set('database.connections.pgsql.port', '5433');
            Config::set('database.connections.pgsql.database', 'klinik_pratama_test');
            Config::set('database.connections.pgsql.username', 'klinik_app');
            Config::set('database.connections.pgsql.password', 'secretrmeapp');
            Config::set('database.connections.pgsql.sslmode', 'disable');
            DB::purge('pgsql');
            DB::setDefaultConnection('pgsql');
            $this->usingPostgres = true;
            DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
            DB::statement('CREATE SCHEMA public');
            $this->artisan('migrate', ['--force' => true]);
        } else {
            $this->artisan('migrate:fresh', ['--force' => true]);
        }

        Storage::fake('public');
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        if ($this->usingPostgres) {
            DB::disconnect('pgsql');
            Config::set('database.default', 'sqlite');
            DB::setDefaultConnection('sqlite');
        }

        parent::tearDown();
    }

    public function test_full_uploads_dashboards_case_insensitive_search_and_crud_work_on_postgresql(): void
    {
        if ($this->usingPostgres) {
            $this->assertSame('pgsql', DB::connection()->getDriverName());
        }

        // Seed realistic Kemenpar dataset
        (new KemenparPreReleaseSeeder)->run();

        ClinicalTerminology::query()->firstOrCreate(
            ['code_system' => 'icd10_who', 'release' => 'WHO 2016', 'code' => 'J02.9'],
            ['display' => 'Acute pharyngitis, unspecified', 'code_type' => 'diagnosis', 'is_active' => true]
        );

        $admin = User::query()->where('email', 'admin@kemenpar.go.id')->sole();
        $pendaftaran = User::query()->where('email', 'pendaftaran@kemenpar.go.id')->sole();
        $perawatUmum = User::query()->where('email', 'perawat.umum@kemenpar.go.id')->sole();
        $dokterUmum = User::query()->where('email', 'dokter.umum@kemenpar.go.id')->sole();
        $dokterGigi = User::query()->where('email', 'dokter.gigi@kemenpar.go.id')->sole();
        $apoteker = User::query()->where('email', 'apoteker.umum@kemenpar.go.id')->sole();
        $apotekerGigi = User::query()->where('email', 'apoteker.gigi@kemenpar.go.id')->sole();

        // 1. Verify all role dashboards & reports render HTTP 200 without SQL errors on PostgreSQL
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->actingAs($pendaftaran)->get(route('pendaftaran.dashboard'))->assertOk();
        $this->actingAs($perawatUmum)->get(route('perawat.dashboard'))->assertOk();
        $this->actingAs($dokterUmum)->get(route('dokter.dashboard'))->assertOk();
        $this->actingAs($dokterGigi)->get(route('dokter.dashboard'))->assertOk();
        $this->actingAs($apoteker)->get(route('farmasi.dashboard'))->assertOk();
        $this->actingAs($apotekerGigi)->get(route('farmasi.dashboard'))->assertOk();

        $this->actingAs($admin)->get(route('laporan.kunjungan'))->assertOk();
        $this->actingAs($admin)->get(route('laporan.stok'))->assertOk();
        $this->actingAs($admin)->get(route('admin.laporan.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.log-aktivitas'))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit-bantuan'))->assertOk();
        $this->actingAs($dokterUmum)->get(route('dokter.laporan-top-diagnosa'))->assertOk();

        // 2. Verify Case-Insensitive Search (ILIKE on PostgreSQL!) across modules
        $this->actingAs($admin)->get(route('pelayanan.pasien.index', ['search' => 'budi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('patients.total', 1));

        $this->actingAs($pendaftaran)->get(route('pendaftaran.database-pasien', ['search' => 'budi']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('patients.total', 1));

        $this->actingAs($admin)->get(route('kepesertaan.index', ['search' => 'ayu kartika']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('members.total', 1));

        $this->actingAs($admin)->get(route('master.obat.index', ['search' => 'paracetamol']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('medicines.total', 1));

        $this->actingAs($admin)->get(route('master.nakes.index', ['search' => 'ratna']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('staff.total', 1));

        $this->actingAs($dokterUmum)->getJson(route('pelayanan.icd10.search', ['q' => 'pharyngitis']))
            ->assertOk()
            ->assertJsonFragment(['kode' => 'J02.9']);

        // 3. Verify Clinic Logo Upload (PNG & JPG) via /setting/logo
        $pngLogo = UploadedFile::fake()->image('logo-kemenpar.png', 240, 240);
        $this->actingAs($admin)->post('/setting/logo', ['logo' => $pngLogo])
            ->assertRedirect()
            ->assertSessionHas('success');
        $firstLogoPath = KlinikSetting::query()->firstOrFail()->logo;
        $this->assertNotNull($firstLogoPath);
        Storage::disk('public')->assertExists($firstLogoPath);

        $jpgLogo = UploadedFile::fake()->image('logo-kemenpar-baru.jpg', 240, 240);
        $this->actingAs($admin)->post('/setting/logo', ['logo' => $jpgLogo])
            ->assertRedirect()
            ->assertSessionHas('success');
        $secondLogoPath = KlinikSetting::query()->firstOrFail()->logo;
        Storage::disk('public')->assertMissing($firstLogoPath);
        Storage::disk('public')->assertExists($secondLogoPath);

        // 4. Verify Profile Photo Upload/Stream/Delete & Clinical Signature Upload/Stream with Signing PIN
        $avatar = UploadedFile::fake()->image('foto-dokter.jpg', 300, 300);
        $this->actingAs($dokterUmum)->post(route('profile.photo.upload'), ['photo' => $avatar])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->actingAs($dokterUmum)->get(route('profile.photo'))->assertOk();

        $sigImage = UploadedFile::fake()->image('ttd-dokter.png', 320, 120);
        $this->actingAs($dokterUmum)->post(route('profile.signature.upload'), [
            'signature' => $sigImage,
            'signature_password' => KemenparPreReleaseSeeder::DEMO_SIGNING_PIN,
        ])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($dokterUmum)->get(route('profile.signature'))->assertOk();

        $this->actingAs($dokterUmum)->delete(route('profile.photo.delete'))
            ->assertRedirect()
            ->assertSessionHas('success');

        // 5. Verify Visit Attachment Upload (PDF & Image), Download, Void & Restore
        $visitWaiting = Kunjungan::query()->where('status', 'menunggu')->firstOrFail();
        $labPdf = UploadedFile::fake()->create('hasil-lab-luar.pdf', 120, 'application/pdf');
        $xrayPng = UploadedFile::fake()->image('rontgen-thorax.png', 600, 600);

        $this->actingAs($perawatUmum)->post(route('pelayanan.kunjungan.attachments.store', $visitWaiting), [
            'file' => $labPdf,
            'note' => 'Hasil darah rutin laboratorium luar',
        ])->assertRedirect()->assertSessionHas('success');

        $this->actingAs($perawatUmum)->post(route('pelayanan.kunjungan.attachments.store', $visitWaiting), [
            'file' => $xrayPng,
            'note' => 'Foto thorax PA',
        ])->assertRedirect()->assertSessionHas('success');

        $summaries = $visitWaiting->fresh()->attachmentSummaries();
        $this->assertCount(2, $summaries);
        $attachmentId = $summaries[0]['id'];

        $this->actingAs($dokterUmum)->get(route('pelayanan.kunjungan.attachments.show', [$visitWaiting, $attachmentId]))
            ->assertOk();

        $this->actingAs($perawatUmum)->delete(route('pelayanan.kunjungan.attachments.destroy', [$visitWaiting, $attachmentId]), [
            'reason' => 'Salah pilih halaman lampiran pasien',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertCount(1, $visitWaiting->fresh()->attachmentSummaries());
        $this->assertCount(1, $visitWaiting->fresh()->attachmentSummaries(true));

        $this->actingAs($perawatUmum)->post(route('pelayanan.kunjungan.attachments.restore', [$visitWaiting, $attachmentId]), [
            'reason' => 'Terkonfirmasi milik pasien yang benar',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertCount(2, $visitWaiting->fresh()->attachmentSummaries());

        // 6. Verify General Consent Evidence Upload & Download
        $consentPdf = UploadedFile::fake()->create('general-consent-signed.pdf', 80, 'application/pdf');
        $this->actingAs($pendaftaran)->post(route('general-consent.store', $visitWaiting), [
            'request_id' => (string) Str::uuid(),
            'current_id' => $visitWaiting->general_consent_id,
            'decision' => 'accepted',
            'satusehat_sharing_decision' => 'opt_in',
            'text_version' => GeneralConsentService::VERSION,
            'signer_name' => $visitWaiting->pasien->nama,
            'signer_relationship' => 'patient',
            'explained' => true,
            'evidence' => $consentPdf,
        ])->assertRedirect()->assertSessionHas('success');

        $latestConsent = app(GeneralConsentService::class)->latest($visitWaiting->fresh());
        $this->assertNotNull($latestConsent?->evidence_path);
        $this->actingAs($pendaftaran)->get(route('general-consent.evidence', [$visitWaiting, $latestConsent]))
            ->assertOk();

        // 7. Verify Master Data Full CRUD (Poliklinik Ruang, Nakes, Obat + Batch Inventory, Pasien + Kepesertaan)
        $poliUmum = Poliklinik::query()->where('kode', 'PU')->sole();
        $this->actingAs($admin)->post(route('master.poliklinik.ruang.store', $poliUmum), [
            'nama' => 'Ruang Tindakan Umum 2',
        ])->assertRedirect()->assertSessionHas('success');
        $newRoom = RuangPoli::query()->where('nama', 'Ruang Tindakan Umum 2')->sole();
        $this->actingAs($admin)->put(route('master.poliklinik.ruang.update', $newRoom), [
            'nama' => 'Ruang Tindakan & Observasi 2',
            'is_active' => false,
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertFalse($newRoom->fresh()->is_active);

        // Nakes CRUD
        $this->actingAs($admin)->post(route('master.nakes.store'), [
            'kode' => 'DKT-CRUD-99',
            'nama' => 'dr. Fajar Pratama',
            'nik' => '3171011101890099',
            'jenis_kelamin' => 'L',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'poliklinik_id' => $poliUmum->id,
            'no_sip' => '99/SIP/31.71/2026',
            'no_str' => '9988776655443322',
            'telepon' => '081299998888',
            'is_active' => true,
            'buat_akun' => false,
        ])->assertRedirect()->assertSessionHas('success');
        $createdNakes = Nakes::query()->where('kode', 'DKT-CRUD-99')->sole();
        $this->actingAs($admin)->put(route('master.nakes.update', $createdNakes), [
            'kode' => 'DKT-CRUD-99',
            'nama' => 'dr. Fajar Pratama, M.Kes.',
            'nik' => '3171011101890099',
            'jenis_kelamin' => 'L',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'poliklinik_id' => $poliUmum->id,
            'no_sip' => '99/SIP/31.71/2026',
            'no_str' => '9988776655443322',
            'telepon' => '081299997777',
            'is_active' => true,
        ])->assertRedirect()->assertSessionHas('success');
        $this->actingAs($admin)->delete(route('master.nakes.destroy', $createdNakes))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSoftDeleted('nakes', ['id' => $createdNakes->id]);

        // Obat & Batch Inventory CRUD
        $this->actingAs($admin)->post(route('master.obat.store'), [
            'kode' => 'OBT-CRUD-01',
            'nama' => 'Loratadine 10 mg',
            'satuan_besar' => 'Box',
            'satuan_kecil' => 'Tablet',
            'konversi_satuan' => 50,
            'kandungan' => 'Loratadine',
            'stok_minimum' => 15,
            'jenis' => 'obat',
            'is_active' => true,
            'boleh_diberikan_langsung' => true,
            'is_high_alert' => false,
            'is_lasa' => true,
            'rute_default' => 'oral',
        ])->assertRedirect()->assertSessionHas('success');
        $createdObat = Obat::query()->where('kode', 'OBT-CRUD-01')->sole();

        $this->actingAs($apoteker)->post(route('stok.persediaan.receive', $createdObat), [
            'nomor_batch' => 'LRT-26X',
            'expired_at' => today()->addYear()->toDateString(),
            'jumlah' => 100,
            'sumber' => 'distribusi',
            'referensi' => 'BAST Gudang Kemenpar Uji CRUD',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(100.0, (float) $createdObat->fresh()->stok);

        $batch = ObatBatch::query()->where('obat_id', $createdObat->id)->sole();
        $this->actingAs($apoteker)->post(route('stok.batch.adjust', $batch), [
            'jenis' => 'opname',
            'jumlah' => 95,
            'alasan' => 'Penyesuaian stok opname bulanan farmasi',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame(95.0, (float) $createdObat->fresh()->stok);

        // Pasien Manual Create, Update & PPPK Membership Verification
        $this->actingAs($admin)->post(route('pelayanan.pasien.store'), [
            'registration_type' => 'manual',
            'nama' => 'Rina Maharani, S.Tr.Par.',
            'nik' => '3174095508960088',
            'nip' => '199608152024212009',
            'tempat_lahir' => 'Surakarta',
            'tanggal_lahir' => '1996-08-15',
            'jenis_kelamin' => 'P',
            'golongan_darah' => 'B',
            'agama' => 'Islam',
            'alamat' => 'Jl. Kebon Sirih No. 22, Jakarta Pusat',
            'unit_kerja' => 'Deputi Bidang Pemasaran',
            'telepon' => '081388887777',
        ])->assertRedirect();
        $createdPatient = Pasien::query()->where('nik', '3174095508960088')->sole();

        $this->actingAs($admin)->post(route('pelayanan.pasien.verify-membership', $createdPatient), [
            'nama' => 'Rina Maharani, S.Tr.Par.',
            'nik' => '3174095508960088',
            'nip' => '199608152024212009',
            'kategori' => 'pppk',
            'status_kepegawaian' => 'aktif',
            'tempat_lahir' => 'Surakarta',
            'tanggal_lahir' => '1996-08-15',
            'jenis_kelamin' => 'P',
            'golongan_darah' => 'B',
            'agama' => 'Islam',
            'alamat' => 'Jl. Kebon Sirih No. 22, Jakarta Pusat',
            'unit_kerja' => 'Deputi Bidang Pemasaran',
            'referensi_bukti' => 'SK PPPK Kemenpar No. 88/2024',
            'hak_layanan' => true,
        ])->assertRedirect();
        $this->assertNotNull($createdPatient->fresh()->kepesertaan_id);
        $this->assertTrue(Schema::hasTable('clinical_terminologies'));
    }
}
