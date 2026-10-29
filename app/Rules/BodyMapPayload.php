<?php

namespace App\Rules;

use App\ClinicalAnswer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BodyMapPayload implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || strlen(json_encode($value) ?: '') > 350000
            || ! $this->keys($value, ['version', 'findings', 'annotation'])
            || ($value['version'] ?? null) !== 1 || ! $this->list($value['findings'] ?? null, 60)) {
            $fail('Data peta tubuh tidak valid atau terlalu besar.');

            return;
        }
        $ids = [];
        foreach ($value['findings'] as $finding) {
            if (! $this->validFinding($finding) || in_array($finding['id'], $ids, true)) {
                $fail('Lokasi, sisi tubuh, atau temuan pemeriksaan tidak valid.');

                return;
            }
            $ids[] = $finding['id'];
        }
        $annotation = $value['annotation'] ?? null;
        if (! is_array($annotation) || ! $this->keys($annotation, ['strokes', 'note'])
            || ! $this->list($annotation['strokes'] ?? null, 100) || ! $this->text($annotation['note'] ?? null, 2000)) {
            $fail('Anotasi tubuh tidak valid. Maksimal 100 goresan dan 2.000 karakter catatan.');

            return;
        }
        $total = 0;
        $ids = [];
        foreach ($annotation['strokes'] as $stroke) {
            if (! is_array($stroke) || ! $this->keys($stroke, ['id', 'tool', 'color', 'width', 'points'])
                || ! $this->id($stroke['id'] ?? null) || in_array($stroke['id'], $ids, true)
                || ! in_array($stroke['tool'] ?? null, ['pen', 'line', 'arrow', 'ellipse'], true)
                || ! in_array($stroke['color'] ?? null, ['#dc2626', '#2563eb', '#16a34a', '#9333ea', '#171717'], true)
                || ! in_array($stroke['width'] ?? null, [2, 4, 6], true)
                || ! $this->list($stroke['points'] ?? null, 400) || count($stroke['points']) < 1
                || ($stroke['tool'] !== 'pen' && count($stroke['points']) !== 2)) {
                $fail('Goresan anotasi tidak valid.');

                return;
            }
            $ids[] = $stroke['id'];
            $total += count($stroke['points']);
            foreach ($stroke['points'] as $point) {
                if (! is_array($point) || ! $this->keys($point, ['x', 'y'])
                    || ! $this->coordinate($point['x'] ?? null, 592) || ! $this->coordinate($point['y'] ?? null, 558)) {
                    $fail('Koordinat anotasi berada di luar gambar tubuh.');

                    return;
                }
            }
        }
        if ($total > 8000) {
            $fail('Anotasi terlalu rinci. Kurangi goresan sebelum menyimpan.');
        }
    }

    public static function normalize(array $value): array
    {
        foreach ($value['findings'] as &$finding) {
            foreach (['side', 'detail', 'method', 'symptom'] as $field) {
                $finding[$field] = trim($finding[$field] ?? '');
            }
            $finding['finding'] = trim($finding['finding']);
            foreach (['methodDetail', 'locationLabel'] as $field) {
                if (array_key_exists($field, $finding)) {
                    $finding[$field] = trim($finding[$field] ?? '');
                }
            }
            $finding['pain'] ??= null;
        }
        unset($finding);
        $value['annotation']['note'] = trim($value['annotation']['note'] ?? '');

        return $value;
    }

    public static function hasContent(?array $value): bool
    {
        return ! empty($value['findings']) || ! empty($value['annotation']['strokes']) || filled($value['annotation']['note'] ?? null);
    }

    private function validFinding(mixed $finding): bool
    {
        if (! is_array($finding) || ! $this->keys($finding, ['id', 'locationId', 'mode', 'view', 'model', 'category', 'side', 'detail', 'method', 'symptom', 'finding', 'pain', 'entryMode', 'locationLabel', 'methodDetail'])
            || ! $this->id($finding['id'] ?? null)
            || ! in_array($finding['entryMode'] ?? 'interactive', ['interactive', 'manual', 'quick'], true)
            || ! $this->text($finding['locationLabel'] ?? null, 150)
            || ! $this->text($finding['methodDetail'] ?? null, 250)
            || ! in_array($finding['mode'] ?? null, ['organs', 'body'], true)
            || ! in_array($finding['view'] ?? null, ['front', 'back'], true)
            || ! in_array($finding['model'] ?? null, ['male', 'female'], true)
            || ! in_array($finding['category'] ?? null, ['Kepala dan leher', 'Thorax', 'Abdomen', 'Ekstremitas', 'Neurologis', 'Kulit', 'Lainnya'], true)
            || ! in_array($finding['side'] ?? '', ['', 'kanan', 'kiri', 'bilateral', 'garis tengah'], true)
            || ! $this->text($finding['detail'] ?? null, 250) || ! $this->text($finding['symptom'] ?? null, 500)
            || ! in_array($finding['method'] ?? '', ['', 'Inspeksi', 'Palpasi', 'Perkusi', 'Auskultasi', 'Rentang gerak', 'Pemeriksaan neurologis', 'Lainnya'], true)
            || ! $this->text($finding['finding'] ?? null, 3000) || ClinicalAnswer::normalize($finding['finding'] ?? null) === null
            || (isset($finding['pain']) && (! is_int($finding['pain']) || $finding['pain'] < 0 || $finding['pain'] > 10))) {
            return false;
        }
        if (isset($finding['entryMode']) && ($finding['method'] ?? '') === 'Lainnya' && blank($finding['methodDetail'] ?? null)) {
            return false;
        }
        if (($finding['entryMode'] ?? '') === 'quick' && (filled($finding['detail'] ?? null) || filled($finding['method'] ?? null) || filled($finding['methodDetail'] ?? null) || filled($finding['symptom'] ?? null) || isset($finding['pain']))) {
            return false;
        }
        $location = $finding['locationId'] ?? '';
        if ($location === 'general') {
            return ($finding['entryMode'] ?? '') === 'quick' && $finding['category'] === 'Lainnya' && ($finding['side'] ?? '') === '' && $finding['mode'] === 'body' && $finding['view'] === 'front' && blank($finding['locationLabel'] ?? null);
        }
        if ($location === 'other') {
            return ClinicalAnswer::normalize($finding['locationLabel'] ?? null) !== null;
        }
        if ($finding['mode'] === 'organs') {
            return $finding['view'] === 'front' && in_array($location, ['brain', 'eyes', 'ent_throat', 'lungs', 'heart', 'liver', 'stomach', 'kidneys', 'pancreas', 'intestines', 'appendix', 'bladder'], true);
        }
        if (in_array($location, [$finding['view'].'-head', $finding['view'].'-neck'], true) || ($location === 'back-spine' && $finding['view'] === 'back')) {
            return true;
        }
        foreach (['shoulder', 'upper-arm', 'elbow', 'forearm', 'wrist', 'hand', 'chest', 'abdomen', 'pelvis', 'thigh', 'knee', 'lower-leg', 'ankle', 'foot'] as $region) {
            foreach (['kanan', 'kiri'] as $side) {
                if ($location === $finding['view'].'-'.$region.'-'.$side) {
                    return ($finding['side'] ?? '') === $side;
                }
            }
        }

        return false;
    }

    private function keys(array $value, array $keys): bool
    {
        return array_diff(array_keys($value), $keys) === [];
    }

    private function list(mixed $value, int $max): bool
    {
        return is_array($value) && array_is_list($value) && count($value) <= $max;
    }

    private function text(mixed $value, int $max): bool
    {
        return $value === null || (is_string($value) && mb_strlen($value) <= $max);
    }

    private function id(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A[a-zA-Z0-9-]{1,64}\z/', $value) === 1;
    }

    private function coordinate(mixed $value, int $max): bool
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0 && $value <= $max;
    }
}
