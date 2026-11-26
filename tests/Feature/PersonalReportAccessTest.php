<?php

namespace Tests\Feature;

use App\Exports\FarmasiDispensingExport;
use App\Exports\KunjunganExport;
use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class PersonalReportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_report_and_export_read_only_visits_registered_by_the_current_account(): void
    {
        $owner = User::factory()->create(['role' => 'pendaftaran']);
        $other = User::factory()->create(['role' => 'pendaftaran', 'is_active' => false]);
        $admin = User::factory()->create(['role' => 'admin']);
        $clinic = Poliklinik::create(['kode' => 'SCOPE-REG', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $ownVisit = $this->visit($clinic, 'REG-01', ['registered_by' => $owner->id]);
        $otherVisit = $this->visit($clinic, 'REG-02', ['registered_by' => $other->id]);
        $this->visit($clinic, 'REG-03');

        $this->actingAs($owner)->get(route('pendaftaran.laporan-kunjungan', ['registered_by' => $other->id]))
            ->assertInertia(fn (Assert $page) => $page->where('visits.total', 1)->where('visits.data.0.id', $ownVisit->id));
        $this->get(route('pendaftaran.kunjungan.show', $otherVisit))->assertOk();
        $this->actingAs($admin)->get(route('pendaftaran.laporan-kunjungan'))
            ->assertInertia(fn (Assert $page) => $page->where('visits.total', 3));

        Excel::fake();
        $this->actingAs($owner)->get(route('pendaftaran.laporan-kunjungan.export'))->assertOk();
        Excel::assertDownloaded('laporan-kunjungan-'.today()->toDateString().'.xlsx',
            fn (KunjunganExport $export): bool => $export->query()->pluck('id')->all() === [$ownVisit->id]);
    }

    public function test_pharmacy_clinic_queue_is_shared_and_reports_use_the_dispensing_account(): void
    {
        [$owner, $other] = User::factory()->count(2)->create(['role' => 'farmasi']);
        $general = Poliklinik::create(['kode' => 'SCOPE-UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $dental = Poliklinik::create(['kode' => 'SCOPE-GIGI', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $ownGeneral = $this->visit($general, 'RX-01', ['status' => 'selesai']);
        $otherGeneral = $this->visit($general, 'RX-02', ['status' => 'selesai']);
        $ownDental = $this->visit($dental, 'RX-03', ['status' => 'selesai']);
        $waitingDental = $this->visit($dental, 'RX-04', ['status' => 'farmasi']);

        foreach ([[$ownGeneral, $owner], [$otherGeneral, $other], [$ownDental, $owner]] as [$visit, $actor]) {
            Farmasi::create(['kunjungan_id' => $visit->id, 'status' => 'selesai', 'dispensed_by' => $actor->id, 'dispensed_at' => now()]);
        }

        $this->actingAs($other)->get(route('pelayanan.farmasi.index', ['poli' => 'gigi']))
            ->assertInertia(fn (Assert $page) => $page->where('stats.completed', 1)->where('todayVisits.data.0.id', $waitingDental->id)->where('visits.total', 2));
        $this->actingAs($owner)->get(route('farmasi.laporan', ['poli' => 'umum', 'account_id' => $other->id]))
            ->assertInertia(fn (Assert $page) => $page->where('stats.total', 1)->where('rows.data.0.number', 'RX-01'));
        $this->get(route('farmasi.laporan', ['poli' => 'gigi']))
            ->assertInertia(fn (Assert $page) => $page->where('stats.total', 1)->where('rows.data.0.number', 'RX-03'));

        Excel::fake();
        $this->get(route('farmasi.laporan.export', ['poli' => 'umum']))->assertOk();
        Excel::assertDownloaded('laporan-penyerahan-obat-'.today()->toDateString().'.xlsx',
            fn (FarmasiDispensingExport $export): bool => $export->query()->pluck('kunjungan_id')->all() === [$ownGeneral->id]);
    }

    private function visit(Poliklinik $clinic, string $number, array $attributes = []): Kunjungan
    {
        $patient = Pasien::create(['no_rm' => $number, 'nama' => 'Pasien Uji '.$number]);

        return Kunjungan::create([
            'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'no_kunjungan' => $number,
            'tanggal' => today(), 'status' => 'menunggu', ...$attributes,
        ]);
    }
}
