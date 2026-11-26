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
                $latest = DB::table($table)->where($column, 'like', $prefix.'%')
                    ->orderByDesc(DB::raw('LENGTH('.$column.')'))
                    ->orderByDesc($column)
                    ->limit(50)
                    ->pluck($column);
                $number = $latest->map(fn (string $value): int => ctype_digit(substr($value, strlen($prefix))) ? (int) substr($value, strlen($prefix)) : 0)->max() ?? 0;
            }
            do {
                $number++;
                $identifier = $prefix.str_pad((string) $number, $width, '0', STR_PAD_LEFT);
            } while (DB::table($table)->where($column, $identifier)->exists());
            DB::table('clinic_document_sequences')->where('scope', $scope)->update(['last_number' => $number]);

            return $identifier;
        }, 3);
    }

    public function nextQueueNumber(int $clinicId, string $date): int
    {
        $scope = 'antrian-'.$clinicId.'-'.str_replace('-', '', $date);

        return DB::transaction(function () use ($scope, $clinicId, $date): int {
            DB::table('clinic_document_sequences')->insertOrIgnore(['scope' => $scope, 'last_number' => 0]);
            $sequence = DB::table('clinic_document_sequences')->where('scope', $scope)->lockForUpdate()->first();
            $number = (int) ($sequence->last_number ?? 0);
            if ($number === 0) {
                $maxQuery = DB::table('kunjungan')->where('poliklinik_id', $clinicId);
                $maxQuery = DB::connection()->getDriverName() === 'sqlite'
                    ? $maxQuery->whereDate('tanggal', $date)
                    : $maxQuery->where('tanggal', $date);
                $number = (int) ($maxQuery->max('no_antrian') ?? 0);
            }
            do {
                $number++;
                $checkQuery = DB::table('kunjungan')->where('poliklinik_id', $clinicId)->where('no_antrian', $number);
                $checkQuery = DB::connection()->getDriverName() === 'sqlite'
                    ? $checkQuery->whereDate('tanggal', $date)
                    : $checkQuery->where('tanggal', $date);
            } while ($checkQuery->exists());
            DB::table('clinic_document_sequences')->where('scope', $scope)->update(['last_number' => $number]);

            return $number;
        }, 3);
    }
}
