<?php

namespace App;

use App\Models\CorrespondenceTemplate;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;

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
        $sequence = $template->nomor_berikutnya;
        $template->increment('nomor_berikutnya');

        return $this->renderText($template->format_nomor, [
            '[prefix]' => $template->prefix,
            '[urut]' => str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            '[bulan]' => $date->format('m'),
            '[tahun]' => $date->format('Y'),
        ]);
    }

    /** @param array<string, string> $values */
    public function renderText(string $template, array $values): string
    {
        return strtr($template, $values);
    }
}
