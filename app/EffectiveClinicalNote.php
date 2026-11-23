<?php

namespace App;

use App\Models\Pemeriksaan;

class EffectiveClinicalNote
{
    public function resolve(?Pemeriksaan $examination): ?array
    {
        if (! $examination?->signed_at) {
            return null;
        }
        abort_unless(app(ClinicalNoteRecorder::class)->verify($examination), 409, 'Integritas catatan final perlu diperiksa sebelum menerbitkan dokumen.');
        $version = $examination->clinicalNoteVersions()->latest('version')->firstOrFail();

        return ['id' => $version->id, 'version' => $version->version, 'hash' => $version->content_hash, 'payload' => $version->payload];
    }
}
