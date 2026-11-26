<?php

namespace App;

use App\Models\Pemeriksaan;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EffectiveDiagnosisReport
{
    public function query(?Builder $visits = null): Builder
    {
        $examinations = DB::table('pemeriksaan')->select('pemeriksaan.id')
            ->when($visits, fn (Builder $query) => $query->whereIn('kunjungan_id', (clone $visits)->select('kunjungan.id')));
        // Verify before aggregation: an invalid signed archive must not silently
        // fall back to its mutable draft rows. Verified (id, latest_note_hash, version_count)
        // tuples are cached because clinical_note_versions is append-only at the DB trigger level.
        $signedCandidates = DB::table('pemeriksaan as p')
            ->whereIn('p.id', clone $examinations)
            ->whereNotNull('p.signed_at')
            ->select([
                'p.id',
                'p.latest_note_hash',
                DB::raw('(SELECT COUNT(*) FROM clinical_note_versions v WHERE v.pemeriksaan_id = p.id) as version_count'),
            ])
            ->get();

        $unverifiedIds = [];
        foreach ($signedCandidates as $candidate) {
            if (blank($candidate->latest_note_hash) || (int) $candidate->version_count === 0) {
                $unverifiedIds[] = (int) $candidate->id;

                continue;
            }
            $cacheKey = 'verified_clinical_note:'.$candidate->id.':'.$candidate->version_count.':'.$candidate->latest_note_hash;
            if (Cache::get($cacheKey) !== true) {
                $unverifiedIds[] = (int) $candidate->id;
            }
        }

        if ($unverifiedIds !== []) {
            Pemeriksaan::query()->whereIn('id', $unverifiedIds)
                ->with('clinicalNoteVersions')->chunkById(100, function ($batch): void {
                    $recorder = app(ClinicalNoteRecorder::class);
                    foreach ($batch as $examination) {
                        abort_unless($recorder->verify($examination), 409,
                            'Integritas catatan final perlu diperiksa sebelum membuat laporan diagnosis.');
                        $count = $examination->clinicalNoteVersions->count();
                        Cache::put('verified_clinical_note:'.$examination->id.':'.$count.':'.$examination->latest_note_hash, true, now()->addDays(30));
                    }
                });
        }

        $driver = DB::connection()->getDriverName();
        [$rows, $code, $name] = match ($driver) {
            'sqlite' => ["json_each(v.payload, '$.diagnoses') j", "json_extract(j.value, '$.code')", "json_extract(j.value, '$.name')"],
            'mysql', 'mariadb' => ["JSON_TABLE(v.payload, '$.diagnoses[*]' COLUMNS(code TEXT PATH '$.code', name TEXT PATH '$.name')) j", 'j.code', 'j.name'],
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
