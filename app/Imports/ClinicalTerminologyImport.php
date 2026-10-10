<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class ClinicalTerminologyImport extends StringValueBinder implements ToCollection, WithCustomValueBinder
{
    public function collection(Collection $collection): void {}
}
