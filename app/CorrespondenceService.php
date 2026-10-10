<?php

namespace App;

use App\Models\CorrespondenceTemplate;
use Carbon\CarbonInterface;

class CorrespondenceService
{
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
