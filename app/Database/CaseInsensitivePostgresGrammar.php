<?php

namespace App\Database;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\PostgresGrammar;

class CaseInsensitivePostgresGrammar extends PostgresGrammar
{
    protected function whereBasic(Builder $query, $where)
    {
        $operator = strtolower(trim((string) ($where['operator'] ?? '')));
        if ($operator === 'like') {
            $where['operator'] = 'ilike';
        } elseif ($operator === 'not like') {
            $where['operator'] = 'not ilike';
        }

        return parent::whereBasic($query, $where);
    }
}
