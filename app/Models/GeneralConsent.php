<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralConsent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['evidence_path'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Bukti consent tidak boleh ditimpa.'));
        static::deleting(fn () => throw new \LogicException('Bukti consent tidak boleh dihapus.'));
    }
}
