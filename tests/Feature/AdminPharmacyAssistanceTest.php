<?php

namespace Tests\Feature;

use App\Models\ClinicalNoteVersion;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\OdontogramFinding;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminPharmacyAssistanceTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['umum', 2])]
    #[TestWith(['gigi', 1])]
    public function test_admin_dispensing_requires_reason_rolls_back_bad_stock_and_is_idempotent(string $type, int $quantity): void
    {
        [$doctor, , $visit] = $this->context($type);
        $medicine = Obat::create(['kode' => 'ADMIN-OB', 'nama' => 'Obat uji', 'satuan_kecil' => 'tablet', 'stok' => 10, 'jenis' => 'obat', 'is_active' => true]);
        $batch = ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => 10]);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.resep.store', $visit), ['obat_id' => $medicine->id, 'jumlah' => 2, 'jenis' => 'jadi', 'aturan_pakai' => 'Sesuai dokter'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasNoErrors();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $item = $visit->resep()->sole()->resepObat()->sole();
        $payload = ['items' => [['resep_obat_id' => $item->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => $quantity]], 'catatan' => $quantity < 2 ? 'Sisa obat ditindaklanjuti sesuai instruksi' : null];
        $this->actingAs($admin)->post(route('pelayanan.farmasi.store', $visit), $payload)->assertSessionHasErrors('assistance_reason');
        $this->post(route('pelayanan.farmasi.selesai', $visit), $payload)->assertSessionHasErrors('assistance_reason');
        $payload['assistance_reason'] = 'Akun farmasi sedang mengalami gangguan.';
        $this->post(route('pelayanan.farmasi.store', $visit), $payload)->assertSessionHasNoErrors();
        $batch->update(['expired_at' => today()]);
        $this->post(route('pelayanan.farmasi.selesai', $visit), $payload)->assertSessionHasErrors('items');
        $this->assertSame('farmasi', $visit->fresh()->status);
        $this->assertSame(10, (int) $medicine->fresh()->stok);
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'pharmacy.dispensed']);
        $batch->update(['expired_at' => today()->addYear()]);
        $this->post(route('pelayanan.farmasi.selesai', $visit), $payload)->assertSessionHasNoErrors();
        $this->post(route('pelayanan.farmasi.selesai', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertSame(10 - $quantity, (int) $medicine->fresh()->stok);
        $this->assertSame('selesai', $visit->fresh()->status);
        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'dispensed_by' => $admin->id]);
        $event = DB::table('clinical_audit_events')->where('action', 'pharmacy.dispensed')->sole();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame($payload['assistance_reason'], json_decode($event->metadata, true)['assistance_reason']);
    }

    public function test_admin_external_prescription_requires_signed_note_and_preserves_doctor_order(): void
    {
        [$doctor, , $visit] = $this->context();
        $medicine = Obat::create(['kode' => 'EMPTY-OB', 'nama' => 'Obat kosong', 'satuan_kecil' => 'tablet', 'stok' => 0, 'jenis' => 'obat', 'is_active' => true]);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.resep.store', $visit), ['obat_id' => $medicine->id, 'jumlah' => 2, 'jenis' => 'jadi', 'aturan_pakai' => 'Sesuai dokter'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasNoErrors();
        $item = $visit->resep()->sole()->resepObat()->sole();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $reason = ['assistance_reason' => 'Bantuan farmasi saat akun petugas terganggu.'];
        $this->actingAs($admin)->post(route('pelayanan.farmasi.resep-luar', [$visit, $item]), [])->assertSessionHasErrors('assistance_reason');
        $this->post(route('pelayanan.farmasi.resep-luar', [$visit, $item]), $reason)->assertSessionHasNoErrors();
        $this->assertFalse($item->fresh()->is_resep_luar);
        $this->assertDatabaseHas('farmasi_resep_luar', ['resep_obat_id' => $item->id, 'petugas_id' => $admin->id]);
        $this->get(route('farmasi.resep-luar.cetak', $visit))->assertOk();
        $this->post(route('pelayanan.farmasi.selesai', $visit), [...$reason, 'items' => []])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $visit->fresh()->status);
        $this->assertSame(0, (int) $medicine->fresh()->stok);
    }

    public function test_admin_cannot_bypass_pending_procedures_or_tampered_clinical_note(): void
    {
        [$doctor, , $visit, $examination] = $this->context();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan tertunda', 'jumlah' => 1])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasNoErrors();
        $visit->update(['status' => 'farmasi']);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $payload = ['assistance_reason' => 'Bantuan layanan farmasi diperlukan.', 'items' => []];
        $this->actingAs($admin)->post(route('pelayanan.farmasi.selesai', $visit), $payload)->assertUnprocessable();
        $visit->tindakanKunjungan()->update(['execution_status' => 'completed']);
        $version = ClinicalNoteVersion::sole();
        $version->update(['payload' => [...$version->payload, 'anamnesis' => 'Isi versi diubah tanpa hash baru']]);
        $this->post(route('pelayanan.farmasi.store', $visit), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('farmasi', 0);
    }

    public function test_direct_admin_dispensing_requires_reason_and_retries_return_the_same_record(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-DIRECT', 'nama' => 'Pasien Sintetis']);
        $medicine = Obat::create(['kode' => 'DIRECT', 'nama' => 'Obat langsung', 'satuan_kecil' => 'tablet', 'jenis' => 'obat', 'stok' => 10, 'is_active' => true, 'boleh_diberikan_langsung' => true]);
        ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => 10]);
        $payload = ['pasien_id' => $patient->id, 'obat_id' => $medicine->id, 'jumlah' => 2, 'aturan_pakai' => 'Sesuai instruksi', 'dasar_pemberian' => 'Permintaan pasien sesuai kriteria', 'request_key' => (string) Str::uuid()];
        $this->actingAs($admin)->post(route('farmasi.pemberian-obat.store'), $payload)->assertSessionHasErrors('assistance_reason');
        $payload['assistance_reason'] = 'Akun farmasi sedang mengalami gangguan.';
        $medicine->update(['boleh_diberikan_langsung' => false]);
        $this->post(route('farmasi.pemberian-obat.store'), $payload)->assertSessionHasErrors('obat_id');
        $medicine->update(['boleh_diberikan_langsung' => true]);
        $medicine->batches()->update(['expired_at' => today()]);
        $this->post(route('farmasi.pemberian-obat.store'), $payload)->assertSessionHasErrors('jumlah');
        $this->assertDatabaseCount('farmasi_mandiris', 0);
        $this->assertSame(10, (int) $medicine->fresh()->stok);
        $medicine->batches()->update(['expired_at' => today()->addYear()]);
        $first = $this->post(route('farmasi.pemberian-obat.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('farmasi.pemberian-obat.store'), $payload)->assertRedirect($first->headers->get('Location'));
        $this->assertDatabaseCount('farmasi_mandiris', 1);
        $this->assertSame(8, (int) $medicine->fresh()->stok);
        $this->assertDatabaseHas('farmasi_mandiris', ['petugas_id' => $admin->id, 'request_key' => $payload['request_key']]);
        $this->post(route('farmasi.pemberian-obat.store'), [...$payload, 'jumlah' => 3])->assertStatus(409);
        $this->assertSame(8, (int) $medicine->fresh()->stok);
        $this->assertSame(1, DB::table('clinical_audit_events')->where('action', 'pharmacy.direct_dispensed')->count());
        $other = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($other)->post(route('farmasi.pemberian-obat.store'), $payload)->assertStatus(409);
        $this->assertDatabaseCount('farmasi_mandiris', 1);
    }

    /** @return array{User, User, Kunjungan, Pemeriksaan} */
    private function context(string $type = 'umum'): array
    {
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'POL-CARE', 'nama' => 'Poli '.$type, 'jenis' => $type, 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOC-CARE', 'nama' => 'Dokter Uji', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctorUser->id, 'is_active' => true]);
        Nakes::create(['kode' => 'NUR-CARE', 'nama' => 'Perawat Uji', 'kategori' => 'medis', 'jabatan' => 'perawat', 'user_id' => $nurse->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-CARE', 'nama' => 'Pasien Sintetis']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-CARE', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'anamnesis' => 'Keluhan uji', 'pemeriksaan_fisik' => 'Temuan uji']);
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'R50.9', 'nama_diagnosa' => 'Diagnosis uji', 'jenis' => 'utama']);
        if ($type === 'gigi') {
            OdontogramFinding::create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'recorded_by_user_id' => $doctorUser->id]);
        }

        return [$doctorUser, $nurse, $visit, $examination];
    }
}
