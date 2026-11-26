<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalInformationReleaseEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'encrypted:array', 'recorded_at' => 'datetime'];
    }

    public function kunjungan(): BelongsTo
    {
        return $this->belongsTo(Kunjungan::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Register pelepasan tidak boleh ditimpa.'));
        static::deleting(fn () => throw new \LogicException('Register pelepasan tidak boleh dihapus.'));
    }
}
