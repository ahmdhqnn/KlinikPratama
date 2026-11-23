<?php

namespace App;

use App\Models\Pemeriksaan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EffectiveDiagnosisReport
{
    public function query(?Builder $visits = null): Builder
    {
        $examinations = DB::table('pemeriksaan')->select('pemeriksaan.id')
            ->when($visits, fn (Builder $query) => $query->whereIn('kunjungan_id', (clone $visits)->select('kunjungan.id')));
        // Verify before aggregation: an invalid signed archive must not silently
        // fall back to its mutable draft rows.
        Pemeriksaan::query()->whereIn('id', clone $examinations)->whereNotNull('signed_at')
            ->with('clinicalNoteVersions')->chunkById(100, function ($examinations): void {
                foreach ($examinations as $examination) {
                    abort_unless(app(ClinicalNoteRecorder::class)->verify($examination), 409,
                        'Integritas catatan final perlu diperiksa sebelum membuat laporan diagnosis.');
                }
            });

        $driver = DB::connection()->getDriverName();
        [$rows, $code, $name] = match ($driver) {
            'sqlite' => ["json_each(v.payload, '$.diagnoses') j", "json_extract(j.value, '$.code')", "json_extract(j.value, '$.name')"],
            'mysql' => ["JSON_TABLE(v.payload, '$.diagnoses[*]' COLUMNS(code TEXT PATH '$.code', name TEXT PATH '$.name')) j", 'j.code', 'j.name'],
            'pgsql' => ["jsonb_array_elements(v.payload::jsonb->'diagnoses') j(value)", "j.value->>'code'", "j.value->>'name'"],
            default => throw new \LogicException('Mesin database laporan diagnosis belum didukung.'),
        };

        $signed = DB::table('clinical_note_versions as v')
            ->join('pemeriksaan as e', 'e.id', '=', 'v.pemeriksaan_id')
            ->crossJoin(DB::raw($rows))->whereNotNull('e.signed_at')->whereIn('e.id', clone $examinations)
            ->whereNotExists(fn (Builder $newer) => $newer->selectRaw('1')->from('clinical_note_versions as newer')
                ->whereColumn('newer.pemeriksaan_id', 'v.pemeriksaan_id')->whereColumn('newer.version', '>', 'v.version'))
            ->selectRaw("v.pemeriksaan_id, {$code} as kode_icd10, {$name} as nama_diagnosa");
        $draft = DB::table('diagnosa as d')->join('pemeriksaan as e', 'e.id', '=', 'd.pemeriksaan_id')
            ->whereNull('e.signed_at')->whereNull('d.voided_at')->whereIn('e.id', clone $examinations)
            ->select('d.pemeriksaan_id', 'd.kode_icd10', 'd.nama_diagnosa');

        return DB::query()->fromSub($draft->unionAll($signed), 'diagnosa');
    }
}
