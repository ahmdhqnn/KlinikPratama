<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminReportHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_report_hub_groups_service_pharmacy_and_stock_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($admin)->get(route('admin.laporan.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/laporan')
                ->has('groups', fn (Assert $groups) => $groups
                    ->where('0.title', 'Pelayanan klinik')
                    ->has('0.reports', 5)
                    ->where('0.reports.0.href', route('laporan.kunjungan'))
                    ->where('0.reports.1.href', route('admin.laporan.pengkajian'))
                    ->where('0.reports.2.href', route('admin.laporan.tindakan-perawat'))
                    ->where('1.title', 'Farmasi dan persediaan')
                    ->has('1.reports', 4)));
    }

    public function test_report_hub_and_admin_scoped_report_routes_are_denied_to_other_roles(): void
    {
        foreach (['perawat', 'dokter', 'farmasi', 'manajemen'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get(route('admin.laporan.index'))->assertForbidden();
        }

        $registration = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $this->actingAs($registration)->get(route('admin.laporan.index'))->assertRedirect(route('pendaftaran.dashboard'));
    }
}
