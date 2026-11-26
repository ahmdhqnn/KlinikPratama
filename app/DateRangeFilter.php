<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class DateRangeFilter
{
    public function __construct(public ?string $from = null, public ?string $to = null) {}

    public static function fromRequest(Request $request, string $fromKey = 'dari', string $toKey = 'sampai'): self
    {
        $data = $request->validate([
            $fromKey => ['nullable', 'date_format:Y-m-d'],
            $toKey => ['nullable', 'date_format:Y-m-d'],
        ]);
        $from = $data[$fromKey] ?? null;
        $to = $data[$toKey] ?? null;

        if ($from !== null && $to !== null && $from > $to) {
            throw ValidationException::withMessages([$toKey => 'Tanggal akhir harus sesudah atau sama dengan tanggal mulai.']);
        }

        return new self($from, $to);
    }

    /**
     * @template T of Builder|QueryBuilder
     *
     * @param  T  $query
     * @return T
     */
    public function apply(Builder|QueryBuilder $query, string $column = 'tanggal', bool $isDateColumn = true): Builder|QueryBuilder
    {
        $driver = $query->getConnection()->getDriverName();
        if ($isDateColumn && $driver !== 'sqlite' && ($column === 'tanggal' || str_ends_with($column, '.tanggal'))) {
            return $query
                ->when($this->from, fn ($query) => $query->where($column, '>=', $this->from))
                ->when($this->to, fn ($query) => $query->where($column, '<=', $this->to));
        }

        return $query
            ->when($this->from, fn ($query) => $query->whereDate($column, '>=', $this->from))
            ->when($this->to, fn ($query) => $query->whereDate($column, '<=', $this->to));
    }
}
