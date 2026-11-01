<?php

namespace Tests\Feature;

use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\SuratMedis;
use App\Models\TindakanKunjungan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminOperationalQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_includes_all_statuses_and_dates_with_independent_pagination_and_full_stage_counts(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        for ($index = 0; $index < 12; $index++) {
            $this->visit('menunggu');
        }
        $this->visit('selesai');
        $this->visit('batal');
        $this->visit('selesai', '2026-10-31');
        $this->visit('menunggu', '2026-11-02');

        $this->actingAs($admin)->get(route('pelayanan.antrian'))->assertInertia(fn (Assert $page) => $page
            ->where('todayVisits.total', 14)->has('todayVisits.data', 10)
            ->where('visits.total', 16)->has('visits.data', 10)
            ->where('serviceQueues.assessment.total', 12)->has('serviceQueues.assessment.data', 5)
            ->where('stageCounts.menunggu', 12)
            ->where('stageCounts.selesai', 1)->where('stageCounts.batal', 1));
        $this->get(route('pelayanan.antrian', ['today_page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('todayVisits.currentPage', 2)->has('todayVisits.data', 4)
            ->where('visits.currentPage', 1)->has('visits.data', 10));
        $this->get(route('pelayanan.antrian', ['page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->where('todayVisits.currentPage', 1)->where('visits.currentPage', 2));
    }

    #[TestWith(['menunggu', ['detail', 'rme', 'screening', 'issue', 'edit', 'cancel']])]
    #[TestWith(['pemeriksaan', ['detail', 'rme', 'screening', 'print', 'examination', 'issue']])]
    #[TestWith(['farmasi', ['detail', 'rme', 'print', 'examination', 'pharmacy', 'issue']])]
    #[TestWith(['selesai', ['detail', 'rme', 'print', 'examination', 'issue']])]
    #[TestWith(['batal', ['detail', 'rme', 'issue']])]
    public function test_queue_actions_follow_visit_workflow(string $status, array $expectedActions): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = $this->visit($status);
        if (! in_array($status, ['menunggu', 'batal'], true)) {
            Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);
        }

        $this->actingAs($admin)->get(route('pelayanan.antrian'))->assertInertia(fn (Assert $page) => $page
            ->has('visits.data.0.actions', 10)
            ->where('visits.data.0.actions', fn ($actions) => collect($actions)->where('disabled', false)->pluck('action')->values()->all() === $expectedActions
                && collect($actions)->where('disabled', true)->every(fn ($action) => $action['href'] === null && filled($action['disabledReason']))));
    }

    public function test_doctor_start_locks_assessment_and_future_reservations_have_no_clinical_actions(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = $this->visit('pemeriksaan');
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'started_at' => now(), 'status' => 'draft']);

        $this->actingAs($admin)->get(route('pelayanan.antrian'))->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.actions', fn ($actions) => collect($actions)->firstWhere('action', 'screening')['disabled'] === true));
        $visit->update(['status' => 'menunggu', 'tanggal' => '2026-11-02']);
        $this->get(route('pelayanan.antrian'))->assertInertia(fn (Assert $page) => $page
            ->where('todayVisits.total', 0)->where('visits.data.0.isReservation', true)
            ->where('visits.data.0.actions', fn ($actions) => collect($actions)->where('disabled', false)->pluck('action')->values()->all() === ['detail', 'rme', 'issue', 'edit', 'cancel']));
    }

    public function test_queue_filters_apply_only_to_all_visits_table_and_scope_correspondence_to_selected_visit(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = $this->visit('selesai');
        $otherTodayVisit = $this->visit('menunggu');
        $oldVisit = $this->visit('selesai', '2026-10-31');
        $letter = SuratMedis::create(['kunjungan_id' => $visit->id, 'jenis' => 'sakit', 'nomor_surat' => 'SK-01', 'tanggal' => today()]);
        SuratMedis::create(['kunjungan_id' => $oldVisit->id, 'jenis' => 'sakit', 'nomor_surat' => 'SK-02', 'tanggal' => today()]);

        $this->actingAs($admin)->get(route('pelayanan.antrian', ['tanggal' => '2026-10-31', 'search' => $visit->pasien->nama, 'status' => 'selesai', 'poliklinik_id' => $visit->poliklinik_id]))
            ->assertInertia(fn (Assert $page) => $page->where('todayVisits.total', 2)->where('visits.total', 1)
                ->where('todayVisits.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$otherTodayVisit->id, $visit->id])
                ->where('visits.data.0.id', $oldVisit->id));
        $this->get(route('persuratan.index', ['kunjungan_id' => $visit->id]))->assertInertia(fn (Assert $page) => $page
            ->where('letters.total', 1)->where('letters.data.0.id', $letter->id)->where('filters.visitId', (string) $visit->id));
        $this->get(route('pelayanan.antrian', ['status' => 'invalid']))->assertSessionHasErrors('status');
    }

    public function test_queue_requires_authentication_and_rejects_nurse_access(): void
    {
        $this->get(route('pelayanan.antrian'))->assertRedirect(route('login'));
        $nurse = User::factory()->create(['role' => 'perawat']);
        $this->actingAs($nurse)->get(route('pelayanan.antrian'))->assertForbidden();
    }

    public function test_service_cards_show_today_queues_independently_of_table_filters(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $assessment = $this->visit('menunggu');
        $this->visit('pemeriksaan');
        $this->visit('tindakan');
        $this->visit('farmasi');
        $this->visit('selesai');
        $this->visit('batal');
        $this->visit('menunggu', '2026-11-02');
        $this->visit('menunggu', '2026-10-31');

        $this->actingAs($admin)->get(route('pelayanan.antrian', ['status' => 'selesai']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('todayVisits.total', 6)
                ->where('serviceQueues.assessment.total', 1)
                ->where('serviceQueues.assessment.data.0.id', $assessment->id)
                ->where('serviceQueues.examination.total', 1)
                ->where('serviceQueues.procedures.total', 1)
                ->where('serviceQueues.pharmacy.total', 1));
    }

    public function test_inactive_clinic_keeps_clinical_actions_visible_but_disabled(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = $this->visit('menunggu');
        $visit->poliklinik->update(['is_active' => false]);

        $this->actingAs($admin)->get(route('pelayanan.antrian'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('visits.data.0.actions', 10)
                ->where('visits.data.0.actions', fn ($actions) => collect($actions)
                    ->whereIn('action', ['screening', 'print', 'examination', 'procedure', 'pharmacy'])
                    ->every(fn ($action) => $action['disabled'] && $action['href'] === null)));
    }

    public function test_completed_visit_retains_nurse_procedure_and_pharmacy_history_actions(): void
    {
        $this->travelTo('2026-11-01 09:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $visit = $this->visit('selesai');
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'signed_at' => now(), 'status' => 'selesai']);
        TindakanKunjungan::create([
            'kunjungan_id' => $visit->id, 'nama_tindakan_manual' => 'Perawatan luka',
            'jumlah' => 1, 'execution_role' => 'perawat', 'execution_status' => 'completed',
        ]);
        Farmasi::create(['kunjungan_id' => $visit->id, 'status' => 'selesai']);

        $this->actingAs($admin)->get(route('pelayanan.antrian'))->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.actions', fn ($actions) => collect($actions)->contains('href', route('pelayanan.tindakan.show', $visit))
                && collect($actions)->contains('label', 'Lihat penyerahan obat')
                && collect($actions)->firstWhere('action', 'cancel')['disabled'] === true));
    }

    private function visit(string $status, string $date = '2026-11-01'): Kunjungan
    {
        $clinic = Poliklinik::firstOrCreate(['kode' => 'UMUM'], ['nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::firstOrCreate(['no_rm' => 'RM-QUEUE'], ['nama' => 'Pasien Antrean']);

        return Kunjungan::create([
            'no_kunjungan' => Kunjungan::generateNomor(), 'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id, 'tanggal' => $date, 'status' => $status, 'jenis_pasien' => 'lama',
        ]);
    }
}
