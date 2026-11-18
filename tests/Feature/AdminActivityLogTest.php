<?php

namespace Tests\Feature;

use App\ClinicalAuditRecorder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_retains_events_after_actor_deletion_without_exposing_metadata(): void
    {
        $this->travelTo('2026-11-18 09:00:00');
        $admin = User::factory()->create(['role' => 'admin']);
        $actor = User::factory()->create(['role' => 'farmasi']);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $actor);
        app(ClinicalAuditRecorder::class)->record($request, 'user.password_reset', metadata: ['user_id' => 42, 'password' => 'must-never-appear']);
        $actor->delete();

        $this->actingAs($admin)->get(route('admin.log-aktivitas'))->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit-bantuan')->where('activityLog', true)->where('events.total', 1)
            ->where('events.data.0.actor', 'Akun tidak tersedia')
            ->where('events.data.0.label', 'Kata sandi akun diatur ulang')
            ->where('events.data.0.subject', 'Akun #42')
            ->missing('events.data.0.metadata')->missing('events.data.0.password'));
    }

    public function test_log_filters_by_period_action_and_operator_with_pagination(): void
    {
        $this->travelTo('2026-11-18 09:00:00');
        $admin = User::factory()->create(['name' => 'Operator Audit', 'role' => 'admin']);
        foreach (range(1, 17) as $index) {
            DB::table('clinical_audit_events')->insert(['actor_id' => $admin->id, 'action' => 'user.updated', 'occurred_at' => now()]);
        }
        DB::table('clinical_audit_events')->insert(['actor_id' => $admin->id, 'action' => 'user.created', 'occurred_at' => now()]);
        DB::table('clinical_audit_events')->insert(['actor_id' => $admin->id, 'action' => 'user.updated', 'occurred_at' => '2026-10-01 09:00:00']);

        $this->actingAs($admin)->get(route('admin.log-aktivitas', ['dari' => '2026-11-18', 'sampai' => '2026-11-18', 'aksi' => 'user.updated', 'q' => 'Operator', 'page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->where('events.total', 17)->where('events.currentPage', 2)->has('events.data', 2));
        $this->get(route('admin.log-aktivitas', ['q' => "' OR 1=1 --"]))->assertInertia(fn (Assert $page) => $page->where('events.total', 0));
        $this->get(route('admin.log-aktivitas', ['dari' => '2026-11-19', 'sampai' => '2026-11-18']))->assertSessionHasErrors(['dari', 'sampai']);
    }

    #[TestWith(['dokter'])]
    #[TestWith(['perawat'])]
    #[TestWith(['farmasi'])]
    #[TestWith(['pendaftaran'])]
    #[TestWith(['manajemen'])]
    public function test_activity_log_rejects_non_admin_roles(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.log-aktivitas'))->assertForbidden();
    }

    public function test_activity_log_requires_an_active_authenticated_admin(): void
    {
        $this->get(route('admin.log-aktivitas'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]))->get(route('admin.log-aktivitas'))->assertForbidden();
    }
}
