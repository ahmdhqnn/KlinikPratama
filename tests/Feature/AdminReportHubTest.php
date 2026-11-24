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
        $this->actingAs($admin)->get(route('admin.laporan.index'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/laporan')->has('groups', 2)->has('groups.0.reports', 2)->has('groups.1.reports', 6)
            ->where('groups.0.reports.0.href', route('laporan.kunjungan'))->where('groups.0.reports.1.href', route('laporan.stok')));
    }

    public function test_admin_can_open_every_report_listed_in_the_hub(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin);

        $hub = $this->get(route('admin.laporan.index'))->assertOk()->viewData('page');
        $reports = collect($hub['props']['groups'])->flatMap(fn (array $group) => $group['reports']);

        foreach ($reports as $report) {
            $this->get($report['href'])->assertOk();
        }
    }

    public function test_report_hub_and_admin_scoped_report_routes_are_denied_to_other_roles(): void
    {
        foreach (['perawat', 'dokter', 'farmasi', 'manajemen'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user)->get(route('admin.laporan.index'))->assertForbidden();
            $this->get(route('admin.laporan.pemeriksaan'))->assertForbidden();
            $this->get(route('admin.laporan.pemeriksaan.export'))->assertForbidden();
        }

        $registration = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $this->actingAs($registration)->get(route('admin.laporan.index'))->assertRedirect(route('pendaftaran.dashboard'));
    }
}
