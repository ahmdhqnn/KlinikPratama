<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcedureConsentTemplate extends Model
{
    use SoftDeletes;

    public const FIELDS = ['indication', 'method', 'benefits', 'risks', 'alternatives', 'without_procedure', 'prognosis'];

    protected $fillable = ['code', 'release', 'clinic_type', 'version', 'mode', 'trigger_condition', 'sop_reference', 'explanation'];

    protected $casts = ['explanation' => 'array', 'published_at' => 'datetime'];

    public function revision(): string
    {
        return hash('sha256', json_encode($this->only([
            'id', 'code', 'release', 'clinic_type', 'version', 'mode', 'trigger_condition', 'sop_reference', 'explanation',
        ]), JSON_THROW_ON_ERROR));
    }
}
