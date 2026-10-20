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
        'nama', 'nik', 'nip', 'kategori', 'jenis_lainnya', 'status_kepegawaian', 'unit_kerja',
        'pegawai_penanggung_id', 'hubungan_keluarga', 'hak_layanan', 'referensi_bukti',
        'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
        'agama', 'golongan_darah', 'alamat', 'rt', 'rw', 'kelurahan', 'kecamatan', 'verified_by', 'verified_at',
    ];

    protected $casts = [
        'hak_layanan' => 'boolean',
        'tanggal_lahir' => 'date', 'verified_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'asn' => 'ASN', 'lainnya' => 'Lainnya',
    ];

    public const OTHER_TYPES = [
        'keluarga' => 'Keluarga ASN', 'magang' => 'Magang', 'staf' => 'Staf non-ASN',
        'pensiunan' => 'Pensiunan', 'tamu' => 'Tamu instansi',
        'lainnya' => 'Lainnya',
    ];

    private const LEGACY_CATEGORY_ALIASES = [
        'pegawai_pusat' => 'asn', 'kontrak' => 'lainnya', 'pensiunan' => 'lainnya',
        'keluarga' => 'lainnya', 'tamu' => 'lainnya', 'khusus' => 'lainnya',
    ];

    private const LEGACY_OTHER_TYPE_ALIASES = [
        'kontrak' => 'staf', 'pensiunan' => 'pensiunan', 'keluarga' => 'keluarga',
        'tamu' => 'tamu', 'khusus' => 'lainnya',
    ];

    public function categoryKey(): string
    {
        return self::LEGACY_CATEGORY_ALIASES[$this->kategori] ?? $this->kategori;
    }

    /**
     * @return array<int, string>
     */
    public static function persistedCategoryKeys(string $category): array
    {
        return match ($category) {
            'asn' => ['asn', 'pegawai_pusat'],
            'lainnya' => ['lainnya', 'kontrak', 'pensiunan', 'keluarga', 'tamu', 'khusus'],
            default => [$category],
        };
    }

    public function otherTypeKey(): ?string
    {
        return $this->jenis_lainnya === 'khusus' ? 'lainnya' : ($this->jenis_lainnya ?? self::LEGACY_OTHER_TYPE_ALIASES[$this->kategori] ?? null);
    }

    public function categoryLabel(): string
    {
        $category = $this->categoryKey();

        return self::CATEGORIES[$category] ?? ucfirst(str_replace('_', ' ', $category));
    }

    public function otherTypeLabel(): ?string
    {
        $otherType = $this->otherTypeKey();

        if ($otherType === null) {
            return null;
        }

        return self::OTHER_TYPES[$otherType] ?? ucfirst(str_replace('_', ' ', $otherType));
    }

    public function membershipLabel(): string
    {
        $otherType = $this->otherTypeLabel();

        return $this->categoryLabel().($otherType && $otherType !== $this->categoryLabel() ? ' · '.$otherType : '');
    }

    public function penanggung(): BelongsTo
    {
        return $this->belongsTo(self::class, 'pegawai_penanggung_id');
    }

    public function pasien(): HasOne
    {
        return $this->hasOne(Pasien::class);
    }
}
