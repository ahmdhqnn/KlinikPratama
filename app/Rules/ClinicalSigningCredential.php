<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Hash;

class ClinicalSigningCredential implements ValidationRule
{
    public function __construct(private ?string $invalidMessage = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $authUser = auth()->user();
        $user = $authUser?->fresh() ?? $authUser;
        if (! $user || ! is_scalar($value)) {
            $fail($this->invalidMessage ?? 'Kredensial pengesahan klinis tidak sesuai.');

            return;
        }

        if ($user->must_setup_signing_pin) {
            $fail('Atur PIN/Passphrase tanda tangan klinis pribadi terlebih dahulu di menu Profil saya sebelum mengesahkan dokumen medis.');

            return;
        }

        if (filled($user->signing_pin_hash)) {
            if (! Hash::check((string) $value, $user->signing_pin_hash)) {
                $fail('PIN/Passphrase tanda tangan klinis tidak sesuai; pengesahan dibatalkan.');
            }

            return;
        }

        if (! Hash::check((string) $value, $user->password)) {
            $fail($this->invalidMessage ?? 'Kata sandi akun tidak sesuai.');
        }
    }
}
