<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class KunjunganInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_visit_list_form_detail_and_queue_use_inertia(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-visit@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Pasien Uji']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-20261007-0001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'menunggu',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);

        $this->actingAs($admin)->get(route('pelayanan.kunjungan.index'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/kunjungan/index')
            ->has('visits.data', 1)
            ->where('visits.data.0.patient', 'Pasien Uji')
        );

        $this->get(route('pelayanan.kunjungan.create', ['pasien_id' => $patient->id]))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/kunjungan/form')
            ->where('patient.name', 'Pasien Uji')
        );

        $this->get(route('pelayanan.kunjungan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/kunjungan/show')
            ->where('visit.number', 'KNJ-20261007-0001')
            ->where('visit.patient.name', 'Pasien Uji')
            ->has('visit.progress', 5)
        );

        $this->get(route('pelayanan.kunjungan.edit', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/kunjungan/form')
            ->where('visit.patient.name', 'Pasien Uji')
        );

        $this->get(route('pelayanan.antrian'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/kunjungan/antrian')
            ->has('visits', 1)
        );

        $this->put(route('pelayanan.kunjungan.update', $visit), [
            'poliklinik_id' => $clinic->id,
            'tanggal' => today()->toDateString(),
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'bpjs',
        ])->assertRedirect(route('pelayanan.kunjungan.show', $visit));

        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'jenis_bayar' => 'bpjs']);

        $visit->update(['status' => 'selesai']);

        $this->get(route('pelayanan.kunjungan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('permissions.editVisit', false)
        );

        $this->put(route('pelayanan.kunjungan.update', $visit), [
            'poliklinik_id' => $clinic->id,
            'tanggal' => today()->toDateString(),
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ])->assertUnprocessable();
        $this->post(route('pelayanan.kunjungan.batal', $visit))->assertUnprocessable();
        $this->delete(route('pelayanan.kunjungan.destroy', $visit))->assertUnprocessable();

        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'selesai', 'jenis_bayar' => 'bpjs']);
    }
}
