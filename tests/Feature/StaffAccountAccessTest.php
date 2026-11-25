<?php

namespace Tests\Feature;

use App\Models\Nakes;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StaffAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['admin'])]
    #[TestWith(['pendaftaran'])]
    public function test_single_roles_cannot_gain_an_additional_active_account(string $role): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if ($role !== 'admin') {
            User::factory()->create(['role' => $role]);
        }
        $replacement = User::factory()->create(['role' => $role, 'is_active' => false]);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Akun Cadangan', 'email' => 'cadangan@example.test', 'password' => 'password', 'role' => $role, 'is_active' => true,
        ])->assertSessionHasErrors('role');
        $this->post(route('users.toggle-active', $replacement))->assertSessionHas('error');
        $this->assertFalse($replacement->refresh()->is_active);
        $this->assertDatabaseMissing('users', ['email' => 'cadangan@example.test']);

        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['role' => $role]);
    }

    public function test_staff_creation_stores_laboratory_profile_and_refuses_login_escalation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $profile = ['kode' => 'LAB-1', 'nama' => 'Petugas Laboratorium', 'kategori' => 'non_medis', 'jabatan' => 'lab', 'is_active' => true];

        $this->actingAs($admin)->post(route('master.nakes.store'), [
            ...$profile, 'buat_akun' => true, 'email' => 'lab@example.test', 'password' => 'password',
        ])->assertSessionHasErrors('buat_akun');
        $this->assertDatabaseMissing('nakes', ['kode' => 'LAB-1']);
        $this->assertDatabaseMissing('users', ['email' => 'lab@example.test']);

        $this->post(route('master.nakes.store'), $profile)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nakes', ['kode' => 'LAB-1', 'user_id' => null]);
    }

    public function test_registration_from_staff_directory_respects_existing_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'pendaftaran']);

        $this->actingAs($admin)->post(route('master.nakes.store'), [
            'kode' => 'REG-2', 'nama' => 'Petugas Baru', 'jabatan' => 'pendaftaran', 'kategori' => 'non_medis',
            'is_active' => true, 'buat_akun' => true, 'email' => 'reg-2@example.test', 'password' => 'password',
        ])->assertSessionHasErrors('buat_akun');
        $this->assertDatabaseMissing('nakes', ['kode' => 'REG-2']);
    }

    public function test_duplicate_str_is_rejected_even_when_the_original_profile_is_archived(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Nakes::create(['kode' => 'STAFF-01', 'nama' => 'Profil Lama', 'jabatan' => 'apotek', 'kategori' => 'medis', 'no_str' => 'STR-01'])->delete();

        $this->actingAs($admin)->post(route('master.nakes.store'), [
            'kode' => 'STAFF-02', 'nama' => 'Profil Baru', 'jabatan' => 'apotek', 'kategori' => 'medis', 'no_str' => ' str-01 ',
        ])->assertSessionHasErrors('no_str');
        $this->assertDatabaseMissing('nakes', ['kode' => 'STAFF-02']);
    }

    public function test_incompatible_legacy_account_cannot_login_or_keep_an_existing_session(): void
    {
        $user = User::factory()->create(['role' => 'perawat']);
        Nakes::create(['kode' => 'LAB-LEGACY', 'nama' => 'Petugas Laboratorium', 'jabatan' => 'lab', 'kategori' => 'non_medis', 'user_id' => $user->id]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('credentials');
        $this->assertGuest();
        $this->actingAs($user)->get(route('profile.show'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_and_email_uniqueness_handle_legacy_letter_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $registrar = User::factory()->create(['role' => 'pendaftaran', 'email' => 'reg@example.test']);
        DB::table('users')->where('id', $registrar->id)->update(['email' => 'Reg@Example.Test']);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Duplikat', 'email' => 'REG@example.test', 'password' => 'password',
            'role' => 'pendaftaran', 'is_active' => true,
        ])->assertSessionHasErrors('email');

        $this->post(route('logout'));
        $this->post('/login', ['email' => 'reg@example.test', 'password' => 'password'])
            ->assertRedirect(route('pendaftaran.dashboard'));
        $this->assertAuthenticatedAs($registrar);
    }

    public function test_database_rejects_two_staff_profiles_linked_to_the_same_account(): void
    {
        $user = User::factory()->create(['role' => 'farmasi']);
        $profile = ['nama' => 'Petugas Farmasi', 'jabatan' => 'farmasi', 'kategori' => 'medis', 'user_id' => $user->id];
        Nakes::create(['kode' => 'FAR-01', ...$profile]);

        $this->expectException(UniqueConstraintViolationException::class);
        Nakes::create(['kode' => 'FAR-02', ...$profile]);
    }
}
