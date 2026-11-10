<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserSettingsInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cannot_disable_or_change_the_role_of_their_own_account_through_edit(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->from(route('users.index'));

        foreach ([['role' => 'manajemen', 'is_active' => true], ['role' => 'admin', 'is_active' => false]] as $changes) {
            $this->put(route('users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, ...$changes])
                ->assertRedirect(route('users.index'))
                ->assertSessionHas('error', 'Peran dan status akun sendiri tidak dapat diubah.');
        }

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin', 'is_active' => true]);
    }

    public function test_admin_can_edit_their_own_identity_without_changing_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->from(route('users.index'))
            ->put(route('users.update', $admin), ['name' => 'Nama Admin Baru', 'email' => $admin->email, 'role' => 'admin'])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => 'Nama Admin Baru', 'role' => 'admin', 'is_active' => true]);
    }

    public function test_admin_user_management_and_clinic_settings_render_inertia_pages(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-settings@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        User::create([
            'name' => 'Petugas Kasir',
            'email' => 'needle@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'kasir',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('users.index', ['search' => 'needle', 'role' => 'admin']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('users/index')
                ->where('users.total', 0)
                ->has('roles', 6)
            );

        $this->get(route('setting.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('setting/index')
                ->where('setting.pelaksanaTtv', 'perawat')
                ->where('setting.logoUrl', null)
            );
    }
}
