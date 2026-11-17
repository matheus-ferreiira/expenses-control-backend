<?php

namespace App\Domains\BankSync\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/** A failure talking to Pluggy: the API answers 502 (or 503 when the server has no credentials). */
final class PluggyException extends HttpException
{
    public static function notConfigured(): self
    {
        return new self(503, 'A conexão bancária ainda não foi configurada no servidor.');
    }

    public static function unavailable(): self
    {
        return new self(502, 'Não foi possível falar com a Pluggy agora. Tente novamente em instantes.');
    }
}
