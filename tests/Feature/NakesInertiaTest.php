<?php

namespace Tests\Feature;

use App\Models\Nakes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NakesInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_staff_and_create_a_role_matched_login_account(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-staff@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        Nakes::create([
            'kode' => 'SDM-002',
            'nama' => 'Staf Non Medis',
            'kategori' => 'non_medis',
            'jabatan' => 'kasir',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('master.nakes.index', ['search' => 'SDM-002', 'kategori' => 'non_medis']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('master/nakes/index')
                ->has('staff.data', 1)
                ->where('staff.data.0.name', 'Staf Non Medis')
                ->where('staff.data.0.position', 'kasir')
            );

        $this->from(route('master.nakes.index'))
            ->post(route('master.nakes.store'), [
                'kode' => 'SDM-001',
                'nama' => 'dr. Dokter Uji',
                'jenis_kelamin' => 'P',
                'kategori' => 'medis',
                'jabatan' => 'dokter',
                'no_sip' => 'SIP-001',
                'no_str' => 'STR-001',
                'telepon' => '08123456789',
                'buat_akun' => true,
            ])
            ->assertSessionHasErrors(['email', 'password']);

        $this->post(route('master.nakes.store'), [
            'kode' => 'SDM-001',
            'nama' => 'dr. Dokter Uji',
            'jenis_kelamin' => 'P',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'no_sip' => 'SIP-001',
            'no_str' => 'STR-001',
            'telepon' => '08123456789',
            'buat_akun' => true,
            'email' => 'dokter-uji@klinik.test',
            'password' => 'secret-password',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $staff = Nakes::query()->where('kode', 'SDM-001')->firstOrFail();
        $account = User::query()->where('email', 'dokter-uji@klinik.test')->firstOrFail();
        $this->assertSame($account->id, $staff->user_id);
        $this->assertSame('dokter', $account->role);
        $this->assertTrue(Hash::check('secret-password', $account->password));

        $this->put(route('master.nakes.update', $staff), [
            'kode' => 'SDM-001',
            'nama' => 'dr. Dokter Uji, Sp.PD',
            'jenis_kelamin' => 'P',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'no_sip' => 'SIP-001',
            'no_str' => 'STR-001',
            'telepon' => '08123456789',
            'is_active' => false,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nakes', ['id' => $staff->id, 'nama' => 'dr. Dokter Uji, Sp.PD', 'is_active' => false]);
        $this->assertFalse($account->fresh()->is_active);

        $this->delete(route('master.nakes.destroy', $staff))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('nakes', ['id' => $staff->id]);
    }

    public function test_linked_staff_cannot_change_to_a_position_with_a_different_account_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $staff = Nakes::create([
            'kode' => 'DR-GUARD',
            'nama' => 'Dokter Tetap',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'user_id' => $doctorUser->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('master.nakes.update', $staff), [
            'kode' => 'DR-GUARD',
            'nama' => 'Dokter Tetap',
            'kategori' => 'non_medis',
            'jabatan' => 'kasir',
            'is_active' => true,
        ])->assertSessionHasErrors('jabatan');

        $this->assertSame('dokter', $staff->fresh()->jabatan);
        $this->assertSame('dokter', $doctorUser->fresh()->role);
    }
}
