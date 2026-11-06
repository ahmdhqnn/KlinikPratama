<?php

namespace Tests;

use App\Models\Kunjungan;
use App\Models\Nakes;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function beginNursingAssessment(Kunjungan $visit): static
    {
        $current = $visit->fresh(['screening']);
        if (in_array($current->status, ['menunggu', 'screening'], true) && ! $current->screening?->started_at) {
            $staff = Nakes::where('poliklinik_id', $current->poliklinik_id)
                ->whereIn('jabatan', ['perawat', 'bidan', 'terapis_gigi'])->where('is_active', true)->first();
            $this->post(route('pelayanan.screening.start', $visit), [
                'petugas_id' => $staff?->id, 'assistance_reason' => 'Memulai pengkajian untuk pengujian alur pelayanan',
            ]);
        }

        return $this;
    }
}
