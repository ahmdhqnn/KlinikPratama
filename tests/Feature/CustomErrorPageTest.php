<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CustomErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_inertia_request_renders_custom_js_404_page(): void
    {
        $this->get('/halaman-acak-yang-tidak-ada')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('error')
                ->where('status', 404)
                ->where('message', null)
                ->missing('auth'));
    }

    #[TestWith([405])]
    #[TestWith([409])]
    #[TestWith([413])]
    #[TestWith([419])]
    #[TestWith([422])]
    #[TestWith([429])]
    #[TestWith([500])]
    #[TestWith([503])]
    public function test_browser_errors_keep_their_status_and_only_display_workflow_messages(int $status): void
    {
        Route::get('/uji-error', fn () => abort($status, 'Pesan internal uji.', ['Retry-After' => '20']));

        $this->get('/uji-error', ['X-Inertia' => 'true'])
            ->assertStatus($status)
            ->assertHeader('X-Inertia', 'true')
            ->assertHeader('Retry-After', '20')
            ->assertJsonPath('props.retryAfter', 20)
            ->assertJsonPath('props.status', $status)
            ->assertJsonPath('props.message', in_array($status, [409, 422], true) ? 'Pesan internal uji.' : null);
    }

    public function test_api_and_validation_keep_their_response_contracts(): void
    {
        $this->get('/api/uji-error')->assertNotFound()->assertJsonStructure(['message']);
        $this->from('/login')->post('/login', ['email' => 'bukan-email'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Masukkan alamat email yang valid.', 'password' => 'Masukkan kata sandi akun Anda.']);
    }

    public function test_inertia_form_rejection_returns_the_message_without_replacing_the_form(): void
    {
        Route::post('/uji-konflik', fn () => abort(409, 'Data sudah diperbarui petugas lain.'));
        $this->post('/uji-konflik', [], ['X-Inertia' => 'true'])
            ->assertConflict()
            ->assertHeaderMissing('X-Inertia')
            ->assertJsonPath('workflowError', 'Data sudah diperbarui petugas lain.');
    }

    public function test_malformed_login_email_is_validated_without_an_internal_error(): void
    {
        $this->from('/login')->post('/login', ['email' => ['email@example.test'], 'password' => 'contoh'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Masukkan alamat email yang valid.']);

        $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('email', ''));
        $this->assertGuest();
    }

    public function test_inertia_request_renders_inertia_error_page_for_404(): void
    {
        $response = $this->withHeaders(['X-Inertia' => 'true'])
            ->get('/halaman-acak-yang-tidak-ada');

        $response->assertStatus(404);
        $response->assertHeader('X-Inertia', 'true');
        $this->assertSame('error', $response->json('component'));
        $this->assertSame(404, $response->json('props.status'));
    }

    public function test_inertia_request_renders_inertia_error_page_for_403(): void
    {
        // Perawat attempting to access doctor/admin-only route
        $nurse = User::factory()->create([
            'role' => 'perawat',
            'is_active' => true,
        ]);

        $version = (new HandleInertiaRequests)->version(request());

        $response = $this->actingAs($nurse)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => $version ?? '',
            ])
            ->get('/persuratan');

        $response->assertStatus(403);
        $response->assertHeader('X-Inertia', 'true');
        $this->assertSame('error', $response->json('component'));
        $this->assertSame(403, $response->json('props.status'));
    }

    public function test_login_with_inactive_user_uses_generic_credentials_message(): void
    {
        $inactiveUser = User::create([
            'name' => 'Staf Nonaktif',
            'email' => 'nonaktif@klinik.com',
            'password' => bcrypt('password123'),
            'role' => 'perawat',
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'nonaktif@klinik.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'credentials' => 'Email atau kata sandi tidak cocok. Periksa kembali isian atau buka bantuan akses.',
        ]);
        $this->assertGuest();
    }

    public function test_login_with_wrong_password_uses_generic_credentials_message(): void
    {
        User::create([
            'name' => 'Dokter Pratama',
            'email' => 'dokter@klinik.com',
            'password' => bcrypt('correct-password'),
            'role' => 'dokter',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => 'dokter@klinik.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors([
            'credentials' => 'Email atau kata sandi tidak cocok. Periksa kembali isian atau buka bantuan akses.',
        ]);
        $this->assertGuest();
    }

    public function test_login_with_nonexistent_email_shows_invalid_credentials_message(): void
    {
        $response = $this->post('/login', [
            'email' => 'tidakada@klinik.com',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors([
            'credentials' => 'Email atau kata sandi tidak cocok. Periksa kembali isian atau buka bantuan akses.',
        ]);
        $this->assertGuest();
    }

    public function test_guest_can_render_auth_assistance_page(): void
    {
        $this->get('/bantuan-akses')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/bantuan-akses'));
    }
}
