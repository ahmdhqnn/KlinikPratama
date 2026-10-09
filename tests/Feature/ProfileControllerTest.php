<?php

namespace Tests\Feature;

use App\Models\Nakes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_can_open_its_own_profile_and_registration_keeps_its_access_boundary(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));

        foreach (['admin', 'dokter', 'perawat', 'farmasi', 'kasir', 'pendaftaran'] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);

            $this->actingAs($user)->get(route('profile.show'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('profile/show')
                    ->where('profile.name', $user->name)
                    ->where('auth.user.role', $role)
                );
        }

        $this->get(route('users.index'))->assertRedirect(route('pendaftaran.dashboard'));
    }

    public function test_profile_update_changes_only_own_account_fields_and_preserves_professional_identity(): void
    {
        $user = User::factory()->create([
            'role' => 'dokter',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $staff = Nakes::create([
            'kode' => 'DR-PROFILE',
            'nama' => 'Nama Legal Dokter',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'no_sip' => 'SIP-123',
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Nama Tampilan Baru',
            'email' => 'dokter-baru@klinik.test',
            'phone' => '08123456789',
            'address' => 'Alamat kontak baru',
            'role' => 'admin',
            'is_active' => false,
            'photo_path' => 'other/photo.png',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nama Tampilan Baru',
            'email' => 'dokter-baru@klinik.test',
            'phone' => '08123456789',
            'address' => 'Alamat kontak baru',
            'role' => 'dokter',
            'is_active' => true,
            'photo_path' => null,
            'email_verified_at' => null,
        ]);
        $this->assertDatabaseHas('nakes', [
            'id' => $staff->id,
            'nama' => 'Nama Legal Dokter',
            'no_sip' => 'SIP-123',
        ]);

        $this->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('profile.professional.name', 'Nama Legal Dokter')
                ->where('profile.professional.sip', 'SIP-123')
            );
    }

    public function test_profile_rejects_duplicate_email_and_invalid_password_change(): void
    {
        $user = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)->put(route('profile.update'), [
            'name' => 'Nama Baru',
            'email' => $other->email,
        ])->assertSessionHasErrors('email');

        $this->put(route('profile.password'), [
            'current_password' => 'salah',
            'password' => 'kata-sandi-baru',
            'password_confirmation' => 'kata-sandi-baru',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame($user->name, $user->fresh()->name);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_user_can_change_own_password_with_current_password(): void
    {
        $user = User::factory()->create(['role' => 'kasir', 'is_active' => true]);

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'password',
            'password' => 'kata-sandi-baru',
            'password_confirmation' => 'kata-sandi-baru',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('kata-sandi-baru', $user->fresh()->password));
    }

    public function test_photo_is_private_to_the_owner_and_can_be_removed(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'kasir', 'is_active' => true]);

        $this->actingAs($user)->post(route('profile.photo.upload'), [
            'photo' => UploadedFile::fake()->image('portrait.png'),
        ])->assertSessionHasNoErrors();

        $photoPath = $user->fresh()->photo_path;
        Storage::disk('local')->assertExists($photoPath);
        $this->get(route('profile.photo'))->assertOk();

        $this->post(route('profile.photo.upload'), [
            'photo' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertSessionHasNoErrors();

        $replacementPath = $user->fresh()->photo_path;
        $this->assertNotSame($photoPath, $replacementPath);
        Storage::disk('local')->assertMissing($photoPath);
        Storage::disk('local')->assertExists($replacementPath);
        $this->actingAs($other)->get(route('profile.photo'))->assertNotFound();
        $this->get(route('profile.show'))
            ->assertInertia(fn (Assert $page) => $page->where('profile.photoUrl', null));

        $this->actingAs($user)->delete(route('profile.photo.delete'))->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->photo_path);
        Storage::disk('local')->assertMissing($replacementPath);
    }

    public function test_photo_upload_rejects_non_image_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);

        $this->actingAs($user)->post(route('profile.photo.upload'), [
            'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('photo');

        $this->assertNull($user->fresh()->photo_path);
    }

    public function test_deactivated_session_cannot_reach_profile_or_role_pages(): void
    {
        $user = User::factory()->create(['role' => 'farmasi', 'is_active' => false]);

        $this->actingAs($user)->get(route('profile.show'))->assertForbidden();
        $this->get(route('pelayanan.farmasi.index'))->assertForbidden();
    }
}
