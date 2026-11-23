<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait RetainsCancelledClinicalEntries
{
    protected static function bootRetainsCancelledClinicalEntries(): void
    {
        // The active projection omits voided drafts; the original rows remain queryable for clinical history.
        static::addGlobalScope('active_clinical_entries', fn (Builder $query) => $query->whereNull($query->getModel()->qualifyColumn('voided_at')));
        static::deleting(fn () => throw new \LogicException('Entri klinis tidak boleh dihapus. Catat pembatalan beralasan.'));
    }

    public function cancelClinicalEntry(Request $request): void
    {
        $reason = $request->input('reason');
        $request->merge(['reason' => is_string($reason) ? trim($reason) : $reason]);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->voidClinicalEntry($request->user(), $data['reason']);
    }

    public function voidClinicalEntry(User $actor, string $reason): void
    {
        // A bound model may have been loaded before another request acquired the visit lock.
        $this->refresh();
        abort_if($this->voided_at !== null, 409, 'Entri klinis sudah dibatalkan.');
        $this->forceFill([...$this->clinicalCancellationAttributes(), 'voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason])->save();
    }

    protected function clinicalCancellationAttributes(): array
    {
        return [];
    }
}
