<?php

namespace App;

use Illuminate\Support\Facades\DB;

class ClinicDocumentNumber
{
    public function next(string $scope, string $prefix, int $width, string $table, string $column): string
    {
        return DB::transaction(function () use ($scope, $prefix, $width, $table, $column): string {
            DB::table('clinic_document_sequences')->insertOrIgnore(['scope' => $scope, 'last_number' => 0]);
            $sequence = DB::table('clinic_document_sequences')->where('scope', $scope)->lockForUpdate()->first();
            $number = (int) $sequence->last_number;
            if ($number === 0) {
                $number = DB::table($table)->where($column, 'like', $prefix.'%')->pluck($column)
                    ->map(fn (string $value): int => ctype_digit(substr($value, strlen($prefix))) ? (int) substr($value, strlen($prefix)) : 0)->max() ?? 0;
            }
            do {
                $number++;
                $identifier = $prefix.str_pad((string) $number, $width, '0', STR_PAD_LEFT);
            } while (DB::table($table)->where($column, $identifier)->exists());
            DB::table('clinic_document_sequences')->where('scope', $scope)->update(['last_number' => $number]);

            return $identifier;
        }, 3);
    }
}
