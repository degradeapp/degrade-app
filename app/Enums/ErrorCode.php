<?php

namespace App\Enums;

enum ErrorCode: string
{
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case UNAUTHORIZED = 'UNAUTHORIZED';
    case FORBIDDEN = 'FORBIDDEN';
    case NOT_FOUND = 'NOT_FOUND';
    case CONFLICT = 'CONFLICT';
    case APPOINTMENT_CONFLICT = 'APPOINTMENT_CONFLICT';
    case BARBER_UNAVAILABLE = 'BARBER_UNAVAILABLE';
    case SUBSCRIPTION_EXPIRED = 'SUBSCRIPTION_EXPIRED';
    case TRIAL_EXPIRED = 'TRIAL_EXPIRED';
    case BARBER_LIMIT_EXCEEDED = 'BARBER_LIMIT_EXCEEDED';
    case INTERNAL_ERROR = 'INTERNAL_ERROR';

    public function message(): string
    {
        return match ($this) {
            self::VALIDATION_ERROR => 'Dados inválidos',
            self::UNAUTHORIZED => 'Autenticação necessária',
            self::FORBIDDEN => 'Acesso negado',
            self::NOT_FOUND => 'Recurso não encontrado',
            self::CONFLICT => 'Conflito de dados',
            self::APPOINTMENT_CONFLICT => 'Conflito de agendamento',
            self::BARBER_UNAVAILABLE => 'Barbeiro não disponível neste horário',
            self::SUBSCRIPTION_EXPIRED => 'Assinatura expirada',
            self::TRIAL_EXPIRED => 'Período de avaliação expirado',
            self::BARBER_LIMIT_EXCEEDED => 'Limite de barbeiros atingido',
            self::INTERNAL_ERROR => 'Erro interno do servidor',
        };
    }
}
