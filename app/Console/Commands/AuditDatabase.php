<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('clinic:audit-database')]
#[Description('Check foreign-key integrity without modifying clinical records')]
class AuditDatabase extends Command
{
    public function handle(): int
    {
        $violations = [];

        foreach (Schema::getTableListing() as $table) {
            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                $parent = $foreignKey['foreign_schema']
                    ? $foreignKey['foreign_schema'].'.'.$foreignKey['foreign_table']
                    : $foreignKey['foreign_table'];
                $query = DB::table($table.' as child');

                foreach ($foreignKey['columns'] as $column) {
                    $query->whereNotNull('child.'.$column);
                }

                $count = $query->whereNotExists(function (Builder $query) use ($parent, $foreignKey): void {
                    $query->selectRaw('1')->from($parent.' as parent');

                    foreach ($foreignKey['columns'] as $index => $column) {
                        $query->whereColumn('parent.'.$foreignKey['foreign_columns'][$index], 'child.'.$column);
                    }
                })->count();

                if ($count > 0) {
                    $violations[] = [$table, implode(', ', $foreignKey['columns']), $parent, $count];
                }
            }
        }

        if ($violations === []) {
            $this->info('Tidak ditemukan pelanggaran foreign key.');

            return self::SUCCESS;
        }

        $this->table(['Tabel', 'Kolom', 'Referensi', 'Baris bermasalah'], $violations);
        $this->error('Ditemukan '.array_sum(array_column($violations, 3)).' pelanggaran foreign key. Rekonsiliasi diperlukan; tidak ada data diubah.');

        return self::FAILURE;
    }
}
