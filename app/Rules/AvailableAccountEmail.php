<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final readonly class AvailableAccountEmail implements ValidationRule
{
    public function __construct(private ?User $except = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && User::query()->forLoginEmail($value)
            ->when($this->except, fn ($query, User $user) => $query->whereKeyNot($user->id))
            ->exists()) {
            $fail('Email akun sudah digunakan.');
        }
    }
}
