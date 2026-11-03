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
        'kunjungan_id', 'tooth_fdi', 'surface', 'finding_code', 'notes', 'details', 'recorded_by_user_id',
    ];

    protected $casts = ['details' => 'array'];

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
            'partial_erupted' => 'Erupsi sebagian (PRE)',
            'impacted' => 'Impaksi',
            'retained_primary' => 'Persistensi gigi sulung',
            'malposition' => 'Kelainan posisi / rotasi / migrasi',
            'non_vital' => 'Gigi non-vital (NVT)',
            'root_canal' => 'Riwayat perawatan saluran akar (RCT)',
            'crown' => 'Mahkota tiruan / crown',
            'bridge_pontic' => 'Bridge — pontik',
            'bridge_abutment' => 'Bridge — gigi penyangga',
            'implant' => 'Implan gigi (IPX)',
            'removable_denture' => 'Gigi tiruan lepasan',
            'sealant' => 'Pit & fissure sealant (FIS)',
            'temporary_restoration' => 'Tambalan sementara',
            'anomaly' => 'Anomali bentuk / ukuran gigi (ANO)',
        ];
    }

    /** @return array<string, list<string>> */
    public static function findingGroups(): array
    {
        return [
            'Kondisi fisik, erupsi & posisi' => ['sound', 'missing', 'unerupted', 'partial_erupted', 'impacted', 'retained_primary', 'malposition', 'anomaly'],
            'Kerusakan & kondisi pulpa' => ['caries', 'fractured_crown', 'root_remnant', 'non_vital'],
            'Restorasi & riwayat perawatan' => ['restored', 'temporary_restoration', 'sealant', 'root_canal', 'crown', 'bridge_pontic', 'bridge_abutment', 'implant', 'removable_denture'],
        ];
    }

    /** @return list<string> */
    public static function wholeToothFindings(): array
    {
        return ['missing', 'unerupted', 'partial_erupted', 'impacted', 'retained_primary', 'malposition', 'anomaly', 'non_vital', 'root_canal', 'crown', 'bridge_pontic', 'bridge_abutment', 'implant', 'removable_denture'];
    }
}
