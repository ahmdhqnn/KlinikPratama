<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcedureSignatureSession extends Model
{
    use HasUuids;

    protected $hidden = ['token_hash', 'credentials', 'snapshot', 'signature'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array', 'snapshot' => 'encrypted:array', 'signature' => 'encrypted:array',
            'expires_at' => 'datetime', 'signed_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(TindakanKunjungan::class)->withoutGlobalScope('active_clinical_entries');
    }

    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('cancelled_at')->whereNull('consent_id')
            ->where(fn (Builder $pending) => $pending->where('expires_at', '>', now())->orWhereNotNull('signed_at'));
    }
}
