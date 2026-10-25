<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Exceptions;

final class LoginCoolingDown extends OukitelException
{
    public function __construct()
    {
        parent::__construct('Oukitel login is cooling down after an authentication failure.');
    }
}
