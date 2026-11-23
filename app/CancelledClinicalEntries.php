<?php

namespace App;

use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\OdontogramFinding;
use App\Models\ResepObat;
use App\Models\TindakanKunjungan;

class CancelledClinicalEntries
{
    public function forVisit(Kunjungan $visit): array
    {
        $entries = collect();
        foreach ([
            [Diagnosa::class, 'pemeriksaan_id', $visit->pemeriksaan?->id, 'Diagnosis'],
            [ResepObat::class, 'resep_id', $visit->resep?->id, 'Item resep'],
            [TindakanKunjungan::class, 'kunjungan_id', $visit->id, 'Rencana tindakan'],
            [OdontogramFinding::class, 'kunjungan_id', $visit->id, 'Odontogram'],
        ] as [$model, $column, $id, $kind]) {
            if (! $id) {
                continue;
            }
            foreach ($model::withoutGlobalScope('active_clinical_entries')->where($column, $id)->whereNotNull('voided_at')->orderByDesc('voided_at')->get() as $entry) {
                $entries->push([
                    'id' => $entry->id, 'kind' => $kind,
                    'label' => $entry instanceof OdontogramFinding ? 'Gigi '.$entry->tooth_fdi.' · '.$entry->surface.' · '.$entry->finding_code : ($entry->nama_diagnosa ?? $entry->nama_obat ?? $entry->nama_tindakan ?? $entry->nama_tindakan_manual),
                    'details' => $entry instanceof ResepObat ? trim($entry->jumlah.' '.$entry->satuan.'; '.$entry->aturan_pakai) : ($entry->keterangan ?? $entry->catatan ?? $entry->notes),
                    'reason' => $entry->void_reason, 'at' => $entry->voided_at, 'actorId' => $entry->voided_by,
                ]);
            }
        }

        return $entries->sortByDesc('at')->values()->all();
    }
}
