<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Exceptions;

final class AuthenticationFailed extends OukitelException
{
    public function __construct()
    {
        parent::__construct('Oukitel authentication failed. Check the account and region settings.');
    }
}
