<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida CPF (11 dígitos) ou CNPJ (14 posições) pelos dígitos verificadores.
 * Aceita o CNPJ alfanumérico da Receita (a partir de jul/2026): as 12 primeiras
 * posições podem ser [0-9A-Z] e o cálculo usa o valor ASCII - 48 de cada uma,
 * que para dígitos é o próprio número (então o CNPJ numérico antigo passa igual).
 * Espera o valor já normalizado (sem pontuação, maiúsculo) — ver normalize().
 */
class CpfCnpj implements ValidationRule
{
    public static function normalize(?string $value): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', (string) $value));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $doc = self::normalize((string) $value);

        $valid = match (strlen($doc)) {
            11 => self::isValidCpf($doc),
            14 => self::isValidCnpj($doc),
            default => false,
        };

        if (! $valid) {
            $fail('Informe um CPF ou CNPJ válido.');
        }
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (! ctype_digit($cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) {
                return false;
            }
        }

        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (! preg_match('/^[0-9A-Z]{12}\d{2}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([12, 13] as $length) {
            $weights = $length === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (ord($cnpj[$i]) - 48) * $weights[$i];
            }
            $rest = $sum % 11;
            $digit = $rest < 2 ? 0 : 11 - $rest;

            if ((int) $cnpj[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
