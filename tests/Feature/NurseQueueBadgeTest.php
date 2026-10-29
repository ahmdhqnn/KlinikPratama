<?php

namespace Tests\Feature;

use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\TindakanKunjungan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class NurseQueueBadgeTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['umum', 'perawat'])]
    #[TestWith(['gigi', 'terapis_gigi'])]
    public function test_nurse_dashboard_schedule_and_shared_badges_follow_the_assigned_clinic(string $type, string $position): void
    {
        $this->travelTo('2026-10-26 09:00:00');
        $user = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'OWN', 'nama' => 'Poli '.ucfirst($type), 'jenis' => $type, 'is_active' => true]);
        $otherType = $type === 'umum' ? 'gigi' : 'umum';
        $otherClinic = Poliklinik::create(['kode' => 'OTHER', 'nama' => 'Poli '.ucfirst($otherType), 'jenis' => $otherType, 'is_active' => true]);
        Nakes::create(['kode' => 'NURSE', 'nama' => 'Perawat Uji', 'kategori' => 'medis', 'jabatan' => $position, 'user_id' => $user->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOCTOR', 'nama' => 'Dokter Poli', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        foreach ([$clinic, $otherClinic] as $scheduledClinic) {
            JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $scheduledClinic->id, 'hari' => 'senin', 'jam_mulai' => '08:00', 'jam_selesai' => '12:00', 'is_active' => true]);
        }
        $patient = Pasien::create(['no_rm' => 'RM-QUEUE', 'nama' => 'Pasien Uji']);
        $visit = function (string $number, Poliklinik $targetClinic, string $status, string $date) use ($patient): Kunjungan {
            return Kunjungan::create(['no_kunjungan' => $number, 'pasien_id' => $patient->id, 'poliklinik_id' => $targetClinic->id, 'tanggal' => $date, 'status' => $status]);
        };
        $waiting = $visit('OWN-WAIT', $clinic, 'menunggu', today()->toDateString());
        $visit('OLD-WAIT', $clinic, 'menunggu', today()->subDay()->toDateString());
        $visit('OWN-PROGRESS', $clinic, 'screening', today()->toDateString());
        $submitted = $visit('OWN-SUBMITTED', $clinic, 'screening', today()->toDateString());
        Screening::create(['kunjungan_id' => $submitted->id, 'submitted_at' => now()]);
        $visit('OTHER-WAIT', $otherClinic, 'menunggu', today()->toDateString());
        $treatmentVisit = $visit('OWN-TREATMENT', $clinic, 'tindakan', today()->toDateString());
        Screening::create(['kunjungan_id' => $treatmentVisit->id, 'kesimpulan_triase' => 'merah', 'submitted_at' => now()]);
        Pemeriksaan::create(['kunjungan_id' => $treatmentVisit->id, 'dokter_id' => $doctor->id, 'signed_at' => now()]);
        $treatment = TindakanKunjungan::create(['kunjungan_id' => $treatmentVisit->id, 'nama_tindakan_manual' => 'Tindakan Uji', 'execution_role' => 'perawat', 'execution_status' => 'pending']);
        $foreignTreatment = $visit('OTHER-TREATMENT', $otherClinic, 'tindakan', today()->toDateString());
        Pemeriksaan::create(['kunjungan_id' => $foreignTreatment->id, 'dokter_id' => $doctor->id, 'signed_at' => now()]);
        TindakanKunjungan::create(['kunjungan_id' => $foreignTreatment->id, 'nama_tindakan_manual' => 'Tindakan Lain', 'execution_role' => 'perawat', 'execution_status' => 'pending']);

        $this->actingAs($user)->get(route('perawat.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('nurseQueues.assessment', 2)->where('nurseQueues.treatments', 1)
            ->where('stats.menunggu_ttv', 2)->where('stats.menunggu_tindakan', 1)
            ->where('stats.triase_darurat', 1)
            ->where('visits.total', 3)
            ->where('visits.data.0.actionLabel', 'Mulai pengkajian')
            ->where('visits.data.1.actionLabel', 'Lanjutkan pengkajian')
            ->where('visits.data.2.actionType', 'procedure')
            ->where('visits.data.2.actionLabel', 'Mulai tindakan')
            ->where('visits.data.2.actionUrl', route('pelayanan.tindakan.show', $treatmentVisit).'#pelaksanaan-tindakan')
            ->where('clinicName', $clinic->nama)->has('schedules', 1)->where('schedules.0.clinic', $clinic->nama)->where('schedules.0.doctor', 'Dokter Poli'));
        $this->get(route('pelayanan.screening.index'))->assertInertia(fn (Assert $page) => $page
            ->where('nurseQueues.assessment', 2)->where('nurseQueues.treatments', 1)
            ->where('todayVisits.total', 2)->where('visits.total', 5));
        $this->get(route('pelayanan.tindakan.index'))->assertInertia(fn (Assert $page) => $page
            ->where('nurseQueues.assessment', 2)->where('nurseQueues.treatments', 1)
            ->where('todayVisits.total', 1)->where('todayVisits.data.0.number', 'OWN-TREATMENT'));

        $waiting->update(['status' => 'pemeriksaan']);
        $treatment->update(['execution_status' => 'completed']);
        $treatmentVisit->update(['status' => 'selesai']);
        $this->get(route('perawat.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('nurseQueues.assessment', 1)->where('nurseQueues.treatments', 0)
            ->where('stats.menunggu_ttv', 1)->where('stats.menunggu_tindakan', 0)
            ->where('stats.triase_darurat', 0)
            ->where('visits.total', 1));
        $this->get(route('pelayanan.screening.index'))->assertInertia(fn (Assert $page) => $page
            ->where('todayVisits.total', 1)->where('visits.total', 5));
        $this->get(route('pelayanan.tindakan.index'))->assertInertia(fn (Assert $page) => $page
            ->where('todayVisits.total', 0)->where('stats.todayCompleted', 1)
            ->where('visits.data.0.completedCount', 1));

        DB::table('jadwal_khusus_dokter')->insert(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today()->toDateString(), 'is_active' => false, 'alasan' => 'Dokter berhalangan']);
        $this->get(route('perawat.dashboard'))->assertInertia(fn (Assert $page) => $page->has('schedules', 0));
    }

    public function test_unassigned_nurse_can_open_profile_without_leaking_another_clinics_badges(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);

        $this->actingAs($nurse)->get(route('profile.show'))->assertInertia(fn (Assert $page) => $page
            ->where('nurseQueues.assessment', 0)->where('nurseQueues.treatments', 0));
        $this->get(route('perawat.dashboard'))->assertForbidden();
    }

    #[TestWith(['umum', 'perawat'])]
    #[TestWith(['gigi', 'terapis_gigi'])]
    public function test_started_examination_is_visible_without_nursing_action_until_treatment_handoff(string $type, string $position): void
    {
        $this->travelTo('2026-10-29 09:00:00');
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'OWN', 'nama' => 'Poli '.ucfirst($type), 'jenis' => $type, 'is_active' => true]);
        $otherClinic = Poliklinik::create(['kode' => 'OTHER', 'nama' => 'Poli Lain', 'jenis' => $type === 'umum' ? 'gigi' : 'umum', 'is_active' => true]);
        Nakes::create(['kode' => 'NURSE', 'nama' => 'Perawat Uji', 'kategori' => 'medis', 'jabatan' => $position, 'user_id' => $nurse->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOCTOR', 'nama' => 'Dokter Uji', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-TRANSITION', 'nama' => 'Pasien Transisi']);
        $visit = Kunjungan::create(['no_kunjungan' => 'OWN-EXAM', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'status' => 'draft', 'started_at' => now()]);
        $foreignVisit = Kunjungan::create(['no_kunjungan' => 'OTHER-EXAM', 'pasien_id' => $patient->id, 'poliklinik_id' => $otherClinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Pemeriksaan::create(['kunjungan_id' => $foreignVisit->id, 'dokter_id' => $doctor->id, 'status' => 'draft', 'started_at' => now()]);

        $this->actingAs($nurse)->get(route('pelayanan.screening.index'))->assertInertia(fn (Assert $page) => $page
            ->where('todayVisits.total', 0)->where('visits.data.0.status', 'pemeriksaan')
            ->where('visits.data.0.examinationStarted', true));
        $this->get(route('perawat.dashboard'))->assertInertia(fn (Assert $page) => $page->where('visits.total', 0));
        $this->get(route('pelayanan.tindakan.index'))->assertInertia(fn (Assert $page) => $page
            ->where('todayVisits.total', 0)->where('visits.data.0.status', 'pemeriksaan')
            ->where('visits.data.0.url', null)->where('visits.data.0.pendingCount', 0)
            ->where('stats.todayTotal', 1)->where('stats.todayPending', 0)->where('stats.todayCompleted', 0)
            ->where('nurseQueues.treatments', 0));

        $treatment = TindakanKunjungan::create(['kunjungan_id' => $visit->id, 'nama_tindakan_manual' => 'Tindakan Uji', 'execution_role' => 'perawat', 'execution_status' => 'pending']);
        $this->post(route('pelayanan.tindakan.complete', [$visit, $treatment]), ['execution_result' => 'Belum dilakukan'])->assertStatus(422);
        $this->get(route('pelayanan.tindakan.index'))->assertInertia(fn (Assert $page) => $page
            ->where('todayVisits.total', 0)->where('visits.data.0.url', null)->where('visits.data.0.pendingCount', 0)
            ->where('stats.todayPending', 0)->where('nurseQueues.treatments', 0));

        $visit->update(['status' => 'tindakan']);
        $visit->pemeriksaan()->update(['signed_at' => now(), 'status' => 'selesai']);
        $visit->update(['tindakan_started_at' => now()]);
        $this->get(route('pelayanan.tindakan.index'))->assertInertia(fn (Assert $page) => $page
            ->where('todayVisits.data.0.url', route('pelayanan.tindakan.show', $visit))
            ->where('todayVisits.data.0.pendingCount', 1)->where('stats.todayPending', 1)
            ->where('nurseQueues.treatments', 1));
        $this->get(route('perawat.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('visits.total', 1)->where('visits.data.0.actionType', 'procedure')
            ->where('visits.data.0.treatmentStarted', true)
            ->where('visits.data.0.actionLabel', 'Lanjutkan tindakan'));
    }
}
