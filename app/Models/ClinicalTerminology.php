<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalTerminology extends Model
{
    protected $fillable = ['code_system', 'release', 'code', 'display', 'code_type', 'source_file', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
