<?php

namespace App\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/**
 * Hérite de CustomUserMessageAccountStatusException : les autres AccountStatusException sont
 * masquées en BadCredentialsException par Symfony (expose_security_errors).
 */
final class EmailNotConfirmedException extends CustomUserMessageAccountStatusException
{
    public function __construct()
    {
        parent::__construct('Email not confirmed.');
    }
}
