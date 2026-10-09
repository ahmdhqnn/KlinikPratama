<?php

namespace App\Models;

use Database\Factories\OdontogramFindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdontogramFinding extends Model
{
    /** @use HasFactory<OdontogramFindingFactory> */
    use HasFactory;

    protected $fillable = [
        'kunjungan_id', 'tooth_fdi', 'surface', 'finding_code', 'notes', 'recorded_by_user_id',
    ];

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return array<int, string>
     */
    public static function toothCodes(): array
    {
        $codes = [];
        foreach ([1, 2, 3, 4] as $quadrant) {
            foreach (range(1, 8) as $position) {
                $codes[] = (string) $quadrant.$position;
            }
        }

        foreach ([5, 6, 7, 8] as $quadrant) {
            foreach (range(1, 5) as $position) {
                $codes[] = (string) $quadrant.$position;
            }
        }

        return $codes;
    }

    /**
     * @return array<string, string>
     */
    public static function surfaceLabels(): array
    {
        return [
            'W' => 'Seluruh gigi',
            'M' => 'Mesial',
            'O' => 'Oklusal',
            'D' => 'Distal',
            'V' => 'Vestibular/bukal/labial',
            'L' => 'Lingual/palatal',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function findingLabels(): array
    {
        return [
            'sound' => 'Sehat',
            'caries' => 'Karies',
            'missing' => 'Hilang',
            'unerupted' => 'Belum erupsi',
            'fractured_crown' => 'Fraktur mahkota',
            'root_remnant' => 'Sisa akar',
            'restored' => 'Restorasi',
        ];
    }
}
