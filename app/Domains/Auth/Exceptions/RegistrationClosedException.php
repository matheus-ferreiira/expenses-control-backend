<?php

namespace App\Domains\Auth\Exceptions;

use RuntimeException;

/** A new account was requested while registration is closed (config auth.registration_enabled). */
final class RegistrationClosedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('O cadastro de novas contas está fechado.');
    }
}
