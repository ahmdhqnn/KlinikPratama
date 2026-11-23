<?php

namespace App;

use App\Models\Nakes;
use App\Models\PharmacyStaffCredential;
use App\Models\User;

class PharmacyStaffAccess
{
    public function current(User $user): ?PharmacyStaffCredential
    {
        if (! $user->is_active || $user->role !== 'farmasi') {
            return null;
        }
        $staff = Nakes::where('user_id', $user->id)->where('is_active', true)->whereIn('jabatan', ['farmasi', 'apotek'])->get();
        if ($staff->count() !== 1) {
            return null;
        }
        $profile = $staff->sole();
        $credential = PharmacyStaffCredential::where('nakes_id', $profile->id)->latest('id')->first();
        if (! $credential || ! in_array($credential->qualification, ['apoteker', 'ttk'], true)
            || $credential->data['user_id'] !== $user->id || $credential->data['sip'] !== $profile->no_sip
            || $credential->data['str'] !== $profile->no_str || $credential->data['valid_until'] < today()->toDateString()) {
            return null;
        }

        return $credential;
    }
}
