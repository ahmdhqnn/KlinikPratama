<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['kind', 'petugas_id', 'request_id', 'request_hash', 'screening_id', 'version', 'actor_id', 'reason', 'payload', 'recorded_at'];

    protected $casts = ['payload' => 'array', 'recorded_at' => 'datetime'];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Versi pengkajian tidak boleh ditimpa.'));
        static::deleting(fn () => throw new \LogicException('Versi pengkajian tidak boleh dihapus.'));
    }
}
