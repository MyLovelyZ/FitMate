<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * BE-035: `max_value` sebuah rentang tidak boleh lebih kecil dari `min_value`.
 * Rentang terbalik membuat entry itu tidak pernah cocok dengan siapa pun.
 */
class MaxValueAtLeastMinValue implements ValidationRule
{
    public function __construct(private mixed $minValue) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->minValue === null || $value === null) {
            return;
        }

        if ((float) $value < (float) $this->minValue) {
            $fail('Nilai maksimum tidak boleh lebih kecil dari nilai minimum.');
        }
    }
}
