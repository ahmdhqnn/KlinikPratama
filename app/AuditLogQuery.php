<?php

namespace App;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AuditLogQuery
{
    public function query(array $filters): Builder
    {
        return DB::table('clinical_audit_events as events')
            ->leftJoin('users as actors', 'actors.id', '=', 'events.actor_id')
            ->leftJoin('kunjungan', 'kunjungan.id', '=', 'events.kunjungan_id')
            ->leftJoin('poliklinik', 'poliklinik.id', '=', 'kunjungan.poliklinik_id')
            ->whereDate('events.occurred_at', '>=', $filters['dari'] ?? today()->subDays(30)->toDateString())
            ->whereDate('events.occurred_at', '<=', $filters['sampai'] ?? today()->toDateString())
            ->when($filters['aksi'] ?? null, fn ($query, $action) => $query->where('events.action', $action))
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($matches) => $matches
                ->where('kunjungan.no_kunjungan', 'like', '%'.trim($search).'%')->orWhere('actors.name', 'like', '%'.trim($search).'%')))
            ->select(['events.id', 'events.action', 'events.occurred_at', 'events.metadata', 'events.actor_id', 'events.actor_role', 'events.outcome', 'events.ip_address', 'events.user_agent', 'actors.name as actor_name', 'kunjungan.no_kunjungan as visit_number', 'poliklinik.nama as clinic_name'])
            ->latest('events.occurred_at')->latest('events.id');
    }
}
