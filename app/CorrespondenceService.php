<?php

namespace App;

use App\Models\CorrespondenceTemplate;
use App\Models\RujukanInternal;
use App\Models\SuratMedis;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CorrespondenceService
{
    public function signatureDataUrl(?User $user): ?string
    {
        $path = $user?->signature_path;
        $storage = Storage::disk('local');
        if (! $path || ! $storage->exists($path)) {
            return null;
        }

        $mime = $storage->mimeType($path);
        if (! in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($storage->get($path));
    }

    public function nextNumber(CorrespondenceTemplate $template, CarbonInterface $date): string
    {
        return DB::transaction(function () use ($template, $date): string {
            $locked = CorrespondenceTemplate::query()->lockForUpdate()->findOrFail($template->id);
            if (! str_contains($locked->format_nomor, '[urut]')) {
                throw ValidationException::withMessages(['surat_template_id' => 'Format nomor template harus memuat [urut].']);
            }
            do {
                $sequence = $locked->nomor_berikutnya;
                $locked->increment('nomor_berikutnya');
                $number = $this->renderText($locked->format_nomor, [
                    '[prefix]' => $locked->prefix,
                    '[urut]' => str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                    '[bulan]' => $date->format('m'), '[tahun]' => $date->format('Y'),
                ]);
                if (mb_strlen($number) > 120 || str_contains($number, '[') || str_contains($number, ']')) {
                    throw ValidationException::withMessages(['surat_template_id' => 'Format nomor tidak valid atau melebihi 120 karakter.']);
                }
                if (SuratMedis::query()->where('nomor_surat', $number)->exists() || RujukanInternal::query()->where('nomor_surat', $number)->exists()) {
                    continue;
                }
                if (DB::table('correspondence_numbers')->insertOrIgnore(['number' => $number]) === 1) {
                    return $number;
                }
            } while (true);
        });
    }

    public function defaultTemplate(string $type): CorrespondenceTemplate
    {
        $prefix = match ($type) {
            'sakit' => 'SKS', 'sehat' => 'SKK', 'rujukan' => 'RJK',
            'laboratorium' => 'LAB', 'rontgen_gigi' => 'XRG', 'ringkasan' => 'RMP',
            'rujukan_internal' => 'RI', default => 'SK',
        };

        $template = CorrespondenceTemplate::query()->firstOrCreate(['kode' => 'KLINIK-'.strtoupper($type)], [
            'nama' => 'Format klinik - '.str_replace('_', ' ', $type), 'jenis' => $type,
            'prefix' => $prefix, 'format_nomor' => '[prefix]/[urut]/KP/[bulan]/[tahun]',
            'nomor_berikutnya' => 1, 'isi' => '', 'is_active' => true,
        ]);
        if (! $template->is_active || $template->jenis !== $type) {
            throw ValidationException::withMessages(['surat_template_id' => 'Format standar klinik tidak aktif atau tidak sesuai. Pilih template aktif yang sesuai.']);
        }

        return $template;
    }

    /** @param array<string, string> $values */
    public function renderText(string $template, array $values): string
    {
        return strtr($template, $values);
    }
}
