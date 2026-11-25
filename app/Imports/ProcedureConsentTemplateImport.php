<?php

namespace App\Imports;

use App\Exports\ProcedureConsentTemplateWorkbook;
use App\Models\ProcedureConsentTemplate;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProcedureConsentTemplateImport implements ToCollection, WithHeadingRow, WithLimit, WithMultipleSheets
{
    private const MAX_ROWS = 500;

    public int $count = 0;

    public function __construct(private readonly Closure $rules) {}

    public function sheets(): array
    {
        return [0 => $this];
    }

    public function limit(): int
    {
        return self::MAX_ROWS + 2;
    }

    public function collection(Collection $rows): void
    {
        if ($rows->count() > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Maksimal 500 baris data per impor.']);
        }

        $columns = ProcedureConsentTemplateWorkbook::COLUMNS;
        $explanationFields = ProcedureConsentTemplate::FIELDS;
        foreach ($rows as $offset => $row) {
            $values = $row->map(fn ($value) => is_string($value) ? trim($value) : $value);
            if (! $values->contains(fn ($value) => filled($value))) {
                continue;
            }

            if (array_diff([...$columns, ...$explanationFields], $values->keys()->all()) !== []) {
                throw ValidationException::withMessages(['file' => 'Kolom tidak lengkap. Unduh template terbaru dan pertahankan nama kolomnya.']);
            }

            $data = $values->only($columns)->all();
            $data['explanation'] = $values->only($explanationFields)->all();
            $validator = Validator::make($data, ($this->rules)($data));
            if ($validator->fails()) {
                $line = $offset + 2;
                throw ValidationException::withMessages(['file' => "Baris {$line}: ".$validator->errors()->first()]);
            }

            ProcedureConsentTemplate::create($validator->validated());
            $this->count++;
        }

        if ($this->count === 0) {
            throw ValidationException::withMessages(['file' => 'Berkas belum berisi data template SOP.']);
        }
    }
}
