<?php

namespace App;

class ClinicalAnswer
{
    public static function normalize(?string $value): ?string
    {
        $value = trim($value ?? '');
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim(rtrim($value, '.!'))) ?? $value);

        return in_array($normalized, [
            '', '-', '—', 'belum dicatat', 'belum di catat', 'belum diisi', 'belum di isi',
            'belum diketahui', 'belum dapat diketahui', 'tidak diketahui', 'belum dikaji', 'belum_diketahui',
        ], true) ? null : $value;
    }
}
