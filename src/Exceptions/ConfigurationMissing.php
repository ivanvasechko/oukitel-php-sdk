<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Exceptions;

final class ConfigurationMissing extends OukitelException
{
    public function __construct()
    {
        parent::__construct('Oukitel region configuration is incomplete or invalid.');
    }
}
