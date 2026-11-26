<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

class RouteAccessInventoryTest extends TestCase
{
    public function test_clinical_and_sensitive_exports_have_an_explicit_role_boundary(): void
    {
        $sensitivePrefixes = [
            'pelayanan.screening',
            'pelayanan.pemeriksaan',
            'pelayanan.tindakan',
            'pelayanan.farmasi',
            'pelayanan.pasien.rekam-medis',
            'pelayanan.pasien.documents',
            'procedure-consent',
            'procedure-signature',
            'information-release',
            'persuratan.surat',
            'persuratan.rujukan',
            'persuratan.resep',
            'admin.log-aktivitas',
            'admin.laporan',
        ];

        $roleMiddleware = [
            'App\\Http\\Middleware\\EnsureRole',
            'App\\Http\\Middleware\\EnsureAdminRole',
            'App\\Http\\Middleware\\EnsureRegistrationRole',
        ];

        $routes = app('router')->getRoutes();
        $checked = 0;

        foreach ($routes as $route) {
            $name = $route->getName();
            if (! $name || in_array($name, ['procedure-signature.patient', 'procedure-signature.pair', 'procedure-signature.sign'], true)
                || ! collect($sensitivePrefixes)->contains(fn (string $prefix): bool => Str::startsWith($name, $prefix))) {
                continue;
            }

            $checked++;
            $middleware = $route->gatherMiddleware();
            $this->assertTrue(
                collect($middleware)->contains(fn (string $item): bool => collect($roleMiddleware)->contains(fn (string $role): bool => Str::startsWith($item, $role))),
                'Route sensitif tanpa middleware role: '.$name
            );
        }

        $this->assertGreaterThan(30, $checked, 'Inventaris route sensitif terlalu kecil; perbarui daftar prefix audit.');
    }
}
