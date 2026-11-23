<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PharmacyStaffCredential extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'encrypted:array', 'recorded_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Bukti kualifikasi tidak boleh ditimpa.'));
        static::deleting(fn () => throw new \LogicException('Bukti kualifikasi tidak boleh dihapus.'));
    }
}
