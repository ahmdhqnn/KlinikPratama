<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserSettingsInertiaTest extends TestCase
{
    use RefreshDatabase;

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
