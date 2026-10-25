<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Exceptions;

final class TransportFailed extends OukitelException
{
    public function __construct()
    {
        parent::__construct('Oukitel HTTP transport failed.');
    }
}
