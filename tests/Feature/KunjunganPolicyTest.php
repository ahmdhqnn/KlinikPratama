<?php

namespace Tests\Feature;

use App\Models\User;
use App\Policies\KunjunganPolicy;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class KunjunganPolicyTest extends TestCase
{
    #[TestWith(['admin', ['consent_read', 'consent_write']])]
    #[TestWith(['pendaftaran', ['consent_read', 'consent_write']])]
    #[TestWith(['perawat', ['screening', 'nursing', 'history', 'attachment', 'consent_read']])]
    #[TestWith(['dokter', ['examination', 'correspondence', 'history', 'attachment', 'consent_read']])]
    #[TestWith(['farmasi', ['pharmacy']])]
    #[TestWith(['manajemen', []])]
    public function test_service_matrix_requires_the_professional_role_and_an_active_account(string $role, array $allowed): void
    {
        $user = User::factory()->make(['role' => $role, 'is_active' => true]);
        $policy = app(KunjunganPolicy::class);
        foreach (['screening', 'nursing', 'examination', 'correspondence', 'history', 'attachment', 'pharmacy', 'consent_read', 'consent_write', 'unknown_service'] as $service) {
            $this->assertSame(in_array($service, $allowed, true), $policy->accessService($user, $service), $role.' / '.$service);
            $user->is_active = false;
            $this->assertFalse($policy->accessService($user, $service), 'Akun nonaktif tidak boleh mengakses '.$service);
            $user->is_active = true;
        }
    }
}
