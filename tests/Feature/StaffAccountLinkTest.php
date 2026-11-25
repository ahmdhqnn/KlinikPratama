<?php

namespace Tests\Feature;

use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StaffAccountLinkTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['perawat', 'terapis_gigi'])]
    #[TestWith(['farmasi', 'apotek'])]
    #[TestWith(['pendaftaran', 'pendaftaran'])]
    public function test_account_links_existing_staff_without_duplicating_professional_identity(string $role, string $position): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $clinic = Poliklinik::create(['kode' => 'GIGI', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $staff = Nakes::create(['kode' => 'STAFF-1', 'nama' => 'Petugas Klinik', 'jabatan' => $position, 'kategori' => 'medis', 'is_active' => true, 'no_str' => 'STR-123']);

        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Akun Petugas', 'email' => 'petugas@example.test', 'password' => 'password', 'role' => $role, 'nakes_id' => $staff->id, 'poliklinik_id' => $clinic->id, 'is_active' => true])->assertSessionHasNoErrors()->assertRedirect();
        $user = User::where('email', 'petugas@example.test')->firstOrFail();
        $this->assertDatabaseCount('nakes', 1);
        $this->assertDatabaseHas('nakes', ['id' => $staff->id, 'user_id' => $user->id, 'nama' => 'Petugas Klinik', 'jabatan' => $position, 'no_str' => 'STR-123']);
        $event = DB::table('clinical_audit_events')->where('action', 'user.created')->first();
        $this->assertSame($user->id, json_decode($event->metadata, true)['user_id']);
        $this->assertStringNotContainsString('password', $event->metadata);
    }

    #[TestWith(['claimed'])]
    #[TestWith(['inactive'])]
    #[TestWith(['deleted'])]
    #[TestWith(['wrong_position'])]
    public function test_account_rejects_unavailable_or_incompatible_staff(string $state): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = $state === 'claimed' ? User::factory()->create(['role' => 'farmasi']) : null;
        $staff = Nakes::create(['kode' => 'STAFF-1', 'nama' => 'Petugas', 'jabatan' => $state === 'wrong_position' ? 'perawat' : 'farmasi', 'kategori' => 'non_medis', 'is_active' => $state !== 'inactive', 'user_id' => $owner?->id]);
        if ($state === 'deleted') {
            $staff->delete();
        }

        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Akun', 'email' => 'new@example.test', 'password' => 'password', 'role' => 'farmasi', 'nakes_id' => $staff->id, 'is_active' => true])->assertSessionHasErrors('nakes_id');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
        $this->assertDatabaseHas('nakes', ['id' => $staff->id, 'user_id' => $owner?->id]);
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'user.created']);
    }

    public function test_updating_account_can_link_existing_staff_and_retains_old_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'farmasi']);
        $old = Nakes::create(['kode' => 'OLD', 'nama' => 'Profil lama', 'jabatan' => 'farmasi', 'kategori' => 'non_medis', 'is_active' => true, 'user_id' => $user->id]);
        $selected = Nakes::create(['kode' => 'NEW', 'nama' => 'Profil tujuan', 'jabatan' => 'apotek', 'kategori' => 'non_medis', 'is_active' => true]);

        $this->actingAs($admin)->put(route('users.update', $user), ['name' => $user->name, 'email' => $user->email, 'role' => 'farmasi', 'is_active' => true, 'nakes_id' => $selected->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('nakes', ['id' => $selected->id, 'user_id' => $user->id, 'jabatan' => 'apotek']);
        $this->assertDatabaseHas('nakes', ['id' => $old->id, 'user_id' => null]);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'user.updated', 'actor_id' => $admin->id]);
    }

    public function test_inactive_staff_profile_prevents_account_reactivation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => false]);
        Nakes::create(['kode' => 'DOCTOR', 'nama' => 'Dokter nonaktif', 'jabatan' => 'dokter', 'kategori' => 'medis', 'is_active' => false, 'user_id' => $user->id]);
        $this->actingAs($admin)->post(route('users.toggle-active', $user))->assertSessionHas('error');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'user.status_changed']);
    }

    public function test_editing_account_cannot_bypass_inactive_staff_guard_by_omitting_profile(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'farmasi', 'is_active' => false]);
        Nakes::create(['kode' => 'INACTIVE', 'nama' => 'Petugas nonaktif', 'jabatan' => 'farmasi', 'kategori' => 'non_medis', 'is_active' => false, 'user_id' => $user->id]);
        $this->actingAs($admin)->put(route('users.update', $user), ['name' => $user->name, 'email' => $user->email, 'role' => 'farmasi', 'is_active' => true])->assertSessionHasErrors('is_active');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->assertDatabaseHas('nakes', ['user_id' => $user->id, 'is_active' => false]);
    }

    #[TestWith(['short'])]
    #[TestWith([['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']])]
    public function test_admin_password_creation_and_reset_reject_invalid_passwords(mixed $password): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'manajemen']);
        $hash = $user->password;
        $this->actingAs($admin)->post(route('users.store'), ['name' => 'Akun Baru', 'email' => 'new@example.test', 'role' => 'manajemen', 'is_active' => true, 'password' => $password])->assertSessionHasErrors('password');
        $this->post(route('users.reset-password', $user), ['password' => $password])->assertSessionHasErrors('password');
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
        $this->assertDatabaseCount('clinical_audit_events', 0);
    }

    public function test_admin_account_changes_record_targets_without_password_material(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'dokter']);
        $this->actingAs($admin)->post(route('users.reset-password', $user), ['password' => 'new-password'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->post(route('users.toggle-active', $user))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_active' => false]);
        $this->delete(route('users.destroy', $user))->assertSessionHasNoErrors();
        $this->assertModelExists($user);
        $this->assertFalse($user->fresh()->is_active);
        foreach (['user.password_reset', 'user.status_changed', 'user.deactivated'] as $action) {
            $event = DB::table('clinical_audit_events')->where('action', $action)->first();
            $this->assertSame($user->id, json_decode($event->metadata, true)['user_id']);
            $this->assertStringNotContainsString('new-password', $event->metadata);
        }
        $this->delete(route('users.destroy', $admin))->assertSessionHas('error');
        $this->assertModelExists($admin);
        $this->assertDatabaseCount('clinical_audit_events', 3);
    }
}
