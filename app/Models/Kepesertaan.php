<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Kepesertaan extends Model
{
    use HasFactory;

    protected $table = 'kepesertaan';

    protected $fillable = [
        'nama', 'nik', 'nip', 'kategori', 'status_kepegawaian', 'unit_kerja', 'cost_center',
        'pegawai_penanggung_id', 'hubungan_keluarga', 'hak_layanan', 'berlaku_mulai',
        'berlaku_sampai', 'referensi_bukti', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
        'agama', 'golongan_darah', 'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'hak_layanan' => 'boolean', 'berlaku_mulai' => 'date', 'berlaku_sampai' => 'date',
        'tanggal_lahir' => 'date', 'verified_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'pegawai_pusat' => 'Pegawai pusat', 'kontrak' => 'Kontrak / honorer',
        'pensiunan' => 'Pensiunan', 'keluarga' => 'Keluarga pegawai', 'tamu' => 'Tamu instansi',
        'khusus' => 'Pasien khusus (verifikasi admin)',
    ];

    public function penanggung(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pegawai_penanggung_id');
    }

    public function pasien(): HasOne
    {
        return $this->hasOne(Pasien::class);
    }
}
