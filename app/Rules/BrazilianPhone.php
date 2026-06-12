<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida celular brasileiro pelos dígitos: exatamente 11 (DDD + 9 + 8 dígitos).
 * Ex.: (92) 99912-0760 → 92999120760.
 * Espera o valor já normalizado (só dígitos) — ver prepareForValidation().
 */
class BrazilianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (strlen($digits) !== 11) {
            $fail('Informe um celular válido com DDD (11 dígitos). Ex.: (99) 99999-9999');

            return;
        }

        if ((int) substr($digits, 0, 2) < 11) {
            $fail('DDD inválido.');

            return;
        }

        if ($digits[2] !== '9') {
            $fail('Número de celular deve ter 9 após o DDD.');
        }
    }
}
