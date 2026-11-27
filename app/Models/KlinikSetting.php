<?php

namespace App\Models;

use App\ClinicalWorkflowSettings;
use Illuminate\Database\Eloquent\Model;

class KlinikSetting extends Model
{
    protected $table = 'klinik_settings';

    protected $casts = [
        'clinical_workflow' => 'array',
    ];

    protected $fillable = [
        'nama_klinik',
        'kode_fasyankes',
        'izin_operasional',
        'satusehat_org_id',
        'alamat',
        'kode_provinsi',
        'kode_kabupaten',
        'kode_kecamatan',
        'kode_kelurahan',
        'kode_pos',
        'telepon',
        'email',
        'logo',
        'kepala_klinik',
        'nip_kepala',
        'tagline',
        'website',
        'pelaksana_ttv',
        'clinical_workflow',
    ];

    protected $attributes = [
        'pelaksana_ttv' => 'perawat',
    ];

    public static function pelaksanaTtv(): string
    {
        return static::query()->value('pelaksana_ttv') ?? 'perawat';
    }

    /**
     * @return array<string, string|bool>
     */
    public static function clinicalWorkflow(): array
    {
        return ClinicalWorkflowSettings::current();
    }
}
