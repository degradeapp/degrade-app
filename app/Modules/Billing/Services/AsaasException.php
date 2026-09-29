<?php

namespace App\Modules\Billing\Services;

/**
 * Erro devolvido pela API do Asaas. A mensagem é a `description` que o próprio
 * Asaas manda (em pt-BR, ex.: "O CPF/CNPJ informado é inválido."), segura pra
 * mostrar ao dono; quando não vier, fica null e a tela usa a mensagem genérica.
 */
class AsaasException extends \RuntimeException
{
    public function __construct(public readonly ?string $asaasMessage, ?\Throwable $previous = null)
    {
        parent::__construct($asaasMessage ?? 'Erro na API do Asaas', 0, $previous);
    }
}
