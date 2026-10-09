<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RmeAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
        );
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Administrator',
            'email' => 'admin@klinik.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@klinik.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_admin_can_view_dashboard(): void
    {
        $user = User::create([
            'name' => 'Administrator',
            'email' => 'admin@klinik.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/index')
            ->where('stats.totalPatients', 0)
            ->where('stats.visitsToday', 0)
            ->where('stats.visitsThisMonth', 0)
            ->where('stats.prescriptionsToday', 0)
            ->has('visitStatuses')
            ->missing('recentVisits')
        );
    }

    public function test_login_is_rate_limited_after_repeated_failed_attempts(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->post('/login', [
                'email' => 'unknown@example.com',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'unknown@example.com',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
